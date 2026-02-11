<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Support;

use Illuminate\Support\Str;

/**
 * Converts parameter names between naming conventions.
 */
final class NameConverter
{
    public static function toSnakeCase(string $value): string
    {
        return Str::snake($value);
    }

    /**
     * Convert path segments (dot notation) using a converter function.
     *
     * Example: 'user.firstName' -> 'user.first_name'
     *
     * @param  callable(string): string  $converter
     */
    public static function convertPath(string $path, callable $converter): string
    {
        return implode('.', array_map($converter, explode('.', $path)));
    }

    /**
     * Convert path to snake_case.
     *
     * Example: 'user.firstName' -> 'user.first_name'
     */
    public static function pathToSnakeCase(string $path): string
    {
        return self::convertPath($path, self::toSnakeCase(...));
    }
}
