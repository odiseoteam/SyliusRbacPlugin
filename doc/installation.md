# Installation

## 1. Require the plugin

```bash
composer require odiseoteam/sylius-rbac-plugin --no-scripts
```

Symfony Flex's recipe registers the bundle and imports `config/packages/` and `config/routes/`
for you. If your project doesn't use Flex, or the recipe didn't apply (contrib recipes need
`extra.symfony.allow-contrib` in `composer.json`), follow
[manual-installation.md](manual-installation.md).

## 2. Wire the assets

The role editor's permission tree is a Stimulus controller. Like Sylius' own admin controllers
and the `symfony/ux-*` packages, it ships as an npm package the consuming app depends on locally.

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

Skip this step and the role editor still renders the tree, but checking a permission does
nothing: the checkbox never reaches the form, so the role saves without it and nothing reports
the miss.

## 3. Make your `AdminUser` administration-role aware

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

## 4. Update the database schema

```bash
bin/console doctrine:migrations:migrate
bin/console cache:clear
bin/console assets:install public
```

## 5. Give someone access

An administrator with no role is denied everything, including the screen that assigns roles, so
right after installing nobody can reach the admin panel. Grant the first one from the console:

```bash
bin/console odiseo:rbac:grant <username-or-email> super_admin --create
```

`--create` mints the role if it does not exist yet. Add `--dry-run` to see the plan without
writing. From then on roles are managed from **Administration › Roles**.

This command never goes through the permission check, so it is also the way back if everyone is
locked out later, a role deleted by mistake, a database edited by hand, or an upgrade left
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
