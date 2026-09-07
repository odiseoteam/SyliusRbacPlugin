[← The permission model](permissions.md) · [Docs index](README.md) · [Managing roles →](managing-roles.md)

# What gets enforced

Every check ends up asking the same Symfony voter the same question: *may this administrator
perform `{package}.{subject}.{operation}`?* The surfaces below just differ in how they get there.

You can ask that same question from your own code:

```twig
{% if is_granted('sylius.product.update') %}...{% endif %}
```

```php
$this->denyAccessUnlessGranted('sylius.order.refund', $order);
```

## The six surfaces

| Surface | What happens when the permission is missing |
|---|---|
| [Admin routes](#admin-routes) | 403 |
| [Admin API](#the-admin-api) | 403, as a JSON response |
| [The main menu](#the-main-menu) | The entry is not rendered |
| [Grid actions](#grid-actions) | The button is not rendered |
| [Action buttons and widgets](#action-buttons-and-dashboard-widgets) | The hookable renders nothing |
| [Live components](#live-components) | The live action is denied |

The first two actually block the request. The rest just make sure nobody sees a button they'd
get a 403 from. Hiding a button isn't the protection by itself, though: every one of those things
is also denied on its own if you hit it directly.

### Admin routes

Admin routes reach the plugin in three ways, and Sylius only handles one of them on its own:

1. **A resource route** carrying `_sylius: permission: true`. The resource controller already
   resolves it to a permission code and checks it. The plugin just fills in the authorization
   check that Sylius leaves open, so it actually reaches the voter instead of always saying yes.
2. **A route named in `route_permissions`.** The plugin's own listener checks it, because nothing
   else looks at those declarations. This is how it covers what Sylius leaves uncovered
   (impersonation, resending a confirmation email, invokable controllers) plus anything you
   declare yourself.
3. **Anything else.** Denied, unless it's listed in `excluded_routes`. See
   [deny by default](#deny-by-default).

Two routes don't fit this: `sylius_admin_entity_autocomplete` and `sylius_admin_live_component`
are each a single route shared by lots of different things, so their permission gets worked out
per request, from the alias or component name in the request.

#### Workflow transitions get their own operation

Sylius runs a state machine transition through `updateAction`, so cancelling an order would ask
for `sylius.order.update`, the same permission you'd need to edit one. The plugin swaps in the
transition's own operation instead:

```
sylius.order.cancel        cancelling an order
sylius.shipment.ship       marking a shipment shipped
sylius.payment.complete    completing a payment
```

That's how you can grant "may cancel orders" without also granting "may edit orders". The API
figures out the same operation from the URI, so the two never disagree.

### The admin API

Every `/api/v2/admin` operation checks the same identifiers as the HTML admin, so an
administrator's JWT can never do more than what their screens let them do.

Resources with no admin screen of their own, like images, translations, provinces, or promotion
rules, don't get their own permissions. They fold into their parent instead: any write asks for
`{parent}.update`, any read for `{parent}.show`. Editing a product image is really editing the
product.

Denials raise `AccessDeniedException`, and API Platform turns that into a 403 JSON response,
not the login redirect you'd get from an HTML firewall.

### The main menu

Menu entries get removed based on **each item's own route**, looked up in the same map the
request uses. So if you can see an entry, it leads somewhere that actually opens. A parent left
with no children and no destination of its own gets removed too, instead of expanding into an
empty page.

| Super admin | Catalog manager |
|---|---|
| <img src="images/menu-super-admin.png" alt="Full menu" height="380" /> | <img src="images/menu-catalog-manager.png" alt="Filtered menu" height="380" /> |

The role editor is the one screen where the menu isn't filtered: the tree builds its groups from
the admin menu, and if you filtered that menu first, whoever is editing a role would lose entire
sections from view.

### Grid actions

Grid buttons get filtered by decorating the grid definition converter. That's what lets this cover
grids the plugin has never seen before, including yours.

An action names what it does in one of two ways, and both get handled: the standard resource
types (`create`, `update`, `delete`, `show`) resolve through the grid's model class, the same way
the resource controller resolves them, and anything carrying a route resolves through the route
map instead. A `links` action only keeps the destinations you're allowed to reach, and disappears
entirely if none of them are.

| Super admin | A role without create or delete |
|---|---|
| ![Full grid](images/grid-actions-super-admin.png) | ![Filtered grid](images/grid-actions-hidden.png) |

### Action buttons and dashboard widgets

Buttons and widgets that render through Sylius' Twig hooks get checked before Sylius' own renderer
even sees them. This has to happen here, because a hookable renders inline as part of the page
that embeds it, it never goes through an HTTP route a listener could intercept.

Which hookable needs which permission lives in configuration
([`hookable_permissions`](configuration.md#hookable_permissions)) instead of being copied from
the hook itself, so it keeps working even after Sylius changes the component or template behind
it. A denied hookable just renders nothing instead of throwing: a missing widget is a gap in the
layout, not a broken page.

If several gated items sit inside one container (an *Actions* dropdown, a button group), the
container shows up as long as you have **any** one of the permissions inside it. Otherwise you'd
open a dropdown with nothing in it.

### Live components

A live component's first render is a hookable. Its follow-up requests go through the shared
`sylius_admin_live_component` route. Both are checked, and each component maps to the permission
its own screen already checks: the dashboard's "shipments to ship" widget asks for
`sylius.shipment.index`, so a role without that permission just doesn't see the widget.

Some components change nothing and expose nothing, like the dashboard's channel selector. Those
are listed as ungated on purpose.

## Deny by default

A route with no matching permission is **denied**. This is the exact bug the plugin exists to
fix: the old engine let unmapped routes through, so every route a third-party plugin added was
wide open.

Exceptions have to go in configuration, they can't just be left silently open:

```yaml
odiseo_sylius_rbac:
    excluded_routes:
        - sylius_admin_login
        - sylius_admin_dashboard
```

Listing a route this way tells you it was left open on purpose, not forgotten. The plugin's own
test suite checks the list too, and fails if something marked public isn't actually anonymous, or
if an entry points at a route that no longer exists.

If you're migrating an application with a lot of uncovered routes, you can turn this off for a
while:

```yaml
odiseo_sylius_rbac:
    deny_unprotected_admin_routes: false
```

Run [`odiseo:rbac:debug --strict`](console.md#the-coverage-check) to see what is still uncovered
before turning it back on.

## Anti-lockout

If saving a role would leave *you* unable to reach the roles screen, the save is refused, and you
get a message telling you what you'd lose.

It doesn't just check whether you unticked a box. It checks whether you'd actually end up without
that access: another of your roles might still grant it, or a `*.*.*` in the role you're editing
might cover it without spelling it out.

This only protects your own access, and only when you save a role. Deleting a role, or removing
one from your own account, still works even if it locks you out; [`odiseo:rbac:grant`](console.md#granting-access)
is how you get back in either way.

## Restricting *which records*, not which screens

The voter checks two things: does the administrator have a matching pattern, **and** does the
scope resolver accept the subject?

```php
interface ScopeResolverInterface
{
    public function isInScope(
        AdministrationRoleAwareInterface $administrator,
        PermissionIdentifier $permission,
        mixed $subject,
    ): bool;
}
```

The default implementation just allows everything. Replace or decorate the service if you want a
permission to reach only part of the data, say only orders from one channel, and it'll apply to
all six surfaces above without you touching any of them. See
[Extending](extending.md#restricting-a-permission-to-part-of-the-data).

---

[← The permission model](permissions.md) · [Docs index](README.md) · [Managing roles →](managing-roles.md)
