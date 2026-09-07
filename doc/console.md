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

The administrator is looked up by **either** username or email, so whoever is locked out does not
also have to guess which one it is.

Before writing anything the command reports who can currently reach the roles screen — because the
first question is whether the lockout is real. If somebody else can still manage roles, this is the
wrong tool: do it from the admin panel.

**This command never goes through the permission check.** That is the point: it is the way in right
after installing, and the way back from a role deleted by mistake, a database edited by hand, or an
upgrade left half-applied. Keep console access available to whoever administers the shop.

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

`--subject=sylius.taxon` narrows the listing by identifier prefix.

The count reads: how many distinct permissions exist, how many the filter left, and how many
declarations (routes, API operations, configuration entries) produced them.

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

The answer per role is computed exactly the way the voter computes it at runtime, wildcards
included — this is the fastest way to settle "why can't this person open that screen?".

Three other outcomes:

```console
$ bin/console odiseo:rbac:debug sylius_admin_login
 [OK] Open by design: listed under "excluded_routes", so every administrator can reach it.

$ bin/console odiseo:rbac:debug app_admin_supplier_export
 [WARNING] No permission covers this route.

$ bin/console odiseo:rbac:debug not_a_route
 [ERROR] Route "not_a_route" does not exist.
```

A route that does not exist is reported as such, rather than as "nobody checks it" — a typo should
not invite you to declare a permission for a phantom route.

### The coverage check

```bash
bin/console odiseo:rbac:debug --strict
```

Exits non-zero when an admin route is left unchecked or a declaration is orphaned. Suitable for CI,
and the thing to run after upgrading Sylius or installing a plugin.

### Finding orphans

The normal output also lists **orphaned declarations** — a `route_permissions` or `excluded_routes`
entry naming a route that no longer exists — and **roles holding a permission that no longer
exists**, which is what a removed plugin leaves behind.

Neither breaks anything at runtime. Both are worth cleaning up: an orphaned declaration is usually
a route someone renamed and never followed up on.

## Migrating from 2.x

```bash
bin/console odiseo:rbac:migrate-permissions [--dry-run] [--overwrite]
```

Translates the pre-3.0 section-based permissions stored on each role into the new patterns, using
the section map — including any `custom_sections` your application declared — to work out which
routes each stored section actually reached.

| Option | Effect |
|---|---|
| `--dry-run` | Print the plan and exit without writing |
| `--overwrite` | Also rewrite roles that already hold 3.0 permissions |

Run it with `--dry-run` first and read the plan. Roles that cannot be translated in full are
reported as problems and the command exits non-zero, so a partial migration cannot pass unnoticed
in a deployment script. The old values are left in `legacy_permissions` either way.

The full procedure, including what to check afterwards, is in [Upgrading to 3.0](../UPGRADE-3.0.md).

---

[← Extending](extending.md) · [Docs index](README.md) · [Troubleshooting →](troubleshooting.md)
