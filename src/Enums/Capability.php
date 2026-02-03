<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Enums;

/**
 * Capabilities that a driver can support.
 */
enum Capability: string
{
    case FILTERS = 'filters';
    case SORTS = 'sorts';
    case INCLUDES = 'includes';
    case FIELDS = 'fields';
    case APPENDS = 'appends';

    /**
     * Get all capability values as an array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_map(fn(self $case) => $case->value, self::cases());
    }
}
