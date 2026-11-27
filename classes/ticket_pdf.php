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
 * Generates the tickets as a PDF (one concert-style ticket per page).
 *
 * The layout is 210 x 90 mm: an illustration or a colour block on the left,
 * the event and the guest in the middle, the QR code on the right.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ticket_pdf {
    /** @var int Page width of the default format, in millimetres. */
    private const WIDTH = 210;
    /** @var int Page height of the default format, in millimetres. */
    private const HEIGHT = 90;

    /**
     * Build the PDF.
     *
     * @param \stdClass $event
     * @param \stdClass $booking
     * @param \stdClass[] $tickets Tickets to print
     * @param bool $sheet All the tickets on A4 pages instead of one page per ticket
     * @return string The PDF content
     */
    public static function generate(\stdClass $event, \stdClass $booking, array $tickets, bool $sheet = false): string {
        global $CFG;

        $event = booking_manager::apply_session($event, $booking);
        $format = in_array($event->ticketformat ?? 'concert', ['concert', 'boarding', 'compact'], true) ?
            ($event->ticketformat ?? 'concert') : 'concert';
        $sizes = ['concert' => [self::WIDTH, self::HEIGHT], 'boarding' => [175, 74], 'compact' => [148, 68]];
        [$w, $h] = $sizes[$format];
        // Several small tickets on one A4 sheet (to print and cut).
        $sheet = $sheet && $format !== 'concert';
        $pdf = $sheet ? new \pdf('P', 'mm', 'A4', true, 'UTF-8') : new \pdf('L', 'mm', [$w, $h], true, 'UTF-8');
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
        $logo = self::get_logo($event);
        $color = self::hex_to_rgb($event->ticketcolor);
        $imagedata = self::get_event_image($event);
        $categories = booking_manager::get_categories((int) $event->id);

        $draw = function ($ticket) use ($pdf, $font, $color, $imagedata, $event, $categories, $logo, $format) {
            if ($format === 'boarding') {
                self::draw_boarding($pdf, $font, $color, $event, $ticket, $categories, $logo);
            } else if ($format === 'compact') {
                self::draw_compact($pdf, $font, $color, $event, $ticket, $categories, $logo);
            } else {
                self::draw_ticket($pdf, $font, $color, $imagedata, $event, $ticket, $categories, $logo);
            }
        };

        if ($sheet) {
            $perpage = (int) floor(280 / $h);
            $left = (210 - $w) / 2;
            $top = (297 - $perpage * $h) / 2;
            foreach (array_values($tickets) as $i => $ticket) {
                if ($i % $perpage === 0) {
                    $pdf->AddPage();
                    $pdf->SetLineStyle(['width' => 0.2, 'dash' => '1,2', 'color' => [170, 170, 170]]);
                    for ($r = 0; $r <= $perpage; $r++) {
                        $pdf->Line(0, $top + $r * $h, 210, $top + $r * $h);
                    }
                    $pdf->SetLineStyle(['dash' => 0]);
                }
                $pdf->StartTransform();
                $pdf->Translate($left, $top + ($i % $perpage) * $h);
                $draw($ticket);
                $pdf->StopTransform();
            }
        } else {
            foreach ($tickets as $ticket) {
                $pdf->AddPage();
                $draw($ticket);
            }
        }

        return $pdf->Output('billets.pdf', 'S');
    }

    /**
     * Boarding-pass style ticket, 175 x 74 mm: guest and details on the left, stub with the QR code on the right.
     *
     * @param \pdf $pdf
     * @param string $font
     * @param int[] $color
     * @param \stdClass $event
     * @param \stdClass $ticket
     * @param \stdClass[] $categories
     * @param string|null $logo
     */
    private static function draw_boarding(
        \pdf $pdf,
        string $font,
        array $color,
        \stdClass $event,
        \stdClass $ticket,
        array $categories,
        ?string $logo
    ): void {
        $w = 175;
        $h = 74;
        $cut = 128;
        $pdf->SetFillColor($color[0], $color[1], $color[2]);
        $pdf->Rect(0, 0, $cut, 12, 'F');
        $pdf->Rect($cut, 0, $w - $cut, 12, 'F');
        $title = trim((string) $event->tickettitle) !== '' ? $event->tickettitle : get_string('ticket', 'local_evenements');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont($font, 'B', 10.5);
        $pdf->SetXY(7, 3.2);
        $pdf->Cell($cut - 40, 6, \core_text::strtoupper($title), 0, 0, 'L');
        self::draw_logo($pdf, $logo, $cut - 33, 1.2, 26, 9.6);
        $pdf->SetFont($font, 'B', 9);
        $pdf->SetXY($cut, 3.5);
        $pdf->Cell($w - $cut - 5, 5, 'N° ' . str_pad((string) $ticket->id, 6, '0', STR_PAD_LEFT), 0, 0, 'R');

        // Guest, large.
        $pdf->SetTextColor(125, 125, 125);
        $pdf->SetFont($font, '', 7.5);
        $pdf->SetXY(7, 16);
        $pdf->Cell(60, 4, \core_text::strtoupper(get_string('guest', 'local_evenements')), 0, 0, 'L');
        $pdf->SetTextColor(20, 20, 20);
        $pdf->SetFont($font, 'B', 18);
        $pdf->SetXY(7, 20.5);
        $pdf->Cell($cut - 14, 9, $ticket->firstname . ' ' . \core_text::strtoupper($ticket->lastname), 0, 0, 'L');
        if (!empty($categories[$ticket->tickettypeid])) {
            $pdf->SetFillColor($color[0], $color[1], $color[2]);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont($font, 'B', 8.5);
            $label = \core_text::strtoupper(booking_manager::ticket_label($ticket, $categories));
            $lw = $pdf->GetStringWidth($label) + 6;
            $pdf->RoundedRect(7, 31.5, $lw, 6, 1.5, '1111', 'F');
            $pdf->SetXY(7, 32.1);
            $pdf->Cell($lw, 4.8, $label, 0, 0, 'C');
        }

        // Event, then three columns: date, time, place.
        $pdf->SetTextColor(40, 40, 40);
        $pdf->SetFont($font, 'B', 11);
        $pdf->SetXY(7, 41);
        $pdf->Cell($cut - 14, 6, format_string($event->name), 0, 0, 'L');
        $cols = self::when_columns($event);
        $x = 7;
        $widths = [38, 28, $cut - 14 - 66];
        foreach (
            [[get_string('ticketcoldate', 'local_evenements'), $cols[0]],
                [get_string('ticketcoltime', 'local_evenements'), $cols[1]],
                [get_string('ticketcolplace', 'local_evenements'), $cols[2]]] as $i => [$label, $value]
        ) {
            $pdf->SetTextColor(125, 125, 125);
            $pdf->SetFont($font, '', 7);
            $pdf->SetXY($x, 50);
            $pdf->Cell($widths[$i], 3.5, \core_text::strtoupper($label), 0, 0, 'L');
            $pdf->SetTextColor(20, 20, 20);
            $pdf->SetFont($font, 'B', 9.5);
            $pdf->SetXY($x, 54);
            $pdf->Cell($widths[$i], 5, $value, 0, 0, 'L', false, '', 1);
            $x += $widths[$i];
        }
        $info = booking_manager::ticket_info($ticket, $categories);
        if ($info !== '') {
            $pdf->SetFont($font, 'B', 7);
            $pdf->SetTextColor(20, 20, 20);
            $pdf->SetXY(7, 60.5);
            $pdf->Cell($cut - 14, 3.5, $info, 0, 0, 'L', false, '', 1);
        }
        if (!empty($event->tickettext)) {
            $pdf->SetFont($font, '', 6.5);
            $pdf->SetTextColor(130, 130, 130);
            $pdf->SetXY(7, $info !== '' ? 65 : 62);
            $pdf->MultiCell($cut - 14, 3, $event->tickettext, 0, 'L', false, 1, '', '', true, 0, false, true, 7, 'T', true);
        }

        // Stub.
        $pdf->SetLineStyle(['width' => 0.3, 'dash' => '2,2', 'color' => [160, 160, 160]]);
        $pdf->Line($cut, 12, $cut, $h);
        $pdf->SetLineStyle(['dash' => 0]);
        self::draw_qr($pdf, $ticket, $cut + 8, 17, 32);
        $pdf->SetTextColor(40, 40, 40);
        $pdf->SetFont($font, 'B', 8);
        $pdf->SetXY($cut, 51);
        $pdf->Cell($w - $cut, 4, \core_text::strtoupper(substr($ticket->token, 0, 8)), 0, 0, 'C');
        $pdf->SetTextColor(125, 125, 125);
        $pdf->SetFont($font, '', 6.5);
        $pdf->SetXY($cut, 56);
        $pdf->Cell($w - $cut, 3.5, \core_text::strtoupper($ticket->lastname), 0, 0, 'C', false, '', 1);
    }

    /**
     * Compact "train ticket" style, 148 x 68 mm: colour band, details in columns, QR code on the right.
     *
     * @param \pdf $pdf
     * @param string $font
     * @param int[] $color
     * @param \stdClass $event
     * @param \stdClass $ticket
     * @param \stdClass[] $categories
     * @param string|null $logo
     */
    private static function draw_compact(
        \pdf $pdf,
        string $font,
        array $color,
        \stdClass $event,
        \stdClass $ticket,
        array $categories,
        ?string $logo
    ): void {
        $w = 148;
        $h = 68;
        $cut = 108;
        $pdf->SetFillColor($color[0], $color[1], $color[2]);
        $pdf->Rect(0, 0, $w, 11, 'F');
        $title = trim((string) $event->tickettitle) !== '' ? $event->tickettitle : get_string('ticket', 'local_evenements');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont($font, 'B', 10);
        $pdf->SetXY(6, 2.8);
        $pdf->Cell($cut - 40, 6, \core_text::strtoupper($title), 0, 0, 'L');
        self::draw_logo($pdf, $logo, $cut - 32, 1, 24, 9);
        $pdf->SetFont($font, 'B', 8.5);
        $pdf->SetXY($cut, 3.2);
        $pdf->Cell($w - $cut - 5, 5, 'N° ' . str_pad((string) $ticket->id, 6, '0', STR_PAD_LEFT), 0, 0, 'R');

        $pdf->SetTextColor(20, 20, 20);
        $pdf->SetFont($font, 'B', 13);
        $pdf->SetXY(6, 14.5);
        $pdf->Cell($cut - 12, 7, format_string($event->name), 0, 0, 'L', false, '', 1);

        $cols = self::when_columns($event);
        $x = 6;
        $widths = [36, 24, $cut - 12 - 60];
        foreach (
            [[get_string('ticketcoldate', 'local_evenements'), $cols[0]],
                [get_string('ticketcoltime', 'local_evenements'), $cols[1]],
                [get_string('ticketcolplace', 'local_evenements'), $cols[2]]] as $i => [$label, $value]
        ) {
            $pdf->SetTextColor(125, 125, 125);
            $pdf->SetFont($font, '', 6.8);
            $pdf->SetXY($x, 23.5);
            $pdf->Cell($widths[$i], 3.5, \core_text::strtoupper($label), 0, 0, 'L');
            $pdf->SetTextColor(20, 20, 20);
            $pdf->SetFont($font, 'B', 9);
            $pdf->SetXY($x, 27.3);
            $pdf->Cell($widths[$i], 5, $value, 0, 0, 'L', false, '', 1);
            $x += $widths[$i];
        }

        $pdf->SetDrawColor($color[0], $color[1], $color[2]);
        $pdf->SetLineWidth(0.4);
        $pdf->Line(6, 36, $cut - 6, 36);
        $pdf->SetTextColor(125, 125, 125);
        $pdf->SetFont($font, '', 7);
        $pdf->SetXY(6, 38);
        $pdf->Cell(60, 3.5, \core_text::strtoupper(get_string('guest', 'local_evenements')), 0, 0, 'L');
        $pdf->SetTextColor(20, 20, 20);
        $pdf->SetFont($font, 'B', 15);
        $pdf->SetXY(6, 42);
        $pdf->Cell($cut - 12, 8, $ticket->firstname . ' ' . \core_text::strtoupper($ticket->lastname), 0, 0, 'L', false, '', 1);
        if (!empty($categories[$ticket->tickettypeid])) {
            $pdf->SetTextColor($color[0], $color[1], $color[2]);
            $pdf->SetFont($font, 'B', 9);
            $pdf->SetXY(6, 51);
            $pdf->Cell($cut - 12, 5, booking_manager::ticket_label($ticket, $categories), 0, 0, 'L', false, '', 1);
        }
        $info = booking_manager::ticket_info($ticket, $categories);
        if ($info !== '') {
            $pdf->SetFont($font, 'B', 6.8);
            $pdf->SetTextColor(20, 20, 20);
            $pdf->SetXY(6, 56.5);
            $pdf->Cell($cut - 12, 3.5, $info, 0, 0, 'L', false, '', 1);
        }
        if (!empty($event->tickettext)) {
            $pdf->SetFont($font, '', 6.3);
            $pdf->SetTextColor(135, 135, 135);
            $pdf->SetXY(6, $info !== '' ? 60.8 : 58);
            $pdf->MultiCell($cut - 12, 2.8, $event->tickettext, 0, 'L', false, 1, '', '', true, 0, false, true, 6, 'T', true);
        }

        $pdf->SetLineStyle(['width' => 0.3, 'dash' => '2,2', 'color' => [160, 160, 160]]);
        $pdf->Line($cut, 11, $cut, $h);
        $pdf->SetLineStyle(['dash' => 0]);
        self::draw_qr($pdf, $ticket, $cut + 4, 17, 32);
        $pdf->SetTextColor(40, 40, 40);
        $pdf->SetFont($font, 'B', 7.5);
        $pdf->SetXY($cut, 51);
        $pdf->Cell($w - $cut, 4, \core_text::strtoupper(substr($ticket->token, 0, 8)), 0, 0, 'C');
    }

    /**
     * Date, time and place of an event as three short texts.
     *
     * @param \stdClass $event
     * @return string[]
     */
    private static function when_columns(\stdClass $event): array {
        $date = userdate($event->timestart, get_string('strftimedaydate', 'langconfig'));
        $time = userdate($event->timestart, get_string('strftimetime', 'langconfig'));
        if (!empty($event->timeend)) {
            $time .= ' - ' . userdate($event->timeend, get_string('strftimetime', 'langconfig'));
        }
        return [$date, $time, (string) ($event->location ?? '')];
    }

    /**
     * Draws the logo if there is one.
     *
     * @param \pdf $pdf
     * @param string|null $logo
     * @param float $x
     * @param float $y
     * @param float $w
     * @param float $h
     */
    private static function draw_logo(\pdf $pdf, ?string $logo, float $x, float $y, float $w, float $h): void {
        if ($logo === null) {
            return;
        }
        try {
            $pdf->Image('@' . $logo, $x, $y, $w, $h, '', '', '', false, 300, '', false, false, 0, 'RM');
        } catch (\Throwable $e) {
            debugging('local_evenements: logo not drawn: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Draws the QR code of a ticket.
     *
     * @param \pdf $pdf
     * @param \stdClass $ticket
     * @param float $x
     * @param float $y
     * @param float $size
     */
    private static function draw_qr(\pdf $pdf, \stdClass $ticket, float $x, float $y, float $size): void {
        local\qr_encoder::draw($pdf, booking_manager::ticket_scan_url($ticket)->out(false), $x, $y, $size);
    }

    /**
     * Draw one ticket on the current page.
     *
     * @param \pdf $pdf
     * @param string $font
     * @param int[] $color
     * @param string|null $imagedata
     * @param \stdClass $event
     * @param \stdClass $ticket
     * @param \stdClass[] $categories
     * @param string|null $logo Logo image data
     */
    private static function draw_ticket(
        \pdf $pdf,
        string $font,
        array $color,
        ?string $imagedata,
        \stdClass $event,
        \stdClass $ticket,
        array $categories,
        ?string $logo = null
    ): void {

        $perforation = 155;

        // Left panel: illustration or plain colour.
        $pdf->SetFillColor($color[0], $color[1], $color[2]);
        $pdf->Rect(0, 0, 55, self::HEIGHT, 'F');
        if ($imagedata !== null) {
            try {
                $pdf->Image('@' . $imagedata, 0, 0, 55, self::HEIGHT, '', '', '', false, 300, '', false, false, 0, 'CM');
            } catch (\Throwable $e) {
                debugging('local_evenements: image not drawn: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        // Top bar of the central area.
        $pdf->SetFillColor($color[0], $color[1], $color[2]);
        $pdf->Rect(55, 0, $perforation - 55, 14, 'F');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont($font, 'B', 11);
        $pdf->SetXY(60, 3.5);
        $title = trim((string) $event->tickettitle) !== '' ? $event->tickettitle : get_string('ticket', 'local_evenements');
        $pdf->Cell($perforation - 65, 7, \core_text::strtoupper($title), 0, 0, 'L');
        if ($logo !== null) {
            try {
                $pdf->Image('@' . $logo, $perforation - 30, 1.5, 24, 11, '', '', '', false, 300, '', false, false, 0, 'RM');
            } catch (\Throwable $e) {
                debugging('local_evenements: logo not drawn: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        // Event name.
        $pdf->SetTextColor(30, 30, 30);
        $pdf->SetFont($font, 'B', 19);
        $pdf->SetXY(60, 19);
        $pdf->MultiCell($perforation - 65, 8, format_string($event->name), 0, 'L');

        // Date and place.
        $pdf->SetFont($font, '', 10.5);
        $pdf->SetTextColor(70, 70, 70);
        $pdf->SetXY(60, 39);
        $pdf->MultiCell($perforation - 65, 5, booking_manager::event_when($event), 0, 'L');
        if (!empty($event->location)) {
            $pdf->SetX(60);
            $pdf->MultiCell($perforation - 65, 5, $event->location, 0, 'L');
        }

        // Guest.
        $pdf->SetDrawColor($color[0], $color[1], $color[2]);
        $pdf->SetLineWidth(0.5);
        $pdf->Line(60, 56, $perforation - 8, 56);
        $pdf->SetFont($font, '', 8.5);
        $pdf->SetTextColor(120, 120, 120);
        $pdf->SetXY(60, 58);
        $pdf->Cell(60, 4, \core_text::strtoupper(get_string('guest', 'local_evenements')), 0, 0, 'L');
        $pdf->SetFont($font, 'B', 16);
        $pdf->SetTextColor(20, 20, 20);
        $pdf->SetXY(60, 63);
        $pdf->Cell($perforation - 65, 8, $ticket->firstname . ' ' . \core_text::strtoupper($ticket->lastname), 0, 0, 'L');
        if (!empty($categories[$ticket->tickettypeid])) {
            $pdf->SetFont($font, '', 10);
            $pdf->SetTextColor($color[0], $color[1], $color[2]);
            $pdf->SetXY(60, 72);
            $pdf->Cell($perforation - 65, 5, booking_manager::ticket_label($ticket, $categories), 0, 0, 'L', false, '', 1);
        }

        // Free text (conditions, access, etc.).
        $info = booking_manager::ticket_info($ticket, $categories);
        if ($info !== '') {
            $pdf->SetFont($font, 'B', 7.5);
            $pdf->SetTextColor(20, 20, 20);
            $pdf->SetXY(60, 78);
            $pdf->Cell($perforation - 65, 4, $info, 0, 0, 'L', false, '', 1);
        }
        if (!empty($event->tickettext)) {
            $pdf->SetFont($font, '', 7);
            $pdf->SetTextColor(130, 130, 130);
            $pdf->SetXY(60, $info !== '' ? 82.5 : 79);
            $pdf->MultiCell($perforation - 65, 3, $event->tickettext, 0, 'L', false, 1, '', '', true, 0, false, true, 6, 'T', true);
        }

        // Perforation line.
        $pdf->SetLineStyle(['width' => 0.3, 'dash' => '2,2', 'color' => [160, 160, 160]]);
        $pdf->Line($perforation, 0, $perforation, self::HEIGHT);
        $pdf->SetLineStyle(['dash' => 0]);

        // QR code and serial number.
        $url = booking_manager::ticket_scan_url($ticket)->out(false);
        local\qr_encoder::draw($pdf, $url, $perforation + 7, 17, 41);

        $pdf->SetFont($font, 'B', 9);
        $pdf->SetTextColor(40, 40, 40);
        $pdf->SetXY($perforation, 62);
        $pdf->Cell(self::WIDTH - $perforation, 5, 'N° ' . str_pad((string) $ticket->id, 6, '0', STR_PAD_LEFT), 0, 0, 'C');
        $pdf->SetFont($font, '', 7);
        $pdf->SetTextColor(130, 130, 130);
        $pdf->SetXY($perforation, 68);
        $pdf->Cell(self::WIDTH - $perforation, 4, \core_text::strtoupper(substr($ticket->token, 0, 8)), 0, 0, 'C');
    }

    /**
     * Content of the event image, if any.
     *
     * @param \stdClass $event
     * @return string|null
     */
    private static function get_event_image(\stdClass $event): ?string {
        $image = self::get_area_image($event, 'eventimage');
        if ($image !== null) {
            return $image;
        }
        $theme = in_array($event->imagetheme ?? '', booking_manager::IMAGE_THEMES, true) ? $event->imagetheme : 'generic';
        $path = __DIR__ . '/../pix/defaults/' . $theme . '.jpg';
        return is_readable($path) ? file_get_contents($path) : null;
    }

    /**
     * Logo printed on tickets and badges: the logo of the event, else the default logo of the plugin settings,
     * else the compact logo (or logo) of the site, else the icon shipped with the plugin.
     *
     * @param \stdClass $event
     * @return string|null Image content (PNG, JPEG or GIF)
     */
    public static function get_logo(\stdClass $event): ?string {
        $own = self::get_area_image($event, 'ticketlogo');
        if ($own !== null) {
            return $own;
        }
        $fs = get_file_storage();
        $sysid = \context_system::instance()->id;
        $candidates = [
            ['local_evenements', 'defaultlogo'],
            ['core_admin', 'logocompact'],
            ['core_admin', 'logo'],
        ];
        foreach ($candidates as [$component, $area]) {
            foreach ($fs->get_area_files($sysid, $component, $area, false, 'sortorder, id', false) as $file) {
                if ($file->is_valid_image() && in_array($file->get_mimetype(), ['image/png', 'image/jpeg', 'image/gif'], true)) {
                    return $file->get_content();
                }
            }
        }
        $path = __DIR__ . '/../pix/default-logo.png';
        return is_readable($path) ? file_get_contents($path) : null;
    }

    /**
     * Content of the first image of a file area of the event.
     *
     * @param \stdClass $event
     * @param string $filearea
     * @return string|null
     */
    public static function get_area_image(\stdClass $event, string $filearea): ?string {
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            \context_system::instance()->id,
            'local_evenements',
            $filearea,
            $event->id,
            'sortorder, id',
            false
        );
        foreach ($files as $file) {
            if ($file->is_valid_image()) {
                return $file->get_content();
            }
        }
        return null;
    }

    /**
     * Convert #RRGGBB to an RGB array.
     *
     * @param string $hex
     * @return int[]
     */
    public static function hex_to_rgb(string $hex): array {
        if (!preg_match('/^#([0-9a-fA-F]{2})([0-9a-fA-F]{2})([0-9a-fA-F]{2})$/', $hex, $m)) {
            return [29, 78, 137];
        }
        return [hexdec($m[1]), hexdec($m[2]), hexdec($m[3])];
    }
}
