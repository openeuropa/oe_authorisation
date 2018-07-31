Feature: User authorization
  In order to protect the integrity of the website
  As a product owner
  I want to make sure users with various roles can only access pages they are authorized to

  Scenario Outline: Anonymous user cannot access restricted pages
    Given I am not logged in
    When I go to "<path>"
    Then I should see "Access denied"

    Examples:
      | path            |
      | admin           |
      | admin/config    |
      | admin/content   |
      | admin/people    |
      | admin/structure |
      | node/add        |

  @api
  Scenario Outline: Site Managers can access most administration pages
    Given I am logged in as a user with the "site_manager" role
    Then I visit "<path>"
    Then I should get a 200 HTTP response

    Examples:
      | path                         |
      | admin/people                 |
      | admin/config/people/accounts |
      | admin/config                 |
      | admin/structure/types        |
      | admin/reports/status         |
      | admin/modules                |
      | admin/content                |

  @api
  Scenario Outline: CEM users can access most administration pages except for
    pages related to user administration
    Given I am logged in as a user with the "cem" role
    Then I go to "<path>"
    Then I should get a "<status>" HTTP response

    Examples:
      | path                  | status |
      | admin/config          | 200    |
      | admin/people          | 403    |
      | admin/people/create   | 403    |
      | admin/structure/types | 200    |
      | admin/reports/status  | 200    |
      | admin/modules         | 200    |
      | admin/content         | 200    |

  @api
  Scenario Outline: Editors can access only content related pages
    Given I am logged in as a user with the "editor" role
    Then I go to "<path>"
    Then I should get a "<status>" HTTP response

    Examples:
      | path                  | status |
      | admin/content         | 200    |
      | admin/people          | 403    |
      | admin/people/create   | 403    |
      | admin/structure/types | 403    |
      | admin/reports/status  | 403    |
      | admin/modules         | 403    |
