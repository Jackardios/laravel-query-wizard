<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
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
    #[DataProvider('blankValues')]
    public function blank_filter_values_are_absent(mixed $value): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['name' => $value])
            ->allowedFilters('name')
            ->toQuery()
            ->toSql();

        $this->assertSame('select * from "test_models"', $sql);
    }

    #[Test]
    #[DataProvider('blankUnsplitValues')]
    public function blank_partial_filter_values_are_absent(mixed $value): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['search' => $value])
            ->allowedFilters(EloquentFilter::partial('name')->alias('search'))
            ->toQuery()
            ->toSql();

        $this->assertSame('select * from "test_models"', $sql);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function blankValues(): array
    {
        return [
            'separators' => [' , ,'],
            ...self::blankUnsplitValues(),
        ];
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function blankUnsplitValues(): array
    {
        return [
            'whitespace' => [' '],
            'list of empty strings' => [['', ' ']],
            'list of nulls' => [[null]],
            'nested blank list' => [[[''], null]],
        ];
    }

    #[Test]
    public function a_blank_structured_value_is_absent(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['id' => ['min' => '', 'max' => ' ']])
            ->allowedFilters(EloquentFilter::range('id'))
            ->toQuery()
            ->toSql();

        $this->assertSame('select * from "test_models"', $sql);
    }

    #[Test]
    public function a_blank_passthrough_value_is_not_captured(): void
    {
        $passthrough = $this
            ->createEloquentWizardWithFilters(['context' => ','])
            ->allowedFilters(EloquentFilter::passthrough('context'))
            ->getPassthroughFilters();

        $this->assertTrue($passthrough->isEmpty());
    }

    #[Test]
    public function a_comma_separated_default_is_passed_whole(): void
    {
        $query = $this
            ->createEloquentWizardWithFilters([])
            ->allowedFilters(EloquentFilter::exact('name')->default('a,b'))
            ->toQuery();

        $this->assertSame('select * from "test_models" where "test_models"."name" = ?', $query->toSql());
        $this->assertSame(['a,b'], $query->getBindings());
    }

    #[Test]
    public function a_blank_default_is_absent(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters([])
            ->allowedFilters(EloquentFilter::exact('name')->default([]))
            ->toQuery()
            ->toSql();

        $this->assertSame('select * from "test_models"', $sql);
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
    #[DataProvider('blankValues')]
    public function blank_filter_values_fall_back_to_default_when_opt_in_enabled(mixed $value): void
    {
        Config::set('query-wizard.apply_filter_default_on_null', true);

        $targetModel = TestModel::query()->firstOrFail();

        $models = $this
            ->createEloquentWizardWithFilters(['name' => $value])
            ->allowedFilters(
                EloquentFilter::exact('name')->default($targetModel->name)
            )
            ->get();

        $this->assertSame([$targetModel->id], $models->modelKeys());
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
