<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use Illuminate\Support\Facades\Config;
use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class QueryWizardConfigTest extends TestCase
{
    private QueryWizardConfig $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = new QueryWizardConfig;
    }

    // ========== Count Suffix Tests ==========
    #[Test]
    public function it_returns_default_count_suffix(): void
    {
        $this->assertEquals('Count', $this->config->getCountSuffix());
    }

    #[Test]
    public function it_returns_custom_count_suffix(): void
    {
        Config::set('query-wizard.count_suffix', 'Total');

        $this->assertEquals('Total', $this->config->getCountSuffix());
    }

    // ========== Array Value Separator Tests ==========
    #[Test]
    public function it_returns_default_array_value_separator(): void
    {
        $this->assertEquals(',', $this->config->getArrayValueSeparator());
    }

    #[Test]
    public function it_returns_custom_array_value_separator(): void
    {
        Config::set('query-wizard.array_value_separator', '|');

        $this->assertEquals('|', $this->config->getArrayValueSeparator());
    }

    // ========== Parameter Names Tests ==========
    #[Test]
    public function it_returns_default_fields_parameter_name(): void
    {
        $this->assertEquals('fields', $this->config->getFieldsParameterName());
    }

    #[Test]
    public function it_returns_custom_fields_parameter_name(): void
    {
        Config::set('query-wizard.parameters.fields', 'select');

        $this->assertEquals('select', $this->config->getFieldsParameterName());
    }

    #[Test]
    public function it_returns_default_appends_parameter_name(): void
    {
        $this->assertEquals('append', $this->config->getAppendsParameterName());
    }

    #[Test]
    public function it_returns_default_filters_parameter_name(): void
    {
        $this->assertEquals('filter', $this->config->getFiltersParameterName());
    }

    #[Test]
    public function it_returns_default_includes_parameter_name(): void
    {
        $this->assertEquals('include', $this->config->getIncludesParameterName());
    }

    #[Test]
    public function it_returns_default_sorts_parameter_name(): void
    {
        $this->assertEquals('sort', $this->config->getSortsParameterName());
    }

    // ========== Request Data Source Tests ==========
    #[Test]
    public function it_returns_default_request_data_source(): void
    {
        $this->assertEquals('query_string', $this->config->getRequestDataSource());
    }

    #[Test]
    public function it_returns_custom_request_data_source(): void
    {
        Config::set('query-wizard.request_data_source', 'body');

        $this->assertEquals('body', $this->config->getRequestDataSource());
    }

    #[Test]
    public function should_use_request_body_returns_false_by_default(): void
    {
        $this->assertFalse($this->config->shouldUseRequestBody());
    }

    #[Test]
    public function should_use_request_body_returns_true_when_configured(): void
    {
        Config::set('query-wizard.request_data_source', 'body');

        $this->assertTrue($this->config->shouldUseRequestBody());
    }

    #[Test]
    public function should_apply_filter_default_on_null_returns_false_by_default(): void
    {
        $this->assertFalse($this->config->shouldApplyFilterDefaultOnNull());
    }

    #[Test]
    public function should_apply_filter_default_on_null_returns_true_when_configured(): void
    {
        Config::set('query-wizard.apply_filter_default_on_null', true);

        $this->assertTrue($this->config->shouldApplyFilterDefaultOnNull());
    }

    // ========== Invalid Filter Query Exception Tests ==========
    #[Test]
    public function it_returns_false_for_disable_invalid_filter_query_exception_by_default(): void
    {
        $this->assertFalse($this->config->isInvalidFilterQueryExceptionDisabled());
    }

    #[Test]
    public function it_returns_true_when_invalid_filter_query_exception_disabled(): void
    {
        Config::set('query-wizard.disable_invalid_filter_query_exception', true);

        $this->assertTrue($this->config->isInvalidFilterQueryExceptionDisabled());
    }

    // ========== Invalid Sort Query Exception Tests ==========
    #[Test]
    public function it_returns_false_for_disable_invalid_sort_query_exception_by_default(): void
    {
        $this->assertFalse($this->config->isInvalidSortQueryExceptionDisabled());
    }

    #[Test]
    public function it_returns_true_when_invalid_sort_query_exception_disabled(): void
    {
        Config::set('query-wizard.disable_invalid_sort_query_exception', true);

        $this->assertTrue($this->config->isInvalidSortQueryExceptionDisabled());
    }

    // ========== Invalid Include Query Exception Tests ==========
    #[Test]
    public function it_returns_false_for_disable_invalid_include_query_exception_by_default(): void
    {
        $this->assertFalse($this->config->isInvalidIncludeQueryExceptionDisabled());
    }

    #[Test]
    public function it_returns_true_when_invalid_include_query_exception_disabled(): void
    {
        Config::set('query-wizard.disable_invalid_include_query_exception', true);

        $this->assertTrue($this->config->isInvalidIncludeQueryExceptionDisabled());
    }

    // ========== Invalid Field Query Exception Tests ==========
    #[Test]
    public function it_returns_false_for_disable_invalid_field_query_exception_by_default(): void
    {
        $this->assertFalse($this->config->isInvalidFieldQueryExceptionDisabled());
    }

    #[Test]
    public function it_returns_true_when_invalid_field_query_exception_disabled(): void
    {
        Config::set('query-wizard.disable_invalid_field_query_exception', true);

        $this->assertTrue($this->config->isInvalidFieldQueryExceptionDisabled());
    }

    // ========== Invalid Append Query Exception Tests ==========
    #[Test]
    public function it_returns_false_for_disable_invalid_append_query_exception_by_default(): void
    {
        $this->assertFalse($this->config->isInvalidAppendQueryExceptionDisabled());
    }

    #[Test]
    public function it_returns_true_when_invalid_append_query_exception_disabled(): void
    {
        Config::set('query-wizard.disable_invalid_append_query_exception', true);

        $this->assertTrue($this->config->isInvalidAppendQueryExceptionDisabled());
    }

    // ========== Security Limits Tests ==========
    #[Test]
    public function it_returns_default_max_include_depth(): void
    {
        $this->assertEquals(3, $this->config->getMaxIncludeDepth());
    }

    #[Test]
    public function it_returns_null_when_max_include_depth_disabled(): void
    {
        Config::set('query-wizard.limits.max_include_depth', null);

        $this->assertNull($this->config->getMaxIncludeDepth());
    }

    #[Test]
    public function it_returns_custom_max_include_depth(): void
    {
        Config::set('query-wizard.limits.max_include_depth', 10);

        $this->assertEquals(10, $this->config->getMaxIncludeDepth());
    }

    #[Test]
    public function it_returns_default_max_includes_count(): void
    {
        $this->assertEquals(10, $this->config->getMaxIncludesCount());
    }

    #[Test]
    public function it_returns_null_when_max_includes_count_disabled(): void
    {
        Config::set('query-wizard.limits.max_includes_count', null);

        $this->assertNull($this->config->getMaxIncludesCount());
    }

    #[Test]
    public function it_returns_default_max_filters_count(): void
    {
        $this->assertEquals(20, $this->config->getMaxFiltersCount());
    }

    #[Test]
    public function it_returns_null_when_max_filters_count_disabled(): void
    {
        Config::set('query-wizard.limits.max_filters_count', null);

        $this->assertNull($this->config->getMaxFiltersCount());
    }

    #[Test]
    public function it_returns_default_max_sorts_count(): void
    {
        $this->assertEquals(5, $this->config->getMaxSortsCount());
    }

    #[Test]
    public function it_returns_null_when_max_sorts_count_disabled(): void
    {
        Config::set('query-wizard.limits.max_sorts_count', null);

        $this->assertNull($this->config->getMaxSortsCount());
    }

    #[Test]
    public function it_returns_default_max_append_depth(): void
    {
        $this->assertEquals(3, $this->config->getMaxAppendDepth());
    }

    #[Test]
    public function it_returns_null_when_max_append_depth_disabled(): void
    {
        Config::set('query-wizard.limits.max_append_depth', null);

        $this->assertNull($this->config->getMaxAppendDepth());
    }

    #[Test]
    public function it_returns_custom_max_append_depth(): void
    {
        Config::set('query-wizard.limits.max_append_depth', 5);

        $this->assertEquals(5, $this->config->getMaxAppendDepth());
    }
}
