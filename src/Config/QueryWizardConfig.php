<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Config;

/**
 * Centralized configuration access for Query Wizard.
 */
final class QueryWizardConfig
{
    private const CONFIG_PREFIX = 'query-wizard';

    private const VALID_REQUEST_DATA_SOURCES = ['query_string', 'body'];

    public function getCountSuffix(): string
    {
        return (string) config(self::CONFIG_PREFIX.'.count_suffix', 'Count');
    }

    public function getExistsSuffix(): string
    {
        return (string) config(self::CONFIG_PREFIX.'.exists_suffix', 'Exists');
    }

    public function getArrayValueSeparator(): string
    {
        return (string) config(self::CONFIG_PREFIX.'.array_value_separator', ',');
    }

    public function getSeparator(string $type): string
    {
        $separators = config(self::CONFIG_PREFIX.'.separators', []);

        if (is_array($separators) && isset($separators[$type])) {
            $separator = (string) $separators[$type];
            if ($separator !== '' && mb_strlen($separator) <= 10) {
                return $separator;
            }
        }

        return $this->getArrayValueSeparator();
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
        return (bool) config(self::CONFIG_PREFIX.'.naming.convert_parameters_to_snake_case', false);
    }

    public function getRelationSelectMode(): string
    {
        $mode = strtolower((string) config(self::CONFIG_PREFIX.'.optimizations.relation_select_mode', 'safe'));

        return in_array($mode, ['off', 'safe'], true) ? $mode : 'safe';
    }

    public function isSafeRelationSelectEnabled(): bool
    {
        return $this->getRelationSelectMode() === 'safe';
    }

    public function getFieldsParameterName(): ?string
    {
        return config(self::CONFIG_PREFIX.'.parameters.fields');
    }

    public function getAppendsParameterName(): ?string
    {
        return config(self::CONFIG_PREFIX.'.parameters.appends');
    }

    public function getFiltersParameterName(): ?string
    {
        return config(self::CONFIG_PREFIX.'.parameters.filters');
    }

    public function getIncludesParameterName(): ?string
    {
        return config(self::CONFIG_PREFIX.'.parameters.includes');
    }

    public function getSortsParameterName(): ?string
    {
        return config(self::CONFIG_PREFIX.'.parameters.sorts');
    }

    public function getRequestDataSource(): string
    {
        $value = config(self::CONFIG_PREFIX.'.request_data_source', 'query_string');
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, self::VALID_REQUEST_DATA_SOURCES, true)
            ? $normalized
            : 'query_string';
    }

    public function shouldUseRequestBody(): bool
    {
        return $this->getRequestDataSource() === 'body';
    }

    public function shouldApplyFilterDefaultOnNull(): bool
    {
        return (bool) config(self::CONFIG_PREFIX.'.apply_filter_default_on_null', false);
    }

    public function isInvalidFilterQueryExceptionDisabled(): bool
    {
        return (bool) config(self::CONFIG_PREFIX.'.disable_invalid_filter_query_exception', false);
    }

    public function isInvalidSortQueryExceptionDisabled(): bool
    {
        return (bool) config(self::CONFIG_PREFIX.'.disable_invalid_sort_query_exception', false);
    }

    public function isInvalidIncludeQueryExceptionDisabled(): bool
    {
        return (bool) config(self::CONFIG_PREFIX.'.disable_invalid_include_query_exception', false);
    }

    public function isInvalidFieldQueryExceptionDisabled(): bool
    {
        return (bool) config(self::CONFIG_PREFIX.'.disable_invalid_field_query_exception', false);
    }

    public function isInvalidAppendQueryExceptionDisabled(): bool
    {
        return (bool) config(self::CONFIG_PREFIX.'.disable_invalid_append_query_exception', false);
    }

    public function getMaxIncludeDepth(): ?int
    {
        return $this->normalizeLimit(config(self::CONFIG_PREFIX.'.limits.max_include_depth'));
    }

    public function getMaxIncludesCount(): ?int
    {
        return $this->normalizeLimit(config(self::CONFIG_PREFIX.'.limits.max_includes_count'));
    }

    public function getMaxFiltersCount(): ?int
    {
        return $this->normalizeLimit(config(self::CONFIG_PREFIX.'.limits.max_filters_count'));
    }

    public function getMaxSortsCount(): ?int
    {
        return $this->normalizeLimit(config(self::CONFIG_PREFIX.'.limits.max_sorts_count'));
    }

    public function getMaxAppendsCount(): ?int
    {
        return $this->normalizeLimit(config(self::CONFIG_PREFIX.'.limits.max_appends_count'));
    }

    public function getMaxAppendDepth(): ?int
    {
        return $this->normalizeLimit(config(self::CONFIG_PREFIX.'.limits.max_append_depth'));
    }

    private function normalizeLimit(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $intValue = (int) $value;

        return $intValue > 0 ? $intValue : null;
    }
}
