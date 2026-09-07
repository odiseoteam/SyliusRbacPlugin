[← What gets enforced](enforcement.md) · [Docs index](README.md) · [Configuration reference →](configuration.md)

# Managing roles

Everything here lives under **Administration → Roles** in the admin panel. The console equivalents
are in [Console commands](console.md).

## The roles screen

![Administration roles](images/roles-grid.png)

A role has a **code**, a **translated name**, and its permissions. Console commands and fixtures
use the code; the name is what administrators actually see, and you can translate it like any
other Sylius resource.

None of this comes built in. `super_admin`, `catalog`, `sales` and `read_only` only exist because
the [fixtures](extending.md#fixtures) create them. Rename them, delete them, or start from
scratch.

> [!WARNING]
> Deleting a role revokes it from every administrator holding it, immediately. If it was the only
> role of the person deleting it, they are locked out on the next request. Recover with
> [`odiseo:rbac:grant`](console.md#granting-access).

## Editing permissions

![The permission tree](images/role-form-permissions.png)

Each group is a section of the admin menu; each row is a subject; each column is an operation.

| Control | What it does |
|---|---|
| Row checkbox | Everything on that subject, stored as `sylius.product.*` |
| Group checkbox | Everything in that section |
| Column **all** | That operation on every subject in the section |
| **Grant: Everything / Read only / Nothing** | Across the whole application: `*.*.*`, the read operations, or clear |
| Filter box | Narrows the tree to matching subjects while you look for one |

Checkboxes are tri-state: a subject or group box is checked when everything below it is granted,
and shows a dash when only some of it is.

A nested row means that screen is only reached from inside another screen, like coupons under
promotions, or product taxa under taxons. The row it's nested under tells you where to actually
find it in the admin.

### Seeing what will be stored

Turn on **Show identifiers** in the footer:

![Identifiers and stored rules](images/role-form-identifiers.png)

Every row shows its identifier, and *What gets stored* lists the exact patterns the role is about
to save. The two counters next to it show how many **rules** you've stored, and how many
**permissions** those rules currently grant.

The two numbers aren't the same thing. `sylius.product.*` is one rule, and it grants six
permissions today, and seven once Sylius adds a new operation to products. That's why we store
patterns instead of expanding them, and this panel is where you can check which of your choices
turned into a wildcard and which turned into a plain list.

### Read only

*Grant → Read only* stores `*.*.index`, `*.*.show` and `*.*.view`: everything that shows something
without changing it, including resources added later by a plugin you have not installed yet.

## Assigning roles

![Assigning administration roles](images/admin-user-roles.png)

You assign roles from the **Roles** field on the administrator's own screen. An administrator can
hold **several roles**, and their permissions just add up. There's no ordering and no precedence
to worry about, since roles only grant, they never take anything away.

An administrator with **no** role is denied everything, including this screen. That's expected
for a brand new account, not a bug.

![Administrators](images/admin-users-grid.png)

## The guard against locking yourself out

If saving a role would leave *you* unable to reach the roles screen, it's refused, and you get a
message telling you what you were about to lose.

It checks your actual access, not just the checkbox: another of your roles might still grant it,
or a `*.*.*` in the role you're editing might cover it without spelling it out. Deleting a role,
or removing it from your own account, isn't guarded the same way; see
[Troubleshooting](troubleshooting.md#i-locked-everyone-out).

## A role that stops working

Two things can change what a role actually means, without anyone touching it:

- **A plugin gets removed.** Its permissions are still named in the role, but they match nothing
  now.
- **A route gets renamed** in a Sylius upgrade. The old declaration is now orphaned.

Neither breaks anything at runtime, and neither one is silent:
[`odiseo:rbac:debug --strict`](console.md#finding-orphans) reports both.

---

[← What gets enforced](enforcement.md) · [Docs index](README.md) · [Configuration reference →](configuration.md)
