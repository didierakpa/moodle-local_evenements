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
 * Event form: live preview of the ticket (colour, format, font, texts).
 *
 * @module     local_evenements/ticket_preview
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const WIDTHS = {compact: '400px', boarding: '470px', concert: '560px'};
const FONTS = {sans: 'Arial, sans-serif', serif: 'Georgia, serif', mono: 'Courier New, monospace'};
const FIELDS = ['id_ticketformat', 'id_ticketfont', 'id_ticketcolor', 'id_tickettitle', 'id_name', 'id_location',
    'id_tickettext'];

/**
 * Value of a form field, or ''.
 *
 * @param {string} id
 * @returns {string}
 */
const value = (id) => {
    const field = document.getElementById(id);
    return field ? field.value || '' : '';
};

/**
 * Follow the form fields and redraw the preview.
 *
 * @param {string} defaulttitle Title shown when the ticket title is empty
 */
export const init = (defaulttitle) => {
    const box = document.getElementById('evt-preview');
    if (!box) {
        return;
    }

    /**
     * Redraw the preview.
     */
    const update = () => {
        const color = value('id_ticketcolor').trim();
        if (/^#[0-9a-fA-F]{6}$/.test(color)) {
            box.style.setProperty('--pv', color);
        }
        if (WIDTHS[value('id_ticketformat')]) {
            box.style.maxWidth = WIDTHS[value('id_ticketformat')];
        }
        if (FONTS[value('id_ticketfont')]) {
            box.style.fontFamily = FONTS[value('id_ticketfont')];
        }
        box.querySelector('.evt-pv-title').textContent = value('id_tickettitle') || defaulttitle;
        box.querySelector('.evt-pv-name').textContent = value('id_name');
        box.querySelector('.evt-pv-where').textContent = value('id_location');
        box.querySelector('.evt-pv-foot').textContent = value('id_tickettext');
    };
    FIELDS.forEach((id) => {
        const field = document.getElementById(id);
        if (field) {
            field.addEventListener('input', update);
            field.addEventListener('change', update);
        }
    });
    update();
};
