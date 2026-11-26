// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Check-in page: scan QR codes with the camera, online or offline.
 *
 * @module     local_evenements/scan
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Config from 'core/config';

const OFFLINE_KEY = 'evt_offline_v1';
const TOKEN = /[0-9a-f]{40}/i;

/**
 * A camera that reads QR codes (BarcodeDetector API: Chrome, Edge, Android).
 *
 * @param {HTMLVideoElement} video
 * @param {Function} onToken Called with each ticket token read
 * @returns {Promise<{stop: Function}>} Rejected with 'unsupported' or 'denied'
 */
const startCamera = async(video, onToken) => {
    if (!('BarcodeDetector' in window) || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        throw new Error('unsupported');
    }
    // eslint-disable-next-line no-undef
    const detector = new BarcodeDetector({formats: ['qr_code']});
    let stream;
    try {
        stream = await navigator.mediaDevices.getUserMedia({video: {facingMode: 'environment'}});
    } catch (e) {
        throw new Error('denied');
    }
    video.srcObject = stream;
    try {
        await video.play();
    } catch (e) {
        // The autoplay attribute does the job.
    }
    const timer = setInterval(async() => {
        try {
            const codes = await detector.detect(video);
            codes.forEach((code) => {
                const match = TOKEN.exec(code.rawValue || '');
                if (match) {
                    onToken(match[0].toLowerCase());
                }
            });
        } catch (e) {
            // Frame not ready: try again with the next one.
        }
    }, 300);
    return {
        stop: () => {
            clearInterval(timer);
            stream.getTracks().forEach((track) => track.stop());
        },
    };
};

/**
 * Online scan: the camera opens the page of the ticket read.
 *
 * @param {Object} texts start, stop, unsupported, denied
 * @param {string} base Address of the check-in page
 * @param {boolean} autostart Open the camera at once
 */
export const initCamera = (texts, base, autostart) => {
    const button = document.getElementById('scan-camera-btn');
    const box = document.getElementById('scan-camera-box');
    const video = document.getElementById('scan-video');
    const message = document.getElementById('scan-camera-msg');
    if (!button) {
        return;
    }
    let camera = null;

    /**
     * Close the camera.
     */
    const stop = () => {
        if (camera) {
            camera.stop();
            camera = null;
        }
        box.style.display = 'none';
        button.textContent = texts.start;
    };

    /**
     * Open the camera.
     */
    const start = async() => {
        message.textContent = '';
        try {
            camera = await startCamera(video, (token) => {
                stop();
                window.location.href = base + '?t=' + token;
            });
        } catch (e) {
            message.textContent = texts[e.message] || texts.unsupported;
            return;
        }
        box.style.display = 'block';
        button.textContent = texts.stop;
    };

    button.addEventListener('click', () => (camera ? stop() : start()));
    if (autostart) {
        start();
    }
};

/**
 * Offline mode: the list of tickets is kept in the phone and the entries are sent later.
 *
 * @param {Object} texts Interface texts
 */
export const initOffline = (texts) => {
    const select = document.getElementById('off-event');
    if (!select) {
        return;
    }
    const base = Config.wwwroot + '/local/evenements/';
    const status = document.getElementById('off-status');
    const result = document.getElementById('off-result');
    const video = document.getElementById('off-video');
    const box = document.getElementById('off-box');
    const scanButton = document.getElementById('off-scan');
    let camera = null;
    let last = {t: '', at: 0};

    /**
     * Data kept in the phone.
     *
     * @returns {Object} events and queue
     */
    const load = () => {
        try {
            return JSON.parse(window.localStorage.getItem(OFFLINE_KEY)) || {events: {}, queue: []};
        } catch (e) {
            return {events: {}, queue: []};
        }
    };

    /**
     * Keep the data in the phone.
     *
     * @param {Object} data
     */
    const save = (data) => {
        try {
            window.localStorage.setItem(OFFLINE_KEY, JSON.stringify(data));
        } catch (e) {
            status.textContent = '!';
        }
    };

    /**
     * Show the state: event, number of tickets, entries waiting.
     */
    const refresh = () => {
        const data = load();
        const event = data.events[select.value];
        status.textContent = (event ? event.name + ' · ' + Object.keys(event.tickets).length + ' · ' : '') +
            texts.pending.replace('{n}', data.queue.length);
    };

    /**
     * Big coloured result.
     *
     * @param {string} type Bootstrap alert class
     * @param {string} text
     */
    const flash = (type, text) => {
        result.className = 'text-center fs-3 fw-bold rounded p-3 alert ' + type;
        result.textContent = text;
    };

    document.getElementById('off-download').addEventListener('click', () => {
        fetch(base + 'scandata.php?id=' + encodeURIComponent(select.value) + '&sesskey=' + Config.sesskey,
            {credentials: 'same-origin'})
            .then((response) => {
                if (!response.ok) {
                    throw new Error('http');
                }
                return response.json();
            })
            .then((list) => {
                if (!list.tickets) {
                    throw new Error('data');
                }
                const data = load();
                data.events[list.id] = {name: list.name, tickets: list.tickets, time: list.time};
                save(data);
                refresh();
                status.textContent = texts.downloaded + ' ' + status.textContent;
                return list;
            })
            .catch(() => {
                status.textContent = texts.failed;
            });
    });

    /**
     * Send the entries waiting in the phone.
     */
    const sync = () => {
        const data = load();
        if (!data.queue.length || !navigator.onLine) {
            refresh();
            return;
        }
        const batch = data.queue.slice(0, 500);
        fetch(base + 'scansync.php?sesskey=' + Config.sesskey, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            body: JSON.stringify({scans: batch}),
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('http');
                }
                return response.json();
            })
            .then((answer) => {
                if (!answer.results) {
                    throw new Error('sync');
                }
                const conflicts = answer.results.filter((x) => x.r !== 'accepted').length;
                const fresh = load();
                fresh.queue = fresh.queue.slice(batch.length);
                save(fresh);
                status.textContent = texts.synced.replace('{n}', batch.length) +
                    (conflicts ? ' ' + texts.conflicts.replace('{n}', conflicts) : '');
                return answer;
            })
            .catch(() => {
                status.textContent = texts.failed;
            });
    };
    document.getElementById('off-sync').addEventListener('click', sync);
    window.addEventListener('online', sync);

    /**
     * Check a ticket against the list kept in the phone.
     *
     * @param {string} token
     */
    const check = (token) => {
        const data = load();
        const event = data.events[select.value];
        if (!event) {
            flash('alert-danger', texts.noevent);
            return;
        }
        const now = Date.now();
        if (last.t === token && now - last.at < 3000) {
            return;
        }
        last = {t: token, at: now};
        const ticket = event.tickets[token];
        if (!ticket) {
            flash('alert-danger', texts.unknown);
            return;
        }
        if (ticket[2]) {
            flash('alert-warning', texts.used + ' · ' + ticket[0]);
            return;
        }
        ticket[2] = 1;
        data.queue.push({t: token, time: Math.floor(now / 1000)});
        save(data);
        flash('alert-success', texts.valid + ' · ' + ticket[0] + (ticket[1] ? ' (' + ticket[1] + ')' : ''));
        refresh();
    };

    /**
     * Close the camera.
     */
    const stop = () => {
        if (camera) {
            camera.stop();
            camera = null;
        }
        box.style.display = 'none';
        scanButton.textContent = texts.scan;
    };

    /**
     * Open the camera.
     */
    const start = async() => {
        try {
            camera = await startCamera(video, check);
        } catch (e) {
            status.textContent = texts[e.message] || texts.unsupported;
            return;
        }
        box.style.display = 'block';
        scanButton.textContent = texts.stop;
    };

    scanButton.addEventListener('click', () => (camera ? stop() : start()));
    select.addEventListener('change', refresh);
    refresh();
};
