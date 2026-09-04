@enforcing_administrator_permissions
Feature: Enforcing administrator permissions
    In order to keep administrators inside the boundaries their roles describe
    As an Administrator
    I want screens, the menu and actions to follow the permissions I granted

    Background:
        Given the store operates on a single channel in "United States"
        And I am logged in as an administrator

    @ui
    Scenario: Reaching a screen the roles cover
        Given my administrator account is allowed to "sylius.product.index"
        When I try to open "/admin/products/"
        Then I should see the page

    @ui
    Scenario: Being turned away from a screen no role covers
        Given my administrator account is allowed to "sylius.product.index"
        When I try to open "/admin/orders/"
        Then I should be denied access

    @ui
    Scenario: An administrator without any role is denied rather than crashing
        When I try to open "/admin/products/"
        Then I should be denied access

    @ui
    Scenario: The menu offers only what the administrator can reach
        Given my administrator account is allowed to "sylius.product.index"
        When I try to open "/admin/products/"
        Then the menu should lead to "/admin/products/"
        And the menu should not lead to "/admin/orders/"

    @ui
    Scenario: A wildcard covers operations the shop has not seen yet
        Given my administrator account is allowed to "sylius.product.*"
        When I try to open "/admin/products/new"
        Then I should see the page
