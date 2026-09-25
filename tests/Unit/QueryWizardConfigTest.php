<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClassConstant;

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

    // ========== Exists Suffix Tests ==========
    #[Test]
    public function it_returns_default_exists_suffix(): void
    {
        $this->assertEquals('Exists', $this->config->getExistsSuffix());
    }

    #[Test]
    public function it_returns_custom_exists_suffix(): void
    {
        Config::set('query-wizard.exists_suffix', 'Has');

        $this->assertEquals('Has', $this->config->getExistsSuffix());
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

    // ========== Per-Type Separator Tests ==========
    #[Test]
    public function it_returns_default_separator_for_includes(): void
    {
        $this->assertEquals(',', $this->config->getIncludesSeparator());
    }

    #[Test]
    public function it_returns_default_separator_for_sorts(): void
    {
        $this->assertEquals(',', $this->config->getSortsSeparator());
    }

    #[Test]
    public function it_returns_default_separator_for_filters(): void
    {
        $this->assertEquals(',', $this->config->getFiltersSeparator());
    }

    #[Test]
    public function it_returns_default_separator_for_fields(): void
    {
        $this->assertEquals(',', $this->config->getFieldsSeparator());
    }

    #[Test]
    public function it_returns_default_separator_for_appends(): void
    {
        $this->assertEquals(',', $this->config->getAppendsSeparator());
    }

    #[Test]
    public function it_returns_custom_separator_for_includes(): void
    {
        Config::set('query-wizard.separators.includes', '|');

        $this->assertEquals('|', $this->config->getIncludesSeparator());
    }

    #[Test]
    public function it_returns_custom_separator_for_filters(): void
    {
        Config::set('query-wizard.separators.filters', ';');

        $this->assertEquals(';', $this->config->getFiltersSeparator());
    }

    #[Test]
    public function it_falls_back_to_array_value_separator_when_type_not_configured(): void
    {
        Config::set('query-wizard.array_value_separator', '|');
        Config::set('query-wizard.separators', []);

        $this->assertEquals('|', $this->config->getFiltersSeparator());
        $this->assertEquals('|', $this->config->getIncludesSeparator());
    }

    #[Test]
    public function it_uses_type_specific_separator_over_array_value_separator(): void
    {
        Config::set('query-wizard.array_value_separator', '|');
        Config::set('query-wizard.separators.filters', ';');

        $this->assertEquals(';', $this->config->getFiltersSeparator());
        $this->assertEquals('|', $this->config->getIncludesSeparator());
    }

    #[Test]
    public function get_separator_returns_type_specific_value(): void
    {
        Config::set('query-wizard.separators.filters', ';');

        $this->assertEquals(';', $this->config->getSeparator('filters'));
    }

    #[Test]
    public function get_separator_falls_back_to_default(): void
    {
        $this->assertEquals(',', $this->config->getSeparator('nonexistent'));
    }

    // ========== Naming Conversion Tests ==========
    #[Test]
    public function it_returns_false_for_convert_parameters_to_snake_case_by_default(): void
    {
        $this->assertFalse($this->config->shouldConvertParametersToSnakeCase());
    }

    #[Test]
    public function it_returns_true_when_convert_parameters_to_snake_case_enabled(): void
    {
        Config::set('query-wizard.naming.convert_parameters_to_snake_case', true);

        $this->assertTrue($this->config->shouldConvertParametersToSnakeCase());
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

    #[Test]
    public function should_use_allowed_fields_as_default_returns_false_by_default(): void
    {
        $this->assertFalse($this->config->shouldUseAllowedFieldsAsDefault());
    }

    #[Test]
    public function should_use_allowed_fields_as_default_returns_true_when_configured(): void
    {
        Config::set('query-wizard.fields.use_allowed_as_default', true);

        $this->assertTrue($this->config->shouldUseAllowedFieldsAsDefault());
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

    // ========== Validation Tests ==========
    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function booleanValues(): array
    {
        return [
            'true' => [true, true],
            'false' => [false, false],
            'string false' => ['false', false],
            'string off' => ['off', false],
            'string 1' => ['1', true],
            'int 0' => [0, false],
            'null' => [null, false],
            'empty string' => ['', false],
        ];
    }

    #[Test]
    #[DataProvider('booleanValues')]
    public function it_reads_booleans_written_as_strings_and_ints(mixed $value, bool $expected): void
    {
        Config::set('query-wizard.disable_invalid_filter_query_exception', $value);

        $this->assertSame($expected, $this->config->isInvalidFilterQueryExceptionDisabled());
    }

    #[Test]
    public function it_rejects_values_that_are_not_booleans(): void
    {
        Config::set('query-wizard.naming.convert_parameters_to_snake_case', 'sometimes');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Config `query-wizard.naming.convert_parameters_to_snake_case` must be a boolean.');

        $this->config->shouldConvertParametersToSnakeCase();
    }

    #[Test]
    #[DataProvider('invalidLimits')]
    public function it_rejects_limits_that_are_not_positive_integers(mixed $value): void
    {
        Config::set('query-wizard.limits.max_filters_count', $value);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Config `query-wizard.limits.max_filters_count` must be a positive integer, or null to disable the limit.');

        $this->config->getMaxFiltersCount();
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidLimits(): array
    {
        return [
            'zero' => [0],
            'zero string' => ['0'],
            'empty string' => [''],
            'negative' => [-5],
            'negative string' => ['-1'],
            'false' => [false],
            'true' => [true],
            'text' => ['abc'],
            'fraction' => [1.5],
            'fraction string' => ['1.5'],
            'array' => [[10]],
        ];
    }

    #[Test]
    public function it_reads_a_digit_string_limit_as_an_integer(): void
    {
        Config::set('query-wizard.limits.max_filters_count', '15');

        $this->assertSame(15, $this->config->getMaxFiltersCount());
    }

    #[Test]
    public function a_missing_key_takes_the_package_default(): void
    {
        Config::set('query-wizard.limits', ['max_filters_count' => 50]);
        Config::set('query-wizard.parameters', ['filters' => 'where']);

        $this->assertSame(50, $this->config->getMaxFiltersCount());
        $this->assertSame(5, $this->config->getMaxSortsCount());
        $this->assertSame(3, $this->config->getMaxIncludeDepth());
        $this->assertSame('where', $this->config->getFiltersParameterName());
        $this->assertSame('sort', $this->config->getSortsParameterName());
    }

    #[Test]
    public function an_empty_configuration_takes_the_package_defaults(): void
    {
        Config::set('query-wizard', null);

        $this->assertSame(20, $this->config->getMaxFiltersCount());
        $this->assertSame('filter', $this->config->getFiltersParameterName());
        $this->assertSame(',', $this->config->getFiltersSeparator());
        $this->assertSame('query_string', $this->config->getRequestDataSource());
        $this->assertSame('Count', $this->config->getCountSuffix());
    }

    #[Test]
    public function the_package_defaults_match_the_config_file(): void
    {
        $defaults = (new ReflectionClassConstant(QueryWizardConfig::class, 'DEFAULTS'))->getValue();

        $this->assertSame(require __DIR__.'/../../config/query-wizard.php', $defaults);
    }

    #[Test]
    public function a_null_parameter_name_disables_the_parameter(): void
    {
        Config::set('query-wizard.parameters.appends', null);

        $this->assertNull($this->config->getAppendsParameterName());
    }

    #[Test]
    #[DataProvider('invalidParameterNames')]
    public function it_rejects_parameter_names_that_are_not_strings(mixed $value): void
    {
        Config::set('query-wizard.parameters.filters', $value);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Config `query-wizard.parameters.filters` must be a non-empty string, or null to disable the parameter.');

        $this->config->getFiltersParameterName();
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidParameterNames(): array
    {
        return [
            'empty string' => [''],
            'array' => [['filter']],
            'number' => [1],
        ];
    }

    #[Test]
    public function it_normalizes_request_data_source_case(): void
    {
        Config::set('query-wizard.request_data_source', 'BODY');

        $this->assertEquals('body', $this->config->getRequestDataSource());
    }

    #[Test]
    public function it_trims_request_data_source(): void
    {
        Config::set('query-wizard.request_data_source', '  query_string  ');

        $this->assertEquals('query_string', $this->config->getRequestDataSource());
    }

    #[Test]
    #[DataProvider('invalidChoices')]
    public function it_rejects_unknown_choices(string $key, mixed $value, string $message): void
    {
        Config::set("query-wizard.{$key}", $value);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $this->config->getRequestDataSource();
    }

    /**
     * @return array<string, array{string, mixed, string}>
     */
    public static function invalidChoices(): array
    {
        return [
            'unknown data source' => ['request_data_source', 'invalid_source', 'Config `query-wizard.request_data_source` must be one of: query_string, body.'],
            'data source array' => ['request_data_source', ['body'], 'Config `query-wizard.request_data_source` must be one of: query_string, body.'],
            'null data source' => ['request_data_source', null, 'Config `query-wizard.request_data_source` must be one of: query_string, body.'],
        ];
    }

    #[Test]
    #[DataProvider('invalidSeparators')]
    public function it_rejects_separators_that_are_not_short_strings(string $key, mixed $value, string $message): void
    {
        Config::set("query-wizard.{$key}", $value);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $this->config->getFiltersSeparator();
    }

    /**
     * @return array<string, array{string, mixed, string}>
     */
    public static function invalidSeparators(): array
    {
        return [
            'empty' => ['separators.filters', '', 'Config `query-wizard.separators.filters` must be a non-empty string of at most 10 characters.'],
            'too long' => ['separators.filters', 'this-is-way-too-long', 'Config `query-wizard.separators.filters` must be a non-empty string of at most 10 characters.'],
            'array' => ['separators.filters', ['|'], 'Config `query-wizard.separators.filters` must be a non-empty string of at most 10 characters.'],
            'empty fallback' => ['array_value_separator', '', 'Config `query-wizard.array_value_separator` must be a non-empty string of at most 10 characters.'],
            'not a list of separators' => ['separators', ';', 'Config `query-wizard.separators` must be an array of separators keyed by parameter type.'],
        ];
    }

    #[Test]
    public function it_accepts_separator_at_max_length(): void
    {
        Config::set('query-wizard.separators.filters', '1234567890');

        $this->assertEquals('1234567890', $this->config->getFiltersSeparator());
    }

    #[Test]
    public function a_null_suffix_blanks_it(): void
    {
        Config::set('query-wizard.count_suffix', null);

        $this->assertSame('', $this->config->getCountSuffix());
    }

    // ========== Snapshot Tests ==========
    #[Test]
    public function a_snapshot_keeps_the_values_it_was_taken_with(): void
    {
        Config::set('query-wizard.limits.max_filters_count', 7);
        $snapshot = $this->config->snapshot();

        Config::set('query-wizard.limits.max_filters_count', 9);

        $this->assertSame(7, $snapshot->getMaxFiltersCount());
        $this->assertSame(9, $this->config->getMaxFiltersCount());
    }
}
