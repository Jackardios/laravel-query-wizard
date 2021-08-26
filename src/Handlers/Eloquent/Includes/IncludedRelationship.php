<?php

namespace Jackardios\QueryWizard\Handlers\Eloquent\Includes;

class IncludedRelationship extends AbstractEloquentInclude
{
    public function handle($queryHandler, $query): void
    {
        $relatedTables = collect(explode('.', $this->getRelationship()));

        $withs = $relatedTables
            ->mapWithKeys(function ($table, $key) use ($queryHandler, $relatedTables) {
                $fullRelationName = $relatedTables->slice(0, $key + 1)->implode('.');

                $fields = $queryHandler->getWizard()->getFieldsByKey($fullRelationName);

                if ($fields->isEmpty()) {
                    return [$fullRelationName];
                }

                return [$fullRelationName => function ($query) use ($fields) {
                    $query->select($fields);
                }];
            })
            ->toArray();

        $query->with($withs);
    }
}
