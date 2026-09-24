<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Jackardios\QueryWizard\Contracts\QueryWizardInterface;
use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Eloquent\EloquentInclude;
use Jackardios\QueryWizard\Eloquent\EloquentQueryWizard;
use Jackardios\QueryWizard\Eloquent\EloquentSort;
use Jackardios\QueryWizard\Exceptions\InvalidAppendQuery;
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;
use Jackardios\QueryWizard\Exceptions\InvalidSortQuery;
use Jackardios\QueryWizard\QueryParametersManager;
use Jackardios\QueryWizard\Schema\ResourceSchema;
use Jackardios\QueryWizard\Tests\App\Models\AppendModel;
use Jackardios\QueryWizard\Tests\App\Models\AppendModelWithBuiltInAppends;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('eloquent')]
#[Group('wizard')]
class QueryWizardTest extends TestCase
{
    protected Collection $models;

    protected function setUp(): void
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
        $schema = new class extends ResourceSchema
        {
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
        $schema = new class extends ResourceSchema
        {
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
        $schema = new class extends ResourceSchema
        {
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
        $schema = new class extends ResourceSchema
        {
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
        $schema = new class extends ResourceSchema
        {
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
            ->tap(fn (Builder $query) => $query->where('id', '<', 3))
            ->get();

        $this->assertCount(2, $models);
    }

    #[Test]
    public function wizard_to_query_returns_builder(): void
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
    public function wizard_first_or_fail_returns_single_model(): void
    {
        $params = new QueryParametersManager(new Request(['sort' => 'id']));
        $model = (new EloquentQueryWizard(TestModel::query(), $params))
            ->allowedSorts('id')
            ->firstOrFail();

        $this->assertInstanceOf(TestModel::class, $model);
        $this->assertEquals(1, $model->id);
    }

    #[Test]
    public function wizard_first_or_fail_throws_exception_when_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        EloquentQueryWizard::for(TestModel::class)
            ->where('id', 99999)
            ->firstOrFail();
    }

    #[Test]
    public function wizard_paginate_works(): void
    {
        $result = EloquentQueryWizard::for(TestModel::class)->paginate(2);

        $this->assertEquals(5, $result->total());
        $this->assertCount(2, $result->items());
    }

    #[Test]
    public function wizard_simple_paginate_works(): void
    {
        $result = EloquentQueryWizard::for(TestModel::class)->simplePaginate(2);

        $this->assertCount(2, $result->items());
    }

    #[Test]
    public function wizard_cursor_paginate_works(): void
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
        $schema = new class extends ResourceSchema
        {
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
            'filter' => ['name' => $targetModel->name],
        ]));

        $models = (new EloquentQueryWizard(TestModel::query(), $params, null, $schema))->get();

        $this->assertTrue($models->contains('id', $targetModel->id));
        $this->assertNotEmpty($models);
    }

    #[Test]
    public function schema_can_use_include_instances(): void
    {
        $schema = new class extends ResourceSchema
        {
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
        $schema = new class extends ResourceSchema
        {
            public function model(): string
            {
                return TestModel::class;
            }

            public function sorts(QueryWizardInterface $wizard): array
            {
                return [
                    EloquentSort::field('name'),
                    EloquentSort::callback('custom', fn ($q, $dir) => $q->orderBy('id', $dir)),
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
    public function terminal_method_count_applies_filters(): void
    {
        $targetModel = $this->models->first();
        $params = new QueryParametersManager(new Request([
            'filter' => ['name' => $targetModel->name],
        ]));

        $count = (new EloquentQueryWizard(TestModel::query(), $params))
            ->allowedFilters('name')
            ->count();

        $this->assertEquals(1, $count);
    }

    #[Test]
    public function terminal_method_exists_applies_filters(): void
    {
        $params = new QueryParametersManager(new Request([
            'filter' => ['name' => 'nonexistent_name_xyz'],
        ]));

        $exists = (new EloquentQueryWizard(TestModel::query(), $params))
            ->allowedFilters('name')
            ->exists();

        $this->assertFalse($exists);
    }

    #[Test]
    public function wizard_proxies_where_clauses(): void
    {
        $models = EloquentQueryWizard::for(TestModel::class)
            ->where('id', '<', 3)
            ->get();

        $this->assertCount(2, $models);
    }

    #[Test]
    public function proxied_where_with_filters_applies_both(): void
    {
        $targetModel = $this->models->first();
        $params = new QueryParametersManager(new Request([
            'filter' => ['name' => $targetModel->name],
        ]));

        $count = (new EloquentQueryWizard(TestModel::query(), $params))
            ->allowedFilters('name')
            ->where('id', '<=', 3)
            ->count();

        $this->assertEquals(1, $count);
    }

    #[Test]
    public function multiple_proxied_wheres_build_only_once(): void
    {
        $models = EloquentQueryWizard::for(TestModel::class)
            ->where('id', '>', 1)
            ->where('id', '<', 4)
            ->get();

        $this->assertCount(2, $models);
    }

    #[Test]
    public function proxied_to_sql_applies_build(): void
    {
        $targetModel = $this->models->first();
        $params = new QueryParametersManager(new Request([
            'filter' => ['name' => $targetModel->name],
        ]));

        $sql = (new EloquentQueryWizard(TestModel::query(), $params))
            ->allowedFilters('name')
            ->toSql();

        $this->assertStringContainsString('where', strtolower($sql));
    }

    #[Test]
    public function proxied_doesnt_exist_applies_filters(): void
    {
        $targetModel = $this->models->first();
        $params = new QueryParametersManager(new Request([
            'filter' => ['name' => $targetModel->name],
        ]));

        $doesntExist = (new EloquentQueryWizard(TestModel::query(), $params))
            ->allowedFilters('name')
            ->doesntExist();

        $this->assertFalse($doesntExist);
    }

    #[Test]
    public function proxied_value_applies_filters(): void
    {
        $targetModel = $this->models->first();
        $params = new QueryParametersManager(new Request([
            'filter' => ['name' => $targetModel->name],
        ]));

        $name = (new EloquentQueryWizard(TestModel::query(), $params))
            ->allowedFilters('name')
            ->value('name');

        $this->assertEquals($targetModel->name, $name);
    }

    #[Test]
    public function proxied_pluck_applies_filters(): void
    {
        $targetModel = $this->models->first();
        $params = new QueryParametersManager(new Request([
            'filter' => ['name' => $targetModel->name],
        ]));

        $names = (new EloquentQueryWizard(TestModel::query(), $params))
            ->allowedFilters('name')
            ->pluck('name');

        $this->assertCount(1, $names);
        $this->assertEquals($targetModel->name, $names->first());
    }

    #[Test]
    public function config_after_proxied_where_throws_logic_exception(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot modify query wizard configuration after calling query builder methods (e.g. where(), orderBy())');

        EloquentQueryWizard::for(TestModel::class)
            ->where('id', 1)
            ->allowedFilters('name');
    }

    #[Test]
    public function config_before_proxied_where_works_correctly(): void
    {
        $models = EloquentQueryWizard::for(TestModel::class)
            ->allowedFilters('name')
            ->where('id', '<', 3)
            ->get();

        $this->assertCount(2, $models);
    }

    #[Test]
    public function multiple_config_calls_before_proxy_work(): void
    {
        $models = EloquentQueryWizard::for(TestModel::class)
            ->allowedFilters('name')
            ->allowedSorts('created_at')
            ->allowedIncludes('relatedModels')
            ->where('id', '<', 3)
            ->get();

        $this->assertCount(2, $models);
    }

    #[Test]
    public function terminal_methods_do_not_taint_subject(): void
    {
        $wizard = EloquentQueryWizard::for(TestModel::class);
        $wizard->count(); // terminal — returns int, not builder

        // Should NOT throw — terminal methods don't set the flag
        $wizard->allowedFilters('name');

        $this->assertInstanceOf(EloquentQueryWizard::class, $wizard);
    }

    #[Test]
    public function a_clone_of_a_proxy_modified_wizard_refuses_reconfiguration(): void
    {
        $wizard = EloquentQueryWizard::for(TestModel::class)
            ->allowedFilters('name')
            ->where('id', '<', 3);

        $clone = clone $wizard;

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('after calling query builder methods');

        $clone->allowedSorts('name');
    }

    #[Test]
    public function a_clone_of_an_escaped_wizard_refuses_reconfiguration(): void
    {
        $wizard = EloquentQueryWizard::for(TestModel::class)->allowedFilters('name');
        $wizard->toQuery();

        $clone = clone $wizard;

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('after retrieving the underlying builder');

        $clone->allowedSorts('name');
    }

    #[Test]
    public function a_clone_of_a_proxy_modified_wizard_keeps_its_constraints(): void
    {
        $wizard = EloquentQueryWizard::for(TestModel::class)
            ->allowedFilters('name')
            ->where('id', '<', 3);

        $clone = clone $wizard;

        $this->assertSame([1, 2], $clone->get()->modelKeys());
    }

    #[Test]
    public function tap_does_not_taint_subject(): void
    {
        $wizard = EloquentQueryWizard::for(TestModel::class)
            ->tap(fn ($q) => $q->where('id', '<', 3));

        // tap() does not go through __call, so config after tap() is fine
        $wizard->allowedFilters('name');

        $models = $wizard->get();
        $this->assertCount(2, $models);
    }

    #[Test]
    public function config_after_proxied_sort_throws_logic_exception(): void
    {
        $this->expectException(\LogicException::class);

        EloquentQueryWizard::for(TestModel::class)
            ->orderBy('name')
            ->allowedSorts('name');
    }

    #[Test]
    public function config_after_to_query_throws_logic_exception(): void
    {
        $wizard = EloquentQueryWizard::for(TestModel::class)
            ->allowedFilters('name');

        $wizard->toQuery();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot modify query wizard configuration after retrieving the underlying builder via build(), toQuery() or getSubject()');

        $wizard->allowedSorts('id');
    }

    #[Test]
    public function config_after_get_subject_throws_logic_exception(): void
    {
        $wizard = EloquentQueryWizard::for(TestModel::class)
            ->allowedFilters('name');

        $wizard->getSubject();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot modify query wizard configuration after retrieving the underlying builder via build(), toQuery() or getSubject()');

        $wizard->allowedSorts('id');
    }

    #[Test]
    public function config_after_build_throws_logic_exception(): void
    {
        $wizard = EloquentQueryWizard::for(TestModel::class)
            ->allowedFilters('name');

        $builder = $wizard->build();

        $this->assertSame($wizard->getSubject(), $builder);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot modify query wizard configuration after retrieving the underlying builder via build(), toQuery() or getSubject()');

        $wizard->allowedSorts('id');
    }

    #[Test]
    public function config_after_executing_methods_is_still_allowed(): void
    {
        $wizard = EloquentQueryWizard::for(TestModel::class)
            ->allowedFilters('name');

        $wizard->get();
        $wizard->count();
        $wizard->allowedSorts('id');

        $this->assertCount(TestModel::query()->count(), $wizard->get());
    }

    #[Test]
    public function build_validates_appends(): void
    {
        $wizard = new EloquentQueryWizard(TestModel::query(), new QueryParametersManager(new Request(['append' => 'unknown'])));

        $this->expectException(InvalidAppendQuery::class);

        $wizard->allowedAppends('fullname')->build();
    }

    // ========== Idempotency Tests ==========
    #[Test]
    public function wizard_to_query_is_idempotent(): void
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
    public function clone_of_a_built_wizard_still_applies_post_processing(): void
    {
        AppendModel::factory()->count(2)->create();

        $params = new QueryParametersManager(new Request(['append' => 'fullname']));

        $wizard = (new EloquentQueryWizard(AppendModel::query(), $params))
            ->allowedAppends('fullname');

        // Build first: this is what used to poison the clone, because __clone()
        // wiped the append tree while leaving the build flag set.
        $wizard->build();

        $clone = clone $wizard;
        $models = $clone->get();

        $this->assertCount(2, $models);
        $this->assertArrayHasKey('fullname', $models->first()->toArray());
    }

    #[Test]
    public function clone_of_a_built_wizard_keeps_the_root_sparse_fieldset_mask(): void
    {
        AppendModelWithBuiltInAppends::factory()->count(2)->create();

        $params = new QueryParametersManager(new Request(['fields' => 'firstname']));

        // A model with built-in $appends forces a full root SELECT, so the sparse
        // fieldset is enforced by masking during post-processing rather than by the
        // query itself - which is exactly the state __clone() used to discard.
        $wizard = (new EloquentQueryWizard(AppendModelWithBuiltInAppends::query(), $params))
            ->allowedFields('firstname', 'lastname');

        $wizard->build();

        $clone = clone $wizard;
        $row = $clone->get()->first()->toArray();

        $this->assertArrayHasKey('firstname', $row);
        $this->assertArrayNotHasKey('lastname', $row);
    }

    #[Test]
    public function clone_of_a_built_wizard_does_not_reapply_filters(): void
    {
        $params = new QueryParametersManager(new Request(['filter' => ['id' => 1]]));

        $wizard = (new EloquentQueryWizard(TestModel::query(), $params))
            ->allowedFilters('id');

        $wizard->build();

        $clone = clone $wizard;

        $this->assertCount(1, $clone->get());
        $this->assertSame(1, substr_count($clone->toQuery()->toSql(), '"id" = ?'));
    }

    #[Test]
    public function clone_keeps_constraints_applied_through_the_builder_proxy(): void
    {
        $wizard = EloquentQueryWizard::for(TestModel::class)->allowedFilters('name');

        // Goes straight onto the subject via __call(), not through the wizard's
        // own filter pipeline - a scope applied this way must survive cloning.
        $wizard->where('id', 12345);

        $clone = clone $wizard;

        $this->assertStringContainsString('"id" = ?', $clone->toSql());
    }

    #[Test]
    public function proxy_returns_other_builders_without_replacing_the_subject(): void
    {
        $wizard = $this->createEloquentWizardWithFilters(['name' => 'x'])->allowedFilters('name');

        $relation = $wizard->getRelation('relatedModels');
        $clone = $wizard->clone();

        $this->assertInstanceOf(Relation::class, $relation);
        $this->assertInstanceOf(Builder::class, $clone);
        $this->assertNotSame($wizard->toQuery(), $clone);
        $this->assertStringContainsString('from "test_models" where "test_models"."name" = ?', $wizard->toSql());
    }

    #[Test]
    public function proxy_on_a_relation_subject_returns_its_query_without_replacing_the_subject(): void
    {
        $model = TestModel::factory()->create();
        $wizard = EloquentQueryWizard::for($model->relatedThroughPivotModels());

        $query = $wizard->getQuery();

        $this->assertInstanceOf(Builder::class, $query);
        $this->assertInstanceOf(Relation::class, $wizard->toQuery());
    }

    #[Test]
    public function proxy_still_returns_the_wizard_for_fluent_calls(): void
    {
        $wizard = EloquentQueryWizard::for(TestModel::class);

        $this->assertSame($wizard, $wizard->where('id', 1)->orderBy('name'));
        $this->assertStringContainsString('where "id" = ? order by "name" asc', $wizard->toSql());
    }

    #[Test]
    public function wizard_tap_applies_before_wizard_operations(): void
    {
        $params = new QueryParametersManager(new Request(['sort' => '-id']));

        $models = (new EloquentQueryWizard(TestModel::query(), $params))
            ->allowedSorts('id')
            ->tap(fn ($query) => $query->where('id', '<=', 3))
            ->get();

        $this->assertCount(3, $models);
        $this->assertEquals(3, $models->first()->id); // Sorted desc
    }

    #[Test]
    public function a_failed_build_leaves_no_partial_constraints_for_the_retry(): void
    {
        $sortCalls = 0;
        $taps = 0;

        $wizard = $this
            ->createEloquentWizardFromQuery(['filter' => ['name' => 'a'], 'sort' => 'name,flaky'])
            ->allowedFilters('name')
            ->allowedSorts('name', EloquentSort::callback('flaky', function ($query) use (&$sortCalls): void {
                if ($sortCalls++ === 0) {
                    throw new \RuntimeException('flaky');
                }
            }))
            ->tap(function ($query) use (&$taps): void {
                $taps++;
                $query->where('id', '>', 0);
            });

        try {
            $wizard->get();
            $this->fail('Expected the first build to fail');
        } catch (\RuntimeException $e) {
            $this->assertSame('flaky', $e->getMessage());
        }

        $sql = $wizard->toQuery()->toSql();

        $this->assertSame(1, substr_count($sql, '"id" > ?'));
        $this->assertSame(1, substr_count($sql, '"name" = ?'));
        $this->assertSame(1, substr_count($sql, '"name" asc'));
        $this->assertSame(2, $taps);
    }

    #[Test]
    public function a_wizard_reconfigured_after_a_failed_build_builds_from_the_original_subject(): void
    {
        $wizard = $this
            ->createEloquentWizardFromQuery(['filter' => ['name' => 'a'], 'sort' => 'name', 'include' => 'relatedModels'])
            ->allowedFilters('name')
            ->allowedSorts('name')
            ->allowedIncludes(EloquentInclude::callback('relatedModels', function (): void {
                throw new \RuntimeException('include failed');
            }));

        try {
            $wizard->get();
            $this->fail('Expected the first build to fail');
        } catch (\RuntimeException) {
        }

        $sql = $wizard->allowedIncludes('relatedModels')->toQuery()->toSql();

        $this->assertSame('select * from "test_models" where "test_models"."name" = ? order by "test_models"."name" asc', $sql);
    }

    #[Test]
    public function a_failed_build_keeps_a_subject_already_handed_out(): void
    {
        $wizard = $this
            ->createEloquentWizardWithSorts('boom')
            ->allowedSorts(EloquentSort::callback('boom', function (): void {
                throw new \RuntimeException('boom');
            }));
        $subject = $wizard->getSubject();

        try {
            $wizard->get();
        } catch (\RuntimeException) {
        }

        $this->assertSame($subject, $wizard->getSubject());
    }

    #[Test]
    public function rollback_failed_build_is_an_extension_point(): void
    {
        $wizard = new class(TestModel::query(), new QueryParametersManager(new Request(['sort' => 'boom']))) extends EloquentQueryWizard
        {
            public int $rollbacks = 0;

            protected function rollbackFailedBuild(): void
            {
                $this->rollbacks++;
                parent::rollbackFailedBuild();
            }
        };
        $wizard->allowedSorts(EloquentSort::callback('boom', function (): void {
            throw new \RuntimeException('boom');
        }));

        try {
            $wizard->get();
        } catch (\RuntimeException) {
        }

        $this->assertSame(1, $wizard->rollbacks);
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
    public function wizard_rebuilds_when_programmatic_filters_change_on_same_manager(): void
    {
        $targetModel = $this->models->first();
        $params = new QueryParametersManager;
        $params->setFiltersParameter(['name' => $targetModel->name]);

        $wizard = (new EloquentQueryWizard(TestModel::query(), $params))
            ->allowedFilters('name');

        $firstResults = $wizard->get();
        $this->assertCount(1, $firstResults);

        $params->setFiltersParameter([]);

        $secondResults = $wizard->get();
        $this->assertCount(5, $secondResults);
    }

    #[Test]
    public function wizard_rebuilds_when_programmatic_includes_change_on_same_manager(): void
    {
        $params = new QueryParametersManager;
        $params->setIncludesParameter(['relatedModels']);

        $wizard = (new EloquentQueryWizard(TestModel::query(), $params))
            ->allowedIncludes('relatedModels', 'otherRelatedModels');

        $firstResults = $wizard->get();
        $this->assertTrue($firstResults->first()->relationLoaded('relatedModels'));
        $this->assertFalse($firstResults->first()->relationLoaded('otherRelatedModels'));

        $params->setIncludesParameter(['otherRelatedModels']);

        $secondResults = $wizard->get();
        $this->assertFalse($secondResults->first()->relationLoaded('relatedModels'));
        $this->assertTrue($secondResults->first()->relationLoaded('otherRelatedModels'));
    }

    #[Test]
    public function wizard_rebuilds_when_programmatic_sorts_change_on_same_manager(): void
    {
        $params = new QueryParametersManager;
        $params->setSortsParameter(['id']);

        $wizard = (new EloquentQueryWizard(TestModel::query(), $params))
            ->allowedSorts('id');

        $firstResults = $wizard->get();
        $this->assertEquals(1, $firstResults->first()->id);

        $params->setSortsParameter(['-id']);

        $secondResults = $wizard->get();
        $this->assertEquals(5, $secondResults->first()->id);
    }

    #[Test]
    public function wizard_rebuilds_when_programmatic_fields_change_on_same_manager(): void
    {
        $params = new QueryParametersManager;
        $params->setFieldsParameter(['testModel' => 'id']);

        $wizard = (new EloquentQueryWizard(TestModel::query(), $params))
            ->allowedFields('id', 'name');

        $firstResults = $wizard->get();
        $this->assertSame(['id'], array_keys($firstResults->first()->getAttributes()));

        $params->setFieldsParameter(['testModel' => 'name']);

        $secondResults = $wizard->get();
        $this->assertSame(['name'], array_keys($secondResults->first()->getAttributes()));
    }

    #[Test]
    public function wizard_rebuilds_when_programmatic_appends_change_on_same_manager(): void
    {
        AppendModel::factory()->create();

        $params = new QueryParametersManager;
        $params->setAppendsParameter(['fullname']);

        $wizard = (new EloquentQueryWizard(AppendModel::query(), $params))
            ->allowedAppends('fullname', 'reversename');

        $firstResult = $wizard->first();
        $this->assertNotNull($firstResult);
        $firstArray = $firstResult->toArray();
        $this->assertArrayHasKey('fullname', $firstArray);
        $this->assertArrayNotHasKey('reversename', $firstArray);

        $params->setAppendsParameter(['reversename']);

        $secondResult = $wizard->first();
        $this->assertNotNull($secondResult);
        $secondArray = $secondResult->toArray();
        $this->assertArrayHasKey('reversename', $secondArray);
        $this->assertArrayNotHasKey('fullname', $secondArray);
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

    // ========== schema() Method Tests ==========

    #[Test]
    public function wizard_can_set_schema_fluently(): void
    {
        $schema = new class extends ResourceSchema
        {
            public function model(): string
            {
                return TestModel::class;
            }

            public function filters(QueryWizardInterface $wizard): array
            {
                return ['name'];
            }
        };

        $targetModel = $this->models->first();
        $params = new QueryParametersManager(new Request(['filter' => ['name' => $targetModel->name]]));

        $models = (new EloquentQueryWizard(TestModel::query(), $params))
            ->schema($schema)
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals($targetModel->id, $models->first()->id);
    }

    #[Test]
    public function schema_method_is_equivalent_to_for_schema(): void
    {
        $schema = new class extends ResourceSchema
        {
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

        // Using forSchema()
        $resultsFromForSchema = EloquentQueryWizard::forSchema($schema)->get();

        // Using for() + schema()
        $resultsFromSchemaMethod = EloquentQueryWizard::for(TestModel::class)
            ->schema($schema)
            ->get();

        $this->assertEquals(
            $resultsFromForSchema->pluck('id')->toArray(),
            $resultsFromSchemaMethod->pluck('id')->toArray()
        );
    }

    #[Test]
    public function schema_method_invalidates_build_state(): void
    {
        $schema = new class extends ResourceSchema
        {
            public function model(): string
            {
                return TestModel::class;
            }
        };

        $wizard = EloquentQueryWizard::for(TestModel::class);
        $wizard->get(); // First build

        $reflection = new \ReflectionClass($wizard);
        $builtProperty = $reflection->getProperty('built');

        $this->assertTrue($builtProperty->getValue($wizard));

        $wizard->schema($schema);

        $this->assertFalse($builtProperty->getValue($wizard), 'schema() should invalidate build state');
    }

    // ========== Disallowed Filters/Sorts Tests ==========

    #[Test]
    public function disallowed_filters_are_rejected(): void
    {
        $targetModel = $this->models->first();
        $params = new QueryParametersManager(new Request([
            'filter' => ['name' => $targetModel->name],
        ]));

        $this->expectException(InvalidFilterQuery::class);

        (new EloquentQueryWizard(TestModel::query(), $params))
            ->allowedFilters('name', 'id')
            ->disallowedFilters('name')
            ->get();
    }

    #[Test]
    public function every_parameter_is_validated_before_any_filter_is_applied(): void
    {
        $applied = 0;
        $params = new QueryParametersManager(new Request([
            'filter' => ['counted' => '1'],
            'sort' => 'unknown',
        ]));

        try {
            (new EloquentQueryWizard(TestModel::query(), $params))
                ->allowedFilters(EloquentFilter::callback('counted', function () use (&$applied): void {
                    $applied++;
                }))
                ->allowedSorts('name')
                ->get();
            $this->fail('Expected InvalidSortQuery');
        } catch (InvalidSortQuery) {
        }

        $this->assertSame(0, $applied);
    }

    #[Test]
    public function disallowed_sorts_are_rejected(): void
    {
        $params = new QueryParametersManager(new Request(['sort' => 'name']));

        $this->expectException(InvalidSortQuery::class);

        (new EloquentQueryWizard(TestModel::query(), $params))
            ->allowedSorts('name', 'id')
            ->disallowedSorts('name')
            ->get();
    }

    #[Test]
    public function disable_invalid_sort_query_exception_ignores_invalid_sorts(): void
    {
        config()->set('query-wizard.disable_invalid_sort_query_exception', true);

        $params = new QueryParametersManager(new Request(['sort' => 'nonexistent']));

        $models = (new EloquentQueryWizard(TestModel::query(), $params))
            ->allowedSorts('name')
            ->get();

        $this->assertCount(5, $models);
    }

    // ========== schema() Method Tests ==========

    #[Test]
    public function schema_method_accepts_class_string(): void
    {
        // Create a named schema class for this test
        $schemaClass = get_class(new class extends ResourceSchema
        {
            public function model(): string
            {
                return TestModel::class;
            }

            public function filters(QueryWizardInterface $wizard): array
            {
                return ['name'];
            }
        });

        // Bind to container
        app()->bind($schemaClass, fn () => new class extends ResourceSchema
        {
            public function model(): string
            {
                return TestModel::class;
            }

            public function filters(QueryWizardInterface $wizard): array
            {
                return ['name'];
            }
        });

        $wizard = EloquentQueryWizard::for(TestModel::class)->schema($schemaClass);

        $this->assertInstanceOf(EloquentQueryWizard::class, $wizard);
    }

    #[Test]
    public function explicit_allowed_filters_override_schema(): void
    {
        $schema = new class extends ResourceSchema
        {
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
        $params = new QueryParametersManager(new Request(['filter' => ['id' => $targetModel->id]]));

        // Schema allows 'name' and 'id', but explicit call only allows 'name'
        // So filtering by 'id' should throw exception
        $this->expectException(InvalidFilterQuery::class);

        (new EloquentQueryWizard(TestModel::query(), $params))
            ->schema($schema)
            ->allowedFilters('name') // Override schema - only 'name' allowed
            ->get();
    }
}
