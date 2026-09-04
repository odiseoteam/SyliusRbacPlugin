<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Legacy;

/**
 * Pre-v3 permission model (`Section` + `read`/`write`, serialized to JSON).
 *
 * Kept solely so the data migration command can read what is currently stored in user
 * databases. Do not use from new code: the data migration is the only layer allowed to
 * import `Legacy\`, and `deptrac.yaml` enforces it.
 *
 * @internal
 *
 * @deprecated removed in 4.0, once the data migration is no longer needed
 */
interface PermissionInterface
{
    public function operationTypes(): ?array;

    public function addOperationType(OperationType $operationType): void;

    public function type(): string;

    public function serialize(): string;
}
