<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Carbon;
use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('eloquent')]
#[Group('filter')]
#[Group('range-filter')]
class RangeFilterTest extends EloquentFilterTestCase
{
    // ========== Range Filter Tests ==========

    #[Test]
    public function it_can_filter_by_range_with_min_and_max(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['id' => ['min' => 2, 'max' => 4]])
            ->allowedFilters(EloquentFilter::range('id'))
            ->get();

        $this->assertCount(3, $models);
        $this->assertTrue($models->every(fn ($m) => $m->id >= 2 && $m->id <= 4));
    }

    #[Test]
    public function it_can_filter_by_range_with_only_min(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['id' => ['min' => 3]])
            ->allowedFilters(EloquentFilter::range('id'))
            ->get();

        $this->assertTrue($models->every(fn ($m) => $m->id >= 3));
    }

    #[Test]
    public function it_can_filter_by_range_with_only_max(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['id' => ['max' => 3]])
            ->allowedFilters(EloquentFilter::range('id'))
            ->get();

        $this->assertTrue($models->every(fn ($m) => $m->id <= 3));
    }

    #[Test]
    public function it_can_filter_by_range_with_comma_separated(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['id' => '2,4'])
            ->allowedFilters(EloquentFilter::range('id'))
            ->get();

        $this->assertCount(3, $models);
    }

    #[Test]
    public function a_range_list_of_more_than_two_values_is_rejected(): void
    {
        $this->expectException(InvalidFilterQuery::class);
        $this->expectExceptionMessage('expects an array with `min`/`max` keys or a flat list of two values.');

        $this
            ->createEloquentWizardWithFilters(['id' => '2,4,6'])
            ->allowedFilters(EloquentFilter::range('id'))
            ->get();
    }

    #[Test]
    public function range_filter_handles_negative_values(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['id' => ['min' => -10, 'max' => 3]])
            ->allowedFilters(EloquentFilter::range('id'))
            ->get();

        $this->assertTrue($models->every(fn ($m) => $m->id >= -10 && $m->id <= 3));
    }

    #[Test]
    public function range_filter_handles_float_values(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['id' => ['min' => 1.5, 'max' => 3.5]])
            ->allowedFilters(EloquentFilter::range('id'))
            ->toQuery()
            ->toSql();

        $this->assertStringContainsString('>=', $sql);
        $this->assertStringContainsString('<=', $sql);
    }

    #[Test]
    #[DataProvider('nonNumericBounds')]
    public function range_filter_rejects_bounds_that_are_not_numbers(array $value, string $reason): void
    {
        $this->expectException(InvalidFilterValue::class);
        $this->expectExceptionMessage($reason);

        $this
            ->createEloquentWizardWithFilters(['id' => $value])
            ->allowedFilters(EloquentFilter::range('id'))
            ->toQuery();
    }

    /**
     * @return array<string, array{array<mixed>, string}>
     */
    public static function nonNumericBounds(): array
    {
        return [
            'text min' => [['min' => 'abc', 'max' => 3], 'Expected a decimal number for `min`.'],
            'text max' => [['min' => 2, 'max' => 'xyz'], 'Expected a decimal number for `max`.'],
            'exponent' => [['min' => '1e3'], 'Expected a decimal number for `min`.'],
            'list' => [['1', 'abc'], 'Expected a decimal number for `max`.'],
        ];
    }

    #[Test]
    public function range_filter_binds_numbers(): void
    {
        $query = $this
            ->createEloquentWizardWithFilters(['id' => ['min' => ' 2 ', 'max' => '3.5']])
            ->allowedFilters(EloquentFilter::range('id'))
            ->toQuery();

        $this->assertSame([2, 3.5], $query->getBindings());

        $models = $this
            ->createEloquentWizardWithFilters(['id' => ['min' => ' 2 ', 'max' => '+3']])
            ->allowedFilters(EloquentFilter::range('id'))
            ->get();

        $this->assertEqualsCanonicalizing([2, 3], $models->modelKeys());
    }

    #[Test]
    public function range_filter_with_alias(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['model_id' => ['min' => 2, 'max' => 4]])
            ->allowedFilters(EloquentFilter::range('id')->alias('model_id'))
            ->get();

        $this->assertCount(3, $models);
    }

    // ========== Date Range Filter Tests ==========

    #[Test]
    public function it_can_filter_by_date_range(): void
    {
        $from = Carbon::now()->subDays(1);
        $to = Carbon::now()->addDays(1);

        $models = $this
            ->createEloquentWizardWithFilters(['created_at' => ['from' => $from->toDateTimeString(), 'to' => $to->toDateTimeString()]])
            ->allowedFilters(EloquentFilter::dateRange('created_at'))
            ->get();

        $this->assertCount(5, $models);
    }

    #[Test]
    public function it_can_filter_by_date_range_with_only_from(): void
    {
        $from = Carbon::now()->subDays(1);

        $models = $this
            ->createEloquentWizardWithFilters(['created_at' => ['from' => $from->toDateTimeString()]])
            ->allowedFilters(EloquentFilter::dateRange('created_at'))
            ->get();

        $this->assertCount(5, $models);
    }

    #[Test]
    public function it_can_filter_by_date_range_with_only_to(): void
    {
        $to = Carbon::now()->addDays(1);

        $models = $this
            ->createEloquentWizardWithFilters(['created_at' => ['to' => $to->toDateTimeString()]])
            ->allowedFilters(EloquentFilter::dateRange('created_at'))
            ->get();

        $this->assertCount(5, $models);
    }

    #[Test]
    public function date_range_filter_handles_various_formats(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['created_at' => [
                'from' => '2020-01-01',
                'to' => now()->addYear()->format('Y-m-d H:i:s'),
            ]])
            ->allowedFilters(EloquentFilter::dateRange('created_at'))
            ->get();

        $this->assertCount(5, $models);
    }

    #[Test]
    public function date_range_filter_with_alias(): void
    {
        $from = Carbon::now()->subDays(1);
        $to = Carbon::now()->addDays(1);

        $models = $this
            ->createEloquentWizardWithFilters(['date' => [
                'from' => $from->toDateTimeString(),
                'to' => $to->toDateTimeString(),
            ]])
            ->allowedFilters(EloquentFilter::dateRange('created_at')->alias('date'))
            ->get();

        $this->assertCount(5, $models);
    }

    #[Test]
    #[DataProvider('invalidDates')]
    public function date_range_filter_rejects_values_that_are_not_iso_dates(mixed $value): void
    {
        $this->expectException(InvalidFilterValue::class);
        $this->expectExceptionMessage('Expected a date (Y-m-d) or an ISO 8601 date-time for `from`.');

        $this
            ->createEloquentWizardWithFilters(['created_at' => ['from' => $value]])
            ->allowedFilters(EloquentFilter::dateRange('created_at'))
            ->toQuery();
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidDates(): array
    {
        return [
            'text' => ['not-a-date'],
            'relative' => ['yesterday'],
            'day out of range' => ['2024-02-30'],
            'hour out of range' => ['2024-01-31T25:00'],
            'local format' => ['31.01.2024'],
            'compact date' => ['20240131'],
            'timestamp string' => ['1706702400'],
            'timestamp' => [1706702400],
        ];
    }

    #[Test]
    #[DataProvider('isoBounds')]
    public function date_range_filter_reads_iso_dates_in_the_app_timezone(string $from, string $bound): void
    {
        $query = $this
            ->createEloquentWizardWithFilters(['created_at' => ['from' => $from]])
            ->allowedFilters(EloquentFilter::dateRange('created_at'))
            ->toQuery();

        $this->assertStringEndsWith('where "test_models"."created_at" >= ?', $query->toSql());
        $this->assertSame([$bound], $query->getConnection()->prepareBindings($query->getBindings()));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function isoBounds(): array
    {
        return [
            'date' => ['2024-01-31', '2024-01-31'],
            'date-time' => ['2024-01-31 10:15:30', '2024-01-31 10:15:30'],
            'without seconds' => ['2024-01-31T10:15', '2024-01-31 10:15:00'],
            'utc' => ['2024-01-31T10:15:30Z', '2024-01-31 10:15:30'],
            'offset' => ['2024-01-31T10:15:30+03:00', '2024-01-31 07:15:30'],
            'fraction' => ['2024-01-31T10:15:30.250-02:00', '2024-01-31 12:15:30'],
            'padded' => [' 2024-01-31 ', '2024-01-31'],
        ];
    }

    #[Test]
    public function date_range_filter_upper_date_includes_the_whole_day(): void
    {
        $late = $this->models->first();
        $late->forceFill(['created_at' => '2024-01-31 23:30:00'])->save();

        $query = $this
            ->createEloquentWizardWithFilters(['created_at' => ['from' => '2024-01-31', 'to' => '2024-01-31']])
            ->allowedFilters(EloquentFilter::dateRange('created_at'))
            ->toQuery();

        $this->assertStringEndsWith('"created_at" >= ? and "test_models"."created_at" < ?', $query->toSql());
        $this->assertSame(['2024-01-31', '2024-02-01'], $query->getBindings());
        $this->assertSame([$late->id], $query->get()->modelKeys());
    }

    #[Test]
    public function date_range_filter_upper_date_time_is_inclusive(): void
    {
        $query = $this
            ->createEloquentWizardWithFilters(['created_at' => ['to' => '2024-01-31T23:30:00']])
            ->allowedFilters(EloquentFilter::dateRange('created_at'))
            ->toQuery();

        $this->assertStringEndsWith('"created_at" <= ?', $query->toSql());
        $this->assertSame(['2024-01-31 23:30:00'], $query->getConnection()->prepareBindings($query->getBindings()));
    }

    #[Test]
    public function date_range_filter_converts_date_objects_to_the_app_timezone(): void
    {
        $this->withTimezone('Europe/Moscow', function (): void {
            $query = $this
                ->createEloquentWizardWithFilters([])
                ->allowedFilters(EloquentFilter::dateRange('created_at')->default([
                    'from' => new DateTimeImmutable('2024-01-31 10:00:00', new DateTimeZone('UTC')),
                ]))
                ->toQuery();

            $this->assertSame(['2024-01-31 13:00:00'], $query->getConnection()->prepareBindings($query->getBindings()));
        });
    }

    #[Test]
    public function date_range_filter_dates_start_at_midnight_in_the_app_timezone(): void
    {
        $this->withTimezone('Asia/Tokyo', function (): void {
            $query = $this
                ->createEloquentWizardWithFilters(['created_at' => ['from' => '2024-01-31', 'to' => '2024-01-31']])
                ->allowedFilters(EloquentFilter::dateRange('created_at')->asUnixTimestamp())
                ->toQuery();

            $this->assertSame([1706626800, 1706713200], $query->getBindings());
        });
    }

    #[Test]
    public function date_range_filter_lenient_mode_accepts_relative_dates(): void
    {
        $query = $this
            ->createEloquentWizardWithFilters(['created_at' => ['from' => '-1 week', 'to' => '2024-01-31']])
            ->allowedFilters(EloquentFilter::dateRange('created_at')->lenient())
            ->toQuery();

        $bindings = $query->getConnection()->prepareBindings($query->getBindings());
        $this->assertSame('2024-02-01', $bindings[1]);
        $this->assertStringStartsWith((new DateTimeImmutable('-1 week'))->format('Y-m-d '), $bindings[0]);
    }

    #[Test]
    public function date_range_filter_lenient_mode_rejects_single_letters(): void
    {
        $this->expectException(InvalidFilterValue::class);
        $this->expectExceptionMessage('Expected a date for `to`.');

        $this
            ->createEloquentWizardWithFilters(['created_at' => ['to' => 'x']])
            ->allowedFilters(EloquentFilter::dateRange('created_at')->lenient())
            ->toQuery();
    }

    #[Test]
    public function date_range_filter_unix_timestamp_mode_takes_timestamps_and_dates(): void
    {
        $query = $this
            ->createEloquentWizardWithFilters(['created_at' => ['from' => '1706702400', 'to' => '2024-01-31T12:00:00+02:00']])
            ->allowedFilters(EloquentFilter::dateRange('created_at')->asUnixTimestamp())
            ->toQuery();

        $this->assertSame([1706702400, 1706695200], $query->getBindings());
    }

    #[Test]
    public function date_range_filter_formats_bounds_with_the_date_format(): void
    {
        $query = $this
            ->createEloquentWizardWithFilters(['created_at' => ['from' => '2024-01-31T10:15:30Z', 'to' => '2024-01-31']])
            ->allowedFilters(EloquentFilter::dateRange('created_at')->dateFormat('Y-m-d H:i'))
            ->toQuery();

        $this->assertStringEndsWith('"created_at" >= ? and "test_models"."created_at" < ?', $query->toSql());
        $this->assertSame(['2024-01-31 10:15', '2024-02-01 00:00'], $query->getBindings());
    }

    #[Test]
    public function date_range_filter_blank_bounds_are_absent(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['created_at' => ['from' => ' ', 'to' => '2024-01-31']])
            ->allowedFilters(EloquentFilter::dateRange('created_at'))
            ->toQuery()
            ->toSql();

        $this->assertStringEndsWith('where "test_models"."created_at" < ?', $sql);
    }

    private function withTimezone(string $timezone, Closure $callback): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set($timezone);

        try {
            $callback();
        } finally {
            date_default_timezone_set($previous);
        }
    }
}
