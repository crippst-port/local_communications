@local @local_communications
Feature: Manage dashboard news stories
  In order to communicate updates to users via the dashboard
  As an admin
  I need to be able to create, enable/disable and delete dashboard news stories

  Background:
    Given I log in as "admin"

  Scenario: Create a new dashboard news story
    Given I visit "/local/communications/manage_news.php"
    When I click on "Create story" "link"
    Then I should see "Create story"
    And I set the following fields to these values:
      | Headline | Behat news story |
    And I press "Save changes"
    Then I should see "Story saved."
    And I should see "Behat news story"

  Scenario: Disable and re-enable a story from the manage list
    Given I visit "/local/communications/edit_news.php"
    And I set the following fields to these values:
      | Headline | Toggle test story |
    And I press "Save changes"
    And I should see "Toggle test story"
    When I click on "Disable" "link" in the "Toggle test story" "table_row"
    Then I should see "Disabled" in the "Toggle test story" "table_row"
    When I click on "Enable" "link" in the "Toggle test story" "table_row"
    Then I should see "Enabled" in the "Toggle test story" "table_row"

  Scenario: Delete a story
    Given I visit "/local/communications/edit_news.php"
    And I set the following fields to these values:
      | Headline | Delete test story |
    And I press "Save changes"
    And I should see "Delete test story"
    When I click on "Delete" "link" in the "Delete test story" "table_row"
    Then I should not see "Delete test story"
