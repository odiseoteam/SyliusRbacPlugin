# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### 3.0.0

A rewrite of the permission engine. Upgrading requires steps: see [UPGRADE-3.0.md](UPGRADE-3.0.md).

#### Added

- **Per-operation permissions**, `{package}.{subject}.{operation}`, using Sylius' own permission
  codes. Roles store patterns with wildcards (`sylius.product.*`, `*.*.index`, `*.*.*`), never
  their expansion.
- **Automatic discovery** of the vocabulary from resource routes, API operations, live components
  and configuration declarations, so installing or removing a plugin changes the tree with no
  configuration edit.
- **Admin API protection**. `/api/v2/admin` operations check the same permissions as the HTML
  admin; previously no API route was ever checked.
- **Deny by default** for admin routes no permission covers, with `excluded_routes` for deliberate
  exceptions and `deny_unprotected_admin_routes` to relax it while migrating.
- **Several roles per administrator**, additive.
- **Filtering of every admin surface**: main menu, grid actions, action buttons, dashboard widgets
  and live components.
- **A permission tree editor** with per-operation checkboxes, tri-state rows and groups, bulk
  grants, a filter, and a panel showing the exact patterns that will be stored.
- **Anti-lockout guard**: a role change that would leave the administrator making it unable to
  manage roles is refused.
- **`odiseo:rbac:debug`**: lists the vocabulary, explains one route and which roles cover it,
  reports uncovered routes, orphaned declarations and roles holding permissions that no longer
  exist. `--strict` makes it CI-usable.
- **`odiseo:rbac:migrate-permissions`**: translates pre-3.0 section permissions into patterns.
- **`ScopeResolverInterface`**: an extension point for restricting a permission to part of the
  data.
- **A Symfony Flex recipe**, cutting installation from eight manual steps to three.
- **Translations in 18 locales.**
- Predefined roles as fixtures: `super_admin`, `catalog`, `sales`, `read_only`.

#### Changed

- Administrators hold a collection of roles: `getAdministrationRole()` /
  `setAdministrationRole()` are replaced by `getAdministrationRoles()`, `addAdministrationRole()`,
  `removeAdministrationRole()` and `hasAdministrationRole()`.
- `AdministrationRoleInterface` is code-aware and translatable, and stores permission patterns
  instead of section permissions.
- `odiseo:rbac:grant-access` is now `odiseo:rbac:grant`, and creates the role with `--create`.
- The package moved to the Sylius 2.x layout: `config/`, `templates/` and `translations/` at the
  root instead of `src/Resources/`.
- Sylius 2.0, 2.1 and 2.2 are supported; PHP 8.2+; Symfony 6.4 and 7.x.

#### Removed

- The section-based engine: `Access\Checker\*`, `Access\Listener\AccessCheckListener`,
  `Access\Creator\*`, `Access\Menu\AdminMenuAccessListener`, `Provider\*`, the `Action\*`
  controllers and their message handlers.
- `odiseo:rbac:install` and `odiseo:rbac:normalize-administrators`.
- Support for Sylius 1.x.

#### Deprecated

- `sylius_sections` and `custom_sections` are read only by the migration command, and are removed
  in 4.0.

#### Fixed

- Administrators without a role no longer trigger a 500; they are denied, which is an ordinary
  state.
- Role forms are CSRF-protected.
- The role editor renders correctly on the Sylius 2.x admin, which is Bootstrap-based rather than
  Semantic UI.

## [2.0.1] - 2025-10-15

## [2.0.0] - 2025-01-14

Sylius 2.0 support.

---

Releases before 3.0 are listed on the
[releases page](https://github.com/odiseoteam/SyliusRbacPlugin/releases).

[Unreleased]: https://github.com/odiseoteam/SyliusRbacPlugin/compare/v2.0.1...master
[2.0.1]: https://github.com/odiseoteam/SyliusRbacPlugin/releases/tag/v2.0.1
[2.0.0]: https://github.com/odiseoteam/SyliusRbacPlugin/releases/tag/v2.0.0
