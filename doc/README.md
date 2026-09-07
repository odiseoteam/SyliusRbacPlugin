# Documentation

Per-operation role-based access control for the Sylius admin.
Back to the [project README](../README.md).

## Getting it running

| | |
|---|---|
| [Installation](installation.md) | `composer require`, plus the two steps the Flex recipe cannot do for you |
| [Manual installation](manual-installation.md) | The same thing with nothing delegated to Flex |
| [Upgrading to 3.0](../UPGRADE-3.0.md) | Coming from 2.x: what breaks and how stored roles are migrated |

## Understanding it

| | |
|---|---|
| [The permission model](permissions.md) | What a permission *is*: identifiers, patterns, wildcards, the tree |
| [What gets enforced](enforcement.md) | The six surfaces a permission is checked on, and deny-by-default |

## Using it

| | |
|---|---|
| [Managing roles](managing-roles.md) | The admin screens, with screenshots |
| [Console commands](console.md) | `odiseo:rbac:grant`, `odiseo:rbac:debug`, `odiseo:rbac:migrate-permissions` |
| [Troubleshooting](troubleshooting.md) | Locked out, unexpected 403, uncovered routes, orphaned declarations |

## Adapting it

| | |
|---|---|
| [Configuration reference](configuration.md) | Every key under `odiseo_sylius_rbac`, with defaults |
| [Extending](extending.md) | Declaring permissions for your own routes, resources and plugins |
| [Contributing](../CONTRIBUTING.md) | Test application, test suites, coding standard |

## In one minute

A **permission** is `{package}.{subject}.{operation}` — `sylius.product.update`. It exists because
some route, API operation or button requires it; nobody has to declare it by hand.

An **administration role** stores **patterns**, where any segment may be `*`. `sylius.product.*`
is every operation on products, `*.*.index` is a read-only role, `*.*.*` is a super administrator.

An **administrator** holds any number of roles, and their permissions add up. Everything else —
the menu, the buttons, the routes, the API — asks the same question of the same voter:

```
may this administrator perform {package}.{subject}.{operation}?
```
