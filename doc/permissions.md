[← Manual installation](manual-installation.md) · [Docs index](README.md) · [What gets enforced →](enforcement.md)

# The permission model

There are three things to know: an **identifier** names one thing an administrator can do, a
**pattern** is what a role actually stores, and the **tree** is how you see both of these in the
admin.

## Identifiers

A permission identifier is always three segments:

```
{package}.{subject}.{operation}
```

| Segment | What it is | Examples |
|---|---|---|
| `package` | The bundle that owns the subject, never a business domain | `sylius`, `odiseo_rbac`, `sylius_refund` |
| `subject` | Usually a resource, sometimes a capability with no resource behind it | `product`, `order`, `impersonation` |
| `operation` | What is being done to it | `index`, `show`, `create`, `update`, `delete`, `bulk_delete`, `ship`, `refund` |

```
sylius.product.update
sylius.order.resend_confirmation_email
sylius.impersonation.execute
odiseo_rbac.administration_role.create
```

This isn't a format we invented. It's Sylius' own: `Sylius\Resource\Metadata::getPermissionCode()`
builds exactly this string, and the resource controller already passes it to an authorization
checker. That's why most admin routes are covered without anyone declaring anything.

The format is validated when a string is parsed:

- **Always exactly three segments.** `sylius.product` gets rejected. Two ways to write the same
  thing would make patterns hard to compare.
- **Lowercase, `[a-z][a-z0-9_]*` per segment.**

### `package` is not a domain

`catalog`, `sales` and `marketing` are *not* packages. Those are menu sections, and the menu
changes over time. The package is the code that owns the resource, and that doesn't change. The
menu still decides where a permission shows up in [the tree](#the-tree), it just never ends up in
the stored value.

## Patterns

A role doesn't store identifiers directly. It stores **patterns**, identifiers where any segment
can be the wildcard `*`.

```
sylius.product.update      exactly one permission
sylius.product.*           every operation on products
sylius.*.index             list every Sylius resource
*.*.index                  a read-only role, across the whole application
*.*.*                      a super administrator
```

A pattern matches an identifier when every segment is either equal or `*`. An administrator can
do something if **any** pattern in **any** of their roles matches it.

### Patterns are never expanded

Saving `sylius.product.*` stores that string, not the six identifiers it happens to cover right
now. That's on purpose: if we expanded it at save time, the role would freeze, and the next time
Sylius adds an operation to products, every role using this pattern would miss it.

### There are no deny patterns

Roles only grant, they never take away. If we allowed deny patterns too, you'd need a precedence
rule to decide which one wins, and then "can this role do X?" stops being something you can answer
just by reading the role.

> [!NOTE]
> Removing access means removing a pattern, or removing a role from an administrator, never
> adding a negative one.

## Where identifiers come from

Nobody maintains a list by hand. Permissions are **discovered** when the container compiles, from
four sources merged into one registry:

| Source | What it contributes |
|---|---|
| Resource routes | Every admin route whose controller resolves to a resource action, via Sylius' own metadata |
| API operations | Every `/api/v2/admin` operation, including sub-resources with no admin screen |
| Declarations | `route_permissions` entries, for routes Sylius leaves uncovered, and for your own |
| Live components | Admin live components mapped to the permission their screen already checks |

In practice: **install a plugin and its permissions show up in the tree, remove it and they're
gone**, without touching any configuration. The one thing left over is a role that still holds a
pattern for something that no longer exists, and
[`odiseo:rbac:debug`](console.md#finding-orphans) will tell you about those.

### Naming and grouping a permission

By default a discovered permission gets a machine-made name. If you want a readable label and a
place in the tree for it, declare it in configuration. That doesn't create the permission, it just
describes one that's already there, or names one for a route nothing else covers. See
[Extending](extending.md).

## The tree

The role editor shows the vocabulary as a table per group, one row per subject, one column per
operation.

![The permission tree](images/role-form-identifiers.png)

- **Groups come from the admin menu.** A permission shows up where an administrator would already
  look for that screen. This is only presentation, it's never part of the stored pattern, so
  reorganizing the menu doesn't break any existing role. Anything with no menu entry of its own
  ends up under **Other**.
- **Shared columns first**: `index`, `show`, `create`, `update`, `delete`, `bulk_delete`. A row
  without one of them says something true: that resource has no such operation.
- **Everything else is an extra operation**, shown as a labelled checkbox at the end of the row:
  `ship`, `refund`, `resend_confirmation_email`, `execute`.
- **Nested rows** are subjects reached only from inside another subject's screen: coupons under
  promotions, product taxa under taxons.
- **Tri-state checkboxes.** The subject box and the group box are checked when everything below is,
  indeterminate when part of it is.
- **Read only**, applied in bulk, means `index`, `show` and `view`.

The footer counts the **rules** you've stored against the **permissions** those rules actually
grant, and *Show identifiers* shows the identifier for every row plus the exact patterns that
will be saved:

```
sylius.product.index   sylius.product.show   sylius.product.update
sylius.product_taxon.*   sylius.product_variant.*   sylius.taxon.index
```

That panel answers "what did this checkbox actually save?"; see
[Managing roles](managing-roles.md).

## Roles

An **administration role** is a resource like any other: a code, a translated name, and its
patterns. An administrator can hold **any number of roles**, and their permissions add up.

None of the default roles are hard-coded. `super_admin`, `catalog`, `sales` and `read_only` exist
because the [fixtures](extending.md#fixtures) create them, not because the plugin knows about
them.

---

[← Manual installation](manual-installation.md) · [Docs index](README.md) · [What gets enforced →](enforcement.md)
