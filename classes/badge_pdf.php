<?php
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

namespace local_evenements;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/pdflib.php');

/**
 * Generates the name badges (staff, reception, volunteers, guests) of an event.
 *
 * The badge takes the name, dates, place, colour, font and logo of the event.
 * The colour band at the bottom depends on the role.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class badge_pdf {
    /** @var array Badge sizes in millimetres: format => [width, height, columns, rows]. */
    public const FORMATS = [
        'a6' => [105, 148, 2, 2],
        'card' => [90, 55, 2, 5],
    ];

    /**
     * Colour of a role (the badge band). Unknown roles use the colour of the event.
     *
     * @param string $role
     * @param int[] $default
     * @return int[]
     */
    public static function role_color(string $role, array $default): array {
        $r = \core_text::strtolower(\core_text::specialtoascii($role));
        $map = [
            '/organi/' => [106, 27, 154],
            '/staff|equipe|team/' => [21, 101, 192],
            '/accueil|hote|reception|welcome/' => [46, 125, 50],
            '/benevol|volunt/' => [239, 108, 0],
            '/interven|speaker|conferenc|jury|enseign|prof/' => [198, 40, 40],
            '/presse|media|photo|press/' => [0, 131, 143],
            '/vip|partenaire|elu|officiel/' => [160, 120, 10],
            '/controle|entree|entrance|scan|securit|security/' => [55, 71, 79],
        ];
        foreach ($map as $pattern => $rgb) {
            if (preg_match($pattern, $r)) {
                return $rgb;
            }
        }
        return $default;
    }

    /**
     * Split "Marie-Claire DUPONT" into first name and last name.
     *
     * Leading words that are not all in capitals form the first name; if every word looks the same,
     * the first word is the first name.
     *
     * @param string $full
     * @return string[] [first, last]
     */
    public static function split_name(string $full): array {
        $words = preg_split('/\s+/u', trim($full), -1, PREG_SPLIT_NO_EMPTY);
        if (count($words) <= 1) {
            return [$words[0] ?? '', ''];
        }
        $n = 0;
        foreach ($words as $w) {
            if ($n > 0 && $w === \core_text::strtoupper($w) && $w !== \core_text::strtolower($w)) {
                break;
            }
            $n++;
        }
        if ($n >= count($words)) {
            $n = 1;
        }
        return [implode(' ', array_slice($words, 0, $n)), implode(' ', array_slice($words, $n))];
    }

    /**
     * Build the PDF.
     *
     * @param \stdClass $event
     * @param array $people List of ['first' => , 'last' => , 'role' => , 'ticket' => \stdClass|null]
     * @param string $format a6 or card
     * @return string
     */
    public static function generate(\stdClass $event, array $people, string $format = 'a6'): string {
        global $CFG;
        if (!isset(self::FORMATS[$format])) {
            $format = 'a6';
        }
        [$w, $h, $cols, $rows] = self::FORMATS[$format];
        $perpage = $cols * $rows;

        $pdf = new \pdf('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->SetCreator('Moodle - local_evenements');
        $pdf->SetTitle($event->name);

        $fonts = ['sans' => 'freesans', 'serif' => 'freeserif', 'mono' => 'freemono'];
        $font = $fonts[$event->ticketfont ?? 'sans'] ?? 'freesans';
        if (!file_exists($CFG->libdir . '/tcpdf/fonts/' . $font . '.php')) {
            $font = 'helvetica';
        }
        $logo = ticket_pdf::get_logo($event);
        $color = ticket_pdf::hex_to_rgb($event->ticketcolor);

        $left = (210 - $cols * $w) / 2;
        $top = (297 - $rows * $h) / 2;
        foreach (array_values($people) as $i => $person) {
            $slot = $i % $perpage;
            if ($slot === 0) {
                $pdf->AddPage();
                $pdf->SetLineStyle(['width' => 0.2, 'dash' => '1,2', 'color' => [170, 170, 170]]);
                for ($c = 0; $c <= $cols; $c++) {
                    $pdf->Line($left + $c * $w, 0, $left + $c * $w, 297);
                }
                for ($r = 0; $r <= $rows; $r++) {
                    $pdf->Line(0, $top + $r * $h, 210, $top + $r * $h);
                }
                $pdf->SetLineStyle(['dash' => 0]);
            }
            $pdf->StartTransform();
            $pdf->Translate($left + ($slot % $cols) * $w, $top + intdiv($slot, $cols) * $h);
            self::draw($pdf, $font, $color, $logo, $event, $person, $w, $h, $format);
            $pdf->StopTransform();
        }
        if (!$people) {
            $pdf->AddPage();
        }
        return $pdf->Output('badges.pdf', 'S');
    }

    /**
     * Shrink the font until the text fits the width.
     *
     * @param \pdf $pdf
     * @param string $font
     * @param string $style
     * @param string $text
     * @param float $size Maximum size
     * @param float $maxwidth
     */
    private static function fit(\pdf $pdf, string $font, string $style, string $text, float $size, float $maxwidth): void {
        while ($size > 6) {
            $pdf->SetFont($font, $style, $size);
            if ($pdf->GetStringWidth($text) <= $maxwidth) {
                return;
            }
            $size -= 1;
        }
        $pdf->SetFont($font, $style, 6);
    }

    /**
     * Draws one badge in the box (0, 0, w, h).
     *
     * @param \pdf $pdf
     * @param string $font
     * @param int[] $color
     * @param string|null $logo
     * @param \stdClass $event
     * @param array $person
     * @param float $w
     * @param float $h
     * @param string $format
     */
    private static function draw(
        \pdf $pdf,
        string $font,
        array $color,
        ?string $logo,
        \stdClass $event,
        array $person,
        float $w,
        float $h,
        string $format
    ): void {
        $big = $format === 'a6';
        $topband = $big ? 34 : 15;
        $botband = $big ? 26 : 13;
        $pad = $big ? 7 : 4;
        $rolecolor = self::role_color($person['role'], $color);

        // Top band: event name and logo, with the lanyard slot.
        $pdf->SetFillColor($color[0], $color[1], $color[2]);
        $pdf->Rect(0, 0, $w, $topband, 'F');
        $textw = $w - 2 * $pad - ($logo ? ($big ? 20 : 11) : 0);
        $pdf->SetTextColor(255, 255, 255);
        $name = format_string($event->name, true, ['escape' => false]);
        self::fit($pdf, $font, 'B', $name, $big ? 17 : 10, $textw * ($big ? 1.9 : 1.6));
        $pdf->SetXY($pad, $big ? 12 : 3.5);
        $pdf->MultiCell($textw, 0, $name, 0, 'L', false, 1, '', '', true, 0, false, true, $topband - ($big ? 14 : 6), 'T');
        if ($logo) {
            $ls = $big ? 18 : 10;
            $pdf->Image(
                '@' . $logo,
                $w - $pad - $ls,
                ($topband - $ls) / 2 + ($big ? 3 : 0),
                $ls,
                $ls,
                '',
                '',
                '',
                false,
                300,
                '',
                false,
                false,
                0,
                'RM'
            );
        }
        if ($big) {
            $pdf->SetDrawColor(255, 255, 255);
            $pdf->SetLineStyle(['width' => 0.5, 'color' => [255, 255, 255]]);
            $pdf->RoundedRect($w / 2 - 8, 4, 16, 3.5, 1.7, '1111', 'D');
        }

        // When and where.
        $when = userdate($event->timestart, get_string('strftimedatefullshort', 'langconfig'));
        $info = trim($when . (empty($event->location) ? '' : ' · ' . $event->location));
        $pdf->SetTextColor(90, 90, 90);
        self::fit($pdf, $font, '', $info, $big ? 8.5 : 6, $w - 2 * $pad);
        $pdf->SetXY($pad, $topband + ($big ? 3 : 1.5));
        $pdf->Cell($w - 2 * $pad, 4, $info, 0, 0, 'C');

        // Name.
        $areatop = $topband + ($big ? 10 : 5);
        $areabottom = $h - $botband - ($big && !empty($person['ticket']) ? 38 : 2);
        $first = $person['first'];
        $last = \core_text::strtoupper($person['last']);
        $pdf->SetTextColor(20, 20, 20);
        $maxw = $w - 2 * $pad;
        $mid = $areatop + ($areabottom - $areatop) / 2;
        $fh = $big ? 13 : 8.5;
        $lh = $big ? 8 : 5;
        $y = $mid - ($fh + ($last !== '' ? $lh : 0)) / 2;
        self::fit($pdf, $font, 'B', $first, $big ? 34 : 20, $maxw);
        $pdf->SetXY($pad, $y);
        $pdf->Cell($maxw, $fh, $first, 0, 0, 'C');
        if ($last !== '') {
            self::fit($pdf, $font, '', $last, $big ? 20 : 11, $maxw);
            $pdf->SetXY($pad, $y + $fh);
            $pdf->Cell($maxw, $lh, $last, 0, 0, 'C');
        }

        // QR of the ticket (guests only).
        if ($big && !empty($person['ticket'])) {
            $qr = 30;
            local\qr_encoder::draw(
                $pdf,
                booking_manager::ticket_scan_url($person['ticket'])->out(false),
                ($w - $qr) / 2,
                $h - $botband - $qr - 3,
                $qr
            );
        }

        // Bottom band: the role.
        $pdf->SetFillColor($rolecolor[0], $rolecolor[1], $rolecolor[2]);
        $pdf->Rect(0, $h - $botband, $w, $botband, 'F');
        $role = \core_text::strtoupper($person['role']);
        $pdf->SetTextColor(255, 255, 255);
        self::fit($pdf, $font, 'B', $role, $big ? 22 : 13, $w - 2 * $pad);
        $pdf->SetXY($pad, $h - $botband);
        $pdf->Cell($w - 2 * $pad, $botband, $role, 0, 0, 'C', false, '', 0, false, 'T', 'M');
    }
}
