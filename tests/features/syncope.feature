@api
Feature: Syncope integration
  In order to handle authorisation centrally
  As a product owner
  I want to make sure user roles are assigned in Syncope

  Scenario Outline: Users should get their roles from Syncope on login
    Given users:
      | name  | mail              |
      | Kevin | Kevin@example.com |
    And the user "Kevin" does not have the role "<role>" in Drupal
    And the user "Kevin" gets the role "<role>" in Syncope
    And I am logged in as "Kevin"
    Then the user "Kevin" should have the role "<role>" in Drupal

    Examples:
      | role             |
      # Regular role.
      | Site Manager     |
      # Global role.
      | Support Engineer |

  Scenario Outline: Users should lose their roles in Drupal on login if they no longer have them assigned in Syncope
    Given users:
      | name  | mail              |
      | Kevin | Kevin@example.com |
    And the user "Kevin" has the roles "Site Manager, Support Engineer" in Drupal
    And the user "Kevin" loses the role "<lost>" in Syncope
    And I am logged in as "Kevin"
    Then the user "Kevin" should not have the role "<lost>" in Drupal
    And the user "Kevin" should have the role "<kept>" in Drupal

    Examples:
      | lost             | kept             |
      # Regular role.
      | Site Manager     | Support Engineer |
      # Global role.
      | Support Engineer | Site Manager     |

  Scenario: Users created in Drupal should be mapped in Syncope with the correct roles
    Given users:
      | name  | mail              | roles                |
      | Kevin | Kevin@example.com | Editor, Site Manager |
    Then the user "Kevin" should have the roles "Editor, Site Manager" in Syncope

  Scenario: Users updated in Drupal should be mapped in Syncope with the correct roles
    Given users:
      | name  | mail              | roles                |
      | Kevin | Kevin@example.com | Editor, Site Manager |
    And I am logged in as a user with the "administer users, administer permissions" permissions
    And I go to "/admin/people"
    And I click "Edit" in the "Kevin" row
    And I uncheck the box "Site Manager"
    And I press "Save"
    Then the user "Kevin" should have the roles "Editor" in Syncope
    And the user "Kevin" should not have the roles "Site Manager" in Syncope

  Scenario: Global roles should not be assignable in Drupal
    Given I am logged in as a user with the "administer users, administer permissions" permissions
    And I go to "/user"
    And I click "Edit"
    Then the "Support Engineer" role checkbox should be disabled

  Scenario: The user edit form should reflect the roles found in Syncope for that user
    Given I am logged in as a user with the "administer users, administer permissions" permissions
    And users:
      | name  | mail              |
      | Kevin | Kevin@example.com |
    And the user "Kevin" gets the role "Site Manager" in Syncope
    And I go to "/admin/people"
    And I click "Edit" in the "Kevin" row
    Then the "Site Manager" checkbox should be checked
