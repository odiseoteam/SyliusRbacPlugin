# DataMigration

Home of the command that upgrades stored permissions from the pre-v3 format to the v3 model
(ROADMAP PR 6).

This is the **only** layer allowed to import `Odiseo\SyliusRbacPlugin\Legacy\`, and the rule is
enforced by `deptrac.yaml`. The directory is committed empty on purpose: it is what tells PR 6
where the command belongs, instead of letting it land somewhere that forces the isolation rule
to be widened.

The command reads the old `permissions` JSON through DBAL rather than through
`Entity\AdministrationRole`, which no longer exposes it — see the docblock on that entity's
`$permissions` property.

Removed in 4.0, together with `src/Legacy/`.
