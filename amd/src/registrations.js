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
 * Participants page: bulk action bar shown when tickets are ticked, row menus.
 *
 * @module     local_evenements/registrations
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Wire the bulk action bar and the row menus.
 *
 * @param {string} one Text for one selected participant
 * @param {string} many Text for several selected participants, with {n}
 */
export const init = (one, many) => {
    const form = document.getElementById('evt-bulkform');
    if (!form) {
        return;
    }
    const all = document.getElementById('evt-checkall');
    const boxes = Array.from(document.querySelectorAll('.evt-check'));
    const count = document.getElementById('evt-bulk-count');
    const action = document.getElementById('evt-bulk-action');

    /**
     * Show the bar and the number of ticked participants.
     */
    const update = () => {
        const n = boxes.filter((box) => box.checked).length;
        form.hidden = n === 0;
        count.textContent = n === 1 ? one : many.replace('{n}', n);
        if (all) {
            all.checked = n > 0 && n === boxes.length;
            all.indeterminate = n > 0 && n < boxes.length;
        }
        boxes.forEach((box) => {
            const row = box.closest('tr');
            if (row) {
                row.classList.toggle('is-selected', box.checked);
            }
        });
    };
    boxes.forEach((box) => box.addEventListener('change', update));
    if (all) {
        all.addEventListener('change', () => {
            boxes.forEach((box) => {
                box.checked = all.checked;
            });
            update();
        });
    }
    action.addEventListener('change', () => {
        form.querySelectorAll('.evt-bulk-extra').forEach((select) => {
            select.hidden = select.getAttribute('data-for') !== action.value;
        });
    });
    form.addEventListener('submit', (e) => {
        if (!action.value) {
            e.preventDefault();
            action.focus();
        }
    });

    // Only one row menu open at a time; a click elsewhere closes it.
    const menus = document.querySelectorAll('.evt-rowmenu');
    menus.forEach((menu) => menu.addEventListener('toggle', () => {
        if (menu.open) {
            menus.forEach((other) => {
                if (other !== menu) {
                    other.open = false;
                }
            });
        }
    }));
    document.addEventListener('click', (e) => {
        menus.forEach((menu) => {
            if (menu.open && !menu.contains(e.target)) {
                menu.open = false;
            }
        });
    });
    update();
};
