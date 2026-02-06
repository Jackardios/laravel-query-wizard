<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Jackardios\QueryWizard\Contracts\QueryWizardInterface;
use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Eloquent\EloquentQueryWizard;
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
}
