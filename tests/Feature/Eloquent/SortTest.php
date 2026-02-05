<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Jackardios\QueryWizard\Eloquent\EloquentSort;
use Jackardios\QueryWizard\Exceptions\InvalidSortQuery;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;

#[Group('eloquent')]
#[Group('sort')]
class SortTest extends TestCase
{
    protected Collection $models;

    public function setUp(): void
    {
        parent::setUp();

        DB::enableQueryLog();

        // Create models with predictable ordering
        $this->models = collect();
        $this->models->push(TestModel::factory()->create(['name' => 'Alpha']));
        $this->models->push(TestModel::factory()->create(['name' => 'Beta']));
        $this->models->push(TestModel::factory()->create(['name' => 'Gamma']));
        $this->models->push(TestModel::factory()->create(['name' => 'Delta']));
        $this->models->push(TestModel::factory()->create(['name' => 'Epsilon']));
    }

    // ========== Basic Sort Tests ==========
    #[Test]
    public function it_can_sort_by_field_ascending(): void
    {
        $models = $this
            ->createEloquentWizardWithSorts('name')
            ->allowedSorts('name')
            ->get();

        // Models are sorted alphabetically by name
        $sortedNames = $models->pluck('name')->values()->all();
        $expectedOrder = ['Alpha', 'Beta', 'Delta', 'Epsilon', 'Gamma'];
        $this->assertEquals($expectedOrder, $sortedNames);
    }
    #[Test]
    public function it_can_sort_by_field_descending(): void
    {
        $models = $this
            ->createEloquentWizardWithSorts('-name')
            ->allowedSorts('name')
            ->get();

        $this->assertEquals('Gamma', $models->first()->name);
        $this->assertEquals('Alpha', $models->last()->name);
    }
    #[Test]
    public function it_can_sort_by_field_with_definition(): void
    {
        $models = $this
            ->createEloquentWizardWithSorts('name')
            ->allowedSorts(EloquentSort::field('name'))
            ->get();

        $this->assertEquals('Alpha', $models->first()->name);
    }
    #[Test]
    public function it_can_sort_by_id_ascending(): void
    {
        $models = $this
            ->createEloquentWizardWithSorts('id')
            ->allowedSorts('id')
            ->get();

        $this->assertEquals(1, $models->first()->id);
        $this->assertEquals(5, $models->last()->id);
    }
    #[Test]
    public function it_can_sort_by_id_descending(): void
    {
        $models = $this
            ->createEloquentWizardWithSorts('-id')
            ->allowedSorts('id')
            ->get();

        $this->assertEquals(5, $models->first()->id);
        $this->assertEquals(1, $models->last()->id);
    }

    // ========== Alias Tests ==========
    #[Test]
    public function it_can_sort_with_alias(): void
    {
        $models = $this
            ->createEloquentWizardWithSorts('fullName')
            ->allowedSorts(EloquentSort::field('name')->alias('fullName'))
            ->get();

        $this->assertEquals('Alpha', $models->first()->name);
    }
    #[Test]
    public function it_can_sort_descending_with_alias(): void
    {
        $models = $this
            ->createEloquentWizardWithSorts('-fullName')
            ->allowedSorts(EloquentSort::field('name')->alias('fullName'))
            ->get();

        $this->assertEquals('Gamma', $models->first()->name);
    }
    #[Test]
    public function alias_with_negative_prefix_in_definition(): void
    {
        $models = $this
            ->createEloquentWizardWithSorts('-createdAt')
            ->allowedSorts(EloquentSort::field('created_at')->alias('createdAt'))
            ->get();

        // Should work normally
        $this->assertCount(5, $models);
    }

    // ========== Callback Sort Tests ==========
    #[Test]
    public function it_can_sort_by_callback(): void
    {
        $models = $this
            ->createEloquentWizardWithSorts('custom')
            ->allowedSorts(
                EloquentSort::callback('custom', function ($query, $direction) {
                    $query->orderBy('name', $direction);
                })
            )
            ->get();

        $this->assertEquals('Alpha', $models->first()->name);
    }
    #[Test]
    public function callback_sort_respects_direction(): void
    {
        $models = $this
            ->createEloquentWizardWithSorts('-custom')
            ->allowedSorts(
                EloquentSort::callback('custom', function ($query, $direction) {
                    $query->orderBy('name', $direction);
                })
            )
            ->get();

        $this->assertEquals('Gamma', $models->first()->name);
    }
    #[Test]
    public function callback_sort_receives_correct_direction(): void
    {
        $receivedDirection = null;

        $this
            ->createEloquentWizardWithSorts('-custom')
            ->allowedSorts(
                EloquentSort::callback('custom', function ($query, $direction) use (&$receivedDirection) {
                    $receivedDirection = $direction;
                    $query->orderBy('name', $direction);
                })
            )
            ->get();

        $this->assertEquals('desc', $receivedDirection);
    }
    #[Test]
    public function callback_sort_can_add_multiple_order_clauses(): void
    {
        $models = $this
            ->createEloquentWizardWithSorts('multi')
            ->allowedSorts(
                EloquentSort::callback('multi', function ($query, $direction) {
                    $query->orderBy('name', $direction)
                          ->orderBy('id', $direction);
                })
            )
            ->get();

        $this->assertEquals('Alpha', $models->first()->name);
    }
    #[Test]
    public function callback_sort_with_alias(): void
    {
        $models = $this
            ->createEloquentWizardWithSorts('sortName')
            ->allowedSorts(
                EloquentSort::callback('name', function ($query, $direction) {
                    $query->orderBy('name', $direction);
                })->alias('sortName')
            )
            ->get();

        $this->assertEquals('Alpha', $models->first()->name);
    }

    // ========== Multiple Sorts Tests ==========
    #[Test]
    public function it_can_sort_by_multiple_fields(): void
    {
        // Create models with same name to test secondary sort
        TestModel::factory()->create(['name' => 'Alpha']);
        TestModel::factory()->create(['name' => 'Alpha']);

        $models = $this
            ->createEloquentWizardWithSorts('name,-id')
            ->allowedSorts('name', 'id')
            ->get();

        $alphas = $models->where('name', 'Alpha');
        // First Alpha should have higher ID (sorted descending by ID)
        $ids = $alphas->pluck('id')->values()->all();
        $this->assertEquals($ids, collect($ids)->sortDesc()->values()->all());
    }
    #[Test]
    public function it_can_sort_by_multiple_fields_as_array(): void
    {
        $models = $this
            ->createEloquentWizardWithSorts(['name', '-id'])
            ->allowedSorts('name', 'id')
            ->get();

        $this->assertEquals('Alpha', $models->first()->name);
    }
    #[Test]
    public function it_applies_sorts_in_order(): void
    {
        TestModel::factory()->create(['name' => 'Alpha']);

        $models = $this
            ->createEloquentWizardWithSorts('name,id')
            ->allowedSorts('name', 'id')
            ->get();

        $alphas = $models->where('name', 'Alpha')->values();
        // IDs should be in ascending order (sorted by name ASC, then id ASC)
        $ids = $alphas->pluck('id')->values()->all();
        $this->assertEquals($ids, collect($ids)->sort()->values()->all());
    }

    // ========== Default Sort Tests ==========
    #[Test]
    public function it_uses_default_sorts_when_none_requested(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery()
            ->allowedSorts('name', 'id')
            ->defaultSorts('-id')
            ->get();

        $this->assertEquals(5, $models->first()->id);
    }
    #[Test]
    public function it_uses_multiple_default_sorts(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery()
            ->allowedSorts('name', 'id')
            ->defaultSorts('name', '-id')
            ->get();

        $this->assertEquals('Alpha', $models->first()->name);
    }
    #[Test]
    public function explicit_sort_overrides_default(): void
    {
        $models = $this
            ->createEloquentWizardWithSorts('name')
            ->allowedSorts('name', 'id')
            ->defaultSorts('-id')
            ->get();

        // Should use name sort, not default -id
        $this->assertEquals('Alpha', $models->first()->name);
    }
    #[Test]
    public function default_sort_with_definition(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery()
            ->allowedSorts(EloquentSort::field('name'))
            ->defaultSorts('-name')
            ->get();

        $this->assertEquals('Gamma', $models->first()->name);
    }

    // ========== Validation Tests ==========
    #[Test]
    public function it_throws_exception_for_not_allowed_sort(): void
    {
        $this->expectException(InvalidSortQuery::class);

        $this
            ->createEloquentWizardWithSorts('not_allowed')
            ->allowedSorts('name')
            ->get();
    }
    #[Test]
    public function it_throws_exception_for_not_allowed_descending_sort(): void
    {
        $this->expectException(InvalidSortQuery::class);

        $this
            ->createEloquentWizardWithSorts('-not_allowed')
            ->allowedSorts('name')
            ->get();
    }
    #[Test]
    public function it_ignores_unknown_sorts_when_no_allowed_set(): void
    {
        $models = $this
            ->createEloquentWizardWithSorts('unknown')
            ->get();

        // No exception, returns all models in default order
        $this->assertCount(5, $models);
    }
    #[Test]
    public function it_throws_exception_with_empty_allowed_sorts_array(): void
    {
        // Empty allowed array means nothing is allowed - strict validation
        $this->expectException(InvalidSortQuery::class);

        $this
            ->createEloquentWizardWithSorts('name')
            ->allowedSorts([])
            ->get();
    }

    #[Test]
    public function it_ignores_not_allowed_sort_when_exception_disabled(): void
    {
        config()->set('query-wizard.disable_invalid_sort_query_exception', true);

        $models = $this
            ->createEloquentWizardWithSorts('not_allowed')
            ->allowedSorts('name')
            ->get();

        // No exception, returns all models in default order
        $this->assertCount(5, $models);
    }

    #[Test]
    public function it_ignores_sorts_with_empty_array_when_exception_disabled(): void
    {
        config()->set('query-wizard.disable_invalid_sort_query_exception', true);

        $models = $this
            ->createEloquentWizardWithSorts('name')
            ->allowedSorts([])
            ->get();

        // No exception, returns all models without sorting
        $this->assertCount(5, $models);
    }

    // ========== SQL Verification Tests ==========
    #[Test]
    public function it_adds_order_by_clause_to_sql(): void
    {
        $sql = $this
            ->createEloquentWizardWithSorts('name')
            ->allowedSorts('name')
            ->toQuery()
            ->toSql();

        // Verify ORDER BY is added with qualified column name
        $this->assertStringContainsString('order by', strtolower($sql));
        $this->assertStringContainsString('"test_models"."name"', $sql);
    }
    #[Test]
    public function it_uses_asc_direction_by_default(): void
    {
        $sql = $this
            ->createEloquentWizardWithSorts('name')
            ->allowedSorts('name')
            ->toQuery()
            ->toSql();

        $this->assertStringContainsString('asc', strtolower($sql));
    }
    #[Test]
    public function it_uses_desc_direction_with_minus_prefix(): void
    {
        $sql = $this
            ->createEloquentWizardWithSorts('-name')
            ->allowedSorts('name')
            ->toQuery()
            ->toSql();

        $this->assertStringContainsString('desc', strtolower($sql));
    }

    // ========== Edge Cases ==========
    #[Test]
    public function it_handles_empty_sort_string(): void
    {
        $models = $this
            ->createEloquentWizardWithSorts('')
            ->allowedSorts('name')
            ->get();

        // Empty sort, returns all
        $this->assertCount(5, $models);
    }
    #[Test]
    public function it_removes_duplicate_sorts(): void
    {
        $models = $this
            ->createEloquentWizardWithSorts('name,name,-name')
            ->allowedSorts('name')
            ->get();

        // Only first sort should be used
        $this->assertEquals('Alpha', $models->first()->name);
    }
    #[Test]
    public function it_handles_sort_with_trailing_comma(): void
    {
        $models = $this
            ->createEloquentWizardWithSorts('name,')
            ->allowedSorts('name')
            ->get();

        $this->assertCount(5, $models);
    }
    #[Test]
    public function it_can_sort_by_empty_string_values(): void
    {
        // Create model with empty name
        TestModel::factory()->create(['name' => '']);

        $models = $this
            ->createEloquentWizardWithSorts('name')
            ->allowedSorts('name')
            ->get();

        // Empty string sorts first in ascending order
        $this->assertEquals('', $models->first()->name);
    }
    #[Test]
    public function it_can_sort_by_created_at(): void
    {
        $sql = $this
            ->createEloquentWizardWithSorts('-created_at')
            ->allowedSorts('created_at')
            ->toQuery()
            ->toSql();

        // Verify ORDER BY created_at DESC is added
        // (actual ordering depends on timestamp precision)
        $this->assertStringContainsString('order by', strtolower($sql));
        $this->assertStringContainsString('"created_at"', $sql);
        $this->assertStringContainsString('desc', strtolower($sql));
    }

    // ========== Mixed Definitions Tests ==========
    #[Test]
    public function it_can_mix_string_and_definition_sorts(): void
    {
        $models = $this
            ->createEloquentWizardWithSorts('name')
            ->allowedSorts(
                'id',
                EloquentSort::field('name')
            )
            ->get();

        $this->assertEquals('Alpha', $models->first()->name);
    }
    #[Test]
    public function it_can_mix_field_and_callback_sorts(): void
    {
        $models = $this
            ->createEloquentWizardWithSorts('custom')
            ->allowedSorts(
                'name',
                EloquentSort::callback('custom', fn($q, $dir) => $q->orderBy('name', $dir))
            )
            ->get();

        $this->assertEquals('Alpha', $models->first()->name);
    }

    // ========== Integration with Other Features ==========
    #[Test]
    public function it_works_with_pagination(): void
    {
        $result = $this
            ->createEloquentWizardWithSorts('-name')
            ->allowedSorts('name')
            ->toQuery()
            ->paginate(2);

        $this->assertEquals('Gamma', $result->first()->name);
        $this->assertEquals(5, $result->total());
    }
    #[Test]
    public function it_works_with_first(): void
    {
        $model = $this
            ->createEloquentWizardWithSorts('-id')
            ->allowedSorts('id')
            ->toQuery()
            ->first();

        $this->assertEquals(5, $model->id);
    }
    #[Test]
    public function it_preserves_sort_through_cloning(): void
    {
        $wizard = $this
            ->createEloquentWizardWithSorts('name')
            ->allowedSorts('name');

        $first = $wizard->toQuery()->get();
        $second = $wizard->toQuery()->get();

        $this->assertEquals($first->first()->id, $second->first()->id);
    }

    // ========== Snake Case / Camel Case Tests ==========
    #[Test]
    public function it_handles_snake_case_sort(): void
    {
        $models = $this
            ->createEloquentWizardWithSorts('created_at')
            ->allowedSorts('created_at')
            ->get();

        $this->assertCount(5, $models);
    }
    #[Test]
    public function it_handles_camel_case_alias(): void
    {
        $models = $this
            ->createEloquentWizardWithSorts('createdAt')
            ->allowedSorts(EloquentSort::field('created_at')->alias('createdAt'))
            ->get();

        $this->assertCount(5, $models);
    }
}
