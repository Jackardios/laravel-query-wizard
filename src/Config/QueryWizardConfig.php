<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Config;

use InvalidArgumentException;

/**
 * Centralized configuration access for Query Wizard.
 *
 * Getters read `config('query-wizard')` on each call, unless the instance is a
 * snapshot (see snapshot()), which keeps the values it was taken with. A key
 * missing from the configuration takes the package default, and an invalid
 * value throws InvalidArgumentException naming the key.
 */
final class QueryWizardConfig
{
    private const CONFIG_PREFIX = 'query-wizard';

    private const VALID_REQUEST_DATA_SOURCES = ['query_string', 'body'];

    private const MAX_SEPARATOR_LENGTH = 10;

    /**
     * Package defaults, the same as config/query-wizard.php.
     */
    private const DEFAULTS = [
        'parameters' => [
            'includes' => 'include',
            'filters' => 'filter',
            'sorts' => 'sort',
            'fields' => 'fields',
            'appends' => 'append',
        ],
        'count_suffix' => 'Count',
        'exists_suffix' => 'Exists',
        'disable_invalid_filter_query_exception' => false,
        'disable_invalid_sort_query_exception' => false,
        'disable_invalid_include_query_exception' => false,
        'disable_invalid_field_query_exception' => false,
        'disable_invalid_append_query_exception' => false,
        'request_data_source' => 'query_string',
        'apply_filter_default_on_null' => false,
        'array_value_separator' => ',',
        'naming' => [
            'convert_parameters_to_snake_case' => false,
        ],
        'separators' => [],
        'fields' => [
            'use_allowed_as_default' => false,
        ],
        'limits' => [
            'max_includes_count' => 10,
            'max_include_depth' => 3,
            'max_filters_count' => 20,
            'max_appends_count' => 20,
            'max_append_depth' => 3,
            'max_sorts_count' => 5,
        ],
    ];

    /**
     * @param  array<array-key, mixed>|null  $values  Fixed configuration values, or null to read config() live
     */
    public function __construct(private readonly ?array $values = null) {}

    /**
     * A copy that keeps the current configuration values, so a build reads
     * config() once however many settings it uses.
     *
     * @api
     */
    public function snapshot(): self
    {
        return new self($this->values());
    }

    public function getCountSuffix(): string
    {
        return $this->getIncludeAliasSuffix('count_suffix', 'Count');
    }

    public function getExistsSuffix(): string
    {
        return $this->getIncludeAliasSuffix('exists_suffix', 'Exists');
    }

    /**
     * Resolve the alias suffix an include type appends by default.
     *
     * The config key is provided by the include itself (e.g. `count_suffix`), so
     * this stays generic instead of hard-coding one accessor per include type.
     */
    public function getIncludeAliasSuffix(string $configKey, ?string $default = null): string
    {
        [$found, $value] = $this->find($configKey);

        // An app that sets the suffix to null is blanking it on purpose, and
        // must keep getting '' rather than silently having the default restored.
        if (! $found) {
            return (string) $default;
        }

        if ($value !== null && ! is_scalar($value)) {
            throw self::invalid($configKey, 'must be a string');
        }

        return (string) $value;
    }

    public function getArrayValueSeparator(): string
    {
        return $this->separatorAt('array_value_separator', $this->get('array_value_separator'));
    }

    public function getSeparator(string $type): string
    {
        $separators = $this->get('separators');

        if ($separators === null) {
            return $this->getArrayValueSeparator();
        }

        if (! is_array($separators)) {
            throw self::invalid('separators', 'must be an array of separators keyed by parameter type');
        }

        $separator = $separators[$type] ?? null;

        return $separator === null
            ? $this->getArrayValueSeparator()
            : $this->separatorAt("separators.{$type}", $separator);
    }

    public function getIncludesSeparator(): string
    {
        return $this->getSeparator('includes');
    }

    public function getSortsSeparator(): string
    {
        return $this->getSeparator('sorts');
    }

    public function getFiltersSeparator(): string
    {
        return $this->getSeparator('filters');
    }

    public function getFieldsSeparator(): string
    {
        return $this->getSeparator('fields');
    }

    public function getAppendsSeparator(): string
    {
        return $this->getSeparator('appends');
    }

    public function shouldConvertParametersToSnakeCase(): bool
    {
        return $this->flag('naming.convert_parameters_to_snake_case');
    }

    public function getFieldsParameterName(): ?string
    {
        return $this->parameterName('fields');
    }

    public function getAppendsParameterName(): ?string
    {
        return $this->parameterName('appends');
    }

    public function getFiltersParameterName(): ?string
    {
        return $this->parameterName('filters');
    }

    public function getIncludesParameterName(): ?string
    {
        return $this->parameterName('includes');
    }

    public function getSortsParameterName(): ?string
    {
        return $this->parameterName('sorts');
    }

    /**
     * @return 'query_string'|'body'
     */
    public function getRequestDataSource(): string
    {
        return $this->oneOf('request_data_source', self::VALID_REQUEST_DATA_SOURCES);
    }

    public function shouldUseRequestBody(): bool
    {
        return $this->getRequestDataSource() === 'body';
    }

    public function shouldApplyFilterDefaultOnNull(): bool
    {
        return $this->flag('apply_filter_default_on_null');
    }

    public function isInvalidFilterQueryExceptionDisabled(): bool
    {
        return $this->flag('disable_invalid_filter_query_exception');
    }

    public function isInvalidSortQueryExceptionDisabled(): bool
    {
        return $this->flag('disable_invalid_sort_query_exception');
    }

    public function isInvalidIncludeQueryExceptionDisabled(): bool
    {
        return $this->flag('disable_invalid_include_query_exception');
    }

    public function isInvalidFieldQueryExceptionDisabled(): bool
    {
        return $this->flag('disable_invalid_field_query_exception');
    }

    public function isInvalidAppendQueryExceptionDisabled(): bool
    {
        return $this->flag('disable_invalid_append_query_exception');
    }

    public function getMaxIncludeDepth(): ?int
    {
        return $this->limit('max_include_depth');
    }

    public function getMaxIncludesCount(): ?int
    {
        return $this->limit('max_includes_count');
    }

    public function getMaxFiltersCount(): ?int
    {
        return $this->limit('max_filters_count');
    }

    public function getMaxSortsCount(): ?int
    {
        return $this->limit('max_sorts_count');
    }

    public function getMaxAppendsCount(): ?int
    {
        return $this->limit('max_appends_count');
    }

    public function getMaxAppendDepth(): ?int
    {
        return $this->limit('max_append_depth');
    }

    public function shouldUseAllowedFieldsAsDefault(): bool
    {
        return $this->flag('fields.use_allowed_as_default');
    }

    /**
     * @return array<array-key, mixed>
     */
    private function values(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }

        $values = config(self::CONFIG_PREFIX);

        return is_array($values) ? $values : [];
    }

    /**
     * The configured value, or the package default when the key is missing.
     */
    private function get(string $key): mixed
    {
        [$found, $value] = $this->find($key);

        return $found ? $value : self::defaultFor($key);
    }

    /**
     * @return array{bool, mixed} Whether the key is set (even to null), and its value
     */
    private function find(string $key): array
    {
        $value = $this->values();

        foreach (explode('.', $key) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return [false, null];
            }

            $value = $value[$segment];
        }

        return [true, $value];
    }

    private static function defaultFor(string $key): mixed
    {
        $value = self::DEFAULTS;

        foreach (explode('.', $key) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * A boolean, or a string or int PHP reads as one ('true', 'off', '1', ...).
     */
    private function flag(string $key): bool
    {
        $value = $this->get($key);
        $flag = is_array($value) || is_object($value) ? null : filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if ($flag !== null) {
            return $flag;
        }

        throw self::invalid($key, 'must be a boolean');
    }

    private function limit(string $name): ?int
    {
        $key = "limits.{$name}";
        $value = $this->get($key);

        if ($value === null) {
            return null;
        }

        if (is_string($value) && preg_match('/^\d+\z/', $value) === 1) {
            $value = filter_var($value, FILTER_VALIDATE_INT);
        }

        if (is_int($value) && $value > 0) {
            return $value;
        }

        throw self::invalid($key, 'must be a positive integer, or null to disable the limit');
    }

    private function parameterName(string $group): ?string
    {
        $key = "parameters.{$group}";
        $value = $this->get($key);

        if ($value === null || (is_string($value) && $value !== '')) {
            return $value;
        }

        throw self::invalid($key, 'must be a non-empty string, or null to disable the parameter');
    }

    private function separatorAt(string $key, mixed $value): string
    {
        if (is_string($value) && $value !== '' && mb_strlen($value) <= self::MAX_SEPARATOR_LENGTH) {
            return $value;
        }

        throw self::invalid($key, 'must be a non-empty string of at most '.self::MAX_SEPARATOR_LENGTH.' characters');
    }

    /**
     * @template T of string
     *
     * @param  list<T>  $allowed
     * @return T
     */
    private function oneOf(string $key, array $allowed): string
    {
        $value = $this->get($key);

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            foreach ($allowed as $option) {
                if ($normalized === $option) {
                    return $option;
                }
            }
        }

        throw self::invalid($key, 'must be one of: '.implode(', ', $allowed));
    }

    private static function invalid(string $key, string $requirement): InvalidArgumentException
    {
        return new InvalidArgumentException('Config `'.self::CONFIG_PREFIX.".{$key}` {$requirement}.");
    }
}
