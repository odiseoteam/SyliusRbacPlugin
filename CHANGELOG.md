# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.0.2] - 2026-09-11

### Added

- PostgreSQL schema migrations, alongside the MySQL ones: one for the 3.0 model and one for the
  pre-v3 table it transforms, which had never shipped for this platform. Each skips on the
  platform the other handles. `doctrine:schema:update` was never an alternative for the 3.0 one:
  it reads the rename of `permissions` to `legacy_permissions` as a drop and an add, losing every
  role's grants. Ids come from sequences rather than `SERIAL`, as Doctrine expects on this
  platform, so `doctrine:schema:validate` reports the tables in sync right after migrating.
- CI runs the whole suite on PostgreSQL as well as MySQL. The test application is built by
  running the migrations, so the job fails if they stop applying.
- `odiseo:rbac:migrate-permissions` reports a section that is configured but covers no
  permission. It used to be indistinguishable from a section that translated cleanly.

### Fixed

- `sylius_sections` and `custom_sections` no longer lose the sections the plugin ships when an
  application declares its own. They came from a schema default, which is dropped as soon as the
  node is set anywhere: declaring one custom section silently dropped `rbac`, and redeclaring one
  Sylius section dropped the other four, taking those roles' migration with them. They now come
  from `config/app/legacy_sections.yaml` and merge.
- `odiseo:rbac:migrate-permissions` translates sections over routes declared in
  `route_permissions`. A section covering invokable controllers — the case `Extending` documents
  — used to translate to nothing at all, without a word, leaving the panel denied for everyone.
- `symfony/yaml` accepts `^7.0` instead of `^7.4`, so the plugin installs on Symfony 7.0–7.3.
- Grid actions whose `link.route` is a map of routes — a toggle switching between enable and
  disable — are filtered too. They used to slip through and stay visible to a role holding
  neither permission. The action stays while any of its routes is allowed; which one applies is
  a per-row decision only the template can make.

### Added

- PostgreSQL schema migrations, alongside the MySQL ones: one for the 3.0 model and one for the
  pre-v3 table it transforms, which had never shipped for this platform. Each skips on the
  platform the other handles. `doctrine:schema:update` was never an alternative for the 3.0 one:
  it reads the rename of `permissions` to `legacy_permissions` as a drop and an add, losing every
  role's grants.
- CI runs the whole suite on PostgreSQL as well as MySQL. The test application is built by
  running the migrations, so the job fails if they stop applying.
- `odiseo:rbac:migrate-permissions` reports a section that is configured but covers no
  permission. It used to be indistinguishable from a section that translated cleanly.

### Fixed

- `sylius_sections` and `custom_sections` no longer lose the sections the plugin ships when an
  application declares its own. They came from a schema default, which is dropped as soon as the
  node is set anywhere: declaring one custom section silently dropped `rbac`, and redeclaring one
  Sylius section dropped the other four, taking those roles' migration with them. They now come
  from `config/app/legacy_sections.yaml` and merge.
- `odiseo:rbac:migrate-permissions` translates sections over routes declared in
  `route_permissions`. A section covering invokable controllers — the case `Extending` documents
  — used to translate to nothing at all, without a word, leaving the panel denied for everyone.
- `symfony/yaml` accepts `^7.0` instead of `^7.4`, so the plugin installs on Symfony 7.0–7.3.

## [3.0.1] - 2026-09-07

### Fixed

- `cache:clear` no longer fails on a bare Symfony skeleton, right after `composer require`,
  before `sylius/sylius-admin-bundle` is registered.

## [3.0.0] - 2026-09-07

A full rewrite of the permission engine. See [UPGRADE-3.0.md](UPGRADE-3.0.md) for the upgrade
steps.

### Added

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

### Changed

- Administrators hold a collection of roles: `getAdministrationRole()` /
  `setAdministrationRole()` are replaced by `getAdministrationRoles()`, `addAdministrationRole()`,
  `removeAdministrationRole()` and `hasAdministrationRole()`.
- `AdministrationRoleInterface` is code-aware and translatable, and stores permission patterns
  instead of section permissions.
- `odiseo:rbac:grant-access` is now `odiseo:rbac:grant`, and creates the role with `--create`.
- The package moved to the Sylius 2.x layout: `config/`, `templates/` and `translations/` at the
  root instead of `src/Resources/`.
- Sylius 2.0, 2.1 and 2.2 are supported; PHP 8.2+; Symfony 6.4 and 7.x.

### Removed

- The section-based engine: `Access\Checker\*`, `Access\Listener\AccessCheckListener`,
  `Access\Creator\*`, `Access\Menu\AdminMenuAccessListener`, `Provider\*`, the `Action\*`
  controllers and their message handlers.
- `odiseo:rbac:install` and `odiseo:rbac:normalize-administrators`.
- Support for Sylius 1.x.

### Deprecated

- `sylius_sections` and `custom_sections` are read only by the migration command, and are removed
  in 4.0.

### Fixed

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

[Unreleased]: https://github.com/odiseoteam/SyliusRbacPlugin/compare/v3.0.2...master
[3.0.2]: https://github.com/odiseoteam/SyliusRbacPlugin/releases/tag/v3.0.2
[3.0.1]: https://github.com/odiseoteam/SyliusRbacPlugin/releases/tag/v3.0.1
[3.0.0]: https://github.com/odiseoteam/SyliusRbacPlugin/releases/tag/v3.0.0
[2.0.1]: https://github.com/odiseoteam/SyliusRbacPlugin/releases/tag/v2.0.1
[2.0.0]: https://github.com/odiseoteam/SyliusRbacPlugin/releases/tag/v2.0.0
