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

/**
 * Tests of the PDF tickets and badges (QR codes included).
 *
 * @package    local_evenements
 * @category   test
 * @copyright  2026 Didier AKPA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_evenements\ticket_pdf
 * @covers     \local_evenements\badge_pdf
 */
final class ticket_pdf_test extends \advanced_testcase {
    /**
     * Tickets and badges are produced without any PHP warning.
     */
    public function test_generate_without_warning(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $plugin = $this->getDataGenerator()->get_plugin_generator('local_evenements');
        $event = $plugin->create_event(['name' => 'Open day']);
        $booking = $plugin->create_participant(['eventid' => $event->id, 'firstname' => 'Lea', 'lastname' => 'Martin',
            'email' => 'lea@example.com']);
        $tickets = booking_manager::get_tickets((int) $booking->id);
        $this->assertNotEmpty($tickets);

        $warnings = [];
        set_error_handler(function (int $no, string $str, string $file, int $line) use (&$warnings): bool {
            $warnings[] = $str . ' @ ' . $file . ':' . $line . "\n" . (new \Exception())->getTraceAsString();
            return true;
        });
        try {
            $ticket = ticket_pdf::generate($event, $booking, $tickets);
            $sheet = ticket_pdf::generate($event, $booking, $tickets, true);
        } finally {
            restore_error_handler();
        }
        $this->assertSame([], $warnings, implode("\n", $warnings));
        $this->assertStringStartsWith('%PDF', $ticket);
        $this->assertStringStartsWith('%PDF', $sheet);
    }
}
