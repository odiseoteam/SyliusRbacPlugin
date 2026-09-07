[← Configuration reference](configuration.md) · [Docs index](README.md) · [Console commands →](console.md)

# Extending

How your own resources, routes and plugins take part in the permission system. The full list of
configuration keys is in the [configuration reference](configuration.md); this page is the "when do
I need which" version.

## A custom resource needs nothing

Say your application has a `Supplier` resource with the usual Sylius routes:

```
app_admin_supplier_index
app_admin_supplier_create
app_admin_supplier_update
app_admin_supplier_delete
app_admin_supplier_bulk_delete
```

Because it is a Sylius resource, its routes already carry `permission: true` and the resource
controller already resolves them to permission codes. They show up in the tree on their own:

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
where the rest of your application's permissions do. **Grid buttons and menu entries for it are
filtered too**, without configuration: both are resolved through the same route map.

> [!TIP]
> If a resource does *not* appear, its routes are probably declared without `permission: true`.
> That is the one thing worth checking before reaching for configuration.

## Routes that are not resource routes

An invokable controller, a custom action, an export endpoint — anything the resource controller
does not check. Declare what it requires:

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
[deny-by-default](enforcement.md#deny-by-default) is on. That is the intended failure mode: you
find out immediately, not the day someone discovers the endpoint is open.

If it should require nothing at all, say so explicitly:

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

Group headings come from the admin menu, so `group: catalog` files a permission under the same
heading as the rest of the catalog. To create a heading of your own, add the menu section — the
tree follows the menu, not the other way around.

Two more knobs, both presentation only:

- [`subject_parents`](configuration.md#subject_parents) nests a subject under the one whose screen
  reaches it.
- [`folded_api_subjects`](configuration.md#folded_api_subjects) folds an API-only resource into its
  parent, so it never becomes a row of its own.

## Buttons, widgets and live components

A button you added through a Twig hook is not gated by the route behind it — the route denies the
request, but the button still renders and invites a 403. Gate it:

```yaml
odiseo_sylius_rbac:
    hookable_permissions:
        'sylius_admin.product.index.content.grid.actions':
            export: app.supplier.export
```

A live component follows the same idea, mapped to the permission its screen already checks:

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

The voter answers to `is_granted()` everywhere Symfony does:

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

The attribute is the permission identifier itself — no `ROLE_` prefix, no voter registration.

## Restricting a permission to part of the data

Permissions answer *which screens*. To answer *which records*, implement `ScopeResolverInterface`
and decorate the shipped service:

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

The voter calls it on every decision it would otherwise grant, so returning `false` denies — on all
six surfaces at once, with nothing else to change. `$subject` is whatever the caller passed to
`isGranted()`, and is `null` when the check is about a screen rather than a record.

## Shipping declarations from a plugin

A plugin should declare its own permissions, so that installing it puts them in the tree and
removing it takes them out. Prepend them from your extension rather than relying on the
application's imports:

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

Guarding on the extension being present keeps the plugin usable without this one. Prepending also
means the application can still override any single entry by key.

## Fixtures

Roles can be seeded like any other Sylius resource:

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

Both are re-runnable: an existing code is reused rather than inserted twice, and a username nothing
created is skipped.

## Replacing the role entity

`AdminUser` references `AdministrationRoleInterface`, so pointing it at your own implementation is
a `resolve_target_entities` entry:

```yaml
doctrine:
    orm:
        resolve_target_entities:
            Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface: App\Entity\AdministrationRole
```

---

[← Configuration reference](configuration.md) · [Docs index](README.md) · [Console commands →](console.md)
