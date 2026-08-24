@local @local_communications
Feature: Feedback prompt preferences
  In order to control whether I get asked for feedback
  As a user
  I need to be able to turn off feedback requests everywhere and turn them back on

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | student1 | Student   | One      | student1@example.com |
    And I log in as "student1"

  Scenario: Turn off and back on all feedback requests
    Given I visit "/local/communications/preferences.php"
    Then I should see "You haven't turned off feedback requests for anything."
    And I should see "Turn off feedback requests everywhere"
    When I press "Turn off feedback requests everywhere"
    Then I should see "Feedback requests turned off everywhere."
    And I should see "You've turned off feedback requests everywhere"
    When I press "Turn feedback requests back on"
    Then I should see "Feedback requests turned back on."
    And I should see "Turn off feedback requests everywhere"
