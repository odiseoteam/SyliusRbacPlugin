[← Manual installation](manual-installation.md) · [Docs index](README.md) · [What gets enforced →](enforcement.md)

# The permission model

Three concepts, and nothing else: an **identifier** names one thing an administrator can do, a
**pattern** is what a role stores, and the **tree** is how the two are presented in the admin.

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

This is **Sylius' own format**: `Sylius\Resource\Metadata::getPermissionCode()` builds exactly
this string, and the resource controller already hands it to an authorization checker. Adopting it
verbatim is why most admin routes are covered without anyone declaring anything.

Two rules the format enforces, both checked when a string is parsed:

- **Always exactly three segments.** `sylius.product` is rejected; two spellings of one concept
  would make patterns ambiguous to compare.
- **Lowercase, `[a-z][a-z0-9_]*` per segment.**

### `package` is not a domain

`catalog`, `sales` and `marketing` are *not* packages. Domains like those mirror the admin menu,
which is presentation and moves; the package is the code that owns the resource, which does not.
The menu still decides where a permission is *shown* (see [the tree](#the-tree)); it just never
reaches the stored value.

## Patterns

A role does not store identifiers. It stores **patterns**: an identifier where any segment may be
the wildcard `*`.

```
sylius.product.update      exactly one permission
sylius.product.*           every operation on products
sylius.*.index             list every Sylius resource
*.*.index                  a read-only role, across the whole application
*.*.*                      a super administrator
```

A pattern matches an identifier when every segment either is equal or is `*`. An administrator is
allowed to do something when **any** pattern of **any** of their roles matches it.

### Patterns are never expanded

Saving `sylius.product.*` stores that string, not the six identifiers it covers today. This is
deliberate: expanding at save time would freeze the role, and the next Sylius release that adds an
operation to products would leave every existing role without it.

### There are no deny patterns

Everything is additive: roles grant, they never revoke. Mixing allow and deny would force a
precedence rule, and precedence rules make "can this role do X?" impossible to answer by looking
at the role.

> [!NOTE]
> Removing access means removing a pattern, or removing a role from an administrator, never
> adding a negative one.

## Where identifiers come from

Nobody maintains a list. Permissions are **discovered** at container-compile time from four
sources, merged into one registry:

| Source | What it contributes |
|---|---|
| Resource routes | Every admin route whose controller resolves to a resource action, via Sylius' own metadata |
| API operations | Every `/api/v2/admin` operation, including sub-resources with no admin screen |
| Declarations | `route_permissions` entries, for routes Sylius leaves uncovered, and for your own |
| Live components | Admin live components mapped to the permission their screen already checks |

The consequence worth remembering: **install a plugin, and its permissions appear in the tree;
remove it, and they disappear**, no configuration edit either way. What stays behind is any role
that still holds a pattern naming something that no longer exists, which
[`odiseo:rbac:debug`](console.md#finding-orphans) reports.

### Naming and grouping a permission

Discovery gives a permission a machine-made name. To give one a readable label and file it under a
heading, declare it in configuration. Declaring does not create the permission, it only describes
one that discovery already found, or names one for a route nothing else covers. See
[Extending](extending.md).

## The tree

The role editor renders the vocabulary as a table per group, one row per subject, one column per
operation.

![The permission tree](images/role-form-identifiers.png)

- **Groups come from the admin menu.** A permission is filed where the administrator already looks
  for the screens it governs. The group is presentation only and never reaches the stored pattern,
  so the menu can be reorganised without invalidating a single role. Anything with no menu entry
  of its own ends up under **Other**.
- **Shared columns first**: `index`, `show`, `create`, `update`, `delete`, `bulk_delete`. A row
  without one of them says something true: that resource has no such operation.
- **Everything else is an extra operation**, shown as a labelled checkbox at the end of the row:
  `ship`, `refund`, `resend_confirmation_email`, `execute`.
- **Nested rows** are subjects reached only from inside another subject's screen: coupons under
  promotions, product taxa under taxons.
- **Tri-state checkboxes.** The subject box and the group box are checked when everything below is,
  indeterminate when part of it is.
- **Read only**, applied in bulk, means `index`, `show` and `view`.

The footer counts the **rules** stored versus the **permissions granted** by them, and
*Show identifiers* reveals both the identifier of every row and the exact patterns that will be
written:

```
sylius.product.index   sylius.product.show   sylius.product.update
sylius.product_taxon.*   sylius.product_variant.*   sylius.taxon.index
```

That panel is the answer to "what did this checkbox actually save?"; see
[Managing roles](managing-roles.md).

## Roles

An **administration role** is a resource of its own: a code, a translated name, and its patterns.
An administrator holds **any number of roles**, and their permissions add up.

Nothing is hard-coded. `super_admin`, `catalog`, `sales` and `read_only` exist because the
[fixtures](extending.md#fixtures) create them, not because the plugin knows about them.

---

[← Manual installation](manual-installation.md) · [Docs index](README.md) · [What gets enforced →](enforcement.md)
