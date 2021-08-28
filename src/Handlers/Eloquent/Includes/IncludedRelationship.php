<?php

namespace Jackardios\QueryWizard\Handlers\Eloquent\Includes;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class IncludedRelationship extends AbstractEloquentInclude
{
    public function handle($queryHandler, $query): void
    {
        $relatedTables = collect(explode('.', $this->getInclude()));

        $withs = $relatedTables
            ->mapWithKeys(function ($table, $key) use ($queryHandler, $relatedTables) {
                $fullRelationName = $relatedTables->slice(0, $key + 1)->implode('.');

                $key = Str::plural(Str::snake($fullRelationName));
                $fields = $queryHandler->getWizard()->getFieldsByKey($key);

                if (empty($fields)) {
                    return [$fullRelationName];
                }

                return [$fullRelationName => function ($query) use ($fields) {
                    $query->select($fields);
                }];
            })
            ->toArray();

        $query->with($withs);
    }

    protected function getIndividualRelationshipPathsFromInclude(string $include): Collection
    {
        return collect(explode('.', $include))
            ->reduce(function (Collection $includes, string $relationship) {
                if ($includes->isEmpty()) {
                    return $includes->push($relationship);
                }

                return $includes->push("{$includes->last()}.{$relationship}");
            }, collect());
    }

    public function createOther(): array
    {
        return $this->getIndividualRelationshipPathsFromInclude($this->getInclude())
            ->map(function ($include) {
                if (empty($include)) {
                    return [];
                }

                $includes = [];

                if ($this->getInclude() !== $include) {
                    $includes[] = new static($include);
                }

                if (! Str::contains($include, '.')) {
                    $includes[] = new IncludedCount($include);
                }

                return $includes;
            })
            ->flatten()
            ->toArray();
    }
}
