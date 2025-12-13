<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\Values\Sort;

class QueryParametersManager
{
    /** @var Collection<int, string>|null */
    protected ?Collection $appends = null;

    /** @var Collection<string, array<string>>|null */
    protected ?Collection $fields = null;

    /** @var Collection<string, mixed>|null */
    protected ?Collection $filters = null;

    /** @var Collection<int, string>|null */
    protected ?Collection $includes = null;

    /** @var Collection<int, Sort>|null */
    protected ?Collection $sorts = null;

    protected QueryWizardConfig $config;

    public function __construct(
        protected ?Request $request = null,
        ?QueryWizardConfig $config = null
    ) {
        $this->config = $config ?? new QueryWizardConfig();
    }

    public function getRequest(): ?Request
    {
        return $this->request;
    }

    public function getConfig(): QueryWizardConfig
    {
        return $this->config;
    }

    /**
     * @return Collection<string, array<string>>
     */
    public function getFields(): Collection
    {
        if ($this->fields instanceof Collection) {
            return $this->fields;
        }

        $fieldsParameterName = $this->config->getFieldsParameterName();

        $this->setFieldsParameter($fieldsParameterName ? $this->getRequestData($fieldsParameterName) : collect());

        /** @var Collection<string, array<string>> */
        return $this->fields ?? collect();
    }

    /**
     * @return Collection<int, string>
     */
    public function getAppends(): Collection
    {
        if ($this->appends instanceof Collection) {
            return $this->appends;
        }

        $appendsParameterName = $this->config->getAppendsParameterName();

        $this->setAppendsParameter($appendsParameterName ? $this->getRequestData($appendsParameterName) : collect());

        /** @var Collection<int, string> */
        return $this->appends ?? collect();
    }

    /**
     * @return Collection<string, mixed>
     */
    public function getFilters(): Collection
    {
        if ($this->filters instanceof Collection) {
            return $this->filters;
        }

        $filtersParameterName = $this->config->getFiltersParameterName();

        $this->setFiltersParameter($filtersParameterName ? $this->getRequestData($filtersParameterName) : collect());

        /** @var Collection<string, mixed> */
        return $this->filters ?? collect();
    }

    /**
     * @return Collection<int, string>
     */
    public function getIncludes(): Collection
    {
        if ($this->includes instanceof Collection) {
            return $this->includes;
        }

        $includesParameterName = $this->config->getIncludesParameterName();

        $this->setIncludesParameter($includesParameterName ? $this->getRequestData($includesParameterName) : collect());

        /** @var Collection<int, string> */
        return $this->includes ?? collect();
    }

    /**
     * @return Collection<int, Sort>
     */
    public function getSorts(): Collection
    {
        if ($this->sorts instanceof Collection) {
            return $this->sorts;
        }

        $sortsParameterName = $this->config->getSortsParameterName();

        $this->setSortsParameter($sortsParameterName ? $this->getRequestData($sortsParameterName) : collect());

        /** @var Collection<int, Sort> */
        return $this->sorts ?? collect();
    }

    public function setFieldsParameter(mixed $fieldsParameter): static
    {
        if (is_string($fieldsParameter)) {
            $fieldsParameter = $this->parseFieldsString($fieldsParameter);
        }

        /** @var Collection<string, array<string>> $fields */
        $fields = collect($fieldsParameter)
            ->map(function ($fields) {
                if (is_string($fields)) {
                    $fields = $this->separateToArray($fields);
                }

                return $this->prepareList($fields)->toArray();
            })
            ->filter();

        $this->fields = $fields;

        return $this;
    }

    /**
     * Parse fields string like "resource.field,resource2.field2,simpleField"
     * into associative array grouped by resource name.
     * Fields without dots are grouped under empty string key.
     *
     * @param string $fieldsString
     * @return array<string, array<string>>
     */
    protected function parseFieldsString(string $fieldsString): array
    {
        $fields = $this->separateToArray($fieldsString);
        $grouped = [];

        foreach ($fields as $field) {
            $field = trim($field);
            if (empty($field)) {
                continue;
            }

            $lastDotPos = strrpos($field, '.');
            if ($lastDotPos !== false) {
                $resource = substr($field, 0, $lastDotPos);
                $fieldName = substr($field, $lastDotPos + 1);
            } else {
                $resource = '';
                $fieldName = $field;
            }

            if (!isset($grouped[$resource])) {
                $grouped[$resource] = [];
            }
            $grouped[$resource][] = $fieldName;
        }

        return $grouped;
    }

    public function setAppendsParameter(mixed $appendsParameter): static
    {
        if (is_string($appendsParameter)) {
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

    /**
     * Get filter value by dot notation name, supporting nested array access.
     * For example, getFilterValue('some.foo.bar') will look for:
     * 1. Direct key 'some.foo.bar'
     * 2. Nested path: filters['some']['foo']['bar']
     * 3. Partial paths: filters['some.foo']['bar'], filters['some']['foo.bar']
     *
     * @return mixed The filter value or null if not found
     */
    public function getFilterValue(string $name): mixed
    {
        $filters = $this->getFilters();

        if ($filters->has($name)) {
            return $filters->get($name);
        }

        $value = $this->getNestedFilterValue($filters->all(), $name);
        if ($value !== null) {
            return $value;
        }

        return null;
    }

    /**
     * Recursively look for a filter value in nested array structure
     *
     * @param array<string, mixed> $data
     */
    protected function getNestedFilterValue(array $data, string $name): mixed
    {
        if (array_key_exists($name, $data)) {
            return $data[$name];
        }

        $parts = explode('.', $name);

        for ($i = 1; $i <= count($parts); $i++) {
            $key = implode('.', array_slice($parts, 0, $i));
            $remainder = implode('.', array_slice($parts, $i));

            if (array_key_exists($key, $data)) {
                $value = $data[$key];

                if ($remainder === '') {
                    return $value;
                }

                if (is_array($value)) {
                    $nested = $this->getNestedFilterValue($value, $remainder);
                    if ($nested !== null) {
                        return $nested;
                    }
                }
            }
        }

        return null;
    }

    public function setIncludesParameter(mixed $includesParameter): static
    {
        if (is_string($includesParameter)) {
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
            ->map(fn($field) => new Sort((string) $field))
            ->unique(fn(Sort $sort) => $sort->getField())
            ->values();

        return $this;
    }

    /**
     * Reset all cached parameters.
     * Useful when reusing the same instance for multiple requests (e.g., in Laravel Octane).
     */
    public function reset(): static
    {
        $this->appends = null;
        $this->fields = null;
        $this->filters = null;
        $this->includes = null;
        $this->sorts = null;

        return $this;
    }

    /**
     * Set a new request instance and reset cached parameters.
     */
    public function setRequest(Request $request): static
    {
        $this->request = $request;

        return $this->reset();
    }

    protected function getRequestData(?string $key = null, mixed $default = null): mixed
    {
        if ($this->config->shouldUseRequestBody()) {
            return $this->request->input($key, $default);
        }

        return $this->request->query($key, $default);
    }

    /**
     * @return array<int, string>
     */
    protected function separateToArray(string $string): array
    {
        return explode($this->config->getArrayValueSeparator(), $string);
    }

    /**
     * @param array<int, string>|null $list
     * @return Collection<int, string>
     */
    protected function prepareList(?array $list): Collection
    {
        /** @var Collection<int, string> $result */
        $result = collect($list)
            ->filter()
            ->unique()
            ->values();

        return $result;
    }

    protected function parseFilterValue(mixed $filterValue): mixed
    {
        if (is_array($filterValue)) {
            return collect($filterValue)->map(function ($item) {
                return $this->parseFilterValue($item);
            })->all();
        }

        if (is_string($filterValue) && Str::contains($filterValue, $this->config->getArrayValueSeparator())) {
            return $this->separateToArray($filterValue);
        }

        if ($filterValue === 'true') {
            return true;
        }

        if ($filterValue === 'false') {
            return false;
        }

        return $filterValue;
    }
}
