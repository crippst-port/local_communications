@local @local_communications
Feature: Manage feedback campaigns
  In order to control when and where the feedback widget appears
  As an admin
  I need to be able to create, enable/disable and delete feedback campaigns

  Background:
    Given I log in as "admin"

  Scenario: Create a new feedback campaign
    Given I visit "/local/communications/manage_campaigns.php"
    When I click on "Create campaign" "link"
    Then I should see "Create campaign"
    And I set the following fields to these values:
      | Name | Behat feedback campaign |
    And I press "Save changes"
    Then I should see "Campaign saved."
    And I should see "Behat feedback campaign"

  Scenario: Disable and re-enable a campaign from the manage list
    Given I visit "/local/communications/edit_campaign.php"
    And I set the following fields to these values:
      | Name | Toggle test campaign |
    And I press "Save changes"
    And I should see "Toggle test campaign"
    When I click on "Disable" "link" in the "Toggle test campaign" "table_row"
    Then I should see "Disabled" in the "Toggle test campaign" "table_row"
    When I click on "Enable" "link" in the "Toggle test campaign" "table_row"
    Then I should see "Enabled" in the "Toggle test campaign" "table_row"

  Scenario: Delete a campaign
    Given I visit "/local/communications/edit_campaign.php"
    And I set the following fields to these values:
      | Name | Delete test campaign |
    And I press "Save changes"
    And I should see "Delete test campaign"
    When I click on "Delete" "link" in the "Delete test campaign" "table_row"
    Then I should not see "Delete test campaign"
