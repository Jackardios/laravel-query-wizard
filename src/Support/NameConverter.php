<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Support;

use Illuminate\Support\Str;

/**
 * Converts parameter names between naming conventions.
 *
 * @internal
 */
final class NameConverter
{
    private const CACHE_LIMIT = 256;

    /** @var array<string, string> */
    private static array $snakeCache = [];

    /**
     * Same result as Str::snake(), without growing its unbounded cache with
     * names taken from the request.
     */
    public static function toSnakeCase(string $value): string
    {
        if (isset(self::$snakeCache[$value])) {
            return self::$snakeCache[$value];
        }

        $converted = $value;

        if (! ctype_lower($value)) {
            $converted = (string) preg_replace('/\s+/u', '', ucwords($value));
            $converted = Str::lower((string) preg_replace('/(.)(?=[A-Z])/u', '$1_', $converted));
        }

        if (count(self::$snakeCache) >= self::CACHE_LIMIT) {
            unset(self::$snakeCache[array_key_first(self::$snakeCache)]);
        }

        return self::$snakeCache[$value] = $converted;
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
