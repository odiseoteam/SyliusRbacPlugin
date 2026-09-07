[← Console commands](console.md) · [Docs index](README.md) · [Upgrading to 3.0 →](../UPGRADE-3.0.md)

# Troubleshooting

## I locked everyone out

Nobody can reach the admin, or nobody can reach **Administration → Roles**. This is recoverable
from the console, which never goes through the permission check:

```bash
bin/console odiseo:rbac:grant <username-or-email> super_admin --create
```

It prints who can currently reach the roles screen before writing anything, so you can confirm the
lockout is real. Add `--dry-run` to see the plan first.

Nothing else is needed, no database edit, no disabling the plugin.

## The admin is empty right after installing

Expected. An administrator with no role is denied everything, including the screen that assigns
roles. Run the `grant` command above; from then on roles are managed from the admin panel.

## An unexpected 403

Ask the plugin what that route wants and who has it:

```bash
bin/console odiseo:rbac:debug <route-name>
```

The three answers that explain almost every case:

| Output | What it means |
|---|---|
| `Requires: x.y.z` and your role says `no` | The role really is missing it, grant it in the tree |
| `No permission covers this route` | Nothing declared it, and [deny-by-default](enforcement.md#deny-by-default) denied it |
| `Route "..." does not exist` | The name is wrong; the 403 is coming from somewhere else |

If you do not know the route name, it is in the profiler's Request panel, or in the exception page
under `_route`.

### It is a route of mine, and nothing declares it

That is deny-by-default doing its job. Either give it a permission or state that it needs none:

```yaml
odiseo_sylius_rbac:
    route_permissions:
        app_admin_supplier_export:
            permission: app.supplier.export
    # or
    excluded_routes:
        - app_admin_supplier_export
```

See [Extending](extending.md#routes-that-are-not-resource-routes).

### It appeared after installing a plugin

The plugin's routes are uncovered. List what is missing:

```bash
bin/console odiseo:rbac:debug --strict
```

Then declare them, or ask the plugin's author to
[ship its own declarations](extending.md#shipping-declarations-from-a-plugin). While you work
through a long list, `deny_unprotected_admin_routes: false` unblocks the application. Put it back
on when the list is empty.

## The permission tree does not save anything

Boxes tick, the role saves, and the permissions are unchanged. The Stimulus controller is not
loaded: the checkboxes are not bound to the form on purpose, and without the controller nothing
writes them into the hidden field.

Re-check step 2 of the [installation](installation.md), then:

```bash
yarn install --force
yarn build
```

## The container fails to compile

```
The administration role engine cannot read roles from "App\Entity\User\AdminUser".
Make that class implement "...\AdministrationRoleAwareInterface" and use
"...\AdministrationRoleAwareTrait", then register it as the "admin_user" resource model.
```

Step 3 of the [installation](installation.md) was skipped or applied to a class that is not the
registered `admin_user` model. The check is deliberate: without it every permission check would
deny, at runtime, with no explanation.

## A permission is granted but the menu entry is missing

Menu entries are filtered by **the route each item points at**, not by the section it sits in. An
entry can require a different permission than the screens under it, the parent's own destination.
Check what the item's route asks for:

```bash
bin/console odiseo:rbac:debug <the-route-the-menu-item-points-at>
```

A parent left with no reachable children and no destination of its own is removed too.

## Changing configuration has no effect

Declarations are read at container build time. In `prod`:

```bash
bin/console cache:clear
```

The plugin's own files are tracked as container resources, so `dev` rebuilds on its own, but a
configuration file added under `config/packages/` in an unusual location may not be.

## After upgrading from 2.x, roles grant nothing

Stored permissions are still in the old section format. Translate them:

```bash
bin/console odiseo:rbac:migrate-permissions --dry-run
bin/console odiseo:rbac:migrate-permissions
```

The full procedure is in [Upgrading to 3.0](../UPGRADE-3.0.md).

## `odiseo:rbac:debug` reports orphans

Two lists, neither of which breaks anything at runtime:

- **Orphaned declarations**: a `route_permissions` or `excluded_routes` entry naming a route that
  no longer exists. Usually a route Sylius renamed, or a plugin you removed. Delete the entry; if
  the route was renamed, re-point it, because nothing is checking the new name.
- **Roles holding a permission that no longer exists**: what a removed plugin leaves behind.
  Harmless, and re-installing the plugin makes them meaningful again. Clean them up from the role's
  screen if the plugin is gone for good.

## The API allows more than the admin does

It should not: both check the same identifiers. Two things to verify:

1. The request really is an admin API request (`/api/v2/admin/...`). Shop endpoints are outside
   this plugin's scope.
2. The operation is not one the plugin folds into a parent. Editing a product image asks for
   `sylius.product.update`, not `sylius.product_image.update`; see
   [`folded_api_subjects`](configuration.md#folded_api_subjects).

If neither explains it, that is a bug worth
[reporting](https://github.com/odiseoteam/SyliusRbacPlugin/issues).

---

[← Console commands](console.md) · [Docs index](README.md) · [Upgrading to 3.0 →](../UPGRADE-3.0.md)
