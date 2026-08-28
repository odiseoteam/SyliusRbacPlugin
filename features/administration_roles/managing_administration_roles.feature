@managing_administration_roles
Feature: Managing administration roles
    In order to describe the permission sets an administrator can be given
    As an Administrator
    I want to be able to manage administration roles

    Background:
        Given the store operates on a single channel in "United States"
        And I am logged in as an administrator

    @ui
    Scenario: Adding a new administration role
        When I want to add a new administration role
        And I name it "Catalog manager"
        And I add it
        Then I should be notified that it has been successfully created
        And there should be an administration role named "Catalog manager"

    @ui
    Scenario: Trying to add an administration role without a name
        When I want to add a new administration role
        And I do not specify its name
        And I add it
        Then I should be notified that name is required

    @ui
    Scenario: Administration role names are unique
        Given there is already an administration role "Catalog manager" in the system
        When I want to add a new administration role
        And I name it "Catalog manager"
        And I add it
        Then I should be notified that this name is already taken
        And there should be 1 administration roles in the system

    @ui
    Scenario: Renaming an administration role
        Given there is already an administration role "Catalog manager" in the system
        When I want to edit the "Catalog manager" administration role
        And I rename it to "Product manager"
        And I save my changes
        Then I should be notified that it has been successfully edited
        And there should be an administration role named "Product manager"

    @ui
    Scenario: Deleting an administration role
        Given there is already an administration role "Catalog manager" in the system
        When I delete the "Catalog manager" administration role
        Then I should be notified that it has been successfully deleted
        And there should be no administration role named "Catalog manager"
