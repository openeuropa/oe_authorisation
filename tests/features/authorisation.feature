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
      | path                    |
      | the Administration page |
      | the Configuration page  |
      | the Content page        |
      | the People page         |
      | the Structure page      |
      | the Add content page    |

  Scenario Outline: Site Managers can access certain administration pages
    Given I am logged in as a user with the "site_manager" role
    Then I go to "<path>"
    Then I should be able to access the page

    Examples:
      | path                         |
      | the People page              |
      | the Add user page            |
      | the Recent log messages page |
      | the Content page             |

  Scenario Outline: Site Managers cannot access administration pages that change
    major configuration
    Given I am logged in as a user with the "site_manager" role
    Then I go to "<path>"
    Then I should not be able to access the page

    Examples:
      | path                      |
      | the Extend page           |
      | the Appearance page       |
      | the Account settings page |
      | the Block layout page     |
      | the Add content type page |

  Scenario Outline: Support Engineers can access some administration pages
    Given I am logged in as a user with the global "support_engineer" role
    Then I go to "<path>"
    Then I should be able to access the page

    Examples:
      | path                         |
      | the Configuration page       |
      | the Recent log messages page |
      | the Content page             |

  Scenario Outline: Support Engineers cannot access user management related
    administration pages
    Given I am logged in as a user with the global "support_engineer" role
    Then I go to "<path>"
    Then I should not be able to access the page

    Examples:
      | path              |
      | the People page   |
      | the Add user page |

  Scenario Outline: Editors can access content related pages
    Given I am logged in as a user with the "editor" role
    Then I go to "<path>"
    Then I should be able to access the page

    Examples:
      | path             |
      | the Content page |

  Scenario Outline: Editors cannot access administration pages
    Given I am logged in as a user with the "editor" role
    Then I go to "<path>"
    Then I should not be able to access the page

    Examples:
      | path                   |
      | the People page        |
      | the Add user page      |
      | the Content types page |
      | the Status report page |
      | the Extend page        |
