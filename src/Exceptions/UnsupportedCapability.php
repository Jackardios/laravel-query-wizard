<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Exceptions;

use LogicException;

class UnsupportedCapability extends LogicException
{
    public function __construct(
        public readonly string $capability,
        public readonly string $driverName
    ) {
        parent::__construct(
            "Driver '{$driverName}' does not support '{$capability}' capability"
        );
    }

    public static function make(string $capability, string $driverName): self
    {
        return new self($capability, $driverName);
    }
}
