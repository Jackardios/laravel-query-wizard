<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\Exceptions\InvalidAppendQuery;
use Jackardios\QueryWizard\Exceptions\InvalidFieldQuery;
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;
use Jackardios\QueryWizard\Exceptions\InvalidRequestBody;
use Jackardios\QueryWizard\Exceptions\MaxFiltersCountExceeded;
use Jackardios\QueryWizard\Support\FilterValueTransformer;
use Jackardios\QueryWizard\Support\NameConverter;
use Jackardios\QueryWizard\Support\ParameterParser;
use Jackardios\QueryWizard\Values\Sort;
use JsonException;
use stdClass;

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
        return self::$missing ??= new stdClass;
    }

    /** @var Collection<string, mixed>|null */
    protected ?Collection $filters = null;

    /**
     * Filter values as sent, without separator splitting.
     *
     * @var Collection<string, mixed>|null
     */
    protected ?Collection $unsplitFilters = null;

    /** @var array<string, Collection<int, Sort>|Collection<int, string>|Collection<string, array<string>>> */
    protected array $simpleParameterCache = [];

    /** @var array<string, bool> */
    protected array $simpleParameterPresenceCache = [];

    protected int $stateVersion = 0;

    private static int $lastStateVersion = 0;

    protected QueryWizardConfig $config;

    /** @var array<string, ParameterParser> */
    protected array $parsers = [];

    protected ?FilterValueTransformer $filterTransformer = null;

    private ?QueryWizardConfig $settings = null;

    /** @var array<string, mixed>|null */
    protected ?array $strictBodyPayload = null;

    public function __construct(
        protected ?Request $request = null,
        ?QueryWizardConfig $config = null
    ) {
        $this->config = $config ?? new QueryWizardConfig;
        $this->bumpStateVersion();
    }

    /**
     * The configuration as of the first read since the manager was created or reset.
     */
    private function settings(): QueryWizardConfig
    {
        return $this->settings ??= $this->config->snapshot();
    }

    protected function getParser(string $type): ParameterParser
    {
        return $this->parsers[$type] ??= new ParameterParser(
            match ($type) {
                'includes' => $this->settings()->getIncludesSeparator(),
                'sorts' => $this->settings()->getSortsSeparator(),
                'fields' => $this->settings()->getFieldsSeparator(),
                'appends' => $this->settings()->getAppendsSeparator(),
                default => throw new \InvalidArgumentException("Unsupported parser type [{$type}]."),
            }
        );
    }

    protected function getFilterTransformer(): FilterValueTransformer
    {
        return $this->filterTransformer ??= new FilterValueTransformer($this->settings()->getFiltersSeparator());
    }

    /**
     * Convert a name to snake_case if the config option is enabled.
     */
    protected function convertName(string $name): string
    {
        if (! $this->settings()->shouldConvertParametersToSnakeCase()) {
            return $name;
        }

        return NameConverter::toSnakeCase($name);
    }

    /**
     * Convert a dotted path to snake_case if the config option is enabled.
     */
    protected function convertPath(string $path): string
    {
        if (! $this->settings()->shouldConvertParametersToSnakeCase()) {
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
        if (! $this->settings()->shouldConvertParametersToSnakeCase()) {
            return $collection;
        }

        return $collection->map(fn (string $item) => $this->convertPath($item))->unique()->values();
    }

    /**
     * Convert a sorts collection field names to snake_case.
     *
     * @param  Collection<int, Sort>  $collection
     * @return Collection<int, Sort>
     */
    protected function convertSortsCollection(Collection $collection): Collection
    {
        if (! $this->settings()->shouldConvertParametersToSnakeCase()) {
            return $collection;
        }

        $sorts = [];

        foreach ($collection as $sort) {
            $field = $this->convertPath($sort->getField());
            $sorts[$field] ??= $field === $sort->getField() ? $sort : new Sort($field, $sort->getSortDirection());
        }

        return collect(array_values($sorts));
    }

    /**
     * Convert a fields/appends collection keys and values to snake_case.
     *
     * Keys (relation paths) are converted using pathToSnakeCase() (preserves dots).
     * Values (field names) are converted using toSnakeCase() (simple conversion).
     * Keys and names that convert to the same snake_case name are merged.
     *
     * @param  Collection<string, array<string>>  $collection
     * @return Collection<string, array<string>>
     */
    protected function convertFieldsCollection(Collection $collection): Collection
    {
        if (! $this->settings()->shouldConvertParametersToSnakeCase()) {
            return $collection;
        }

        /** @var array<string, array<string>> $converted */
        $converted = [];

        foreach ($collection as $key => $fields) {
            $convertedKey = $this->convertPath((string) $key);
            $convertedFields = array_map(fn (string $field) => $this->convertName($field), $fields);
            $converted[$convertedKey] = array_values(array_unique([...$converted[$convertedKey] ?? [], ...$convertedFields]));
        }

        return collect($converted);
    }

    /**
     * Convert top-level filter keys to snake_case.
     *
     * Values are left as sent: keys inside them are converted one level at a
     * time while a filter name is looked up (see getNestedFilterValue()), so
     * a filter's own payload keys, such as a range's `minPrice`, stay as sent.
     *
     * @param  Collection<string, mixed>  $collection
     * @return Collection<string, mixed>
     */
    protected function convertFiltersCollection(Collection $collection): Collection
    {
        if (! $this->settings()->shouldConvertParametersToSnakeCase()) {
            return $collection;
        }

        /** @var Collection<string, mixed> */
        return new Collection($this->convertFilterKeys($collection->all()));
    }

    /**
     * Convert one level of filter keys to snake_case. When two keys convert to
     * the same name, the key that is already snake_case wins.
     *
     * @param  array<string|int, mixed>  $filters
     * @return array<string|int, mixed>
     */
    protected function convertFilterKeys(array $filters): array
    {
        if (! $this->settings()->shouldConvertParametersToSnakeCase()) {
            return $filters;
        }

        $result = [];

        foreach ($filters as $key => $value) {
            $convertedKey = is_string($key) ? $this->convertPath($key) : $key;

            if (! array_key_exists($convertedKey, $result) || $convertedKey === $key) {
                $result[$convertedKey] = $value;
            }
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
     * Monotonic revision of the manager state.
     *
     * Increases when request-bound or manually injected parameters change, and
     * is unique across managers, so no two states share a version.
     */
    public function getStateVersion(): int
    {
        return $this->stateVersion;
    }

    /**
     * @return Collection<string, array<string>>
     */
    public function getFields(): Collection
    {
        /** @var Collection<string, array<string>> */
        return $this->readSimpleParameter('fields');
    }

    /**
     * @return Collection<string, array<string>>
     */
    public function getAppends(): Collection
    {
        /** @var Collection<string, array<string>> */
        return $this->readSimpleParameter('appends');
    }

    /**
     * Filter values keyed by name. With snake_case conversion on, only the
     * top-level keys are converted; keys inside values are kept as sent.
     *
     * @return Collection<string, mixed>
     */
    public function getFilters(): Collection
    {
        $this->loadFilters();

        /** @var Collection<string, mixed> */
        return $this->filters;
    }

    /**
     * Filter values as sent, without separator splitting.
     *
     * @return Collection<string, mixed>
     */
    public function getUnsplitFilters(): Collection
    {
        $this->loadFilters();

        /** @var Collection<string, mixed> */
        return $this->unsplitFilters;
    }

    protected function loadFilters(): void
    {
        if ($this->filters instanceof Collection && $this->unsplitFilters instanceof Collection) {
            return;
        }

        $filtersParameterName = $this->settings()->getFiltersParameterName();
        $rawValue = $filtersParameterName ? $this->getRequestData($filtersParameterName) : null;

        $limit = $this->settings()->getMaxFiltersCount();
        if ($limit !== null && is_array($rawValue) && count($rawValue) > $limit) {
            throw new MaxFiltersCountExceeded(count($rawValue), $limit);
        }

        try {
            $this->filters = $this->parseFiltersParameter($rawValue);
            $this->unsplitFilters = $this->parseFiltersParameter($rawValue, false);
        } catch (\InvalidArgumentException $exception) {
            throw InvalidFilterQuery::invalidFormat($exception->getMessage());
        }
    }

    /**
     * @return Collection<int, string>
     */
    public function getIncludes(): Collection
    {
        /** @var Collection<int, string> */
        return $this->readSimpleParameter('includes');
    }

    /**
     * @return Collection<int, Sort>
     */
    public function getSorts(): Collection
    {
        /** @var Collection<int, Sort> */
        return $this->readSimpleParameter('sorts');
    }

    /**
     * Check whether a top-level simple parameter is present in the raw request payload.
     *
     * Presence is tracked separately from parsed emptiness so empty parameters like
     * ?include= or ?fields[user]= remain distinguishable from complete absence.
     */
    public function hasSimpleParameter(string $type): bool
    {
        if (array_key_exists($type, $this->simpleParameterPresenceCache)) {
            return $this->simpleParameterPresenceCache[$type];
        }

        $parameterName = $this->getParameterName($type);

        return $this->simpleParameterPresenceCache[$type] = $parameterName !== null
            && $this->hasRequestData($parameterName);
    }

    /**
     * Set fields parameter manually (for testing or programmatic use).
     */
    public function setFieldsParameter(mixed $fieldsParameter): static
    {
        return $this->setSimpleParameter('fields', $fieldsParameter);
    }

    /**
     * Set appends parameter manually (for testing or programmatic use).
     */
    public function setAppendsParameter(mixed $appendsParameter): static
    {
        return $this->setSimpleParameter('appends', $appendsParameter);
    }

    /**
     * Set filters parameter manually (for testing or programmatic use).
     */
    public function setFiltersParameter(mixed $filtersParameter): static
    {
        $this->filters = $this->parseFiltersParameter($filtersParameter);
        $this->unsplitFilters = $this->parseFiltersParameter($filtersParameter, false);
        $this->bumpStateVersion();

        return $this;
    }

    /**
     * Get filter value by dot notation name, supporting nested array access.
     * For example, getFilterValue('some.foo.bar') will look for:
     * 1. Direct key 'some.foo.bar'
     * 2. Nested path: filters['some']['foo']['bar']
     * 3. Partial paths: filters['some.foo']['bar'], filters['some']['foo.bar']
     *
     * With $splitValues = false, string values are returned as sent instead of
     * being split by the filters separator.
     *
     * @return mixed The filter value or null if not found
     */
    public function getFilterValue(string $name, bool $splitValues = true): mixed
    {
        $filters = $splitValues ? $this->getFilters() : $this->getUnsplitFilters();

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
     * Uses progressive key building (O(n)) instead of repeated implode/array_slice (O(n²)).
     * Keys of each nested level are converted to snake_case before they are
     * matched, when that option is on; `$data` itself is matched as given.
     *
     * @param  array<string, mixed>  $data
     */
    protected function getNestedFilterValue(array $data, string $name): mixed
    {
        if (array_key_exists($name, $data)) {
            return $data[$name];
        }

        $parts = explode('.', $name);
        $partsCount = count($parts);
        $key = '';

        for ($i = 0; $i < $partsCount; $i++) {
            $key = $i === 0 ? $parts[0] : $key.'.'.$parts[$i];

            if (array_key_exists($key, $data)) {
                $value = $data[$key];

                if ($i === $partsCount - 1) {
                    return $value;
                }

                if (is_array($value)) {
                    $remainder = implode('.', array_slice($parts, $i + 1));
                    /** @var array<string, mixed> $nestedData */
                    $nestedData = $this->convertFilterKeys($value);
                    $nested = $this->getNestedFilterValue($nestedData, $remainder);
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
        return $this->setSimpleParameter('includes', $includesParameter);
    }

    /**
     * Set sorts parameter manually (for testing or programmatic use).
     */
    public function setSortsParameter(mixed $sortsParameter): static
    {
        return $this->setSimpleParameter('sorts', $sortsParameter);
    }

    /**
     * Reset all cached parameters.
     * Useful when reusing the same instance for multiple requests (e.g., in Laravel Octane).
     */
    public function reset(): static
    {
        $this->filters = null;
        $this->unsplitFilters = null;
        $this->simpleParameterCache = [];
        $this->simpleParameterPresenceCache = [];
        $this->parsers = [];
        $this->filterTransformer = null;
        $this->strictBodyPayload = null;
        $this->settings = null;
        $this->bumpStateVersion();

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

        if ($this->settings()->shouldUseRequestBody()) {
            return data_get($this->getStrictBodyPayload(), $key, $default);
        }

        return data_get($this->request->query(), $key, $default);
    }

    /**
     * Check if the raw request payload contains the given key.
     */
    protected function hasRequestData(string $key): bool
    {
        if ($this->request === null) {
            return false;
        }

        if ($this->settings()->shouldUseRequestBody()) {
            return Arr::has($this->getStrictBodyPayload(), $key);
        }

        return Arr::has($this->request->query(), $key);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getStrictBodyPayload(): array
    {
        if ($this->strictBodyPayload !== null) {
            return $this->strictBodyPayload;
        }

        if ($this->request === null) {
            return $this->strictBodyPayload = [];
        }

        if (! $this->request->isJson()) {
            return $this->strictBodyPayload = $this->request->request->all();
        }

        /** @var array<array-key, mixed> $payload */
        $payload = $this->request->json()->all();

        if ($payload === [] || array_is_list($payload)) {
            $this->assertJsonObjectBody($this->request->getContent());
        }

        /** @var array<string, mixed> $payload */
        return $this->strictBodyPayload = $payload;
    }

    /**
     * A JSON body that is not blank must be a JSON object.
     *
     * @throws InvalidRequestBody
     */
    protected function assertJsonObjectBody(string $content): void
    {
        if (trim($content) === '') {
            return;
        }

        try {
            $decoded = json_decode($content, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw InvalidRequestBody::malformedJson($exception->getMessage());
        }

        if (! $decoded instanceof stdClass) {
            throw InvalidRequestBody::notAnObject();
        }
    }

    /**
     * @return Collection<int, string>|Collection<int, Sort>|Collection<string, array<string>>
     */
    protected function readSimpleParameter(string $type): Collection
    {
        if (isset($this->simpleParameterCache[$type])) {
            return $this->simpleParameterCache[$type];
        }

        $parameterName = $this->getParameterName($type);
        $isPresent = $parameterName !== null && $this->hasRequestData($parameterName);
        $this->simpleParameterPresenceCache[$type] = $isPresent;
        $rawValue = $isPresent ? $this->getRequestData($parameterName) : null;

        return $this->simpleParameterCache[$type] = $this->parseSimpleParameter($type, $rawValue);
    }

    protected function setSimpleParameter(string $type, mixed $rawValue): static
    {
        $this->simpleParameterPresenceCache[$type] = true;
        $this->simpleParameterCache[$type] = $this->parseSimpleParameter($type, $rawValue);
        $this->bumpStateVersion();

        return $this;
    }

    protected function getParameterName(string $type): ?string
    {
        return match ($type) {
            'fields' => $this->settings()->getFieldsParameterName(),
            'appends' => $this->settings()->getAppendsParameterName(),
            'includes' => $this->settings()->getIncludesParameterName(),
            'sorts' => $this->settings()->getSortsParameterName(),
            'filters' => $this->settings()->getFiltersParameterName(),
            default => throw new \InvalidArgumentException("Unsupported parameter type [{$type}]."),
        };
    }

    /**
     * @return Collection<string, array<string>>
     */
    private function parseFieldsParameter(string $type, mixed $rawValue): Collection
    {
        try {
            return $this->getParser($type)->parseFields($rawValue);
        } catch (\InvalidArgumentException $exception) {
            throw $type === 'fields'
                ? InvalidFieldQuery::invalidFormat($exception->getMessage())
                : InvalidAppendQuery::invalidFormat($exception->getMessage());
        }
    }

    /**
     * @return Collection<int, string>|Collection<int, Sort>|Collection<string, array<string>>
     */
    protected function parseSimpleParameter(string $type, mixed $rawValue): Collection
    {
        return match ($type) {
            'fields', 'appends' => $this->convertFieldsCollection($this->parseFieldsParameter($type, $rawValue)),
            'includes' => $this->convertListCollection(
                $this->getParser($type)->parseList($rawValue)
            ),
            'sorts' => $this->convertSortsCollection(
                $this->getParser($type)->parseSorts($rawValue)
            ),
            default => throw new \InvalidArgumentException("Unsupported parameter type [{$type}]."),
        };
    }

    /**
     * A blank string, as in ?filter=, means no filters.
     *
     * @return Collection<string, mixed>
     */
    protected function parseFiltersParameter(mixed $filtersParameter, bool $splitValues = true): Collection
    {
        if (is_string($filtersParameter) && trim($filtersParameter) !== '') {
            throw new \InvalidArgumentException(
                'Filters parameter must be an array or null, string given. '
                .'Use ?filter[name]=value format in the query string.'
            );
        }

        $filtersArray = is_array($filtersParameter) ? $filtersParameter : [];
        $parsed = collect($filtersArray)->map(function ($value) use ($splitValues) {
            return $this->getFilterTransformer()->transform($value, $splitValues);
        });

        /** @var Collection<string, mixed> */
        return $this->convertFiltersCollection($parsed);
    }

    protected function bumpStateVersion(): void
    {
        $this->stateVersion = ++self::$lastStateVersion;
    }
}
