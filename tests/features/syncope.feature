@api @run
Feature: Syncope integration
  In order to handle authorisation centrally
  As a product owner
  I want to make sure user roles are assigned in Syncope

  Scenario Outline: Users should get their roles from Syncope on login
    Given users:
      | name | mail             |
      | jack | jack@example.com |
    And the user "jack" gets the role "<role>" in Syncope
    And the user "jack" does not have the role "<role>" in Drupal
    And I am logged in as "jack"
    Then the user "jack" should have the role "<role>" in Drupal

    Examples:
      | role             |
      # Regular role.
      | Site Manager     |
      # Global role.
      | Support Engineer |

  Scenario Outline: Users should loses their roles in Drupal on login if they no longer have them assigned in Syncope
    Given users:
      | name | mail             |
      | jack | jack@example.com |
    And the user "jack" has the roles "Site Manager, Support Engineer" in Drupal
    And the user "jack" loses the role "<lost>" in Syncope
    And I am logged in as "jack"
    Then the user "jack" should not have the role "<lost>" in Drupal
    And the user "jack" should have the role "<kept>" in Drupal

    Examples:
      | lost             | kept             |
      # Regular role.
      | Site Manager     | Support Engineer |
      # Global role.
      | Support Engineer | Site Manager     |

  Scenario: Users created in Drupal should be mapped in Syncope with the correct roles
    Given users:
      | name | mail             | roles                |
      | jack | jack@example.com | Editor, Site Manager |
    Then the user "jack" should have the roles "Editor, Site Manager" in Syncope

  Scenario: Users updated in Drupal should be mapped in Syncope with the correct roles
    Given users:
      | name | mail             | roles                |
      | jack | jack@example.com | Editor, Site Manager |
    And I am logged in as a user with the "administer users, administer permissions" permissions
    And I go to "/admin/people"
    And I click "Edit" in the "jack" row
    And I uncheck the box "Site Manager"
    And I press "Save"
    Then the user "jack" should have the roles "Editor" in Syncope
    And the user "jack" should not have the roles "Site Manager" in Syncope

  Scenario: Global roles should not be assignable in Drupal
    Given I am logged in as a user with the "administer users, administer permissions" permissions
    And I go to "/user"
    And I click "Edit"
    Then the "Support Engineer" role checkbox should be disabled

  Scenario: Roles created in Drupal should be mapped in Syncope
    Given I am logged in as a user with the "administer users, administer permissions" permissions
    And I go to "/admin/people/roles"
    And I click "Add role"
    And print last response
    And I fill in "Role name" with "My new role"
    And I fill in "Machine-readable name" with "my_new_role"
    And I press "Save"
    Then the role "My new role" should exist in Syncope

