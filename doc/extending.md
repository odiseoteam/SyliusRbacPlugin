[← Configuration reference](configuration.md) · [Docs index](README.md) · [Console commands →](console.md)

# Extending

How to hook your own resources, routes and plugins into the permission system. The full list of
configuration keys is in the [configuration reference](configuration.md); this page is more about
when you'd actually reach for each one.

## A custom resource needs nothing

Say your application has a `Supplier` resource with the usual Sylius routes:

```
app_admin_supplier_index
app_admin_supplier_create
app_admin_supplier_update
app_admin_supplier_delete
app_admin_supplier_bulk_delete
```

Since it's a Sylius resource, its routes already carry `permission: true`, and the resource
controller already resolves them to permission codes. They just show up in the tree:

```bash
bin/console odiseo:rbac:debug | grep supplier
```

```
app.supplier.bulk_delete
app.supplier.create
app.supplier.delete
app.supplier.index
app.supplier.update
```

The `package` segment comes from the resource's own alias (`app.supplier` → `app`), so it lands
next to the rest of your application's permissions. **Grid buttons and menu entries get filtered
too**, with no configuration needed, since both go through the same route map.

> [!TIP]
> If a resource doesn't show up, check whether its routes are missing `permission: true`. That's
> the usual reason before you reach for any configuration.

## Routes that are not resource routes

An invokable controller, a custom action, an export endpoint, anything the resource controller
doesn't check on its own. Declare what it needs:

```yaml
# config/packages/odiseo_sylius_rbac_plugin.yaml
odiseo_sylius_rbac:
    route_permissions:
        app_admin_supplier_export:
            permission: app.supplier.export
            label: app.ui.permission.supplier_export
            group: catalog
```

Without this the route is **denied** to everyone, because
[deny-by-default](enforcement.md#deny-by-default) is on. That's on purpose: you find out right
away instead of finding out the day someone notices the endpoint is wide open.

If the route really doesn't need a permission, say so explicitly:

```yaml
odiseo_sylius_rbac:
    excluded_routes:
        - app_admin_supplier_public_feed
```

## Naming things in the tree

`label` and `group` are translation keys. Define them in your own catalogue:

```yaml
# translations/messages.en.yaml
app:
    ui:
        permission:
            supplier_export: 'Export suppliers'
```

Group headings come from the admin menu, so `group: catalog` puts a permission under the same
heading as the rest of catalog. If you want a heading of your own, add the menu section first, the
tree follows the menu, not the other way around.

Two more settings, both just for presentation:

- [`subject_parents`](configuration.md#subject_parents) nests a subject under the one whose screen
  reaches it.
- [`folded_api_subjects`](configuration.md#folded_api_subjects) folds an API-only resource into its
  parent, so it never shows up as its own row.

## Buttons, widgets and live components

A button you added through a Twig hook isn't gated by the route behind it. The route will deny the
request, but the button still renders, so it just invites a 403. Gate it directly:

```yaml
odiseo_sylius_rbac:
    hookable_permissions:
        'sylius_admin.product.index.content.grid.actions':
            export: app.supplier.export
```

A live component works the same way, just map it to the permission its screen already checks:

```yaml
odiseo_sylius_rbac:
    live_component_permissions:
        'app_admin:supplier:form': app.supplier.update
```

Inside a Twig template you can also ask directly:

```twig
{% if is_granted('app.supplier.export') %}
    <a href="{{ path('app_admin_supplier_export') }}">Export</a>
{% endif %}
```

## In your own code

The voter works with `is_granted()` wherever Symfony calls it:

```php
// A controller
$this->denyAccessUnlessGranted('app.supplier.update', $supplier);

// A service
if ($this->authorizationChecker->isGranted('app.supplier.export')) {
    // ...
}
```

```yaml
# An API Platform operation
security: "is_granted('app.supplier.export')"
```

Notice the identifier is used directly, there's no `ROLE_` prefix and nothing to register.

## Restricting a permission to part of the data

Permissions only answer *which screens*. If you also need *which records*, implement
`ScopeResolverInterface` and decorate the default service:

```php
final readonly class ChannelScopeResolver implements ScopeResolverInterface
{
    public function isInScope(
        AdministrationRoleAwareInterface $administrator,
        PermissionIdentifier $permission,
        mixed $subject,
    ): bool {
        if (!$subject instanceof OrderInterface) {
            return true;
        }

        return $subject->getChannel()?->getCode() === $this->channelOf($administrator);
    }
}
```

```yaml
services:
    App\Security\ChannelScopeResolver:
        decorates: odiseo_rbac.security.scope_resolver
```

The voter calls this on every decision it would otherwise grant, so returning `false` denies it
across all six surfaces at once, with nothing else to change. `$subject` is whatever got passed to
`isGranted()`, and it's `null` when the check is about a screen rather than a record.

## Shipping declarations from a plugin

If you're building a plugin, declare its permissions so installing it puts them in the tree and
removing it takes them back out. Prepend them from your extension instead of relying on the
application to import them:

```php
public function prepend(ContainerBuilder $container): void
{
    if (!isset($container->getExtensions()['odiseo_sylius_rbac'])) {
        return;
    }

    $file = __DIR__ . '/../../config/rbac_permissions.yaml';

    $container->addResource(new FileResource($file));
    $container->prependExtensionConfig('odiseo_sylius_rbac', Yaml::parseFile($file)['odiseo_sylius_rbac']);
}
```

Checking the extension is present keeps your plugin usable without this one installed. Prepending
also means the application can still override any single entry by key.

## Fixtures

You can seed roles like any other Sylius resource:

```yaml
sylius_fixtures:
    suites:
        default:
            fixtures:
                administration_role_suppliers:
                    name: 'administration_role'
                    options:
                        code: 'supplier_manager'
                        name: 'Supplier manager'
                        permissions:
                            - 'app.supplier.*'
                            - '*.*.index'

                admin_user_administration_role:
                    name: 'admin_user_administration_role'
                    priority: -1     # after the roles and the admin users
                    options:
                        role: 'supplier_manager'
                        usernames: ['supplier@example.com']
```

Both fixtures are safe to run again: an existing code gets reused instead of duplicated, and a
username nothing created just gets skipped.

## Replacing the role entity

`AdminUser` references `AdministrationRoleInterface`, so to point it at your own implementation,
add a `resolve_target_entities` entry:

```yaml
doctrine:
    orm:
        resolve_target_entities:
            Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface: App\Entity\AdministrationRole
```

---

[← Configuration reference](configuration.md) · [Docs index](README.md) · [Console commands →](console.md)
