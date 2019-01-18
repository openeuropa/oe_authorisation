@api
Feature: User authorisation
  In order to protect the integrity of the website
  As a product owner
  I want to make sure users with various roles can only access pages they are authorized to

  Scenario Outline: Anonymous user cannot access restricted pages
    Given I am not logged in
    When I go to "<path>"
    Then I should not be able to access the page

    Examples:
      | path                                  |
      | the administration page               |
      | the site configuration page           |
      | the content administration page       |
      | the user administration page          |
      | the site building administration page |
      | the content creation page             |

  Scenario Outline: Site Managers can access certain administration pages
    Given I am logged in as a user with the "site_manager" role
    Then I go to "<path>"
    Then I should be able to access the page

    Examples:
      | path                            |
      | the user administration page    |
      | the user account creation page  |
      | the recent log messages page    |
      | the content administration page |

  Scenario Outline: Site Managers cannot access administration pages that change
    major configuration
    Given I am logged in as a user with the "site_manager" role
    Then I go to "<path>"
    Then I should not be able to access the page

    Examples:
      | path                      |
      | the modules administration page           |
      | the site appearance administration page       |
      | the user account settings page |
      | the block layout administration page     |
      | the content type creation page |

  Scenario Outline: Support Engineers can access some administration pages
    Given I am logged in as a user with the global "support_engineer" role
    Then I go to "<path>"
    Then I should be able to access the page

    Examples:
      | path                            |
      | the site configuration page     |
      | the recent log messages page    |
      | the content administration page |

  Scenario Outline: Support Engineers cannot access user management related
    administration pages
    Given I am logged in as a user with the global "support_engineer" role
    Then I go to "<path>"
    Then I should not be able to access the page

    Examples:
      | path                           |
      | the user administration page   |
      | the user account creation page |

  Scenario Outline: Editors can access content related pages
    Given I am logged in as a user with the "editor" role
    Then I go to "<path>"
    Then I should be able to access the page

    Examples:
      | path                            |
      | the content administration page |

  Scenario Outline: Editors cannot access administration pages
    Given I am logged in as a user with the "editor" role
    Then I go to "<path>"
    Then I should not be able to access the page

    Examples:
      | path                                  |
      | the user administration page          |
      | the user account creation page        |
      | the content types administration page |
      | the site status page                  |
      | the modules administration page       |
