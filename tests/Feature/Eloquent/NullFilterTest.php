<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;
use PHPUnit\Framework\Attributes\DataProvider;
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
    public function not_null_filter_matches_not_null_for_true_and_null_for_false(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['has_name' => true])
            ->allowedFilters(EloquentFilter::notNull('name', 'has_name'))
            ->toQuery()
            ->toSql();

        $this->assertStringEndsWith('where "test_models"."name" is not null', $sql);

        $sql = $this
            ->createEloquentWizardWithFilters(['has_name' => 'false'])
            ->allowedFilters(EloquentFilter::notNull('name', 'has_name'))
            ->toQuery()
            ->toSql();

        $this->assertStringEndsWith('where "test_models"."name" is null', $sql);
    }

    #[Test]
    #[DataProvider('nonBooleanValues')]
    public function null_filter_rejects_values_that_are_not_booleans(string $value): void
    {
        $this->expectException(InvalidFilterValue::class);
        $this->expectExceptionMessage('Expected a boolean (true, false, 1, 0, yes, no, on or off).');

        $this
            ->createEloquentWizardWithFilters(['name' => $value])
            ->allowedFilters(EloquentFilter::null('name'))
            ->toQuery();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonBooleanValues(): array
    {
        return [
            'text' => ['invalid'],
            'number' => ['123'],
        ];
    }

    #[Test]
    public function null_filter_reads_booleans_in_any_letter_case(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['name' => 'ON'])
            ->allowedFilters(EloquentFilter::null('name'))
            ->toQuery()
            ->toSql();

        $this->assertStringEndsWith('"name" is null', $sql);
    }
}
