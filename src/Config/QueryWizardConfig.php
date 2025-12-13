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
        return (string) config(self::CONFIG_PREFIX . '.count_suffix', 'Count');
    }

    public function getArrayValueSeparator(): string
    {
        return (string) config(self::CONFIG_PREFIX . '.array_value_separator', ',');
    }

    public function getFieldsParameterName(): ?string
    {
        return config(self::CONFIG_PREFIX . '.parameters.fields');
    }

    public function getAppendsParameterName(): ?string
    {
        return config(self::CONFIG_PREFIX . '.parameters.appends');
    }

    public function getFiltersParameterName(): ?string
    {
        return config(self::CONFIG_PREFIX . '.parameters.filters');
    }

    public function getIncludesParameterName(): ?string
    {
        return config(self::CONFIG_PREFIX . '.parameters.includes');
    }

    public function getSortsParameterName(): ?string
    {
        return config(self::CONFIG_PREFIX . '.parameters.sorts');
    }

    public function getRequestDataSource(): string
    {
        return (string) config(self::CONFIG_PREFIX . '.request_data_source', 'query_string');
    }

    public function shouldUseRequestBody(): bool
    {
        return $this->getRequestDataSource() === 'body';
    }

    public function isInvalidFilterQueryExceptionDisabled(): bool
    {
        return (bool) config(self::CONFIG_PREFIX . '.disable_invalid_filter_query_exception', false);
    }
}
