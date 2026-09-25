<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Jackardios\QueryWizard\Contracts\QueryWizardInterface;
use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Eloquent\EloquentQueryWizard;
use Jackardios\QueryWizard\ModelQueryWizard;
use Jackardios\QueryWizard\Schema\ResourceSchema;
use Jackardios\QueryWizard\Tests\App\Models\AppendModel;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('eloquent')]
#[Group('schema')]
class SchemaDefaultsTest extends TestCase
{
    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        DB::enableQueryLog();

        $this->models = TestModel::factory()->count(5)->create();
        $this->models->each(function (TestModel $model) {
            RelatedModel::factory()->count(2)->create([
                'test_model_id' => $model->id,
            ]);
        });

        AppendModel::factory()->count(3)->create();
    }

    private function createTestModelSchema(array $overrides = []): ResourceSchema
    {
        return new class($overrides) extends ResourceSchema
        {
            public function __construct(private array $overrides = []) {}

            public function model(): string
            {
                return TestModel::class;
            }

            public function filters(QueryWizardInterface $wizard): array
            {
                return $this->overrides['filters'] ?? [
                    EloquentFilter::exact('name'),
                    EloquentFilter::exact('id'),
                ];
            }

            public function sorts(QueryWizardInterface $wizard): array
            {
                return $this->overrides['sorts'] ?? ['name', 'id'];
            }

            public function includes(QueryWizardInterface $wizard): array
            {
                return $this->overrides['includes'] ?? ['relatedModels', 'otherRelatedModels'];
            }

            public function fields(QueryWizardInterface $wizard): array
            {
                return $this->overrides['fields'] ?? ['id', 'name'];
            }

            public function appends(QueryWizardInterface $wizard): array
            {
                return $this->overrides['appends'] ?? ['fullname'];
            }

            public function defaultSorts(QueryWizardInterface $wizard): array
            {
                return $this->overrides['defaultSorts'] ?? ['-id'];
            }

            public function defaultIncludes(QueryWizardInterface $wizard): array
            {
                return $this->overrides['defaultIncludes'] ?? ['relatedModels'];
            }

            public function defaultAppends(QueryWizardInterface $wizard): array
            {
                return $this->overrides['defaultAppends'] ?? ['fullname'];
            }

            public function defaultFilters(QueryWizardInterface $wizard): array
            {
                return $this->overrides['defaultFilters'] ?? [];
            }

            public function defaultFields(QueryWizardInterface $wizard): array
            {
                return $this->overrides['defaultFields'] ?? [];
            }
        };
    }

    // ========== Schema Filters ==========

    #[Test]
    public function schema_filters_apply_without_explicit_allowed_filters(): void
    {
        $target = $this->models->first();
        $schema = $this->createTestModelSchema();

        $models = $this
            ->createEloquentWizardFromQuery([
                'filter' => ['name' => $target->name],
            ])
            ->schema($schema)
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals($target->name, $models->first()->name);
    }

    // ========== Schema Default Sorts ==========

    #[Test]
    public function schema_default_sorts_apply_without_request(): void
    {
        $schema = $this->createTestModelSchema();

        $models = $this
            ->createEloquentWizardFromQuery()
            ->schema($schema)
            ->get();

        // defaultSorts = ['-id'], so models should be in descending ID order
        $ids = $models->pluck('id')->toArray();
        $sorted = $ids;
        rsort($sorted);
        $this->assertEquals($sorted, $ids);
    }

    // ========== Schema Default Includes ==========

    #[Test]
    public function schema_default_includes_load_without_request(): void
    {
        $schema = $this->createTestModelSchema();

        $models = $this
            ->createEloquentWizardFromQuery()
            ->schema($schema)
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
    }

    // ========== Schema Default Appends ==========

    #[Test]
    public function schema_default_appends_apply_without_request(): void
    {
        $schema = $this->createTestModelSchema();

        $models = $this
            ->createEloquentWizardFromQuery()
            ->schema($schema)
            ->get();

        $this->assertTrue(array_key_exists('fullname', $models->first()->toArray()));
    }

    // ========== Explicit Overrides Schema ==========

    #[Test]
    public function explicit_allowed_filters_override_schema(): void
    {
        $schema = $this->createTestModelSchema();

        $models = $this
            ->createEloquentWizardFromQuery([
                'filter' => ['name' => $this->models->first()->name],
            ])
            ->schema($schema)
            ->allowedFilters('name')
            ->get();

        $this->assertCount(1, $models);
    }

    #[Test]
    public function explicit_default_sorts_override_schema_defaults(): void
    {
        $schema = $this->createTestModelSchema();

        $models = $this
            ->createEloquentWizardFromQuery()
            ->schema($schema)
            ->defaultSorts('name')
            ->get();

        // Explicit defaultSorts('name') should override schema's ['-id']
        $names = $models->pluck('name')->toArray();
        $sorted = $names;
        sort($sorted);
        $this->assertEquals($sorted, $names);
    }

    // ========== Schema Default Filters ==========

    #[Test]
    public function schema_default_filters_apply_when_filter_not_in_request(): void
    {
        $target = $this->models->first();
        $schema = $this->createTestModelSchema([
            'defaultFilters' => ['name' => $target->name],
        ]);

        $models = $this
            ->createEloquentWizardFromQuery()
            ->schema($schema)
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals($target->name, $models->first()->name);
    }

    #[Test]
    public function schema_default_filters_ignored_when_filter_in_request(): void
    {
        $target = $this->models->first();
        $other = $this->models->last();
        $schema = $this->createTestModelSchema([
            'defaultFilters' => ['name' => $target->name],
        ]);

        $models = $this
            ->createEloquentWizardFromQuery([
                'filter' => ['name' => $other->name],
            ])
            ->schema($schema)
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals($other->name, $models->first()->name);
    }

    #[Test]
    public function filter_default_takes_priority_over_schema_default_filters(): void
    {
        $target = $this->models->first();
        $other = $this->models->last();
        $schema = $this->createTestModelSchema([
            'filters' => [
                EloquentFilter::exact('name')->default($target->name),
            ],
            'defaultFilters' => ['name' => $other->name],
        ]);

        $models = $this
            ->createEloquentWizardFromQuery()
            ->schema($schema)
            ->get();

        // Filter's default() takes priority over schema's defaultFilters()
        $this->assertCount(1, $models);
        $this->assertEquals($target->name, $models->first()->name);
    }

    // ========== forSchema() vs ->schema() ==========

    #[Test]
    public function for_schema_works_same_as_schema_method(): void
    {
        $schema = $this->createTestModelSchema();

        $viaSchema = $this
            ->createEloquentWizardFromQuery()
            ->schema($schema)
            ->toQuery()
            ->toSql();

        $viaForSchema = EloquentQueryWizard::forSchema($schema)
            ->toQuery()
            ->toSql();

        $this->assertEquals($viaSchema, $viaForSchema);
    }

    // ========== Explicitly empty defaults ==========

    #[Test]
    public function default_calls_without_arguments_turn_off_schema_defaults(): void
    {
        DB::enableQueryLog();
        DB::flushQueryLog();

        $models = $this
            ->createEloquentWizardFromQuery()
            ->schema($this->createTestModelSchema(['defaultFields' => ['id']]))
            ->defaultSorts()
            ->defaultIncludes()
            ->defaultAppends()
            ->defaultFields()
            ->get();

        $this->assertSame('select * from "test_models"', DB::getQueryLog()[0]['query']);
        $this->assertCount(1, DB::getQueryLog());
        $this->assertArrayNotHasKey('fullname', $models->first()->toArray());
    }

    #[Test]
    public function default_fields_without_arguments_skip_the_allowed_fields_fallback(): void
    {
        config()->set('query-wizard.fields.use_allowed_as_default', true);

        $sql = $this
            ->createEloquentWizardFromQuery()
            ->allowedFields('id', 'name')
            ->defaultFields()
            ->toQuery()
            ->toSql();

        $this->assertSame('select * from "test_models"', $sql);
    }

    #[Test]
    public function model_wizard_default_calls_without_arguments_turn_off_schema_defaults(): void
    {
        $model = (new ModelQueryWizard(TestModel::query()->firstOrFail()))
            ->schema($this->createTestModelSchema(['defaultFields' => ['id']]))
            ->defaultIncludes()
            ->defaultAppends()
            ->defaultFields()
            ->process();

        $this->assertFalse($model->relationLoaded('relatedModels'));
        $this->assertArrayNotHasKey('fullname', $model->toArray());
        $this->assertArrayHasKey('name', $model->toArray());
    }

    #[Test]
    public function schema_default_filters_are_read_once_per_build(): void
    {
        $schema = new class extends ResourceSchema
        {
            public int $defaultFiltersCalls = 0;

            public function model(): string
            {
                return TestModel::class;
            }

            public function filters(QueryWizardInterface $wizard): array
            {
                return ['id', 'name', EloquentFilter::partial('title'), EloquentFilter::passthrough('custom')];
            }

            public function defaultFilters(QueryWizardInterface $wizard): array
            {
                $this->defaultFiltersCalls++;

                return ['custom' => 'default'];
            }
        };
        $wizard = $this->createEloquentWizardFromQuery([])->schema($schema);

        $wizard->get();

        $this->assertSame(1, $schema->defaultFiltersCalls);
        $this->assertSame(['custom' => 'default'], $wizard->getPassthroughFilters()->all());
        $this->assertSame(1, $schema->defaultFiltersCalls);
    }
}
