## Installation

1. Run `composer require odiseoteam/sylius-rbac-plugin --no-scripts`

2. Enable the plugin in bundles.php

```php
<?php
// config/bundles.php

return [
    // ...
    Odiseo\SyliusRbacPlugin\OdiseoSyliusRbacPlugin::class => ['all' => true],
];
```

3. Import the plugin configurations

```yml
# config/packages/_sylius.yaml
imports:
    # ...
    - { resource: "@OdiseoSyliusRbacPlugin/Resources/config/config.yaml" }
```

4. Add the admin route

```yml
# config/routes.yaml
odiseo_sylius_rbac_plugin_admin:
    resource: "@OdiseoSyliusRbacPlugin/Resources/config/routing/admin.yaml"
    prefix: /admin
```

5. Include traits and override the models

```php
<?php
// src/Entity/User/AdminUser.php

// ...
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

6. Finish the installation updating the database schema and installing assets

```
php bin/console doctrine:migrations:migrate
php bin/console sylius:theme:assets:install
php bin/console cache:clear
```

7. Run installation command

    ```
    php bin/console odiseo:rbac:install
    ```

   Which consists of:

    * `sylius:fixtures:load`

      Loading fixture with a default "No sections access" role.

      The command runs in non-interactive mode so it will NOT purge your database.
      However, once you run it again it will throw an exception because of duplicate entry constraint violation.

      If you want to install RBAC plugin again on the same environment you will have to remove all roles manually
      via administration panel or run all commands except `sylius:fixtures:load` separately.

    * `odiseo:rbac:normalize-administrators`

      Assigns role created in a previous step to all already existent administrators.

    * `odiseo:rbac:grant-access <roleName> <adminSections>`

      Where `adminSections` can be a space-separated list of any of these:
        * catalogManagement
        * configuration
        * customerManagement
        * marketingManagement
        * salesManagement

      #### Beware!

      There are two ways of defining root administrator's email address:

        * Provide it as a parameter in your configuration file (you will not be asked to enter it again via CLI during
          plugin's installation)

        ```yml
        parameters:
            root_administrator_email: example@example.com
        ``` 

        * Provide it via CLI

      e.g. `bin/console odiseo:rbac:grant-access administrator configuration catalogManagement`

      `In order to permit access to admin panel sections, please provide administrator's email address: sylius@example.com`

      By default, installation command creates *Configurator* role with access granted to all sections.

#### Beware!

You can also use `bin/console odiseo:rbac:grant-access-to-given-administrator <email> <roleName> <adminSections>`
command in order to provide an email address as an input parameter.

#### Beware!

`AdminUser` entity references `AdministrationRoleInterface`, which is an abstraction layer above the default
`AdministrationRole` implementation. You can easily customize it by adding a following snippet in your `*.yaml` configuration file:

```yml
doctrine:
    orm:
        resolve_target_entities:
            Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface: FullyQualifiedClassName
```
