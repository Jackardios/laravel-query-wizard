<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent\Includes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Jackardios\QueryWizard\Includes\AbstractInclude;

/**
 * Include for checking relationship existence via withExists().
 *
 * Adds a boolean attribute `{relation}_exists` to each model.
 */
final class ExistsInclude extends AbstractInclude
{
    /**
     * Create a new exists include.
     *
     * @param  string  $relation  The relationship name
     * @param  string|null  $alias  Optional alias for URL parameter name
     */
    public static function make(string $relation, ?string $alias = null): static
    {
        return new self($relation, $alias);
    }

    public function getType(): string
    {
        return 'exists';
    }

    public function getDefaultAliasSuffix(): string
    {
        return 'Exists';
    }

    public function getSuffixConfigKey(): string
    {
        return 'exists_suffix';
    }

    /**
     * @param  Builder<Model>|Relation<Model, Model, mixed>  $subject
     * @return Builder<Model>|Relation<Model, Model, mixed>
     */
    public function apply(mixed $subject): mixed
    {
        return $subject->withExists($this->relation);
    }
}
