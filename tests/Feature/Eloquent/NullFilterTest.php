<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('eloquent')]
#[Group('filter')]
#[Group('null-filter')]
class NullFilterTest extends EloquentFilterTestCase
{
    #[Test]
    public function null_filter_generates_correct_sql(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['name' => true])
            ->allowedFilters(EloquentFilter::null('name'))
            ->toQuery()
            ->toSql();

        $this->assertStringContainsString('is null', strtolower($sql));
    }

    #[Test]
    public function null_filter_with_false_generates_not_null_sql(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['name' => false])
            ->allowedFilters(EloquentFilter::null('name'))
            ->toQuery()
            ->toSql();

        $this->assertStringContainsString('is not null', strtolower($sql));
    }

    #[Test]
    public function null_filter_with_string_true(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['name' => 'true'])
            ->allowedFilters(EloquentFilter::null('name'))
            ->toQuery()
            ->toSql();

        // 'true' is converted to boolean true, which checks for NULL
        $this->assertStringContainsString('is null', strtolower($sql));
    }

    #[Test]
    public function null_filter_with_string_one_is_truthy(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['name' => '1'])
            ->allowedFilters(EloquentFilter::null('name'))
            ->toQuery()
            ->toSql();

        $this->assertStringContainsString('is null', strtolower($sql));
    }

    #[Test]
    public function null_filter_with_string_zero_is_falsy(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['name' => '0'])
            ->allowedFilters(EloquentFilter::null('name'))
            ->toQuery()
            ->toSql();

        $this->assertStringContainsString('is not null', strtolower($sql));
    }

    #[Test]
    public function null_filter_with_inverted_logic_sql(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['has_name' => true])
            ->allowedFilters(
                EloquentFilter::null('name')->alias('has_name')
                    ->withInvertedLogic()
            )
            ->toQuery()
            ->toSql();

        // withInvertedLogic: "true" checks for NOT NULL
        $this->assertStringContainsString('is not null', strtolower($sql));
    }

    #[Test]
    public function null_filter_with_invalid_value_skips_filter(): void
    {
        // Invalid values that can't be interpreted as boolean should skip the filter
        $sql = $this
            ->createEloquentWizardWithFilters(['name' => 'invalid'])
            ->allowedFilters(EloquentFilter::null('name'))
            ->toQuery()
            ->toSql();

        // 'invalid' is not a valid boolean — filter should be skipped entirely
        $this->assertStringNotContainsString('is null', strtolower($sql));
        $this->assertStringNotContainsString('is not null', strtolower($sql));
    }

    #[Test]
    public function null_filter_with_numeric_value_skips_filter(): void
    {
        // Numeric values like '123' can't be parsed as boolean — filter should be skipped
        $sql = $this
            ->createEloquentWizardWithFilters(['name' => '123'])
            ->allowedFilters(EloquentFilter::null('name'))
            ->toQuery()
            ->toSql();

        $this->assertStringNotContainsString('is null', strtolower($sql));
        $this->assertStringNotContainsString('is not null', strtolower($sql));
    }
}
