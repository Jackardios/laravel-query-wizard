<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Jackardios\QueryWizard\Contracts\ResourceSchemaInterface;
use Jackardios\QueryWizard\Contracts\SchemaContextInterface;
use Jackardios\QueryWizard\Drivers\Eloquent\Definitions\FilterDefinition;
use Jackardios\QueryWizard\Drivers\Eloquent\Definitions\IncludeDefinition;
use Jackardios\QueryWizard\Drivers\Eloquent\Definitions\SortDefinition;
use Jackardios\QueryWizard\QueryParametersManager;
use Jackardios\QueryWizard\QueryWizard;
use Jackardios\QueryWizard\Schema\ResourceSchema;
use Jackardios\QueryWizard\Schema\SchemaContext;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use Jackardios\QueryWizard\Wizards\ItemQueryWizard;
use Jackardios\QueryWizard\Wizards\ListQueryWizard;

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

    // ========== QueryWizard::for() Tests ==========
    #[Test]
    public function it_creates_list_wizard_with_for(): void
    {
        $wizard = QueryWizard::for(TestModel::class);

        $this->assertInstanceOf(ListQueryWizard::class, $wizard);
    }
    #[Test]
    public function it_creates_list_wizard_with_builder(): void
    {
        $wizard = QueryWizard::for(TestModel::query());

        $this->assertInstanceOf(ListQueryWizard::class, $wizard);
    }
    #[Test]
    public function it_creates_list_wizard_with_custom_parameters(): void
    {
        $params = new QueryParametersManager(new Request(['filter' => ['name' => 'test']]));
        $wizard = QueryWizard::for(TestModel::class, $params);

        $this->assertInstanceOf(ListQueryWizard::class, $wizard);
    }
    #[Test]
    public function for_wizard_can_get_results(): void
    {
        $models = QueryWizard::for(TestModel::class)->get();

        $this->assertCount(5, $models);
    }
    #[Test]
    public function for_wizard_can_use_filters(): void
    {
        $targetModel = $this->models->first();
        $params = new QueryParametersManager(new Request(['filter' => ['name' => $targetModel->name]]));

        $models = QueryWizard::for(TestModel::class, $params)
            ->setAllowedFilters('name')
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals($targetModel->id, $models->first()->id);
    }
    #[Test]
    public function for_wizard_can_use_sorts(): void
    {
        $params = new QueryParametersManager(new Request(['sort' => '-id']));

        $models = QueryWizard::for(TestModel::class, $params)
            ->setAllowedSorts('id')
            ->get();

        $this->assertEquals(5, $models->first()->id);
    }
    #[Test]
    public function for_wizard_can_use_includes(): void
    {
        $params = new QueryParametersManager(new Request(['include' => 'relatedModels']));

        $models = QueryWizard::for(TestModel::class, $params)
            ->setAllowedIncludes('relatedModels')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
    }

    // ========== QueryWizard::using() Tests ==========
    #[Test]
    public function it_creates_wizard_with_explicit_driver(): void
    {
        $wizard = QueryWizard::using('eloquent', TestModel::class);

        $this->assertInstanceOf(ListQueryWizard::class, $wizard);
    }
    #[Test]
    public function using_wizard_works_correctly(): void
    {
        $models = QueryWizard::using('eloquent', TestModel::class)->get();

        $this->assertCount(5, $models);
    }

    // ========== QueryWizard::forList() with Schema Tests ==========
    #[Test]
    public function it_creates_list_wizard_from_schema_class(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return TestModel::class;
            }
        };

        $wizard = QueryWizard::forList($schema);

        $this->assertInstanceOf(ListQueryWizard::class, $wizard);
    }
    #[Test]
    public function list_wizard_uses_schema_filters(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return TestModel::class;
            }

            public function filters(): array
            {
                return ['name', 'id'];
            }
        };

        $targetModel = $this->models->first();
        $params = new QueryParametersManager(new Request(['filter' => ['name' => $targetModel->name]]));

        $models = QueryWizard::forList($schema, $params)->get();

        $this->assertCount(1, $models);
    }
    #[Test]
    public function list_wizard_uses_schema_sorts(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return TestModel::class;
            }

            public function sorts(): array
            {
                return ['name', 'id'];
            }
        };

        $params = new QueryParametersManager(new Request(['sort' => '-id']));
        $models = QueryWizard::forList($schema, $params)->get();

        $this->assertEquals(5, $models->first()->id);
    }
    #[Test]
    public function list_wizard_uses_schema_includes(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return TestModel::class;
            }

            public function includes(): array
            {
                return ['relatedModels'];
            }
        };

        $params = new QueryParametersManager(new Request(['include' => 'relatedModels']));
        $models = QueryWizard::forList($schema, $params)->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
    }
    #[Test]
    public function list_wizard_uses_schema_defaults(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return TestModel::class;
            }

            public function sorts(): array
            {
                return ['id'];
            }

            public function defaultSorts(): array
            {
                return ['-id'];
            }
        };

        $models = QueryWizard::forList($schema)->get();

        $this->assertEquals(5, $models->first()->id);
    }

    // ========== QueryWizard::forItem() Tests ==========
    #[Test]
    public function it_creates_item_wizard_from_schema(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return TestModel::class;
            }
        };

        $wizard = QueryWizard::forItem($schema, 1);

        $this->assertInstanceOf(ItemQueryWizard::class, $wizard);
    }
    #[Test]
    public function item_wizard_can_get_model_by_id(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return TestModel::class;
            }
        };

        $targetModel = $this->models->first();
        $model = QueryWizard::forItem($schema, $targetModel->id)->get();

        $this->assertNotNull($model);
        $this->assertEquals($targetModel->id, $model->id);
    }
    #[Test]
    public function item_wizard_returns_null_for_non_existent_id(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return TestModel::class;
            }
        };

        $model = QueryWizard::forItem($schema, 9999)->get();

        $this->assertNull($model);
    }
    #[Test]
    public function item_wizard_throws_on_getOrFail_for_non_existent(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return TestModel::class;
            }
        };

        $this->expectException(ModelNotFoundException::class);

        QueryWizard::forItem($schema, 9999)->getOrFail();
    }
    #[Test]
    public function item_wizard_can_process_loaded_model(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return TestModel::class;
            }

            public function includes(): array
            {
                return ['relatedModels'];
            }
        };

        // Load model with relations
        $loadedModel = TestModel::with('relatedModels', 'otherRelatedModels')->first();

        // Process it - should keep only allowed includes
        $model = QueryWizard::forItem($schema, $loadedModel)->get();

        $this->assertTrue($model->relationLoaded('relatedModels'));
        // otherRelatedModels should be removed as it's not in schema
        $this->assertFalse($model->relationLoaded('otherRelatedModels'));
    }
    #[Test]
    public function item_wizard_applies_includes(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return TestModel::class;
            }

            public function includes(): array
            {
                return ['relatedModels'];
            }
        };

        $params = new QueryParametersManager(new Request(['include' => 'relatedModels']));
        $model = QueryWizard::forItem($schema, $this->models->first()->id, $params)->get();

        $this->assertTrue($model->relationLoaded('relatedModels'));
    }

    // ========== ListQueryWizard Methods Tests ==========
    #[Test]
    public function list_wizard_query_method_sets_custom_builder(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return TestModel::class;
            }
        };

        $models = QueryWizard::forList($schema)
            ->query(TestModel::where('id', '<', 3))
            ->get();

        $this->assertCount(2, $models);
    }
    #[Test]
    public function list_wizard_modifyQuery_applies_callback(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return TestModel::class;
            }
        };

        $models = QueryWizard::forList($schema)
            ->modifyQuery(fn(Builder $query) => $query->where('id', '<', 3))
            ->get();

        $this->assertCount(2, $models);
    }
    #[Test]
    public function list_wizard_build_returns_builder(): void
    {
        $wizard = QueryWizard::for(TestModel::class);
        $builder = $wizard->build();

        $this->assertInstanceOf(Builder::class, $builder);
    }
    #[Test]
    public function list_wizard_first_returns_single_model(): void
    {
        $params = new QueryParametersManager(new Request(['sort' => 'id']));
        $model = QueryWizard::for(TestModel::class, $params)
            ->setAllowedSorts('id')
            ->first();

        $this->assertInstanceOf(TestModel::class, $model);
        $this->assertEquals(1, $model->id);
    }
    #[Test]
    public function list_wizard_paginate_works(): void
    {
        $result = QueryWizard::for(TestModel::class)->paginate(2);

        $this->assertEquals(5, $result->total());
        $this->assertCount(2, $result->items());
    }
    #[Test]
    public function list_wizard_simplePaginate_works(): void
    {
        $result = QueryWizard::for(TestModel::class)->simplePaginate(2);

        $this->assertCount(2, $result->items());
    }
    #[Test]
    public function list_wizard_cursorPaginate_works(): void
    {
        $result = QueryWizard::for(TestModel::class)->cursorPaginate(2);

        $this->assertCount(2, $result->items());
    }
    #[Test]
    public function list_wizard_getFilters_returns_prepared_values(): void
    {
        $params = new QueryParametersManager(new Request(['filter' => ['name' => 'test']]));

        $wizard = QueryWizard::for(TestModel::class, $params)
            ->setAllowedFilters(FilterDefinition::exact('name'));

        $filters = $wizard->getFilters();

        $this->assertEquals('test', $filters->get('name'));
    }
    #[Test]
    public function list_wizard_getIncludes_returns_requested(): void
    {
        $params = new QueryParametersManager(new Request(['include' => 'relatedModels,otherRelatedModels']));

        $wizard = QueryWizard::for(TestModel::class, $params);
        $includes = $wizard->getIncludes();

        $this->assertEquals(['relatedModels', 'otherRelatedModels'], $includes->all());
    }
    #[Test]
    public function list_wizard_getSorts_returns_sort_objects(): void
    {
        $params = new QueryParametersManager(new Request(['sort' => '-name,id']));

        $wizard = QueryWizard::for(TestModel::class, $params);
        $sorts = $wizard->getSorts();

        $this->assertCount(2, $sorts);
        $this->assertEquals('name', $sorts[0]->getField());
        $this->assertEquals('desc', $sorts[0]->getDirection());
    }

    // ========== Schema with Definitions Tests ==========
    #[Test]
    public function schema_can_use_filter_definitions(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return TestModel::class;
            }

            public function filters(): array
            {
                return [
                    FilterDefinition::partial('name'),
                    FilterDefinition::exact('id'),
                ];
            }
        };

        $targetModel = $this->models->first();
        $params = new QueryParametersManager(new Request([
            'filter' => ['name' => substr($targetModel->name, 0, 3)],
        ]));

        $models = QueryWizard::forList($schema, $params)->get();

        $this->assertGreaterThanOrEqual(1, $models->count());
    }
    #[Test]
    public function schema_can_use_include_definitions(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return TestModel::class;
            }

            public function includes(): array
            {
                return [
                    IncludeDefinition::relationship('relatedModels'),
                    IncludeDefinition::count('otherRelatedModels'),
                ];
            }
        };

        $params = new QueryParametersManager(new Request([
            'include' => 'relatedModels,otherRelatedModelsCount',
        ]));

        $models = QueryWizard::forList($schema, $params)->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
        $this->assertTrue(isset($models->first()->other_related_models_count));
    }
    #[Test]
    public function schema_can_use_sort_definitions(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return TestModel::class;
            }

            public function sorts(): array
            {
                return [
                    SortDefinition::field('name'),
                    SortDefinition::callback('custom', fn($q, $dir) => $q->orderBy('id', $dir)),
                ];
            }
        };

        $params = new QueryParametersManager(new Request(['sort' => 'custom']));
        $models = QueryWizard::forList($schema, $params)->get();

        $this->assertEquals(1, $models->first()->id);
    }

    // ========== Schema Context Tests ==========
    #[Test]
    public function schema_forList_context_is_applied(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return TestModel::class;
            }

            public function includes(): array
            {
                return ['relatedModels', 'otherRelatedModels'];
            }

            public function forList(): ?SchemaContextInterface
            {
                return SchemaContext::make()
                    ->disallowIncludes(['otherRelatedModels']);
            }
        };

        $params = new QueryParametersManager(new Request(['include' => 'relatedModels']));
        $models = QueryWizard::forList($schema, $params)->get();

        // Should work since relatedModels is not disallowed
        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
    }
    #[Test]
    public function schema_forItem_context_is_applied(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return TestModel::class;
            }

            public function includes(): array
            {
                return ['relatedModels', 'otherRelatedModels'];
            }

            public function defaultIncludes(): array
            {
                return [];
            }

            public function forItem(): ?SchemaContextInterface
            {
                return SchemaContext::make()
                    ->defaultIncludes(['relatedModels']);
            }
        };

        $model = QueryWizard::forItem($schema, $this->models->first()->id)->get();

        // Default includes from forItem context should be applied
        $this->assertTrue($model->relationLoaded('relatedModels'));
    }

    // ========== Magic Methods Tests ==========
    #[Test]
    public function list_wizard_proxies_method_calls_to_builder(): void
    {
        $count = QueryWizard::for(TestModel::class)->count();

        $this->assertEquals(5, $count);
    }
    #[Test]
    public function list_wizard_proxies_where_clauses(): void
    {
        $models = QueryWizard::for(TestModel::class)
            ->where('id', '<', 3)
            ->get();

        $this->assertCount(2, $models);
    }

    // ========== ListQueryWizard Idempotency Tests ==========
    #[Test]
    public function list_wizard_build_is_idempotent(): void
    {
        $params = new QueryParametersManager(new Request([
            'filter' => ['id' => 1],
            'sort' => 'name',
        ]));

        $wizard = QueryWizard::for(TestModel::class, $params)
            ->setAllowedFilters('id')
            ->setAllowedSorts('name');

        $builder1 = $wizard->build();
        $builder2 = $wizard->build();

        // Same builder instance, operations applied only once
        $this->assertSame($builder1, $builder2);
    }

    #[Test]
    public function list_wizard_operations_applied_only_once(): void
    {
        DB::flushQueryLog();

        $params = new QueryParametersManager(new Request(['sort' => 'name']));

        $wizard = QueryWizard::for(TestModel::class, $params)
            ->setAllowedSorts('name');

        // Call build multiple times
        $wizard->build();
        $wizard->build();
        $wizard->get();

        // Verify only one query executed (operations not reapplied)
        $queryLog = DB::getQueryLog();
        $this->assertCount(1, $queryLog);
    }

    #[Test]
    public function list_wizard_query_method_allows_chaining(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return TestModel::class;
            }
        };

        $result = QueryWizard::forList($schema)
            ->query(TestModel::where('id', '>', 0))
            ->where('id', '<', 10)
            ->get();

        $this->assertNotEmpty($result);
    }

    #[Test]
    public function list_wizard_query_method_replaces_underlying_builder(): void
    {
        $wizard = QueryWizard::for(TestModel::class);

        // Set a new query with where clause
        $wizard->query(TestModel::where('id', '>', 2));

        // Build should use the new query
        $builder = $wizard->build();
        $sql = $builder->toSql();

        $this->assertStringContainsString('"id" > ?', $sql);
    }

    #[Test]
    public function list_wizard_clone_creates_independent_instance(): void
    {
        $wizard = QueryWizard::for(TestModel::class)
            ->setAllowedFilters('name')
            ->setAllowedSorts('id');

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
    public function list_wizard_modifyQuery_applies_before_wizard_operations(): void
    {
        $params = new QueryParametersManager(new Request(['sort' => '-id']));

        $models = QueryWizard::for(TestModel::class, $params)
            ->setAllowedSorts('id')
            ->modifyQuery(fn($query) => $query->where('id', '<=', 3))
            ->get();

        $this->assertCount(3, $models);
        $this->assertEquals(3, $models->first()->id); // Sorted desc
    }
}
