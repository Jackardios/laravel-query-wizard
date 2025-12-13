<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Includes;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Contracts\Definitions\IncludeDefinitionInterface;
use Jackardios\QueryWizard\Contracts\IncludeStrategyInterface;

class RelationshipIncludeStrategy implements IncludeStrategyInterface
{
    /**
     * @param Builder<\Illuminate\Database\Eloquent\Model> $subject
     * @param array<string> $fields
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public function apply(mixed $subject, IncludeDefinitionInterface $include, array $fields = []): mixed
    {
        $relationName = $include->getRelation();
        $relationNames = collect(explode('.', $relationName));

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
     * @return (Closure(Builder<\Illuminate\Database\Eloquent\Model>|\Illuminate\Database\Eloquent\Relations\Relation<\Illuminate\Database\Eloquent\Model>): void)|null
     */
    protected function buildFieldSelectionCallback(string $fullRelationName, array $fields): ?Closure
    {
        if (empty($fields)) {
            return null;
        }

        return function (Builder|\Illuminate\Database\Eloquent\Relations\Relation $query) use ($fields): void {
            $query->select($query->qualifyColumns($fields));
        };
    }
}
