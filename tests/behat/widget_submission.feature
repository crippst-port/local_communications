@local @local_communications @javascript
Feature: Submit course feedback via the floating widget
  In order to give feedback about a course
  As a student
  I need to be able to open the feedback widget and submit a response

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                 |
      | student1 | Student   | One      | student1@example.com |
    And the following "courses" exist:
      | fullname            | shortname |
      | Behat widget course | BWC1      |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | BWC1   | student |
    And I log in as "student1"
    And I am on "Behat widget course" course homepage

  Scenario: Submit non-anonymous feedback and see the thank-you message
    Given I should see "Feedback"
    When I click on "Feedback" "button"
    And I click on "Good" "button"
    And I click on "Not sure / skip" "button"
    And I set the field "Type your feedback here..." to "This course is great, thanks!"
    And I click on "Submit feedback" "button"
    Then I should see "Thanks for your feedback!"
    And I click on "Close" "button"
    And I visit "/local/communications/my_submissions.php"
    And I should see "This course is great, thanks!"
