<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;
use Jackardios\QueryWizard\Support\FilterValueTransformer;
use Jackardios\QueryWizard\Support\NameConverter;
use Jackardios\QueryWizard\Support\ParameterParser;
use Jackardios\QueryWizard\Values\Sort;

/**
 * Manages query parameters from HTTP requests.
 *
 * Handles parsing and caching of filter, sort, include, field, and append parameters.
 * Uses ParameterParser for list/sort parsing and FilterValueTransformer for filter values.
 */
class QueryParametersManager
{
    private static ?object $missing = null;

    private static function missing(): object
    {
        return self::$missing ??= new \stdClass;
    }

    /** @var Collection<string, array<string>>|null */
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

    protected ?ParameterParser $includesParser = null;

    protected ?ParameterParser $sortsParser = null;

    protected ?ParameterParser $fieldsParser = null;

    protected ?ParameterParser $appendsParser = null;

    protected ?FilterValueTransformer $filterTransformer = null;

    public function __construct(
        protected ?Request $request = null,
        ?QueryWizardConfig $config = null
    ) {
        $this->config = $config ?? new QueryWizardConfig;
    }

    protected function getIncludesParser(): ParameterParser
    {
        return $this->includesParser ??= new ParameterParser($this->config->getIncludesSeparator());
    }

    protected function getSortsParser(): ParameterParser
    {
        return $this->sortsParser ??= new ParameterParser($this->config->getSortsSeparator());
    }

    protected function getFieldsParser(): ParameterParser
    {
        return $this->fieldsParser ??= new ParameterParser($this->config->getFieldsSeparator());
    }

    protected function getAppendsParser(): ParameterParser
    {
        return $this->appendsParser ??= new ParameterParser($this->config->getAppendsSeparator());
    }

    protected function getFilterTransformer(): FilterValueTransformer
    {
        return $this->filterTransformer ??= new FilterValueTransformer($this->config->getFiltersSeparator());
    }

    /**
     * Convert a name to snake_case if the config option is enabled.
     */
    protected function convertName(string $name): string
    {
        if (! $this->config->shouldConvertParametersToSnakeCase()) {
            return $name;
        }

        return NameConverter::toSnakeCase($name);
    }

    /**
     * Convert a dotted path to snake_case if the config option is enabled.
     */
    protected function convertPath(string $path): string
    {
        if (! $this->config->shouldConvertParametersToSnakeCase()) {
            return $path;
        }

        return NameConverter::pathToSnakeCase($path);
    }

    /**
     * Convert a list collection (includes, etc.) paths to snake_case.
     *
     * @param  Collection<int, string>  $collection
     * @return Collection<int, string>
     */
    protected function convertListCollection(Collection $collection): Collection
    {
        if (! $this->config->shouldConvertParametersToSnakeCase()) {
            return $collection;
        }

        return $collection->map(fn (string $item) => $this->convertPath($item))->values();
    }

    /**
     * Convert a sorts collection field names to snake_case.
     *
     * @param  Collection<int, Sort>  $collection
     * @return Collection<int, Sort>
     */
    protected function convertSortsCollection(Collection $collection): Collection
    {
        if (! $this->config->shouldConvertParametersToSnakeCase()) {
            return $collection;
        }

        return $collection->map(function (Sort $sort) {
            $convertedField = $this->convertPath($sort->getField());
            if ($convertedField === $sort->getField()) {
                return $sort;
            }

            return new Sort(
                $sort->getDirection() === 'desc' ? '-'.$convertedField : $convertedField
            );
        })->values();
    }

    /**
     * Convert a fields/appends collection keys and values to snake_case.
     *
     * @param  Collection<string, array<string>>  $collection
     * @return Collection<string, array<string>>
     */
    protected function convertFieldsCollection(Collection $collection): Collection
    {
        if (! $this->config->shouldConvertParametersToSnakeCase()) {
            return $collection;
        }

        return $collection->mapWithKeys(function (array $fields, string $key) {
            $convertedKey = $this->convertPath($key);
            $convertedFields = array_map(fn (string $field) => $this->convertName($field), $fields);

            return [$convertedKey => $convertedFields];
        });
    }

    /**
     * Convert a filters collection keys to snake_case (recursively for nested).
     *
     * @param  Collection<string, mixed>  $collection
     * @return Collection<string, mixed>
     */
    protected function convertFiltersCollection(Collection $collection): Collection
    {
        if (! $this->config->shouldConvertParametersToSnakeCase()) {
            return $collection;
        }

        return $collection->mapWithKeys(function (mixed $value, string $key) {
            $convertedKey = $this->convertPath($key);

            if (is_array($value)) {
                $value = $this->convertFiltersArray($value);
            }

            return [$convertedKey => $value];
        });
    }

    /**
     * Convert filter array keys recursively to snake_case.
     *
     * @param  array<string|int, mixed>  $array
     * @return array<string|int, mixed>
     */
    protected function convertFiltersArray(array $array): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $convertedKey = is_string($key) ? $this->convertPath($key) : $key;

            if (is_array($value)) {
                $value = $this->convertFiltersArray($value);
            }

            $result[$convertedKey] = $value;
        }

        return $result;
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
        $rawValue = $fieldsParameterName ? $this->getRequestData($fieldsParameterName) : null;

        $parsed = $this->getFieldsParser()->parseFields($rawValue);
        $this->fields = $this->convertFieldsCollection($parsed);

        /** @var Collection<string, array<string>> */
        return $this->fields;
    }

    /**
     * @return Collection<string, array<string>>
     */
    public function getAppends(): Collection
    {
        if ($this->appends instanceof Collection) {
            return $this->appends;
        }

        $appendsParameterName = $this->config->getAppendsParameterName();
        $rawValue = $appendsParameterName ? $this->getRequestData($appendsParameterName) : null;

        $parsed = $this->getAppendsParser()->parseFields($rawValue);
        $this->appends = $this->convertFieldsCollection($parsed);

        /** @var Collection<string, array<string>> */
        return $this->appends;
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
        $rawValue = $filtersParameterName ? $this->getRequestData($filtersParameterName) : null;

        try {
            $this->setFiltersParameter($rawValue);
        } catch (\InvalidArgumentException $exception) {
            throw InvalidFilterQuery::invalidFormat($exception->getMessage());
        }

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
        $rawValue = $includesParameterName ? $this->getRequestData($includesParameterName) : null;

        $parsed = $this->getIncludesParser()->parseList($rawValue);
        $this->includes = $this->convertListCollection($parsed);

        /** @var Collection<int, string> */
        return $this->includes;
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
        $rawValue = $sortsParameterName ? $this->getRequestData($sortsParameterName) : null;

        $parsed = $this->getSortsParser()->parseSorts($rawValue);
        $this->sorts = $this->convertSortsCollection($parsed);

        /** @var Collection<int, Sort> */
        return $this->sorts;
    }

    /**
     * Set fields parameter manually (for testing or programmatic use).
     */
    public function setFieldsParameter(mixed $fieldsParameter): static
    {
        $parsed = $this->getFieldsParser()->parseFields($fieldsParameter);
        $this->fields = $this->convertFieldsCollection($parsed);

        return $this;
    }

    /**
     * Set appends parameter manually (for testing or programmatic use).
     */
    public function setAppendsParameter(mixed $appendsParameter): static
    {
        $parsed = $this->getAppendsParser()->parseFields($appendsParameter);
        $this->appends = $this->convertFieldsCollection($parsed);

        return $this;
    }

    /**
     * Set filters parameter manually (for testing or programmatic use).
     */
    public function setFiltersParameter(mixed $filtersParameter): static
    {
        if (is_string($filtersParameter)) {
            throw new \InvalidArgumentException(
                'Filters parameter must be an array or null, string given. '
                .'Use ?filter[name]=value format in the query string.'
            );
        }

        $parsed = collect($filtersParameter)->map(function ($value) {
            return $this->getFilterTransformer()->transform($value);
        });
        $this->filters = $this->convertFiltersCollection($parsed);

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
        if ($value !== self::missing()) {
            return $value;
        }

        return null;
    }

    /**
     * Check whether a filter key exists in the request payload.
     *
     * Unlike getFilterValue(), this distinguishes between:
     * - missing key
     * - existing key with null value
     */
    public function hasFilter(string $name): bool
    {
        $filters = $this->getFilters();

        if ($filters->has($name)) {
            return true;
        }

        return $this->getNestedFilterValue($filters->all(), $name) !== self::missing();
    }

    /**
     * Recursively look for a filter value in nested array structure.
     *
     * Returns the sentinel missing() object when the key is not found,
     * to distinguish "not found" from "found with null value".
     *
     * @param  array<string, mixed>  $data
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
                    if ($nested !== self::missing()) {
                        return $nested;
                    }
                }
            }
        }

        return self::missing();
    }

    /**
     * Set includes parameter manually (for testing or programmatic use).
     */
    public function setIncludesParameter(mixed $includesParameter): static
    {
        $parsed = $this->getIncludesParser()->parseList($includesParameter);
        $this->includes = $this->convertListCollection($parsed);

        return $this;
    }

    /**
     * Set sorts parameter manually (for testing or programmatic use).
     */
    public function setSortsParameter(mixed $sortsParameter): static
    {
        $parsed = $this->getSortsParser()->parseSorts($sortsParameter);
        $this->sorts = $this->convertSortsCollection($parsed);

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
        $this->includesParser = null;
        $this->sortsParser = null;
        $this->fieldsParser = null;
        $this->appendsParser = null;
        $this->filterTransformer = null;

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

    /**
     * Get data from request (query string or body based on config).
     */
    protected function getRequestData(?string $key = null, mixed $default = null): mixed
    {
        if ($this->request === null) {
            return $default;
        }

        if ($this->config->shouldUseRequestBody()) {
            return $this->request->input($key, $default);
        }

        return $this->request->query($key, $default);
    }
}
