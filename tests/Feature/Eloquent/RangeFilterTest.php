<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Support\Carbon;
use Jackardios\QueryWizard\Eloquent\EloquentFilter;
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
    public function range_filter_ignores_non_numeric_min(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['id' => ['min' => 'abc', 'max' => 3]])
            ->allowedFilters(EloquentFilter::range('id'))
            ->get();

        // Only max should be applied (no min constraint)
        $this->assertTrue($models->every(fn ($m) => $m->id <= 3));
    }

    #[Test]
    public function range_filter_ignores_non_numeric_max(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['id' => ['min' => 2, 'max' => 'xyz']])
            ->allowedFilters(EloquentFilter::range('id'))
            ->get();

        // Only min should be applied (no max constraint)
        $this->assertTrue($models->every(fn ($m) => $m->id >= 2));
    }

    #[Test]
    public function range_filter_ignores_both_non_numeric_values(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['id' => ['min' => 'abc', 'max' => 'xyz']])
            ->allowedFilters(EloquentFilter::range('id'))
            ->get();

        // Both non-numeric — no range constraint applied
        $this->assertCount(5, $models);
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
                'to' => '2030-12-31 23:59:59',
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
}
