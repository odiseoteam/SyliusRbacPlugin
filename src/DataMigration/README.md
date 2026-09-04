# DataMigration

Home of the command that upgrades stored permissions from the pre-v3 format to the v3 model.

This is the **only** layer allowed to import `Odiseo\SyliusRbacPlugin\Legacy\`, and the rule is
enforced by `deptrac.yaml`. The isolation rule is what keeps the command here, instead of letting it land somewhere that
forces the rule to be widened.

The command reads the old `permissions` JSON through DBAL rather than through
`Entity\AdministrationRole`, which no longer exposes it — see the docblock on that entity's
`$permissions` property.

Removed in 4.0, together with `src/Legacy/`.
