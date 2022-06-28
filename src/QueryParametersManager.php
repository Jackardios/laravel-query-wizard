<?php

namespace Jackardios\QueryWizard;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Jackardios\QueryWizard\Values\Sort;

class QueryParametersManager
{
    protected ?Collection $appends = null;
    protected ?Collection $fields = null;
    protected ?Collection $filters = null;
    protected ?Collection $includes = null;
    protected ?Collection $sorts = null;

    protected string $arrayValueSeparator;

    public function __construct(
        protected Request $request
    ) {
        $this->arrayValueSeparator = config('query-wizard.array_value_separator', ',');
    }

    public function getFields(): Collection
    {
        if ($this->fields instanceof Collection) {
            return $this->fields;
        }

        $fieldsParameterName = config('query-wizard.parameters.fields');

        $this->setFieldsParameter($fieldsParameterName ? $this->getRequestData($fieldsParameterName) : collect());

        return $this->fields;
    }

    public function getAppends(): Collection
    {
        if ($this->appends instanceof Collection) {
            return $this->appends;
        }

        $appendsParameterName = config('query-wizard.parameters.appends');

        $this->setAppendsParameter($appendsParameterName ? $this->getRequestData($appendsParameterName) : collect());

        return $this->appends;
    }

    public function getFilters(): Collection
    {
        if ($this->filters instanceof Collection) {
            return $this->filters;
        }

        $filtersParameterName = config('query-wizard.parameters.filters');

        $this->setFiltersParameter($filtersParameterName ? $this->getRequestData($filtersParameterName) : collect());

        return $this->filters;
    }

    public function getIncludes(): Collection
    {
        if ($this->includes instanceof Collection) {
            return $this->includes;
        }

        $includesParameterName = config('query-wizard.parameters.includes');

        $this->setIncludesParameter($includesParameterName ? $this->getRequestData($includesParameterName) : collect());

        return $this->includes;
    }

    public function getSorts(): Collection
    {
        if ($this->sorts instanceof Collection) {
            return $this->sorts;
        }

        $sortsParameterName = config('query-wizard.parameters.sorts');

        $this->setSortsParameter($sortsParameterName ? $this->getRequestData($sortsParameterName) : collect());

        return $this->sorts;
    }

    public function setFieldsParameter(mixed $fieldsParameter): static
    {
        if(is_string($fieldsParameter)) {
            $fieldsParameter = [
                $this->separateToArray($fieldsParameter)
            ];
        }

        $this->fields = collect($fieldsParameter)
            ->map(function ($fields) {
                if (is_string($fields)) {
                    $fields = $this->separateToArray($fields);
                }

                return $this->prepareList($fields)->toArray();
            })
            ->filter();

        return $this;
    }

    public function setAppendsParameter(mixed $appendsParameter): static
    {
        if(is_string($appendsParameter)) {
            $appendsParameter = $this->separateToArray($appendsParameter);
        }

        $this->appends = $this->prepareList($appendsParameter);

        return $this;
    }

    public function setFiltersParameter(mixed $filtersParameter): static
    {
        if (is_string($filtersParameter)) {
            $this->filters = collect();

            return $this;
        }

        $this->filters = collect($filtersParameter)->map(function ($value) {
            return $this->parseFilterValue($value);
        });

        return $this;
    }

    public function setIncludesParameter(mixed $includesParameter): static
    {
        if(is_string($includesParameter)) {
            $includesParameter = $this->separateToArray($includesParameter);
        }

        $this->includes = $this->prepareList($includesParameter);

        return $this;
    }

    public function setSortsParameter(mixed $sortsParameter): static
    {
        if (is_string($sortsParameter)) {
            $sortsParameter = $this->separateToArray($sortsParameter);
        }

        $this->sorts = collect($sortsParameter)
            ->filter()
            ->map(fn($field) => new Sort((string)$field))
            ->unique(fn(Sort $sort) => $sort->getField())
            ->values();

        return $this;
    }

    protected function getRequestData(?string $key = null, $default = null)
    {
        if (config('query-wizard.request_data_source') === 'body') {
            return $this->request->input($key, $default);
        }

        return $this->request->query($key, $default);
    }

    protected function separateToArray(string $string): array
    {
        return explode($this->arrayValueSeparator, $string);
    }

    protected function prepareList(?array $list): Collection
    {
        return collect($list)
            ->filter()
            ->unique()
            ->values();
    }

    protected function parseFilterValue($filterValue)
    {
        if (is_array($filterValue)) {
            return collect($filterValue)->map(function ($item) {
                return $this->parseFilterValue($item);
            })->all();
        }

        if (Str::contains($filterValue, $this->arrayValueSeparator)) {
            return $this->separateToArray($filterValue);
        }

        return $filterValue;
    }
}
