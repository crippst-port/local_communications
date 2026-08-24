@local @local_communications
Feature: Course feedback reports
  In order to see what students think of a course
  As a user with the view reports capability
  I need to be able to view a course's feedback report for a campaign

  Background:
    Given the following "courses" exist:
      | fullname               | shortname |
      | Behat feedback course  | BFC1      |
    And I log in as "admin"
    # The plugin seeds an always-on "Default" campaign on install (db/install.php) so a
    # fresh site behaves like the pre-campaign widget out of the box. It is also
    # course-focused, so it must be removed first - otherwise two course-focused
    # campaigns would match this course and the nav link below would route to the
    # multi-campaign index page instead of straight to this one campaign's own report.
    And I visit "/local/communications/manage_campaigns.php"
    And I click on "Delete" "link" in the "Default" "table_row"
    And I visit "/local/communications/edit_campaign.php"
    And I set the following fields to these values:
      | Name | Course report test campaign |
    And I press "Save changes"

  Scenario: A campaign's own dashboard is reachable from the manage list
    Given I visit "/local/communications/manage_campaigns.php"
    When I click on "View responses" "link" in the "Course report test campaign" "table_row"
    Then I should see "Feedback report: Course report test campaign"

  Scenario: A course with a matching course-focused campaign links to its own report
    Given I am on "Behat feedback course" course homepage
    When I navigate to "Course feedback report" in current page administration
    Then I should see "Course report test campaign feedback: Behat feedback course"
    And I should see "No feedback"
