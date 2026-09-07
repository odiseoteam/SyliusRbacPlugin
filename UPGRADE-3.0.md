# Upgrading from 2.x to 3.0

3.0 replaces the permission engine. Sections are gone, permissions are per operation now, an
administrator can hold several roles, and the API is protected too. A command migrates your
stored data, but if your own code touched the plugin's classes, you'll need to update it.

Read [the permission model](doc/permissions.md) first. Most of the work here is understanding
what your old sections become.

> [!WARNING]
> **Between the schema migration and the data migration, every role grants nothing.** Plan for a
> maintenance window, or run both steps back to back. Recovery, if you get it wrong, is
> `odiseo:rbac:grant` from the console.

## The procedure

### 1. Require 3.0

```bash
composer require odiseoteam/sylius-rbac-plugin:^3.0
```

Sylius 2.0 or newer is required. There is no 3.0 for Sylius 1.x.

### 2. Repoint your config and route imports

Flex only applies a recipe on a fresh install, never on an update of a package it already
configured, so `composer require` alone leaves your own `config/packages/*.yaml` and
`config/routes/*.yaml` importing paths that no longer exist:

```diff
 # config/packages/odiseo_sylius_rbac_plugin.yaml
 imports:
-    - { resource: "@OdiseoSyliusRbacPlugin/Resources/config/config.yaml" }
+    - { resource: "@OdiseoSyliusRbacPlugin/config/config.yaml" }
```

```diff
 # config/routes/odiseo_sylius_rbac_plugin.yaml
 odiseo_sylius_rbac_admin:
-    resource: "@OdiseoSyliusRbacPlugin/Resources/config/routing/admin.yaml"
-    prefix: /admin
+    resource: "@OdiseoSyliusRbacPlugin/config/routes/admin.yaml"
+    prefix: '/%sylius_admin.path_name%'
```

Skip this and every command below fails before doing anything, starting with `cache:clear`:
`Unable to find file "@OdiseoSyliusRbacPlugin/Resources/config/config.yaml"`.

### 3. Update your `AdminUser`

An administrator now holds **several** roles, so the trait's mapping changed from `ManyToOne` to a
join table. You don't need to touch the class itself if it already uses
`AdministrationRoleAwareTrait`, but any code you have calling the old accessors needs updating:

```diff
-$adminUser->setAdministrationRole($role);
-$adminUser->getAdministrationRole();
+$adminUser->addAdministrationRole($role);
+$adminUser->removeAdministrationRole($role);
+$adminUser->getAdministrationRoles();   // Collection
+$adminUser->hasAdministrationRole($role);
```

### 4. Run the schema migration

```bash
bin/console doctrine:migrations:migrate
```

This adds the role's `code`, its translations table, the `permissions` column, and the
administrator ↔ role join table. It also copies the old permission blob into
`legacy_permissions`, untouched.

At the end it tells you how many roles are still holding pre-3.0 permissions.

### 5. Translate the stored permissions

```bash
bin/console odiseo:rbac:migrate-permissions --dry-run   # read the plan
bin/console odiseo:rbac:migrate-permissions             # apply it
```

This uses your section map, `sylius_sections` and `custom_sections`, including any sections your
application declared, to figure out which routes each stored section reached, and which
permissions those routes need now.

If a role can't be fully translated, it's reported and the command exits non-zero. Read those
before continuing, they're usually sections pointing at routes that don't exist anymore.

`legacy_permissions` stays in place, so you can re-run the command and check the old values
whenever you need to.

### 6. Check what your roles ended up with

```bash
bin/console odiseo:rbac:debug <a-route-your-team-uses>
```

The per-role yes/no table is the fastest way to confirm nobody lost access they actually need.
Then open a role in the admin and look at *Show identifiers* → *What gets stored*.

### 7. Deal with uncovered routes

3.0 denies any admin route with no matching permission, including routes from your application
and from third-party plugins that the old engine let through.

```bash
bin/console odiseo:rbac:debug --strict
```

Declare what it lists ([Extending](doc/extending.md#routes-that-are-not-resource-routes)). If
there's a lot, `deny_unprotected_admin_routes: false` keeps the application usable while you work
through them. Turn it back on once the list is empty.

### 8. Wire the role editor's assets

The permission tree is a Stimulus controller now. That's step 2 of the
[installation](doc/installation.md). Skip it and the tree still renders, but it won't save
anything.

## What changed

### Permissions

| 2.x | 3.0 |
|---|---|
| Five fixed sections plus custom ones | One permission per resource *and operation*, discovered automatically |
| `read` / `write`, where write implied delete | `index`, `show`, `create`, `update`, `delete`, `bulk_delete`, plus operations like `ship` and `refund` |
| Sections mapped to route-name prefixes | Sylius' own permission codes, `{package}.{subject}.{operation}` |
| A route matching no section was **allowed** | A route no permission covers is **denied** |
| Only routes containing `sylius_admin` were checked | Admin routes, the admin API, menu, grids, buttons, widgets and live components |
| One role per administrator | Several, additive |

### Configuration

`sylius_sections` and `custom_sections` still validate, so an upgrading application boots fine
with its old configuration in place, but **nothing at runtime reads them anymore**. Only the
migration command does, and they'll be removed in 4.0.

Everything else under `odiseo_sylius_rbac` is new. See the
[configuration reference](doc/configuration.md).

### Removed classes

The 2.x engine is gone: `Access\Checker\*` (including `HardcodedRouteNameChecker`),
`Access\Listener\AccessCheckListener`, `Access\Creator\*`, `Access\Menu\AdminMenuAccessListener`,
`Provider\AdminPermissionsProvider`, `Provider\SyliusSectionsProvider`, the `Action\*` controllers,
and the `Message`/`CommandHandler` pairs behind them.

If you overrode `HardcodedRouteNameChecker` to make the plugin see your routes, that whole
customization is gone. Routes are matched by name against the permission map instead, and your
own routes get picked up by declaring them.

The 2.x permission value objects are still around, under `Odiseo\SyliusRbacPlugin\Legacy\`, but
**only** the migration command uses them. Don't import them from your own code.

### The role entity

```diff
-$role->addPermission(Permission::ofType('catalog_management', ['read', 'write']));
-$role->hasPermission($permission);
+$role->setPermissions(['sylius.product.*', '*.*.index']);
+$role->getPermissionPatterns();
+$role->addPermissionPattern(PermissionPattern::fromString('sylius.order.ship'));
+$role->hasPermissionPattern($pattern);
```

`AdministrationRoleInterface` is now `CodeAwareInterface` and `TranslatableInterface`. A role has
a stable `code` now, which is what commands and fixtures use, plus a translated `name`.

### Commands

| 2.x | 3.0 |
|---|---|
| `odiseo:rbac:install` | removed |
| `odiseo:rbac:normalize-administrators` | removed |
| `odiseo:rbac:grant-access` | `odiseo:rbac:grant` |
| `odiseo:rbac:grant-access-to-given-administrator` | `odiseo:rbac:grant <administrator> <role>` |
| *(new)* | `odiseo:rbac:debug` |
| *(new)* | `odiseo:rbac:migrate-permissions` |

### Templates and layout

The plugin moved to the Sylius 2.x package layout: `config/`, `templates/` and `translations/` at
the root instead of `src/Resources/`. If you override anything at
`@OdiseoSyliusRbacPlugin/Resources/views/...`, repoint it at
`@OdiseoSyliusRbacPlugin/templates/...`. The role form is now a set of Twig hookables instead of
one hand-written template.

## After the upgrade

- Run `odiseo:rbac:debug --strict` in CI. It fails when a Sylius upgrade or a new plugin adds a
  route nobody covered, which is exactly when you want to know about it.
- Once every role is migrated and checked, you can delete `sylius_sections` and `custom_sections`
  from your configuration.

Something not covered here? [Open an issue](https://github.com/odiseoteam/SyliusRbacPlugin/issues).
