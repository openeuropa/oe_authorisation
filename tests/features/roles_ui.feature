@api
Feature: Roles UI not available
  In order to protect the integrity of the website
  As a product owner
  I want to make sure roles cannot be created/edited/deleted by anyone in the website

  Background:
    Given I am logged in as a user with the "administer users, administer permissions" permissions

  Scenario: Users cannot change role permissions
    When I go to "the permissions page"
    Then I should not be able to edit permissions

  Scenario: Users cannot add roles
    When I go to "the role creation page"
    Then I should not be able to access the page

  Scenario: Users cannot edit or delete roles
    When I go to "the role administration page"
    Then I should not be able to edit or delete roles
