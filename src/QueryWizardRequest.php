<?php

namespace Jackardios\QueryWizard;


use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Jackardios\QueryWizard\Values\Sort;

class QueryWizardRequest extends Request
{
    protected ?Collection $appends = null;
    protected ?Collection $fields = null;
    protected ?Collection $filters = null;
    protected ?Collection $includes = null;
    protected ?Collection $sorts = null;

    private static string $includesArrayValueDelimiter = ',';

    private static string $appendsArrayValueDelimiter = ',';

    private static string $fieldsArrayValueDelimiter = ',';

    private static string $sortsArrayValueDelimiter = ',';

    private static string $filterArrayValueDelimiter = ',';

    public static function setArrayValueDelimiter(string $delimiter): void
    {
        static::$filterArrayValueDelimiter = $delimiter;
        static::$includesArrayValueDelimiter = $delimiter;
        static::$appendsArrayValueDelimiter = $delimiter;
        static::$fieldsArrayValueDelimiter = $delimiter;
        static::$sortsArrayValueDelimiter = $delimiter;
    }

    public static function fromRequest(Request $request): self
    {
        return static::createFrom($request, new self());
    }

    protected function getRequestData(?string $key = null, $default = null)
    {
        if (config('query-wizard.request_data_source') === 'body') {
            return $this->input($key, $default);
        }

        return $this->query($key, $default);
    }

    public function appends(): Collection
    {
        if ($this->appends instanceof Collection) {
            return $this->appends;
        }

        $appendParameterName = config('query-wizard.parameters.append');

        $appendParts = $this->getRequestData($appendParameterName);

        if (is_string($appendParts)) {
            $appendParts = explode(static::getAppendsArrayValueDelimiter(), $appendParts);
        }

        return $this->appends = collect($appendParts)
            ->filter()
            ->unique()
            ->values();
    }

    public function fields(): Collection
    {
        if ($this->fields instanceof Collection) {
            return $this->fields;
        }

        $fieldsParameterName = config('query-wizard.parameters.fields');

        $fieldsPerTable = collect($this->getRequestData($fieldsParameterName));

        if ($fieldsPerTable->isEmpty()) {
            return collect();
        }

        return $this->fields = $fieldsPerTable->map(function ($fields) {
            if (is_string($fields)) {
                $fields = explode(static::getFieldsArrayValueDelimiter(), $fields);
            }
            return collect($fields)
                ->filter()
                ->unique()
                ->values()
                ->toArray();
        })->filter();
    }

    public function filters(): Collection
    {
        if ($this->filters instanceof Collection) {
            return $this->filters;
        }

        $filterParameterName = config('query-wizard.parameters.filter');

        $filterParts = $this->getRequestData($filterParameterName, []);

        if (is_string($filterParts)) {
            return collect();
        }

        $filters = collect($filterParts);

        return $this->filters = $filters->map(function ($value) {
            return $this->getFilterValue($value);
        });
    }

    public function includes(): Collection
    {
        if ($this->includes instanceof Collection) {
            return $this->includes;
        }

        $includeParameterName = config('query-wizard.parameters.include');

        $includeParts = $this->getRequestData($includeParameterName);

        if (is_string($includeParts)) {
            $includeParts = explode(static::getIncludesArrayValueDelimiter(), $this->getRequestData($includeParameterName));
        }

        return $this->includes = collect($includeParts)
            ->filter()
            ->unique();
    }

    public function sorts(): Collection
    {
        if ($this->sorts instanceof Collection) {
            return $this->sorts;
        }

        $sortParameterName = config('query-wizard.parameters.sort');

        $sortParts = $this->getRequestData($sortParameterName);

        if (is_string($sortParts)) {
            $sortParts = explode(static::getSortsArrayValueDelimiter(), $sortParts);
        }

        return $this->sorts = collect($sortParts)
            ->filter()
            ->unique(function($sort) {
                return ltrim((string)$sort, '-');
            })
            ->values()
            ->map(function($field) {
                return new Sort((string)$field);
            });
    }

    /**
     * @param $value
     *
     * @return array|bool
     */
    protected function getFilterValue($value)
    {
        if (is_array($value)) {
            return collect($value)->map(function ($valueValue) {
                return $this->getFilterValue($valueValue);
            })->all();
        }

        if (Str::contains($value, static::getFilterArrayValueDelimiter())) {
            return explode(static::getFilterArrayValueDelimiter(), $value);
        }

        if ($value === 'true') {
            return true;
        }

        if ($value === 'false') {
            return false;
        }

        return $value;
    }

    public static function setIncludesArrayValueDelimiter(string $includesArrayValueDelimiter): void
    {
        static::$includesArrayValueDelimiter = $includesArrayValueDelimiter;
    }

    public static function setAppendsArrayValueDelimiter(string $appendsArrayValueDelimiter): void
    {
        static::$appendsArrayValueDelimiter = $appendsArrayValueDelimiter;
    }

    public static function setFieldsArrayValueDelimiter(string $fieldsArrayValueDelimiter): void
    {
        static::$fieldsArrayValueDelimiter = $fieldsArrayValueDelimiter;
    }

    public static function setSortsArrayValueDelimiter(string $sortsArrayValueDelimiter): void
    {
        static::$sortsArrayValueDelimiter = $sortsArrayValueDelimiter;
    }

    public static function setFilterArrayValueDelimiter(string $filterArrayValueDelimiter): void
    {
        static::$filterArrayValueDelimiter = $filterArrayValueDelimiter;
    }

    public static function getIncludesArrayValueDelimiter(): string
    {
        return static::$includesArrayValueDelimiter;
    }

    public static function getAppendsArrayValueDelimiter(): string
    {
        return static::$appendsArrayValueDelimiter;
    }

    public static function getFieldsArrayValueDelimiter(): string
    {
        return static::$fieldsArrayValueDelimiter;
    }

    public static function getSortsArrayValueDelimiter(): string
    {
        return static::$sortsArrayValueDelimiter;
    }

    public static function getFilterArrayValueDelimiter(): string
    {
        return static::$filterArrayValueDelimiter;
    }
}
