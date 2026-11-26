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
 * Organiser console: side menu (drawer on phones), remembered groups, command palette (Ctrl+K)
 * and a few small page behaviours (print, select a link, confirm, submit on change).
 *
 * @module     local_evenements/console
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const GROUPS_KEY = 'evt_nav_groups';

/** @var {boolean} actionsready The small page behaviours are wired once. */
let actionsready = false;

/**
 * Lower case text without accents, for the palette search.
 *
 * @param {string} text
 * @returns {string}
 */
const normalise = (text) => (text || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

/**
 * Small behaviours set with data attributes, instead of inline event handlers.
 */
export const initActions = () => {
    if (actionsready) {
        return;
    }
    actionsready = true;
    document.addEventListener('click', (e) => {
        const print = e.target.closest('[data-evt-print]');
        if (print) {
            e.preventDefault();
            window.print();
            return;
        }
        const ask = e.target.closest('[data-evt-confirm]');
        // eslint-disable-next-line no-alert
        if (ask && !window.confirm(ask.getAttribute('data-evt-confirm'))) {
            e.preventDefault();
        }
    });
    const select = (e) => {
        if (e.target.matches && e.target.matches('[data-evt-select]')) {
            e.target.select();
        }
    };
    document.addEventListener('click', select);
    document.addEventListener('focusin', select);
    document.addEventListener('change', (e) => {
        if (e.target.matches && e.target.matches('[data-evt-autosubmit]') && e.target.form) {
            e.target.form.submit();
        }
    });
};

/**
 * Side menu and command palette.
 */
export const init = () => {
    initActions();
    const side = document.getElementById('evt-side');
    const pal = document.getElementById('evt-pal');
    if (!side) {
        return;
    }
    document.body.classList.add('evt-console');
    const scrim = document.createElement('div');
    scrim.className = 'evt-scrim';
    document.body.appendChild(scrim);
    const button = document.querySelector('.evt-menubtn');

    /**
     * Open or close the menu drawer (phones).
     *
     * @param {boolean} open
     */
    const drawer = (open) => {
        side.classList.toggle('is-open', open);
        scrim.classList.toggle('is-open', open);
        if (button) {
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    };
    if (button) {
        button.addEventListener('click', () => drawer(!side.classList.contains('is-open')));
    }
    scrim.addEventListener('click', () => drawer(false));
    const close = side.querySelector('.evt-side-close');
    if (close) {
        close.addEventListener('click', () => drawer(false));
    }

    // On wide screens the menu is only as tall as needed and never covers the page footer.
    const wide = window.matchMedia('(min-width: 992px)');

    /**
     * Height of the menu on wide screens.
     */
    const fit = () => {
        if (!wide.matches) {
            side.style.maxHeight = '';
            document.documentElement.style.removeProperty('--evt-side-h');
            return;
        }
        const footer = document.querySelector('#page-footer') || document.querySelector('footer');
        let limit = window.innerHeight;
        if (footer) {
            limit = Math.min(limit, footer.getBoundingClientRect().top);
        }
        const top = side.getBoundingClientRect().top;
        document.documentElement.style.setProperty('--evt-side-h', (side.scrollHeight + 24) + 'px');
        side.style.maxHeight = Math.max(220, limit - top - 12) + 'px';
    };
    window.addEventListener('scroll', fit, {passive: true});
    window.addEventListener('resize', fit);
    document.addEventListener('toggle', fit, true);
    fit();
    setTimeout(fit, 400);

    // Remember which groups are open; only one closed-by-default group open at a time.
    let saved = {};
    try {
        saved = JSON.parse(window.localStorage.getItem(GROUPS_KEY)) || {};
    } catch (e) {
        saved = {};
    }
    side.querySelectorAll('details.evt-nav-group').forEach((group) => {
        const key = group.getAttribute('data-key');
        if (saved[key] !== undefined && !group.querySelector('.is-active')) {
            group.open = !!saved[key];
        }
        group.addEventListener('toggle', () => {
            if (group.open) {
                document.querySelectorAll('.evt-nav-group[open]').forEach((other) => {
                    if (other !== group && !other.querySelector('.is-active')) {
                        other.open = false;
                    }
                });
            }
            saved[key] = group.open;
            try {
                window.localStorage.setItem(GROUPS_KEY, JSON.stringify(saved));
            } catch (e) {
                // Private browsing: the groups are simply not remembered.
            }
        });
    });
    side.querySelectorAll('[data-anchor]').forEach((link) => {
        link.href = link.href.split('#')[0] + '#' + link.getAttribute('data-anchor');
    });

    // Command palette.
    const input = document.getElementById('evt-pal-input');
    const list = document.getElementById('evt-pal-list');
    if (!pal || !input || !list) {
        return;
    }
    let items = [];
    try {
        items = JSON.parse(side.getAttribute('data-palette') || '[]');
    } catch (e) {
        items = [];
    }
    let selected = 0;
    let shown = [];

    /**
     * Show the entries matching the search.
     */
    const render = () => {
        const query = normalise(input.value).trim();
        shown = items.filter((item) => !query || normalise(item.l).indexOf(query) !== -1).slice(0, 12);
        selected = Math.min(selected, Math.max(0, shown.length - 1));
        list.innerHTML = '';
        shown.forEach((item, n) => {
            const li = document.createElement('li');
            li.setAttribute('role', 'option');
            li.className = (n === selected ? 'is-sel ' : '') + 'k-' + item.k;
            li.textContent = item.l;
            li.addEventListener('mousedown', (e) => {
                e.preventDefault();
                window.location.href = item.u;
            });
            list.appendChild(li);
        });
    };

    /**
     * Open the palette.
     */
    const openPalette = () => {
        pal.hidden = false;
        pal.classList.remove('hidden');
        input.value = '';
        selected = 0;
        render();
        input.focus();
        drawer(false);
    };

    /**
     * Close the palette.
     */
    const closePalette = () => {
        pal.hidden = true;
        pal.classList.add('hidden');
    };

    document.querySelectorAll('[data-evt-palette]').forEach((b) => b.addEventListener('click', openPalette));
    pal.addEventListener('mousedown', (e) => {
        if (e.target === pal) {
            closePalette();
        }
    });
    input.addEventListener('input', () => {
        selected = 0;
        render();
    });
    input.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown') {
            selected = Math.min(shown.length - 1, selected + 1);
            render();
            e.preventDefault();
        } else if (e.key === 'ArrowUp') {
            selected = Math.max(0, selected - 1);
            render();
            e.preventDefault();
        } else if (e.key === 'Enter' && shown[selected]) {
            window.location.href = shown[selected].u;
        } else if (e.key === 'Escape') {
            closePalette();
        }
    });
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            openPalette();
        } else if (e.key === 'Escape') {
            closePalette();
            drawer(false);
        }
    });
};
