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

    public function getMaxIncludeDepth(): ?int
    {
        $value = config(self::CONFIG_PREFIX . '.limits.max_include_depth');
        return $value !== null ? (int) $value : null;
    }

    public function getMaxIncludesCount(): ?int
    {
        $value = config(self::CONFIG_PREFIX . '.limits.max_includes_count');
        return $value !== null ? (int) $value : null;
    }

    public function getMaxFiltersCount(): ?int
    {
        $value = config(self::CONFIG_PREFIX . '.limits.max_filters_count');
        return $value !== null ? (int) $value : null;
    }

    public function getMaxFilterDepth(): ?int
    {
        $value = config(self::CONFIG_PREFIX . '.limits.max_filter_depth');
        return $value !== null ? (int) $value : null;
    }

    public function getMaxSortsCount(): ?int
    {
        $value = config(self::CONFIG_PREFIX . '.limits.max_sorts_count');
        return $value !== null ? (int) $value : null;
    }

    public function getUnsupportedCapabilityBehavior(): string
    {
        return (string) config(self::CONFIG_PREFIX . '.unsupported_capability_behavior', 'exception');
    }

    public function shouldThrowOnUnsupportedCapability(): bool
    {
        return $this->getUnsupportedCapabilityBehavior() === 'exception';
    }

    public function shouldLogUnsupportedCapability(): bool
    {
        return $this->getUnsupportedCapabilityBehavior() === 'log';
    }
}
