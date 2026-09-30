<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Enums;

enum FilterOperator: string
{
    case Equal = '=';
    case NotEqual = '!=';
    case GreaterThan = '>';
    case GreaterThanOrEqual = '>=';
    case LessThan = '<';
    case LessThanOrEqual = '<=';
    case Like = 'LIKE';
    case NotLike = 'NOT LIKE';
    case Dynamic = 'dynamic';

    /**
     * @internal
     */
    public function supportsArrayValues(): bool
    {
        return match ($this) {
            self::Equal, self::NotEqual, self::Like, self::NotLike => true,
            default => false,
        };
    }

    /**
     * @internal
     */
    public function getSqlOperator(): ?string
    {
        return match ($this) {
            self::Dynamic => null,
            default => $this->value,
        };
    }
}
