<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers\Eloquent\Includes;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Jackardios\QueryWizard\Includes\AbstractInclude;

class RelationshipInclude extends AbstractInclude
{
    public static function make(string $relation, ?string $alias = null): static
    {
        return new static($relation, $alias);
    }

    public function getType(): string
    {
        return 'relationship';
    }

    /**
     * @param Builder<Model> $subject
     * @param array<string> $fields
     * @return Builder<Model>
     */
    public function apply(mixed $subject, array $fields = []): mixed
    {
        $relationNames = collect(explode('.', $this->relation));

        $withs = $relationNames
            ->mapWithKeys(function ($table, $key) use ($relationNames, $fields) {
                $fullRelationName = $relationNames->slice(0, $key + 1)->implode('.');
                $callback = $this->buildFieldSelectionCallback($fullRelationName, $fields);

                if ($callback === null) {
                    return [$fullRelationName => static function (): void {}];
                }

                return [$fullRelationName => $callback];
            })
            ->filter()
            ->toArray();

        $subject->with($withs);

        return $subject;
    }

    /**
     * @param array<string> $fields
     * @return (Closure(Builder<Model>|Relation<Model, Model, mixed>): void)|null
     */
    protected function buildFieldSelectionCallback(string $fullRelationName, array $fields): ?Closure
    {
        if (empty($fields)) {
            return null;
        }

        return function (Builder|Relation $query) use ($fields): void {
            $query->select($query->qualifyColumns($fields));
        };
    }
}
