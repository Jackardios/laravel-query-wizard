<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent\Includes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Includes\AbstractInclude;

/**
 * Include for eager loading relationships via with().
 */
final class RelationshipInclude extends AbstractInclude
{
    /**
     * Create a new relationship include.
     *
     * @param  string  $relation  The relationship name
     * @param  string|null  $alias  Optional alias for URL parameter name
     */
    public static function make(string $relation, ?string $alias = null): static
    {
        return new self($relation, $alias);
    }

    /**
     * Create an include from string, auto-detecting count and exists includes by suffix.
     *
     * @param  string  $name  The include name (e.g., 'posts', 'postsCount', or 'postsExists')
     * @param  string  $countSuffix  The suffix used for count includes (e.g., 'Count')
     * @param  string  $existsSuffix  The suffix used for exists includes (e.g., 'Exists')
     */
    public static function fromString(string $name, string $countSuffix, string $existsSuffix = 'Exists'): IncludeInterface
    {
        if ($existsSuffix !== '' && str_ends_with($name, $existsSuffix)) {
            $relation = substr($name, 0, -strlen($existsSuffix));

            return ExistsInclude::make($relation)->alias($name);
        }

        if ($countSuffix !== '' && str_ends_with($name, $countSuffix)) {
            $relation = substr($name, 0, -strlen($countSuffix));

            return CountInclude::make($relation)->alias($name);
        }

        return static::make($name);
    }

    public function getType(): string
    {
        return 'relationship';
    }

    /**
     * @param  Builder<Model>|Relation<Model, Model, mixed>  $subject
     * @return Builder<Model>|Relation<Model, Model, mixed>
     */
    public function apply(mixed $subject): mixed
    {
        return $subject->with($this->relation);
    }
}
