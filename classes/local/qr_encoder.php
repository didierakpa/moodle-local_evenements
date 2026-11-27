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

namespace local_evenements\local;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tcpdf/include/barcodes/qrcode.php');

/**
 * QR codes of the tickets, based on the TCPDF encoder shipped with Moodle.
 *
 * With PHP 8.4 and the OPcache JIT set to 1235, the frame filler of the TCPDF
 * encoder is compiled wrongly: the QR code cannot be built and the PDF fails.
 * The frame filler is rewritten here in a form that the JIT compiles correctly;
 * the rest of the encoder (data, error correction, masks) is the TCPDF one.
 * The modules are then drawn directly, in the PDF or as SVG.
 *
 * @package    local_evenements
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qr_encoder extends \QRcode {
    /** @var int Quiet zone around the code, in modules (same as TCPDF "auto" padding). */
    private const QUIET_ZONE = 4;

    /**
     * Next free position of the frame (zigzag from the bottom right corner).
     *
     * @return array|null x, y coordinates or null when the frame is full
     */
    protected function getNextPosition() { // phpcs:ignore moodle.NamingConventions.ValidFunctionName.LowercaseMethod
        $w = (int) $this->width;
        while (true) {
            if ($this->bit === -1) {
                $this->bit = 0;
                return ['x' => $this->x, 'y' => $this->y];
            }
            $x = (int) $this->x;
            $y = (int) $this->y;
            if ($this->bit === 0) {
                $x = $x - 1;
                $this->bit = 1;
            } else {
                $x = $x + 1;
                $y = $y + (int) $this->dir;
                $this->bit = 0;
            }
            if ($this->dir < 0 && $y < 0) {
                $y = 0;
                $x = $x - 2;
                $this->dir = 1;
                if ($x === 6) {
                    $x = 5;
                    $y = 9;
                }
            } else if ($this->dir > 0 && $y === $w) {
                $y = $w - 1;
                $x = $x - 2;
                $this->dir = -1;
                if ($x === 6) {
                    $x = 5;
                    $y = $y - 8;
                }
            }
            if ($x < 0 || $y < 0) {
                return null;
            }
            $this->x = $x;
            $this->y = $y;
            if ((ord($this->frame[$y][$x]) & 0x80) === 0) {
                return ['x' => $x, 'y' => $y];
            }
        }
    }

    /**
     * Modules of the QR code of a text (error correction level M).
     *
     * @param string $text
     * @return int[][] Rows of 0 (light) and 1 (dark)
     * @throws \moodle_exception When the text cannot be encoded
     */
    public static function matrix(string $text): array {
        $failed = false;
        set_error_handler(function () use (&$failed): bool {
            $failed = true;
            return true;
        });
        try {
            $array = (new self($text, 'M'))->getBarcodeArray();
        } catch (\Throwable $e) {
            $failed = true;
        } finally {
            restore_error_handler();
        }
        if ($failed || empty($array['bcode']) || !is_array($array['bcode'])) {
            throw new \moodle_exception('qrcodefailed', 'local_evenements');
        }
        return $array['bcode'];
    }

    /**
     * Draw the QR code of a text in a PDF, quiet zone included.
     *
     * @param \pdf $pdf
     * @param string $text
     * @param float $x Left edge of the square, in user units
     * @param float $y Top edge of the square
     * @param float $size Side of the square, quiet zone included
     */
    public static function draw(\pdf $pdf, string $text, float $x, float $y, float $size): void {
        $rows = self::matrix($text);
        $count = count($rows);
        $module = $size / ($count + 2 * self::QUIET_ZONE);
        $left = $x + self::QUIET_ZONE * $module;
        $top = $y + self::QUIET_ZONE * $module;
        $pdf->SetFillColor(0, 0, 0);
        foreach ($rows as $r => $row) {
            foreach (self::runs($row) as [$start, $length]) {
                $pdf->Rect($left + $start * $module, $top + $r * $module, $length * $module, $module, 'F');
            }
        }
    }

    /**
     * SVG picture of the QR code of a text (without quiet zone).
     *
     * @param string $text
     * @param int $modulesize Side of one module, in pixels
     * @return string
     */
    public static function svg(string $text, int $modulesize = 5): string {
        $rows = self::matrix($text);
        $side = count($rows) * $modulesize;
        $rects = '';
        foreach ($rows as $r => $row) {
            foreach (self::runs($row) as [$start, $length]) {
                $rects .= '<rect x="' . ($start * $modulesize) . '" y="' . ($r * $modulesize) . '" width="' .
                    ($length * $modulesize) . '" height="' . $modulesize . '"/>';
            }
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $side . '" height="' . $side . '" viewBox="0 0 ' .
            $side . ' ' . $side . '" role="img" aria-label="QR code"><g fill="black" stroke="none">' . $rects . '</g></svg>';
    }

    /**
     * Runs of dark modules in a row, to draw one rectangle per run.
     *
     * @param int[] $row
     * @return array[] [start, length] pairs
     */
    private static function runs(array $row): array {
        $runs = [];
        $start = null;
        $count = count($row);
        for ($c = 0; $c <= $count; $c++) {
            $dark = $c < $count && !empty($row[$c]);
            if ($dark && $start === null) {
                $start = $c;
            } else if (!$dark && $start !== null) {
                $runs[] = [$start, $c - $start];
                $start = null;
            }
        }
        return $runs;
    }
}
