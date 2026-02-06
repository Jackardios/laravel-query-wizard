<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Support\Facades\DB;
use Jackardios\QueryWizard\Eloquent\EloquentSort;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('eloquent')]
#[Group('sort')]
class SortSqlVerificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::enableQueryLog();

        TestModel::factory()->count(3)->create();
    }

    // ========== CountSort SQL Verification ==========

    #[Test]
    public function count_sort_asc_generates_with_count_subquery(): void
    {
        $sql = $this
            ->createEloquentWizardWithSorts('relatedModels')
            ->allowedSorts(EloquentSort::count('relatedModels'))
            ->toQuery()
            ->toSql();

        $sqlLower = strtolower($sql);
        $this->assertStringContainsString('select count(*)', $sqlLower);
        $this->assertStringContainsString('"related_models_count"', $sql);
        $this->assertStringContainsString('order by', $sqlLower);
        $this->assertStringContainsString('asc', $sqlLower);
    }

    #[Test]
    public function count_sort_desc_generates_with_count_subquery(): void
    {
        $sql = $this
            ->createEloquentWizardWithSorts('-relatedModels')
            ->allowedSorts(EloquentSort::count('relatedModels'))
            ->toQuery()
            ->toSql();

        $sqlLower = strtolower($sql);
        $this->assertStringContainsString('select count(*)', $sqlLower);
        $this->assertStringContainsString('"related_models_count"', $sql);
        $this->assertStringContainsString('desc', $sqlLower);
    }

    #[Test]
    public function count_sort_alias_does_not_affect_column_name(): void
    {
        $sql = $this
            ->createEloquentWizardWithSorts('popularity')
            ->allowedSorts(EloquentSort::count('relatedModels')->alias('popularity'))
            ->toQuery()
            ->toSql();

        $this->assertStringContainsString('"related_models_count"', $sql);
        $this->assertStringNotContainsString('popularity_count', $sql);
    }

    #[Test]
    public function count_sort_does_not_duplicate_with_count(): void
    {
        RelatedModel::factory()->count(2)->create([
            'test_model_id' => TestModel::first()->id,
        ]);

        $query = TestModel::query()->withCount('relatedModels');

        DB::flushQueryLog();

        $this
            ->createEloquentWizardWithSorts('-relatedModels', $query)
            ->allowedSorts(EloquentSort::count('relatedModels'))
            ->get();

        $queries = DB::getQueryLog();
        $selectQuery = collect($queries)->first(fn ($q) => str_contains($q['query'], 'select'));
        $subqueryCount = substr_count($selectQuery['query'], 'select count(*)');

        $this->assertEquals(1, $subqueryCount);
    }

    // ========== RelationSort SQL Verification ==========

    #[Test]
    public function relation_sort_asc_generates_with_aggregate(): void
    {
        $sql = $this
            ->createEloquentWizardWithSorts('relatedName')
            ->allowedSorts(EloquentSort::relation('relatedModels', 'name', 'max')->alias('relatedName'))
            ->toQuery()
            ->toSql();

        $sqlLower = strtolower($sql);
        $this->assertStringContainsString('max', $sqlLower);
        $this->assertStringContainsString('order by', $sqlLower);
        $this->assertStringContainsString('asc', $sqlLower);
    }

    #[Test]
    public function relation_sort_desc_generates_with_aggregate(): void
    {
        $sql = $this
            ->createEloquentWizardWithSorts('-relatedName')
            ->allowedSorts(EloquentSort::relation('relatedModels', 'name', 'max')->alias('relatedName'))
            ->toQuery()
            ->toSql();

        $sqlLower = strtolower($sql);
        $this->assertStringContainsString('max', $sqlLower);
        $this->assertStringContainsString('desc', $sqlLower);
    }

    #[Test]
    public function relation_sort_with_sum_aggregate(): void
    {
        $sql = $this
            ->createEloquentWizardWithSorts('totalName')
            ->allowedSorts(EloquentSort::relation('relatedModels', 'name', 'sum')->alias('totalName'))
            ->toQuery()
            ->toSql();

        $sqlLower = strtolower($sql);
        $this->assertStringContainsString('sum', $sqlLower);
    }

    #[Test]
    public function relation_sort_with_avg_aggregate(): void
    {
        $sql = $this
            ->createEloquentWizardWithSorts('avgName')
            ->allowedSorts(EloquentSort::relation('relatedModels', 'name', 'avg')->alias('avgName'))
            ->toQuery()
            ->toSql();

        $sqlLower = strtolower($sql);
        $this->assertStringContainsString('avg', $sqlLower);
    }

    #[Test]
    public function relation_sort_with_min_aggregate(): void
    {
        $sql = $this
            ->createEloquentWizardWithSorts('minName')
            ->allowedSorts(EloquentSort::relation('relatedModels', 'name', 'min')->alias('minName'))
            ->toQuery()
            ->toSql();

        $sqlLower = strtolower($sql);
        $this->assertStringContainsString('min', $sqlLower);
    }

    #[Test]
    public function relation_sort_column_name_follows_convention(): void
    {
        $sql = $this
            ->createEloquentWizardWithSorts('relatedName')
            ->allowedSorts(EloquentSort::relation('relatedModels', 'name', 'max')->alias('relatedName'))
            ->toQuery()
            ->toSql();

        $this->assertStringContainsString('related_models_max_name', $sql);
    }
}
