# Legacy

Pre-v3 permission model: `Section` + `read`/`write`, serialized to JSON in the `permissions`
column of `odiseo_rbac_administration_role`.

These classes are **not part of the new engine**. They exist solely so the data migration
command can interpret what is currently stored in the databases of users coming from 1.x / 2.x.
Rewriting that parsing from scratch would be more expensive and riskier than keeping it.

## Rules

- The only code allowed to import `Odiseo\SyliusRbacPlugin\Legacy\` is the data migration
  command. The rule is enforced with `deptrac` in CI (ROADMAP PR 20), and it currently holds
  with no exceptions: no production file imports this namespace.
- The migration command reads the old JSON through **DBAL**, not through
  `Entity\AdministrationRole`. The entity keeps the column mapped — see the docblock on its
  `$permissions` property — but does not expose it, so PR 5 can reshape the entity without
  breaking the migration.
- Nothing here gets fixed, refactored or extended. If it is broken, it is fixed in the new
  engine. `Permission::equals()` has a known bug, documented in its test; it stays as is.

Removed in its entirety in 4.0, together with the migration command.

See ROADMAP §5.1.
