@local @local_evenements
Feature: Visitors book tickets without a Moodle account
  In order to come to an event
  As a visitor
  I need to see the open events and book named tickets

  Background:
    Given the following "local_evenements > events" exist:
      | name         | status | capacity |
      | Open day     | open   | 50       |
      | Secret draft | draft  | 50       |

  Scenario: Only published events are listed
    When I am on the "local_evenements > Public list" page
    Then I should see "Open day"
    And I should not see "Secret draft"

  Scenario: A visitor books a ticket and sees it on the personal page
    When I am on the "Open day" "local_evenements > Booking" page
    And I set the following fields to these values:
      | Your full name              | Jane Parent      |
      | Your e-mail address         | jane@example.com |
      | Confirm your e-mail address | jane@example.com |
      | First name                  | Lea              |
      | Last name                   | Martin           |
      | I have read the information above and agree that my data is used for this event | 1 |
    And I press "Confirm the booking"
    Then I should see "Lea"
    And I should see "Martin"
    And I should see "Open day"
