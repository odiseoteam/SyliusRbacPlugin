<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Legacy;

use Webmozart\Assert\Assert;

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
final class OperationType
{
    public const READ = 'read';

    public const WRITE = 'write';

    private string $type;

    public static function read(): self
    {
        return new self(self::READ);
    }

    public static function write(): self
    {
        return new self(self::WRITE);
    }

    public function __construct(string $type)
    {
        Assert::oneOf($type, [self::READ, self::WRITE]);

        $this->type = $type;
    }

    public function __toString(): string
    {
        return $this->type;
    }
}
