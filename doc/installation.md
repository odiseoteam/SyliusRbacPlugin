# Installation

## 1. Require the plugin

```bash
composer require odiseoteam/sylius-rbac-plugin
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
    prefix: /admin
```

## 5. Make your `AdminUser` administration-role aware

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

## 6. Update the database schema

```bash
bin/console doctrine:migrations:migrate
bin/console cache:clear
```

## Customizing the administration role entity

`AdminUser` references `AdministrationRoleInterface`, an abstraction over the default
`AdministrationRole` implementation. To point it at your own class:

```yaml
doctrine:
    orm:
        resolve_target_entities:
            Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface: FullyQualifiedClassName
```
