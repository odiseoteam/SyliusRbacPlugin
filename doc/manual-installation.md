# Manual installation

Every step, with nothing delegated to Symfony Flex. Use this if the project doesn't have Flex, if
`composer require` didn't apply the recipe (contrib recipes need `extra.symfony.allow-contrib`
in `composer.json`), or to see exactly what the recipe automates. Most installs should follow
[installation.md](installation.md) instead.

## 1. Require the plugin

```bash
composer require odiseoteam/sylius-rbac-plugin --no-scripts
```

## 2. Enable the plugin

```php
<?php
// config/bundles.php

return [
    // ...
    Odiseo\SyliusRbacPlugin\OdiseoSyliusRbacPlugin::class => ['all' => true],
];
```

## 3. Import the plugin configuration

```yaml
# config/packages/odiseo_sylius_rbac_plugin.yaml
imports:
    - { resource: "@OdiseoSyliusRbacPlugin/config/config.yaml" }
```

## 4. Import the routes

```yaml
# config/routes/odiseo_sylius_rbac_plugin.yaml
odiseo_sylius_rbac_admin:
    resource: "@OdiseoSyliusRbacPlugin/config/routes/admin.yaml"
    prefix: '/%sylius_admin.path_name%'
```

## 5. Wire the assets

```json
// package.json
{
    "dependencies": {
        "@odiseoteam/sylius-rbac-plugin": "file:vendor/odiseoteam/sylius-rbac-plugin/assets/admin"
    }
}
```

```json
// assets/admin/controllers.json
{
    "controllers": {
        "@odiseoteam/sylius-rbac-plugin": {
            "rbac-permissions": {
                "enabled": true,
                "fetch": "lazy",
                "autoimport": {
                    "@odiseoteam/sylius-rbac-plugin/styles/rbac-permissions.css": true
                }
            }
        }
    }
}
```

```bash
yarn install --force
yarn build
```

## 6. Make your `AdminUser` administration-role aware

```php
<?php
// src/Entity/User/AdminUser.php

declare(strict_types=1);

namespace App\Entity\User;

use Doctrine\ORM\Mapping as ORM;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleAwareInterface;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleAwareTrait;
use Sylius\Component\Core\Model\AdminUser as BaseAdminUser;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_admin_user')]
class AdminUser extends BaseAdminUser implements AdministrationRoleAwareInterface
{
    use AdministrationRoleAwareTrait;

    // ...
}
```

## 7. Update the database schema

```bash
bin/console doctrine:migrations:migrate
bin/console cache:clear
bin/console assets:install public
```

## 8. Give someone access

An administrator with no role is denied everything, including the screen that assigns roles, so
right after installing nobody can reach the admin panel. Grant the first one from the console:

```bash
bin/console odiseo:rbac:grant <username-or-email> super_admin --create
```

`--create` mints the role if it does not exist yet. Add `--dry-run` to see the plan without
writing. From then on roles are managed from **Administration › Roles**.

This command never goes through the permission check, so it is also the way back if everyone is
locked out later — a role deleted by mistake, a database edited by hand, or an upgrade left
half-applied. Keep console access available to whoever administers the shop.

## Customizing the administration role entity

`AdminUser` references `AdministrationRoleInterface`, an abstraction over the default
`AdministrationRole` implementation. To point it at your own class:

```yaml
doctrine:
    orm:
        resolve_target_entities:
            Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface: FullyQualifiedClassName
```
