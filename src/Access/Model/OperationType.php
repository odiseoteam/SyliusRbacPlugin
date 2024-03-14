<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Access\Model;

use Webmozart\Assert\Assert;

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
