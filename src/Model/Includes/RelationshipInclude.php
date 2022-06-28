<?php

namespace Jackardios\QueryWizard\Model\Includes;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Jackardios\QueryWizard\Model\ModelInclude;

class RelationshipInclude extends ModelInclude
{
    public function handle($queryWizard, $model): void
    {
        $relatedTables = collect(explode('.', $this->getInclude()));

        $loads = $relatedTables
            ->mapWithKeys(function ($table, $key) use ($queryWizard, $relatedTables) {
                $fullRelationName = $relatedTables->slice(0, $key + 1)->implode('.');

                $key = Str::plural(Str::snake($fullRelationName));
                $fields = method_exists($queryWizard, 'getFieldsByKey') ? $queryWizard->getFieldsByKey($key) : null;

                if (empty($fields)) {
                    return [$fullRelationName];
                }

                return [$fullRelationName => function ($query) use ($fields) {
                    $query->select($fields);
                }];
            })
            ->toArray();

        $model->loadMissing($loads);
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

    public function createExtra(): array
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
                    $includes[] = new CountInclude($include);
                }

                return $includes;
            })
            ->flatten()
            ->toArray();
    }
}
