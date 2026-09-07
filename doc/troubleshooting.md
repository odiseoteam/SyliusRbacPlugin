[← Console commands](console.md) · [Docs index](README.md) · [Upgrading to 3.0 →](../UPGRADE-3.0.md)

# Troubleshooting

## I locked everyone out

Nobody can reach the admin, or nobody can reach **Administration → Roles**. You can fix this from
the console, since it skips the permission check entirely:

```bash
bin/console odiseo:rbac:grant <username-or-email> super_admin --create
```

It prints who can currently reach the roles screen before writing anything, so you can check the
lockout is real. Add `--dry-run` if you just want to see the plan first.

You don't need to do anything else, no database edit, no disabling the plugin.

## The admin is empty right after installing

That's expected. An administrator with no role is denied everything, including the screen that
assigns roles. Run the `grant` command above, and from then on you manage roles from the admin
panel.

## An unexpected 403

Ask the plugin what that route needs, and who has it:

```bash
bin/console odiseo:rbac:debug <route-name>
```

This usually explains it:

| Output | What it means |
|---|---|
| `Requires: x.y.z` and your role says `no` | The role really is missing it, grant it in the tree |
| `No permission covers this route` | Nothing declared it, and [deny-by-default](enforcement.md#deny-by-default) denied it |
| `Route "..." does not exist` | The name is wrong; the 403 is coming from somewhere else |

If you don't know the route name, check the profiler's Request panel, or the exception page under
`_route`.

### It's a route of mine, and nothing declares it

That's deny-by-default doing its job. Either give it a permission, or say it doesn't need one:

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

The plugin's routes aren't covered yet. List what's missing:

```bash
bin/console odiseo:rbac:debug --strict
```

Then declare them yourself, or ask the plugin's author to
[ship its own declarations](extending.md#shipping-declarations-from-a-plugin). If the list is
long, `deny_unprotected_admin_routes: false` unblocks the application while you work through it.
Turn it back on once the list is empty.

## The permission tree does not save anything

You tick boxes, save the role, and nothing changed. The Stimulus controller isn't loaded: the
checkboxes aren't bound to the form directly, so without the controller nothing writes them into
the hidden field.

Re-check step 2 of the [installation](installation.md), then run:

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

Either step 3 of the [installation](installation.md) got skipped, or it was applied to a class
that isn't the registered `admin_user` model. This check exists on purpose: without it, every
permission check would silently deny at runtime with no explanation.

## A permission is granted but the menu entry is missing

Menu entries get filtered by **the route each item points at**, not by which section it's in. So
an entry can need a different permission than the screens under it, whatever its own destination
is. Check what the item's route actually needs:

```bash
bin/console odiseo:rbac:debug <the-route-the-menu-item-points-at>
```

A parent left with no reachable children and no destination of its own gets removed too.

## Changing configuration has no effect

Declarations are read when the container builds. In `prod`, run:

```bash
bin/console cache:clear
```

The plugin's own files are tracked as container resources, so `dev` rebuilds automatically. A
configuration file you added somewhere unusual under `config/packages/` might not be.

## After upgrading from 2.x, roles grant nothing

Stored permissions are still in the old section format. You need to translate them:

```bash
bin/console odiseo:rbac:migrate-permissions --dry-run
bin/console odiseo:rbac:migrate-permissions
```

The full procedure is in [Upgrading to 3.0](../UPGRADE-3.0.md).

## `odiseo:rbac:debug` reports orphans

Two lists, and neither one breaks anything at runtime:

- **Orphaned declarations**: a `route_permissions` or `excluded_routes` entry pointing at a route
  that no longer exists. Usually that means Sylius renamed the route, or you removed a plugin.
  Delete the entry, or if the route was just renamed, point it at the new name, since nothing is
  checking the old one anymore.
- **Roles holding a permission that no longer exists**: this is what's left over after you remove
  a plugin. It's harmless, and reinstalling the plugin makes it meaningful again. Clean it up from
  the role's screen if the plugin is gone for good.

## The API allows more than the admin does

It shouldn't, both check the same identifiers. Check two things:

1. The request really is an admin API request (`/api/v2/admin/...`). Shop endpoints are outside
   this plugin's scope.
2. The operation is not one the plugin folds into a parent. Editing a product image asks for
   `sylius.product.update`, not `sylius.product_image.update`; see
   [`folded_api_subjects`](configuration.md#folded_api_subjects).

If neither of those explains it, that's a bug, please
[report it](https://github.com/odiseoteam/SyliusRbacPlugin/issues).

---

[← Console commands](console.md) · [Docs index](README.md) · [Upgrading to 3.0 →](../UPGRADE-3.0.md)
