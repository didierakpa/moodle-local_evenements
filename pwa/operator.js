/* Door app (PWA) of local_evenements: sign in with an access code, scan tickets, works offline. */
(function() {
    'use strict';
    var C = window.EVT_OP;
    var T = C.t;
    var LS = 'evt_op_v1';
    var $ = function(id) { return document.getElementById(id); };
    var state = load();
    var camStream = null, camTimer = null, wakeLock = null, last = {t: '', at: 0}, deferredInstall = null;
    var detector = null, canvas = document.createElement('canvas'), ctx2d = canvas.getContext('2d', {willReadFrequently: true});

    function load() {
        try {
            var d = JSON.parse(localStorage.getItem(LS));
            if (d && d.key) { return d; }
        } catch (e) { /* empty */ }
        return {key: '', info: null, tickets: {}, queue: [], recent: [], counters: {total: 0, used: 0}};
    }
    function save() {
        try { localStorage.setItem(LS, JSON.stringify(state)); } catch (e) { toast('!'); }
    }
    function fmt(s, vars) {
        return s.replace(/\{(\w+)\}/g, function(m, k) { return vars && vars[k] !== undefined ? vars[k] : m; });
    }
    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }
    function norm(s) {
        return (s || '').toString().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    }
    function hhmm(ts) {
        var d = new Date(ts * 1000);
        return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
    }
    function toast(msg) {
        var el = document.createElement('div');
        el.className = 'toast';
        el.textContent = msg;
        document.body.appendChild(el);
        setTimeout(function() { el.remove(); }, 3500);
    }

    // ------------------------------------------------------------------ Feedback.
    var audio = null;
    function beep(freq, ms, times) {
        try {
            audio = audio || new (window.AudioContext || window.webkitAudioContext)();
            for (var i = 0; i < (times || 1); i++) {
                var o = audio.createOscillator(), g = audio.createGain();
                o.frequency.value = freq; o.connect(g); g.connect(audio.destination);
                g.gain.value = 0.15;
                var t0 = audio.currentTime + i * (ms / 1000 + 0.08);
                o.start(t0); o.stop(t0 + ms / 1000);
            }
        } catch (e) { /* no sound */ }
    }
    function feedback(kind) {
        if (navigator.vibrate) { navigator.vibrate(kind === 'ok' ? 80 : [120, 80, 120]); }
        beep(kind === 'ok' ? 880 : (kind === 'warn' ? 520 : 220), 140, kind === 'ok' ? 1 : 2);
    }

    // ------------------------------------------------------------------ Network.
    function api(action, body, timeout) {
        var ctl = new AbortController();
        var to = setTimeout(function() { ctl.abort(); }, timeout || 7000);
        return fetch(C.api + '?action=' + action, {
            method: 'POST', cache: 'no-store', signal: ctl.signal,
            headers: {'Content-Type': 'application/json', 'X-Operator-Key': state.key},
            body: JSON.stringify(body || {})
        }).then(function(r) {
            clearTimeout(to);
            return r.json().then(function(j) {
                if (!r.ok) { var e = new Error(j.error || 'http'); e.code = j.error || 'http'; throw e; }
                return j;
            });
        }, function(err) { clearTimeout(to); throw err; });
    }
    function isNetwork(err) { return !err.code; }
    function setOnline(on) {
        $('dot').className = 'dot' + (on ? '' : ' off');
        $('netlabel').textContent = on ? T.online : T.offline;
    }

    // ------------------------------------------------------------------ Views.
    function show(view) {
        ['login', 'app'].forEach(function(v) { $(v).classList.toggle('hidden', v !== view); });
    }
    function renderCounters() {
        var c = state.counters || {total: 0, used: 0};
        var pending = state.queue.length;
        $('count').innerHTML = esc(String(c.used)) + ' <small>/ ' + esc(String(c.total)) + '</small>';
        $('bar').style.width = (c.total ? Math.min(100, Math.round(100 * c.used / c.total)) : 0) + '%';
        $('syncinfo').textContent = pending ? fmt(T.pending, {n: pending}) : '';
    }
    function renderRecent() {
        var ul = $('recentlist');
        ul.innerHTML = '';
        if (!state.recent.length) { $('recentempty').classList.remove('hidden'); return; }
        $('recentempty').classList.add('hidden');
        state.recent.forEach(function(r) {
            var li = document.createElement('li');
            li.innerHTML = '<span>' + esc(r.name) + '<br><span class="muted">' + hhmm(r.time) + (r.cat ? ' · ' + esc(r.cat) : '') +
                '</span></span><span class="tag ' + r.kind + '">' + esc(r.label) + '</span>';
            ul.appendChild(li);
        });
    }
    function renderHeader() {
        if (!state.info) { return; }
        $('evname').textContent = state.info.event.name;
        $('opname').textContent = state.info.operator + (state.info.event.when ? ' · ' + state.info.event.when : '');
    }
    function renderAll() { renderHeader(); renderCounters(); renderRecent(); }

    // ------------------------------------------------------------------ Result screen.
    function showResult(kind, title, who, info, opts) {
        opts = opts || {};
        var el = $('result');
        el.className = kind;
        $('rticon').textContent = kind === 'ok' ? '✓' : (kind === 'warn' ? '!' : '✕');
        $('rtstate').textContent = title;
        $('rtwho').textContent = who || '';
        $('rtinfo').textContent = info || '';
        $('rtundo').classList.toggle('hidden', !opts.undo);
        $('rtundo').onclick = function() {
            api('undo', {t: opts.undo}).then(function(j) {
                if (j.ok) {
                    if (state.tickets[opts.undo]) { state.tickets[opts.undo][3] = 0; }
                    if (j.counters) { state.counters = j.counters; }
                    save(); renderCounters(); toast(T.undone);
                } else { toast(T.undofail); }
                closeResult();
            }).catch(function() { toast(T.undofail); });
        };
        el.classList.remove('hidden');
        feedback(kind);
        clearTimeout(showResult.timer);
        if (kind === 'ok') { showResult.timer = setTimeout(closeResult, 2200); }
    }
    function closeResult() { clearTimeout(showResult.timer); $('result').classList.add('hidden'); }

    function remember(kind, label, name, cat) {
        state.recent.unshift({kind: kind, label: label, name: name, cat: cat || '', time: Math.floor(Date.now() / 1000)});
        state.recent = state.recent.slice(0, 12);
        save(); renderRecent();
    }

    // ------------------------------------------------------------------ Checking a ticket.
    function extract(raw) {
        var m = /[0-9a-f]{40}/i.exec(raw || '');
        return m ? m[0].toLowerCase() : '';
    }
    function handle(raw) {
        var tok = extract(raw);
        if (!tok) { showResult('bad', T.unknown, '', ''); return; }
        var now = Date.now();
        if (last.t === tok && now - last.at < 4000) { return; }
        last = {t: tok, at: now};
        api('checkin', {t: tok}, 5000).then(function(j) {
            setOnline(true);
            if (j.counters) { state.counters = j.counters; }
            var local = state.tickets[tok];
            if (j.r === 'accepted') {
                if (local) { local[3] = 1; }
                remember('ok', T.ok, j.name, j.cat);
                showResult('ok', T.ok, j.name, [j.cat, j.slot].filter(Boolean).join(' · '), {undo: tok});
            } else if (j.r === 'alreadyused') {
                if (local) { local[3] = 1; }
                var when = j.usedat ? fmt(T.usedat, {time: hhmm(j.usedat), by: j.by || '-'}) : '';
                remember('warn', T.used, j.name, j.cat);
                showResult('warn', T.used, j.name, when);
            } else if (j.r === 'wrongevent') {
                remember('bad', T.wrong, '', '');
                showResult('bad', T.wrong, '', '');
            } else if (j.r === 'refused') {
                remember('bad', T.cancelled, j.name, j.cat);
                showResult('bad', T.cancelled, j.name, '');
            } else {
                remember('bad', T.unknown, '', '');
                showResult('bad', T.unknown, '', '');
            }
            save(); renderCounters();
        }).catch(function(err) {
            if (!isNetwork(err)) { authProblem(err); return; }
            setOnline(false);
            offlineCheck(tok);
        });
    }
    function offlineCheck(tok) {
        var t = state.tickets[tok];
        if (!t) { remember('bad', T.unknown, '', ''); showResult('bad', T.unknown, '', T.offline); return; }
        if (t[3]) { remember('warn', T.used, t[0], t[1]); showResult('warn', T.used, t[0], T.offline); return; }
        t[3] = 1;
        state.queue.push({t: tok, time: Math.floor(Date.now() / 1000)});
        state.counters.used++;
        remember('ok', T.okoffline, t[0], t[1]);
        save(); renderCounters();
        showResult('ok', T.okoffline, t[0], [t[1], t[2]].filter(Boolean).join(' · '));
    }
    function authProblem(err) {
        if (err.code === 'revoked' || err.code === 'badcode') {
            toast(T.revoked);
            if (!state.queue.length) { logout(true); }
        } else if (err.code === 'toomanyattempts') { toast(T.many); }
    }

    // ------------------------------------------------------------------ Synchronisation.
    var syncing = false;
    function sync() {
        if (syncing || !state.queue.length || !navigator.onLine) { return Promise.resolve(); }
        syncing = true;
        var batch = state.queue.slice(0, 500);
        return api('sync', {scans: batch}, 15000).then(function(j) {
            state.queue = state.queue.slice(batch.length);
            if (j.counters) { state.counters = j.counters; }
            var conflicts = (j.results || []).filter(function(x) { return x.r !== 'accepted'; }).length;
            save(); renderCounters();
            toast(T.synced + (conflicts ? ' · ' + fmt(T.conflicts, {n: conflicts}) : ''));
            setOnline(true);
        }).catch(function(err) { if (!isNetwork(err)) { authProblem(err); } else { setOnline(false); } })
            .then(function() { syncing = false; });
    }
    function refreshList() {
        if (!navigator.onLine) { return Promise.resolve(); }
        return sync().then(function() {
            return api('list', {}, 15000);
        }).then(function(j) {
            var queued = {};
            state.queue.forEach(function(q) { queued[q.t] = true; });
            Object.keys(j.tickets).forEach(function(k) { if (queued[k]) { j.tickets[k][3] = 1; } });
            state.tickets = j.tickets;
            state.counters = j.counters;
            save(); renderCounters(); setOnline(true);
        }).catch(function(err) { if (!isNetwork(err)) { authProblem(err); } else { setOnline(false); } });
    }

    // ------------------------------------------------------------------ Camera.
    function stopCamera() {
        if (camTimer) { clearInterval(camTimer); camTimer = null; }
        if (camStream) { camStream.getTracks().forEach(function(t) { t.stop(); }); camStream = null; }
        if (wakeLock) { try { wakeLock.release(); } catch (e) { /* ignore */ } wakeLock = null; }
        $('camera').classList.add('hidden');
    }
    function loadJsQR() {
        return new Promise(function(resolve) {
            if (window.jsQR) { resolve(true); return; }
            if (!C.jsqr) { resolve(false); return; }
            var s = document.createElement('script');
            s.src = C.jsqr; s.onload = function() { resolve(!!window.jsQR); }; s.onerror = function() { resolve(false); };
            document.head.appendChild(s);
        });
    }
    async function startCamera() {
        var useNative = 'BarcodeDetector' in window;
        var useJs = !useNative && await loadJsQR();
        if ((!useNative && !useJs) || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            toast(T.camunsupported); return;
        }
        try {
            camStream = await navigator.mediaDevices.getUserMedia({video: {facingMode: 'environment'}, audio: false});
        } catch (e) { toast(T.camdenied); return; }
        var video = $('video');
        video.srcObject = camStream;
        try { await video.play(); } catch (e) { /* autoplay */ }
        $('camera').classList.remove('hidden');
        try { if (navigator.wakeLock) { wakeLock = await navigator.wakeLock.request('screen'); } } catch (e) { /* ignore */ }
        if (useNative) { detector = detector || new BarcodeDetector({formats: ['qr_code']}); }
        camTimer = setInterval(async function() {
            if (!$('result').classList.contains('hidden') || video.readyState < 2) { return; }
            try {
                var raw = '';
                if (useNative) {
                    var codes = await detector.detect(video);
                    if (codes.length) { raw = codes[0].rawValue; }
                } else {
                    var w = video.videoWidth, h = video.videoHeight, k = Math.min(1, 640 / w);
                    canvas.width = Math.round(w * k); canvas.height = Math.round(h * k);
                    ctx2d.drawImage(video, 0, 0, canvas.width, canvas.height);
                    var img = ctx2d.getImageData(0, 0, canvas.width, canvas.height);
                    var res = window.jsQR(img.data, img.width, img.height);
                    if (res) { raw = res.data; }
                }
                if (raw && extract(raw)) { handle(raw); }
            } catch (e) { /* next frame */ }
        }, 250);
    }

    // ------------------------------------------------------------------ Search by name.
    function runSearch() {
        var q = norm($('q').value).trim();
        var ul = $('searchlist');
        ul.innerHTML = '';
        if (q.length < 2) { return; }
        var n = 0;
        Object.keys(state.tickets).forEach(function(tok) {
            var t = state.tickets[tok];
            if (n >= 30 || norm(t[0]).indexOf(q) === -1) { return; }
            n++;
            var li = document.createElement('li');
            li.innerHTML = '<span>' + esc(t[0]) + '<br><span class="muted">' + esc([t[1], t[2]].filter(Boolean).join(' · ')) + '</span></span>';
            var b = document.createElement('button');
            b.className = 'btn small' + (t[3] ? ' secondary' : '');
            b.textContent = t[3] ? T.used : T.validate;
            b.onclick = function() {
                if (!t[3] && !confirm(fmt(T.confirmmanual, {name: t[0]}))) { return; }
                $('search').classList.add('hidden');
                last = {t: '', at: 0};
                handle(tok);
            };
            li.appendChild(b);
            ul.appendChild(li);
        });
        if (!n) { ul.innerHTML = '<li class="muted">' + esc(T.nomatch) + '</li>'; }
    }

    // ------------------------------------------------------------------ Login / logout.
    function login(code) {
        $('loginerr').textContent = '';
        var previous = state;
        state = {key: code, info: null, tickets: {}, queue: [], recent: [], counters: {total: 0, used: 0}};
        return api('login', {}, 10000).then(function(j) {
            state.info = j; state.counters = j.counters;
            save(); renderAll(); show('app');
            return refreshList();
        }).catch(function(err) {
            state = previous;
            $('loginerr').textContent = err.code === 'badcode' ? T.badcode : (err.code === 'revoked' ? T.revoked :
                (err.code === 'toomanyattempts' ? T.many : T.errnet));
        });
    }
    function logout(force) {
        if (!force && state.queue.length && !confirm(T.logoutpending)) { return; }
        stopCamera();
        try { localStorage.removeItem(LS); } catch (e) { /* ignore */ }
        state = {key: '', info: null, tickets: {}, queue: [], recent: [], counters: {total: 0, used: 0}};
        show('login');
    }

    // ------------------------------------------------------------------ Start.
    function init() {
        var m = /[?&]k=([A-Za-z0-9-]+)/.exec(location.search);
        if (m) {
            history.replaceState(null, '', location.pathname);
            login(m[1].toUpperCase());
        } else if (state.key) {
            show('app'); renderAll(); setOnline(navigator.onLine);
            refreshList();
        } else {
            show('login');
        }
        $('loginform').addEventListener('submit', function(e) { e.preventDefault(); login($('code').value.trim().toUpperCase()); });
        $('btnscan').onclick = startCamera;
        $('camclose').onclick = stopCamera;
        $('btnsearch').onclick = function() { $('search').classList.remove('hidden'); $('q').value = ''; $('searchlist').innerHTML = ''; $('q').focus(); };
        $('searchclose').onclick = function() { $('search').classList.add('hidden'); };
        $('q').addEventListener('input', runSearch);
        $('btnmanual').onclick = function() { $('manual').classList.remove('hidden'); $('manualcode').value = ''; $('manualcode').focus(); };
        $('manualclose').onclick = function() { $('manual').classList.add('hidden'); };
        $('manualform').addEventListener('submit', function(e) {
            e.preventDefault(); $('manual').classList.add('hidden'); last = {t: '', at: 0}; handle($('manualcode').value);
        });
        $('rtnext').onclick = closeResult;
        $('btnlogout').onclick = function() { logout(false); };
        window.addEventListener('online', function() { setOnline(true); refreshList(); });
        window.addEventListener('offline', function() { setOnline(false); });
        setInterval(function() { if (state.key) { sync(); } }, 20000);
        setInterval(function() { if (state.key && !camStream) { refreshList(); } }, 120000);

        window.addEventListener('beforeinstallprompt', function(e) {
            e.preventDefault(); deferredInstall = e; $('btninstall').classList.remove('hidden');
        });
        $('btninstall').onclick = function() {
            if (deferredInstall) { deferredInstall.prompt(); deferredInstall = null; $('btninstall').classList.add('hidden'); }
        };
        var ios = /iphone|ipad|ipod/i.test(navigator.userAgent);
        var standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone;
        if (ios && !standalone) { $('ioshint').classList.remove('hidden'); }
        if ('serviceWorker' in navigator) { navigator.serviceWorker.register(C.sw).catch(function() { /* optional */ }); }
    }
    init();
})();
