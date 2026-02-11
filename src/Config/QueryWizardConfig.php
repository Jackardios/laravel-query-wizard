<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Config;

/**
 * Centralized configuration access for Query Wizard.
 */
final class QueryWizardConfig
{
    private const CONFIG_PREFIX = 'query-wizard';

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
            return (string) $separators[$type];
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
        return (string) config(self::CONFIG_PREFIX.'.request_data_source', 'query_string');
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
        $value = config(self::CONFIG_PREFIX.'.limits.max_include_depth');

        return $value !== null ? (int) $value : null;
    }

    public function getMaxIncludesCount(): ?int
    {
        $value = config(self::CONFIG_PREFIX.'.limits.max_includes_count');

        return $value !== null ? (int) $value : null;
    }

    public function getMaxFiltersCount(): ?int
    {
        $value = config(self::CONFIG_PREFIX.'.limits.max_filters_count');

        return $value !== null ? (int) $value : null;
    }

    public function getMaxSortsCount(): ?int
    {
        $value = config(self::CONFIG_PREFIX.'.limits.max_sorts_count');

        return $value !== null ? (int) $value : null;
    }

    public function getMaxAppendsCount(): ?int
    {
        $value = config(self::CONFIG_PREFIX.'.limits.max_appends_count');

        return $value !== null ? (int) $value : null;
    }

    public function getMaxAppendDepth(): ?int
    {
        $value = config(self::CONFIG_PREFIX.'.limits.max_append_depth');

        return $value !== null ? (int) $value : null;
    }
}
