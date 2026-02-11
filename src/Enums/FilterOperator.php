<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Enums;

enum FilterOperator: string
{
    case EQUAL = '=';
    case NOT_EQUAL = '!=';
    case GREATER_THAN = '>';
    case GREATER_THAN_OR_EQUAL = '>=';
    case LESS_THAN = '<';
    case LESS_THAN_OR_EQUAL = '<=';
    case LIKE = 'LIKE';
    case NOT_LIKE = 'NOT LIKE';
    case DYNAMIC = 'dynamic';

    public function supportsArrayValues(): bool
    {
        return match ($this) {
            self::EQUAL, self::NOT_EQUAL => true,
            default => false,
        };
    }

    public function getSqlOperator(): ?string
    {
        return match ($this) {
            self::DYNAMIC => null,
            default => $this->value,
        };
    }
}
