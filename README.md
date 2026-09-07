<p align="center">
    <a href="https://odiseo.io/en/products/sylius-plugins?utm_source=github&utm_medium=readme&utm_campaign=sylius-rbac-plugin" target="_blank">
        <img src="doc/images/rbac-og.png" alt="Odiseo Sylius RBAC Plugin" width="640" />
    </a>
</p>

<h1 align="center">Sylius RBAC Plugin</h1>

<p align="center">
    <strong>Per-operation role-based access control for the Sylius admin.</strong>
</p>

<p align="center">
    <a href="https://packagist.org/packages/odiseoteam/sylius-rbac-plugin"><img src="https://img.shields.io/packagist/v/odiseoteam/sylius-rbac-plugin.svg?style=flat-square" alt="Version" /></a>
    <a href="https://packagist.org/packages/odiseoteam/sylius-rbac-plugin"><img src="https://img.shields.io/packagist/dt/odiseoteam/sylius-rbac-plugin.svg?style=flat-square" alt="Downloads" /></a>
    <a href="https://github.com/odiseoteam/SyliusRbacPlugin/actions/workflows/build.yaml"><img src="https://img.shields.io/github/actions/workflow/status/odiseoteam/SyliusRbacPlugin/build.yaml?branch=master&style=flat-square" alt="Build status" /></a>
    <img src="https://img.shields.io/badge/sylius-2.0%20%7C%202.1%20%7C%202.2-1abb9c?style=flat-square" alt="Sylius versions" />
    <img src="https://img.shields.io/packagist/dependency-v/odiseoteam/sylius-rbac-plugin/php?style=flat-square" alt="PHP version" />
    <a href="LICENSE"><img src="https://img.shields.io/packagist/l/odiseoteam/sylius-rbac-plugin.svg?style=flat-square" alt="License" /></a>
</p>

---

Sylius ships one level of admin access. Whoever has it can do everything such as refund an order, delete a product, impersonate a customer, change a payment gateway.

This plugin splits that into **roles you define**, granting one operation on one resource at a time, and enforces them everywhere the admin can be reached.

## What you get

- **A permission per operation, not per section.** `sylius.product.update` is a different permission from `sylius.product.delete`. Wildcards keep it manageable: `sylius.product.*`, `*.*.index`, `*.*.*`.
- **Every admin route covered.** Permissions are discovered from Sylius' own resource metadata, so a route added by the next Sylius release, or by any plugin you install, shows up on its own.
- **Admin API coverage.**
- **Deny by default.** A route nobody protected is denied, not allowed. Deliberate exceptions are a config list, so "left open" is distinguishable from "forgotten".
- **Nothing dead on screen.** Menu entries, grid actions, action buttons and dashboard widgets the role cannot use are not rendered.
- **Several roles per administrator**, additive, plus an anti-lockout guard that refuses a change that would leave you unable to manage roles.
- **Console tooling**: grant access from the CLI, list what exists, find orphans, migrate v2 data.
- **18 locales** and a Symfony Flex recipe.

## Screenshots

<p align="center">
    <a href="doc/managing-roles.md#editing-permissions">
        <img src="doc/images/role-form-identifiers.png" alt="The permission tree" width="820" />
    </a>
</p>

<table>
    <tr>
        <td width="50%" align="center">
            <a href="doc/managing-roles.md#the-roles-screen"><img src="doc/images/roles-grid.png" alt="Administration roles" /></a>
            <br /><sub><b>Roles:</b> The ones you define, not a fixed list</sub>
        </td>
        <td width="50%" align="center">
            <a href="doc/managing-roles.md#assigning-roles"><img src="doc/images/admin-user-roles.png" alt="Assigning roles" /></a>
            <br /><sub><b>Assignment:</b> An administrator may hold several roles</sub>
        </td>
    </tr>
    <tr>
        <td width="50%" align="center">
            <a href="doc/enforcement.md#the-main-menu"><img src="doc/images/menu-catalog-manager.png" alt="Filtered menu" height="260" /></a>
            <br /><sub><b>Menu:</b> Trimmed to what the role can reach</sub>
        </td>
        <td width="50%" align="center">
            <a href="doc/enforcement.md#grid-actions"><img src="doc/images/grid-actions-hidden.png" alt="Filtered grid actions" /></a>
            <br /><sub><b>Grids:</b> No Create, no Delete, for a role without them</sub>
        </td>
    </tr>
</table>

## Quick start

```bash
composer require odiseoteam/sylius-rbac-plugin
bin/console doctrine:migrations:migrate
bin/console odiseo:rbac:grant <your-admin-email> super_admin --create
```

Two manual steps remain — making your `AdminUser` role-aware and wiring the tree's Stimulus
controller. Both are in [Installation](doc/installation.md), and skipping either is noisy, not
silent.

> [!WARNING]
> An administrator with no role is denied everything, including the screen that assigns roles.
> Run `odiseo:rbac:grant` right after installing, or nobody can get into the admin.

## What a permission looks like

Every permission is `{package}.{subject}.{operation}`, the same code Sylius' own resource
metadata produces:

```
sylius.product.update                  one operation on one resource
sylius.product.*                       everything on products
sylius.order.ship                      an operation that is not CRUD
*.*.index                              a read-only role, across the whole application
*.*.*                                  a super administrator
```

A role stores the patterns, never their expansion — so `sylius.product.*` keeps covering
operations that Sylius adds after the role was created. See
[The permission model](doc/permissions.md).

## Documentation

|                                                 |                                                              |
| ----------------------------------------------- | ------------------------------------------------------------ |
| [Installation](doc/installation.md)             | Three steps with Flex, plus the two the recipe cannot do     |
| [The permission model](doc/permissions.md)      | Identifiers, patterns, wildcards, how the tree is built      |
| [What gets enforced](doc/enforcement.md)        | The six surfaces, and what happens to a route nobody covered |
| [Managing roles](doc/managing-roles.md)         | Day-to-day use of the admin screens                          |
| [Configuration reference](doc/configuration.md) | Every key under `odiseo_sylius_rbac`                         |
| [Extending](doc/extending.md)                   | Declaring permissions for your own routes and plugins        |
| [Console commands](doc/console.md)              | `grant`, `debug`, `migrate-permissions`                      |
| [Troubleshooting](doc/troubleshooting.md)       | Locked out, unexpected 403, orphaned declarations            |
| [Upgrading to 3.0](UPGRADE-3.0.md)              | What breaks, and how stored roles are migrated               |
| [Contributing](CONTRIBUTING.md)                 | Running the test application and the suites                  |

## Compatibility

| Plugin | Sylius          | PHP             | Symfony   |
| ------ | --------------- | --------------- | --------- |
| `^3.0` | 2.0 · 2.1 · 2.2 | 8.2 · 8.3 · 8.4 | 6.4 · 7.x |
| `^2.0` | 1.12 · 2.0      | 8.0+            | 5.4 · 6.x |

Only the latest minor is supported. Security fixes land on `master`.

## Demo

Want a live walkthrough of this plugin? [Get in touch](https://odiseo.io/en/contact-us?utm_source=github&utm_medium=readme&utm_campaign=sylius-rbac-plugin)
— or browse all our Sylius plugins at [odiseo.io](https://odiseo.io/en/products/sylius-plugins?utm_source=github&utm_medium=readme&utm_campaign=sylius-rbac-plugin).

## Credits

<p>
    <img src="badge-partner-by-sylius.png" alt="Sylius partner" width="120" align="left" />
    Maintained by <a href="https://odiseo.io/en?utm_source=github&utm_medium=readme&utm_campaign=sylius-rbac-plugin">Odiseo</a>,
    a Sylius partner. Want us to help you with this plugin or any Sylius project?
    <a href="https://odiseo.io/en/contact-us?utm_source=github&utm_medium=readme&utm_campaign=sylius-rbac-plugin">Get in touch</a>.
    <br clear="left" />
</p>

Running a marketplace? Vendor role separation is one of the things a
[multi-vendor marketplace for Sylius](https://odiseo.io/en/products/sylius-marketplace-plugin?utm_source=github&utm_medium=readme&utm_campaign=sylius-rbac-plugin)
has to get right, and this plugin is how we do it.

## License

[MIT](LICENSE).
