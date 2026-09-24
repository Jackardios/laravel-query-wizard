<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Support;

/**
 * Allow/deny matching for public parameter names.
 *
 * Deny entries: '*' denies every name, 'relation.*' denies the direct children
 * of 'relation', and any other entry denies itself and all of its descendants.
 *
 * Allow entries grant an attribute of a relation path either explicitly
 * ('relation.attribute') or through the wildcard of that level only
 * ('relation.*', or '*' at the root).
 *
 * Names are matched as given; callers normalize them first.
 *
 * @internal
 */
final class NamePolicy
{
    /**
     * @param  array<string, true>  $allowed
     * @param  array<string, true>  $denied
     * @param  array<string, true>  $deniedChildrenOf
     */
    private function __construct(
        private readonly array $allowed,
        private readonly array $denied,
        private readonly array $deniedChildrenOf,
        private readonly bool $deniesAll,
    ) {}

    /**
     * @param  array<string>  $names
     */
    public static function allowing(array $names): self
    {
        return new self(array_fill_keys($names, true), [], [], false);
    }

    /**
     * @param  array<string>  $names
     */
    public static function denying(array $names): self
    {
        $deniedChildrenOf = [];

        foreach ($names as $name) {
            if (str_ends_with($name, '.*')) {
                $deniedChildrenOf[substr($name, 0, -2)] = true;
            }
        }

        return new self([], array_fill_keys($names, true), $deniedChildrenOf, in_array('*', $names, true));
    }

    /**
     * @param  string  $path  Relation path ('' for root)
     */
    public function allowsAttribute(string $path, string $attribute): bool
    {
        if ($path === '') {
            return isset($this->allowed[$attribute]) || isset($this->allowed['*']);
        }

        return isset($this->allowed[$path.'.'.$attribute]) || isset($this->allowed[$path.'.*']);
    }

    /**
     * Whether the attribute is allowed by its own name, not only through a wildcard.
     */
    public function allowsAttributeByName(string $path, string $attribute): bool
    {
        return isset($this->allowed[$path === '' ? $attribute : $path.'.'.$attribute]);
    }

    public function denies(string $name): bool
    {
        if ($this->deniesAll || isset($this->denied[$name])) {
            return true;
        }

        $offset = 0;
        while (($dot = strpos($name, '.', $offset)) !== false) {
            if (isset($this->denied[substr($name, 0, $dot)])) {
                return true;
            }

            $offset = $dot + 1;
        }

        if ($this->deniedChildrenOf === []) {
            return false;
        }

        $lastDot = strrpos($name, '.');

        return $lastDot !== false && isset($this->deniedChildrenOf[substr($name, 0, $lastDot)]);
    }
}
