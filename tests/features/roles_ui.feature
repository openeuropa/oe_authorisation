@api
Feature: User authorisation
  In order to protect the integrity of the website
  As a product owner
  I want to make sure roles cannot be created/edited/deleted by anyone in the website

  Scenario Outline: Site Managers cannot change role permissions
    Given I am logged in as a user with the "administer users, administer permissions" permissions
    Then I go to "<path>"
    Then I should not be able to edit permissions

    Examples:
      | path                            |
      | the permissions page            |

  Scenario Outline: Site Managers cannot add roles
    Given I am logged in as a user with the "administer users, administer permissions" permissions
    Then I go to "<path>"
    Then I should not be able to access the page

    Examples:
      | path                      |
      | the role creation page    |

  Scenario Outline: Site managers cannot edit or delete roles
    Given I am logged in as a user with the "administer users, administer permissions" permissions
    Then I go to "<path>"
    Then I should not be able to edit or delete roles

    Examples:
      | path                            |
      | the role administration page    |