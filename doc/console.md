[← Extending](extending.md) · [Docs index](README.md) · [Troubleshooting →](troubleshooting.md)

# Console commands

Three commands, all under the `odiseo:rbac` namespace.

| Command | What it is for |
|---|---|
| [`odiseo:rbac:grant`](#granting-access) | Give an administrator a role, bypassing the permission check |
| [`odiseo:rbac:debug`](#inspecting-permissions) | List the vocabulary, inspect one route, find gaps |
| [`odiseo:rbac:migrate-permissions`](#migrating-from-2x) | Rewrite pre-3.0 roles in the new format |

## Granting access

```bash
bin/console odiseo:rbac:grant <username-or-email> <role-code> [--create] [--dry-run]
```

```bash
bin/console odiseo:rbac:grant sylius@example.com super_admin --create
```

| Option | Effect |
|---|---|
| `--create` | Create the role if no role has that code, granting `*.*.*` |
| `--dry-run` | Print the plan and exit without writing |

You can look up the administrator by **either** username or email, so if someone is locked out you
don't have to guess which one to use.

Before writing anything, the command shows you who can currently reach the roles screen. That's
worth checking first: if somebody else can still manage roles, use the admin panel instead of this
command.

**This command skips the permission check entirely.** That's the point of it: it's what gets you
in right after installing, and what gets you back in after a role gets deleted by mistake, a
database gets edited by hand, or an upgrade only half-applies. Keep console access available to
whoever runs the shop.

## Inspecting permissions

```bash
bin/console odiseo:rbac:debug [route] [--subject=...] [--strict]
```

### The whole vocabulary

```console
$ bin/console odiseo:rbac:debug

RBAC permissions
================

 -------------------------- --------- -------
  Permission                 Group     Label
 -------------------------- --------- -------
  sylius.taxon.bulk_delete   —         —
  sylius.taxon.create        —         —
  sylius.taxon.index         catalog   —
  ...
 -------------------------- --------- -------

 223 permissions, 223 shown, from 406 declarations.

 [OK] Every admin route is either covered by a permission or declared excluded.
```

`--subject=sylius.taxon` narrows the listing to identifiers with that prefix.

The count at the bottom shows how many distinct permissions exist, how many are left after your
filter, and how many declarations (routes, API operations, configuration entries) produced them.

### One route

```console
$ bin/console odiseo:rbac:debug sylius_admin_product_update

Route "sylius_admin_product_update"
===================================

 Requires: sylius.product.update

Roles
-----

 ----------------- -------------------
  Role              Covers this route
 ----------------- -------------------
  super_admin       yes
  catalog_manager   yes
  order_manager     no
  read_only         no
 ----------------- -------------------
```

Each role's answer is computed exactly the way the voter does it at runtime, wildcards included.
It's the fastest way to answer "why can't this person open that screen?".

There are three other things this command can tell you:

```console
$ bin/console odiseo:rbac:debug sylius_admin_login
 [OK] Open by design: listed under "excluded_routes", so every administrator can reach it.

$ bin/console odiseo:rbac:debug app_admin_supplier_export
 [WARNING] No permission covers this route.

$ bin/console odiseo:rbac:debug not_a_route
 [ERROR] Route "not_a_route" does not exist.
```

A route that doesn't exist gets reported as such, instead of "nobody checks it". Otherwise a typo
would look like an invitation to declare a permission for a route that isn't even real.

### The coverage check

```bash
bin/console odiseo:rbac:debug --strict
```

This exits non-zero if an admin route is left unchecked, or a declaration is orphaned. Good for
CI, and worth running after upgrading Sylius or installing a plugin.

### Finding orphans

The normal output also lists **orphaned declarations**, a `route_permissions` or `excluded_routes`
entry pointing at a route that no longer exists, and **roles holding a permission that no longer
exists**, which is what's left over after you remove a plugin.

Neither one breaks anything at runtime, but both are worth cleaning up. An orphaned declaration
usually just means someone renamed a route and never went back to fix it.

## Migrating from 2.x

```bash
bin/console odiseo:rbac:migrate-permissions [--dry-run] [--overwrite]
```

This translates the old section-based permissions stored on each role into the new patterns. It
uses your section map, including any `custom_sections` your application declared, to work out
which routes each stored section actually covered.

| Option | Effect |
|---|---|
| `--dry-run` | Print the plan and exit without writing |
| `--overwrite` | Also rewrite roles that already hold 3.0 permissions |

Run it with `--dry-run` first and read the plan. If a role can't be fully translated, it's
reported as a problem and the command exits non-zero, so a partial migration won't slip through a
deployment script unnoticed. The old values stay in `legacy_permissions` either way.

The full procedure, including what to check afterwards, is in [Upgrading to 3.0](../UPGRADE-3.0.md).

---

[← Extending](extending.md) · [Docs index](README.md) · [Troubleshooting →](troubleshooting.md)
