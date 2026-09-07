[← Managing roles](managing-roles.md) · [Docs index](README.md) · [Extending →](extending.md)

# Configuration reference

Everything lives under the `odiseo_sylius_rbac` extension. Nothing here is required: the plugin
ships its own declarations and boots with them.

```yaml
# config/packages/odiseo_sylius_rbac_plugin.yaml
odiseo_sylius_rbac:
    # your overrides
```

Your configuration is **merged by key** with what the plugin prepends, so redefining one route
leaves the rest untouched.

See what the resulting configuration looks like at any time:

```bash
bin/console debug:config odiseo_sylius_rbac
```

## The keys

| Key | Type | Default | What it is for |
|---|---|---|---|
| [`route_permissions`](#route_permissions) | map | the routes Sylius leaves uncovered | Say which permission a route requires |
| [`excluded_routes`](#excluded_routes) | list | login, logout, password reset, dashboard | Routes that deliberately require nothing |
| [`deny_unprotected_admin_routes`](#deny_unprotected_admin_routes) | bool | `true` | Deny anything no permission covers |
| [`hookable_permissions`](#hookable_permissions) | map | Sylius' buttons and widgets | Hide a button or widget behind a permission |
| [`ungated_action_hookables`](#ungated_action_hookables) | map | form and navigation controls | Buttons that deliberately carry no permission |
| [`live_component_permissions`](#live_component_permissions) | map | Sylius' admin components | Which permission a live component checks |
| [`live_component_excluded`](#live_component_excluded) | list | the channel selector | Components that deliberately require nothing |
| [`entity_autocomplete_permissions`](#entity_autocomplete_permissions) | map | four Sylius aliases | Which permission an autocomplete alias checks |
| [`subject_parents`](#subject_parents) | map | five Sylius subjects | Nest a subject under another in the tree |
| [`folded_api_subjects`](#folded_api_subjects) | map | 29 API-only subjects | Fold an API-only resource into its parent |
| [`sylius_sections`](#legacy-keys) | map | the pre-v3 sections | Read only by the migration command |
| [`custom_sections`](#legacy-keys) | map | `rbac` | Read only by the migration command |

---

## `route_permissions`

Maps an admin route to the permission it requires, and optionally gives that permission a label
and a group in the tree.

```yaml
odiseo_sylius_rbac:
    route_permissions:
        app_admin_supplier_index:
            permission: app.supplier.index
            label: app.ui.permission.supplier_index   # translation key, optional
            group: catalog                            # tree heading, optional
```

| Field | Required | Notes |
|---|---|---|
| `permission` | yes | `{package}.{subject}.{operation}` |
| `label` | no | Translation key shown in the tree; a machine-made name is used otherwise |
| `group` | no | Which tree heading to file it under; presentation only |

Use it for the two kinds of route Sylius does not cover on its own: routes that enforce nothing,
and routes that declare `permission: true` but whose controller cannot be mapped to a resource
action, so nothing would ever check it.

Declaring a route is also how you **override** what a resource route asks for: declarations win
over discovery, on both the HTML admin and the API.

> [!NOTE]
> These declarations are loaded by the plugin itself, not by your `config.yaml` imports: they are
> the only thing standing between an administrator and endpoints like impersonation, so they
> cannot be optional. Your own entries still override any of them by key.

<details>
<summary>What the plugin ships</summary>

Around 35 entries covering impersonation, the taxon index redirect, avatar removal, confirmation
email resends, invokable delete controllers, the order history screen, statistics, and the API
endpoints whose operation cannot be derived from the URI. Read them, with the reasoning for each,
in [`config/app/route_permissions.yaml`](../config/app/route_permissions.yaml).

</details>

## `excluded_routes`

Admin routes that deliberately require no permission.

```yaml
odiseo_sylius_rbac:
    excluded_routes:
        - app_admin_health_check
```

Defaults: `sylius_admin_login`, `sylius_admin_login_check`, `sylius_admin_logout`, the four
password-reset routes, the API's password reset and authentication token endpoints, and
`sylius_admin_dashboard`.

The dashboard is open on purpose, so a role missing one widget's permission lands somewhere after
logging in instead of on a 403; the widgets themselves are gated individually.

> [!WARNING]
> This list is for permissions that deliberately do not exist, not for skipping one that does.
> The plugin's own suite fails if an entry names a route the resource controller already checks.

## `deny_unprotected_admin_routes`

```yaml
odiseo_sylius_rbac:
    deny_unprotected_admin_routes: true   # default
```

When on, an admin route that no permission covers is denied. A route nobody protected is the
failure this plugin exists to prevent.

Turn it off only while migrating an application that has many uncovered routes, and use
[`odiseo:rbac:debug --strict`](console.md#the-coverage-check) to work the list down before turning
it back on.

## `hookable_permissions`

Hides a button, an action or a dashboard widget behind a permission, by hook and hookable name.

```yaml
odiseo_sylius_rbac:
    hookable_permissions:
        'sylius_admin.product.index.content.grid.actions':
            export: app.product.export

        # A list grants on *any* of them -- for a dropdown that would
        # otherwise open onto nothing once every item inside it is denied.
        'sylius_admin.order.show.content.header.title_block.actions':
            list: ['sylius.order.cancel', 'sylius.order.resend_confirmation_email']
```

Nothing here *declares* a hookable: the permission is added to the one Sylius (or another plugin)
already registered, and an entry naming a hookable the installed version does not ship is skipped.
That is what lets hooks that only exist from Sylius 2.1 on sit next to the rest.

Gate the button itself, not a whole table cell: hiding a `<td>` drops a cell and shifts the row
against its header.

<details>
<summary>What the plugin ships</summary>

The dashboard widgets, the order screen's transitions and Actions dropdown, the product and
variant action buttons, promotion and catalog promotion buttons, taxon and customer screen
buttons. See [`config/app/hookable_permissions.yaml`](../config/app/hookable_permissions.yaml).

</details>

## `ungated_action_hookables`

Hookables under an `*.actions*` hook that deliberately carry no permission: form controls
(`update`, `cancel`), navigation (`list`, `show`, `view_in_store`), and rows that are not buttons
despite the hook name.

```yaml
odiseo_sylius_rbac:
    ungated_action_hookables:
        'app_admin.supplier.update.content.header.title_block.actions': ['cancel', 'update']
```

Runtime ignores this key: it exists so a coverage test can tell a decision apart from an oversight,
and so a new button shipped by Sylius shows up as a red build rather than as a button that invites
a 403.

## `live_component_permissions`

Live components mapped to the permission their own screen already checks.

```yaml
odiseo_sylius_rbac:
    live_component_permissions:
        'app_admin:supplier:form': app.supplier.update
```

`sylius_admin_live_component` is one route shared by every live component, so no single declared
permission means the right thing for all of it, hence one entry per component.

<details>
<summary>What the plugin ships</summary>

Every Sylius admin component: the dashboard widgets mapped to the resource each one summarises
(`count_shipments_to_ship` → `sylius.shipment.index`), every `:form` component mapped to its
resource's `update`, the coupon generator, the shipment ship form, and the components of the
Mollie and PayPal plugins bundled with `sylius-standard`.

</details>

## `live_component_excluded`

The live-component counterpart to `excluded_routes`.

```yaml
odiseo_sylius_rbac:
    live_component_excluded:
        - 'sylius_admin:dashboard:channel_selector'   # default
```

The channel selector only changes which channel the *other* widgets are filtered by: it exposes
nothing and mutates nothing on its own.

## `entity_autocomplete_permissions`

`sylius_admin_entity_autocomplete` is one route shared by every autocomplete field. Aliases that
always query one fixed entity get the permission that entity's own index screen checks.

```yaml
odiseo_sylius_rbac:
    entity_autocomplete_permissions:
        sylius_admin_taxon: sylius.taxon.index            # defaults
        sylius_admin_product: sylius.product.index
        sylius_admin_product_variant: sylius.product_variant.index
        sylius_admin_product_attribute: sylius.product_attribute.index
```

Grid filter aliases are deliberately absent: they are reused across every grid, so their target
travels with the request and is resolved from it.

## `subject_parents`

Nests a subject under another in the tree, for the cases the identifier cannot express or gets
wrong.

```yaml
odiseo_sylius_rbac:
    subject_parents:
        sylius.shop_user: sylius.customer                       # defaults
        sylius.product_taxon: sylius.taxon
        sylius.channel_pricing_log_entry: sylius.product_variant
        sylius.impersonation: sylius.customer
        sylius.address: sylius.customer
```

Presentation only, it never reaches a stored pattern. A subject whose identifier already extends
its parent (`sylius.promotion_coupon` under `sylius.promotion`) is nested without being listed.

The defaults exist because the identifier is built from the controller's service name, not from
where the screen is reached: "Manage product positions" is `sylius.product_taxon` but lives on the
*taxon* screen, and "Price history" is `sylius.channel_pricing_log_entry` but lives on the
*variant* screen.

## `folded_api_subjects`

Resources the API exposes but the admin has no screen for, images, translations, provinces,
promotion rules. Their operations resolve to the parent's `update` or `show` instead of getting
permissions of their own.

```yaml
odiseo_sylius_rbac:
    folded_api_subjects:
        app.supplier_translation: app.supplier
```

The rule of thumb behind the defaults: if the admin edits it as part of the parent's form (a row
of a collection, a translation in the locale accordion) and nobody would grant it separately, it
is not a subject of its own. Folding keeps it out of both the registry and the tree, so the tree
lists screens rather than database tables.

<details>
<summary>What the plugin ships</summary>

29 entries: product images, associations and translations; taxon images and translations; variant
translations and channel pricings; attribute, option and association-type translations and values;
provinces; promotion rules, actions and translations; catalog promotion actions, scopes and
translations; shipping and payment method rules, translations and gateway config; avatar images;
order items, units, adjustments and address log entries.

</details>

## Legacy keys

`sylius_sections` and `custom_sections` are the pre-v3 section map: a section name and the admin
route prefixes it covered.

```yaml
odiseo_sylius_rbac:
    custom_sections:
        suppliers:
            - app_admin_supplier
```

They are still accepted so an application upgrading from 2.x boots with its old configuration in
place, and so a role holding a custom section can be translated into the routes that section
actually covered. **Nothing at runtime reads them**, only
[`odiseo:rbac:migrate-permissions`](console.md#migrating-from-2x) does.

They are removed in 4.0. See [Upgrading to 3.0](../UPGRADE-3.0.md).

---

[← Managing roles](managing-roles.md) · [Docs index](README.md) · [Extending →](extending.md)
