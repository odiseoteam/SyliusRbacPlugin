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

A **permission** looks like `{package}.{subject}.{operation}`, for example `sylius.product.update`.
You don't declare these by hand: they exist because some route, API operation or button needs them,
and the plugin finds them on its own.

An **administration role** stores **patterns**, where any segment can be `*`. `sylius.product.*`
means every operation on products, `*.*.index` is a read-only role, `*.*.*` is a super administrator.

An **administrator** can hold several roles, and their permissions just add up. The menu, the
buttons, the routes, the API, they all ask the same voter the same question:

```
may this administrator perform {package}.{subject}.{operation}?
```
