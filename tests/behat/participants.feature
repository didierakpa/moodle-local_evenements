@local @local_evenements
Feature: Organisers manage the participants of an event
  In order to keep the guest list right
  As an organiser
  I need to change, cancel and restore tickets

  Background:
    Given the following "local_evenements > events" exist:
      | name     | status | capacity |
      | Open day | open   | 50       |
    And the following "local_evenements > participants" exist:
      | event    | firstname | lastname |
      | Open day | Lea       | Martin   |
      | Open day | Tom       | Durand   |

  Scenario: An organiser cancels and restores a ticket
    Given I log in as "admin"
    And I am on the "Open day" "local_evenements > Registrations" page
    And I should see "DURAND Tom"
    When I click on "MARTIN Lea" "link"
    And I click on "Cancel the ticket" "link"
    Then I should see "Ticket cancelled"
    And I click on "Restore the ticket" "link"
    And I should see "Ticket restored"

  Scenario: An organiser changes the name of a participant
    Given I log in as "admin"
    And I am on the "Open day" "local_evenements > Registrations" page
    When I click on "DURAND Tom" "link"
    And I set the field "Last name" to "Durand-Petit"
    And I press "Save changes"
    Then I should see "Changes saved"
    And I should see "DURAND-PETIT Tom"
