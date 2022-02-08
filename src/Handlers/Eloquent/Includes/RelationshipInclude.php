<?php

namespace Jackardios\QueryWizard\Handlers\Eloquent\Includes;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Jackardios\QueryWizard\Abstracts\Handlers\AbstractQueryHandler;

class RelationshipInclude extends AbstractEloquentInclude
{
    /** {@inheritdoc} */
    public function handle(AbstractQueryHandler $queryHandler, $queryBuilder): void
    {
        $relatedTables = collect(explode('.', $this->getInclude()));

        $eagerLoads = $queryBuilder->getEagerLoads();
        $withs = $relatedTables
            ->mapWithKeys(function ($table, $key) use ($queryHandler, $relatedTables, $eagerLoads) {
                $fullRelationName = $relatedTables->slice(0, $key + 1)->implode('.');

                if (array_key_exists($fullRelationName, $eagerLoads)) {
                    return [];
                }

                $key = Str::plural(Str::snake($fullRelationName));
                $fields = $queryHandler->getWizard()->getFieldsByKey($key);

                if (empty($fields)) {
                    return [$fullRelationName => static function() {}];
                }

                return [$fullRelationName => function ($query) use ($fields) {
                    $query->select($fields);
                }];
            })
            ->filter()
            ->toArray();

        $queryBuilder->setEagerLoads(array_merge($eagerLoads, $withs));
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
                    $includes[] = new CountInclude($include);
                }

                return $includes;
            })
            ->flatten()
            ->toArray();
    }
}
