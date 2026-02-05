<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Jackardios\QueryWizard\Contracts\QueryWizardInterface;
use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Eloquent\EloquentInclude;
use Jackardios\QueryWizard\Eloquent\EloquentQueryWizard;
use Jackardios\QueryWizard\Eloquent\EloquentSort;
use Jackardios\QueryWizard\QueryParametersManager;
use Jackardios\QueryWizard\Schema\ResourceSchema;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;

#[Group('eloquent')]
#[Group('wizard')]
class QueryWizardTest extends TestCase
{
    protected Collection $models;

    public function setUp(): void
    {
        parent::setUp();

        DB::enableQueryLog();

        $this->models = TestModel::factory()->count(5)->create();

        // Create related models
        $this->models->each(function (TestModel $model) {
            RelatedModel::factory()->count(2)->create([
                'test_model_id' => $model->id,
            ]);
        });
    }

    // ========== EloquentQueryWizard::for() Tests ==========
    #[Test]
    public function it_creates_wizard_with_for(): void
    {
        $wizard = EloquentQueryWizard::for(TestModel::class);

        $this->assertInstanceOf(EloquentQueryWizard::class, $wizard);
    }
    #[Test]
    public function it_creates_wizard_with_builder(): void
    {
        $wizard = EloquentQueryWizard::for(TestModel::query());

        $this->assertInstanceOf(EloquentQueryWizard::class, $wizard);
    }
    #[Test]
    public function it_creates_wizard_with_custom_parameters(): void
    {
        $params = new QueryParametersManager(new Request(['filter' => ['name' => 'test']]));
        $wizard = new EloquentQueryWizard(TestModel::query(), $params);

        $this->assertInstanceOf(EloquentQueryWizard::class, $wizard);
    }
    #[Test]
    public function for_wizard_can_get_results(): void
    {
        $models = EloquentQueryWizard::for(TestModel::class)->get();

        $this->assertCount(5, $models);
    }
    #[Test]
    public function for_wizard_can_use_filters(): void
    {
        $targetModel = $this->models->first();
        $params = new QueryParametersManager(new Request(['filter' => ['name' => $targetModel->name]]));

        $models = (new EloquentQueryWizard(TestModel::query(), $params))
            ->allowedFilters('name')
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals($targetModel->id, $models->first()->id);
    }
    #[Test]
    public function for_wizard_can_use_sorts(): void
    {
        $params = new QueryParametersManager(new Request(['sort' => '-id']));

        $models = (new EloquentQueryWizard(TestModel::query(), $params))
            ->allowedSorts('id')
            ->get();

        $this->assertEquals(5, $models->first()->id);
    }
    #[Test]
    public function for_wizard_can_use_includes(): void
    {
        $params = new QueryParametersManager(new Request(['include' => 'relatedModels']));

        $models = (new EloquentQueryWizard(TestModel::query(), $params))
            ->allowedIncludes('relatedModels')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
    }

    // ========== EloquentQueryWizard::forSchema() Tests ==========
    #[Test]
    public function it_creates_wizard_from_schema_class(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return TestModel::class;
            }
        };

        $wizard = EloquentQueryWizard::forSchema($schema);

        $this->assertInstanceOf(EloquentQueryWizard::class, $wizard);
    }
    #[Test]
    public function wizard_uses_schema_filters(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return TestModel::class;
            }

            public function filters(QueryWizardInterface $wizard): array
            {
                return ['name', 'id'];
            }
        };

        $targetModel = $this->models->first();
        $params = new QueryParametersManager(new Request(['filter' => ['name' => $targetModel->name]]));

        $models = (new EloquentQueryWizard(TestModel::query(), $params, null, $schema))->get();

        $this->assertCount(1, $models);
    }
    #[Test]
    public function wizard_uses_schema_sorts(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return TestModel::class;
            }

            public function sorts(QueryWizardInterface $wizard): array
            {
                return ['name', 'id'];
            }
        };

        $params = new QueryParametersManager(new Request(['sort' => '-id']));
        $models = (new EloquentQueryWizard(TestModel::query(), $params, null, $schema))->get();

        $this->assertEquals(5, $models->first()->id);
    }
    #[Test]
    public function wizard_uses_schema_includes(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return TestModel::class;
            }

            public function includes(QueryWizardInterface $wizard): array
            {
                return ['relatedModels'];
            }
        };

        $params = new QueryParametersManager(new Request(['include' => 'relatedModels']));
        $models = (new EloquentQueryWizard(TestModel::query(), $params, null, $schema))->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
    }
    #[Test]
    public function wizard_uses_schema_defaults(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return TestModel::class;
            }

            public function sorts(QueryWizardInterface $wizard): array
            {
                return ['id'];
            }

            public function defaultSorts(QueryWizardInterface $wizard): array
            {
                return ['-id'];
            }
        };

        $models = EloquentQueryWizard::forSchema($schema)->get();

        $this->assertEquals(5, $models->first()->id);
    }

    // ========== Wizard Methods Tests ==========
    #[Test]
    public function wizard_tap_applies_callback(): void
    {
        $models = EloquentQueryWizard::for(TestModel::class)
            ->tap(fn(Builder $query) => $query->where('id', '<', 3))
            ->get();

        $this->assertCount(2, $models);
    }
    #[Test]
    public function wizard_toQuery_returns_builder(): void
    {
        $wizard = EloquentQueryWizard::for(TestModel::class);
        $builder = $wizard->toQuery();

        $this->assertInstanceOf(Builder::class, $builder);
    }
    #[Test]
    public function wizard_first_returns_single_model(): void
    {
        $params = new QueryParametersManager(new Request(['sort' => 'id']));
        $model = (new EloquentQueryWizard(TestModel::query(), $params))
            ->allowedSorts('id')
            ->first();

        $this->assertInstanceOf(TestModel::class, $model);
        $this->assertEquals(1, $model->id);
    }
    #[Test]
    public function wizard_paginate_works(): void
    {
        $result = EloquentQueryWizard::for(TestModel::class)->paginate(2);

        $this->assertEquals(5, $result->total());
        $this->assertCount(2, $result->items());
    }
    #[Test]
    public function wizard_simplePaginate_works(): void
    {
        $result = EloquentQueryWizard::for(TestModel::class)->simplePaginate(2);

        $this->assertCount(2, $result->items());
    }
    #[Test]
    public function wizard_cursorPaginate_works(): void
    {
        $result = EloquentQueryWizard::for(TestModel::class)->cursorPaginate(2);

        $this->assertCount(2, $result->items());
    }
    #[Test]
    public function parameters_manager_returns_filters(): void
    {
        $params = new QueryParametersManager(new Request(['filter' => ['name' => 'test']]));

        // QueryParametersManager provides access to request parameters
        $filters = $params->getFilters();

        $this->assertEquals('test', $filters->get('name'));
    }
    #[Test]
    public function parameters_manager_returns_includes(): void
    {
        $params = new QueryParametersManager(new Request(['include' => 'relatedModels,otherRelatedModels']));

        $includes = $params->getIncludes();

        $this->assertEquals(['relatedModels', 'otherRelatedModels'], $includes->all());
    }
    #[Test]
    public function parameters_manager_returns_sorts(): void
    {
        $params = new QueryParametersManager(new Request(['sort' => '-name,id']));

        $sorts = $params->getSorts();

        $this->assertCount(2, $sorts);
        $this->assertEquals('name', $sorts[0]->getField());
        $this->assertEquals('desc', $sorts[0]->getDirection());
    }

    // ========== Schema with Definitions Tests ==========
    #[Test]
    public function schema_can_use_filter_instances(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return TestModel::class;
            }

            public function filters(QueryWizardInterface $wizard): array
            {
                return [
                    EloquentFilter::partial('name'),
                    EloquentFilter::exact('id'),
                ];
            }
        };

        $targetModel = $this->models->first();
        $params = new QueryParametersManager(new Request([
            'filter' => ['name' => substr($targetModel->name, 0, 3)],
        ]));

        $models = (new EloquentQueryWizard(TestModel::query(), $params, null, $schema))->get();

        $this->assertGreaterThanOrEqual(1, $models->count());
    }
    #[Test]
    public function schema_can_use_include_instances(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return TestModel::class;
            }

            public function includes(QueryWizardInterface $wizard): array
            {
                return [
                    EloquentInclude::relationship('relatedModels'),
                    EloquentInclude::count('otherRelatedModels'),
                ];
            }
        };

        $params = new QueryParametersManager(new Request([
            'include' => 'relatedModels,otherRelatedModelsCount',
        ]));

        $models = (new EloquentQueryWizard(TestModel::query(), $params, null, $schema))->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
        $this->assertTrue(isset($models->first()->other_related_models_count));
    }
    #[Test]
    public function schema_can_use_sort_instances(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return TestModel::class;
            }

            public function sorts(QueryWizardInterface $wizard): array
            {
                return [
                    EloquentSort::field('name'),
                    EloquentSort::callback('custom', fn($q, $dir) => $q->orderBy('id', $dir)),
                ];
            }
        };

        $params = new QueryParametersManager(new Request(['sort' => 'custom']));
        $models = (new EloquentQueryWizard(TestModel::query(), $params, null, $schema))->get();

        $this->assertEquals(1, $models->first()->id);
    }

    // ========== Magic Methods Tests ==========
    #[Test]
    public function wizard_proxies_method_calls_to_builder(): void
    {
        $count = EloquentQueryWizard::for(TestModel::class)->count();

        $this->assertEquals(5, $count);
    }
    #[Test]
    public function wizard_proxies_where_clauses(): void
    {
        $models = EloquentQueryWizard::for(TestModel::class)
            ->where('id', '<', 3)
            ->get();

        $this->assertCount(2, $models);
    }

    // ========== Idempotency Tests ==========
    #[Test]
    public function wizard_toQuery_is_idempotent(): void
    {
        $params = new QueryParametersManager(new Request([
            'filter' => ['id' => 1],
            'sort' => 'name',
        ]));

        $wizard = (new EloquentQueryWizard(TestModel::query(), $params))
            ->allowedFilters('id')
            ->allowedSorts('name');

        $builder1 = $wizard->toQuery();
        $builder2 = $wizard->toQuery();

        // Same builder instance, operations applied only once
        $this->assertSame($builder1, $builder2);
    }

    #[Test]
    public function wizard_operations_applied_only_once(): void
    {
        DB::flushQueryLog();

        $params = new QueryParametersManager(new Request(['sort' => 'name']));

        $wizard = (new EloquentQueryWizard(TestModel::query(), $params))
            ->allowedSorts('name');

        // Call toQuery multiple times
        $wizard->toQuery();
        $wizard->toQuery();
        $wizard->get();

        // Verify only one query executed (operations not reapplied)
        $queryLog = DB::getQueryLog();
        $this->assertCount(1, $queryLog);
    }

    #[Test]
    public function wizard_clone_creates_independent_instance(): void
    {
        $wizard = EloquentQueryWizard::for(TestModel::class)
            ->allowedFilters('name')
            ->allowedSorts('id');

        $clone = clone $wizard;

        // Modify original
        $wizard->where('id', 1);

        // Clone should be independent
        $cloneModels = $clone->get();
        $originalModels = $wizard->get();

        $this->assertCount(5, $cloneModels);
        $this->assertCount(1, $originalModels);
    }

    #[Test]
    public function wizard_tap_applies_before_wizard_operations(): void
    {
        $params = new QueryParametersManager(new Request(['sort' => '-id']));

        $models = (new EloquentQueryWizard(TestModel::query(), $params))
            ->allowedSorts('id')
            ->tap(fn($query) => $query->where('id', '<=', 3))
            ->get();

        $this->assertCount(3, $models);
        $this->assertEquals(3, $models->first()->id); // Sorted desc
    }

    // ========== Build State Invalidation Tests ==========

    #[Test]
    public function wizard_rebuilds_when_filters_added_after_build(): void
    {
        $targetModel = $this->models->first();
        $params = new QueryParametersManager(new Request([
            'filter' => ['name' => $targetModel->name],
        ]));

        $wizard = (new EloquentQueryWizard(TestModel::query(), $params))
            ->allowedFilters('name');

        // First build with name filter
        $firstResults = $wizard->get();
        $this->assertCount(1, $firstResults);
        $this->assertEquals($targetModel->id, $firstResults->first()->id);

        // Add additional filter
        $wizard->allowedFilters('name', 'id');

        // Second call should rebuild (not use cached result)
        // Since wizard clones subject on rebuild, we verify it re-runs the query
        DB::flushQueryLog();
        $secondResults = $wizard->get();

        // Query was executed again (not cached)
        $this->assertNotEmpty(DB::getQueryLog());
        $this->assertCount(1, $secondResults);
    }

    #[Test]
    public function wizard_rebuilds_when_sorts_added_after_build(): void
    {
        $params = new QueryParametersManager(new Request(['sort' => 'id']));

        $wizard = (new EloquentQueryWizard(TestModel::query(), $params))
            ->allowedSorts('id');

        // First build with asc sort
        $firstResults = $wizard->get();
        $this->assertEquals(1, $firstResults->first()->id);

        // Change to allow name sort too (triggers invalidateBuild)
        $wizard->allowedSorts('id', 'name');

        // Second build should re-apply
        DB::flushQueryLog();
        $secondResults = $wizard->get();

        // Query was executed again
        $this->assertNotEmpty(DB::getQueryLog());
        $this->assertEquals(1, $secondResults->first()->id);
    }

    #[Test]
    public function wizard_invalidates_build_state_when_config_changes(): void
    {
        $wizard = EloquentQueryWizard::for(TestModel::class);

        // First build
        $wizard->get();

        // Access protected property via reflection to verify built state
        $reflection = new \ReflectionClass($wizard);
        $builtProperty = $reflection->getProperty('built');
        $builtProperty->setAccessible(true);

        $this->assertTrue($builtProperty->getValue($wizard), 'Should be built after get()');

        // Change config - should invalidate build state
        $wizard->allowedFilters('name');

        $this->assertFalse($builtProperty->getValue($wizard), 'Should reset built after config change');

        // Test other config methods also invalidate
        $wizard->get(); // Re-build
        $this->assertTrue($builtProperty->getValue($wizard));

        $wizard->allowedSorts('id');
        $this->assertFalse($builtProperty->getValue($wizard));

        $wizard->get();
        $wizard->allowedIncludes('relatedModels');
        $this->assertFalse($builtProperty->getValue($wizard));

        $wizard->get();
        $wizard->defaultSorts('-id');
        $this->assertFalse($builtProperty->getValue($wizard));
    }
}
