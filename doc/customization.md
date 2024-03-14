## Customization

By default, **RbacPlugin** is provided with access configuration for basic Sylius sections (catalog, configuration, customers, marketing and sales) as well as for RBAC section, added by the plugin itself.
Each section has a bunch of route prefixes associated with them, that describes which section gives permissions to which resources management.

However, usually, a Sylius application has a plenty of custom functions within existing or entirely new sections. This plugin allows you to extend its configuration, in order to restrict access to these custom routes.

For the matter of example let's assume we have a simple `Supplier` resource (containing only `string $name` property). It also has already generated routes, that we would like to restrict access to:

- `app_admin_supplier_index`
- `app_admin_supplier_create`
- `app_admin_supplier_update`
- `app_admin_supplier_bulk_delete`
- `app_admin_supplier_delete`

If you don't know how to create and configure custom resource in Sylius application, check out [relevant documentation chapter](https://docs.sylius.com/en/1.3/cookbook/entities/custom-model.html).

### Extending basic Sylius section with new route prefixes

The only thing required to restrict Supplier-related routes with, for example, "Customer management" permission, is adding appropriate route prefix to customers section configuration:

```yaml
odiseo_sylius_rbac_plugin:
    sylius_sections:
        customers:
            - app_admin_supplier
```

You would probably also want to add extend "Customers" section in Admin main menu (take a look at [this docs chapter](https://docs.sylius.com/en/1.3/customization/menu.html) for more information).

![Customers sections customized](images/customers_section_customized.png)

As a result, each Administrator allowed to manage customers in the Admin panel would also be able to manage Suppliers. You may also notice, nothing has changed in permissions configuration form,
as no new section has been added to the RBAC configuration.

![Permissions configuration - no changes](images/permissions_configuration_no_changes.png)

### Adding a custom section to the application

What if you want to differentiate your new resources management permission? The other possibility is to define your own, custom section in a plugin configuration:

```yaml
odiseo_sylius_rbac_plugin:
    custom_sections:
        suppliers:
            - app_admin_supplier
```

> Curiosity: RBAC is also defined as a custom section! You can easily check it out in a plugin source code.

With such a configuration, you should notice a new permission configuration available in the Administration Role form.

![Permissions configuration - no changes](images/permissions_configuration_changes.png)

To display new permission name nicely, you should also configure a translation in your application's translation file:

```yaml
odiseo_sylius_rbac_plugin:
    ui:
        permission:
            suppliers: Suppliers
```

#### Beware!

You should take into account that by default the RBAC Plugin recognizes the admin-related routes using logic
placed in the `HardcodedRouteNameChecker` class, which is the following:

```php
    public function isAdminRoute(string $routeName): bool
    {
        return
            strpos($routeName, 'sylius_admin') !== false ||
            strpos($routeName, 'odiseo_sylius_rbac_plugin_admin') !== false
        ;
    }
``` 

Let's assume that you added a new route to your application and you want it to be handled by the RBAC plugin.
Once you did so, you should override the checker placed above and customize it in the following manner:

```php
    public function isAdminRoute(string $routeName): bool
    {
        return
            strpos($routeName, 'sylius_admin') !== false ||
            strpos($routeName, 'odiseo_sylius_rbac_plugin_admin') !== false ||
            strpos($routeName, 'your_custom_phrase' !== false
        ;
    }
```

#### Remember!

When configuring a custom section in Admin main menu, name it the same way you named it under `custom_sections` key in the plugin configuration. It will be automatically hidden and shown, exactly as
basic Sylius sections!

```php
$suppliersSubmenu = $menu->addChild('suppliers')->setLabel('Suppliers');

$suppliersSubmenu
    ->addChild('supplier', ['route' => 'app_admin_supplier_index'])
    ->setLabel('Manage Suppliers')
    ->setLabelAttribute('icon', 'address card outline')
;
```

![Suppliers section](images/suppliers_section.png)

After these few simple steps, you can already give your custom permission to any already existent Administration role.
