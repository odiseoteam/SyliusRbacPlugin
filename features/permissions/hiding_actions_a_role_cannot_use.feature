@hiding_actions_a_role_cannot_use
Feature: Hiding the actions a role cannot use
    In order not to offer administrators buttons and widgets that would only answer 403
    As an Administrator
    I want a screen to show only the actions the permissions I granted cover

    Background:
        Given the store operates on a single channel in "United States"
        And I am logged in as an administrator

    @ui
    Scenario: An action button the role cannot use is not offered
        Given the store has a product "PHP T-Shirt"
        And my administrator account is allowed to "sylius.product.show"
        When I try to open the "PHP T-Shirt" product's show page
        Then I should see the page
        And I should not see the "edit-product" action

    @ui
    Scenario: The same button is offered once a role covers it
        Given the store has a product "PHP T-Shirt"
        And my administrator account is allowed to "sylius.product.show, sylius.product.update"
        When I try to open the "PHP T-Shirt" product's show page
        Then I should see the "edit-product" action

    @ui
    Scenario: A dashboard widget the role cannot use is not rendered
        Given my administrator account is allowed to "sylius.order.index"
        When I try to open "/admin/"
        Then I should see the page
        And I should not see the "statistics-component" widget

    @ui
    Scenario: The same widget is rendered once a role covers it
        Given my administrator account is allowed to "sylius.statistics.view"
        When I try to open "/admin/"
        Then I should see the "statistics-component" widget
