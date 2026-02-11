<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('eloquent')]
#[Group('filter')]
class FilterEdgeCasesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::enableQueryLog();

        TestModel::factory()->count(5)->create();
    }

    #[Test]
    public function empty_string_filter_value_is_treated_as_null_and_not_applied(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['name' => ''])
            ->allowedFilters('name')
            ->get();

        // Empty string is converted to null by FilterValueTransformer, filter not applied
        $this->assertCount(5, $models);
    }

    #[Test]
    public function zero_string_filter_value_is_applied(): void
    {
        TestModel::factory()->create(['name' => '0']);

        $models = $this
            ->createEloquentWizardWithFilters(['name' => '0'])
            ->allowedFilters('name')
            ->get();

        // '0' is a valid value, should match the model with name '0'
        $this->assertCount(1, $models);
        $this->assertEquals('0', $models->first()->name);
    }

    #[Test]
    public function comma_only_filter_value_produces_empty_array_and_filter_is_skipped(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['name' => ',,,'])
            ->allowedFilters('name')
            ->get();

        // ',,,' is split into empty array by FilterValueTransformer (all parts are empty strings)
        // ExactFilter with empty array skips the filter entirely (no whereIn)
        $this->assertCount(5, $models);
    }

    #[Test]
    public function whitespace_only_filter_value_is_applied(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['name' => ' '])
            ->allowedFilters('name')
            ->get();

        // ' ' (space) is NOT empty string, so it's applied as a filter value
        // No model has name ' ', so 0 results
        $this->assertCount(0, $models);
    }

    #[Test]
    public function prepare_value_with_returning_null_skips_filter(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['name' => 'something'])
            ->allowedFilters(
                EloquentFilter::exact('name')->prepareValueWith(fn ($value) => null)
            )
            ->get();

        // prepareValueWith returns null → filter is not applied
        $this->assertCount(5, $models);
    }

    #[Test]
    public function empty_filter_value_does_not_fall_back_to_default_filter_value(): void
    {
        $targetModel = TestModel::query()->firstOrFail();

        $models = $this
            ->createEloquentWizardWithFilters(['name' => ''])
            ->allowedFilters(
                EloquentFilter::exact('name')->default($targetModel->name)
            )
            ->get();

        // Explicit empty filter value means "skip this filter", not "use default".
        $this->assertCount(5, $models);
    }

    #[Test]
    public function empty_filter_value_falls_back_to_default_when_opt_in_enabled(): void
    {
        Config::set('query-wizard.apply_filter_default_on_null', true);

        $targetModel = TestModel::query()->firstOrFail();

        $models = $this
            ->createEloquentWizardWithFilters(['name' => ''])
            ->allowedFilters(
                EloquentFilter::exact('name')->default($targetModel->name)
            )
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals($targetModel->name, $models->first()->name);
    }

    #[Test]
    public function null_filter_value_falls_back_to_default_when_opt_in_enabled(): void
    {
        Config::set('query-wizard.apply_filter_default_on_null', true);

        $targetModel = TestModel::query()->firstOrFail();

        $models = $this
            ->createEloquentWizardWithFilters(['name' => null])
            ->allowedFilters(
                EloquentFilter::exact('name')->default($targetModel->name)
            )
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals($targetModel->name, $models->first()->name);
    }
}
