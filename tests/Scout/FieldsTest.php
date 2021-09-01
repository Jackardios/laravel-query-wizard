<?php

namespace Jackardios\QueryWizard\Tests\Scout;

use Jackardios\QueryWizard\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Jackardios\QueryWizard\Exceptions\InvalidFieldQuery;
use Jackardios\QueryWizard\ScoutQueryWizard;
use Jackardios\QueryWizard\Tests\TestClasses\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\TestClasses\Models\TestModel;

/**
 * @group scout
 * @group fields
 * @group scout-fields
 */
class FieldsTest extends TestCase
{
    /** @var TestModel */
    protected $model;

    /** @var string */
    protected string $modelTableName;

    public function setUp(): void
    {
        parent::setUp();

        $this->model = factory(TestModel::class)->create();
        $this->modelTableName = $this->model->getTable();
    }

    /** @test */
    public function it_fetches_all_columns_if_no_field_was_requested(): void
    {
        $scoutModel = $this
            ->createQueryFromFieldRequest()
            ->build()
            ->first();

        $expectedModel = TestModel::query()->first();

        $this->assertModelsAttributesEqual($scoutModel, $expectedModel);
    }

    /** @test */
    public function it_fetches_all_columns_if_no_field_was_requested_but_allowed_fields_were_specified(): void
    {
        $scoutModel = $this
            ->createQueryFromFieldRequest()
            ->setAllowedFields('id', 'name')
            ->build()
            ->first();

        $expectedModel = TestModel::query()->first();

        $this->assertModelsAttributesEqual($scoutModel, $expectedModel);
    }

    /** @test */
    public function it_replaces_selected_columns_on_the_query(): void
    {
        $scoutModel = $this
            ->createQueryFromFieldRequest(['test_models' => 'name,id'])
            ->query(function(Builder $query) {
                $query->select(['id', 'is_visible']);
            })
            ->setAllowedFields(['name', 'id'])
            ->build()
            ->first();

        $expectedModel = TestModel::query()
            ->select("{$this->modelTableName}.name", "{$this->modelTableName}.id")
            ->first();

        $this->assertModelsAttributesEqual($scoutModel, $expectedModel);
    }

    /** @test */
    public function it_can_fetch_specific_columns(): void
    {
        $scoutModel = $this
            ->createQueryFromFieldRequest(['test_models' => 'name,id'])
            ->setAllowedFields(['name', 'id'])
            ->build()
            ->first();

        $expectedModel = TestModel::query()
            ->select("{$this->modelTableName}.name", "{$this->modelTableName}.id")
            ->first();

        $this->assertModelsAttributesEqual($scoutModel, $expectedModel);
    }

    /** @test */
    public function it_wont_fetch_a_specific_column_if_its_not_allowed(): void
    {
        $scoutModel = $this
            ->createQueryFromFieldRequest(['test_models' => 'random-column'])
            ->build()
            ->first();

        $expectedModel = TestModel::query()->first();

        $this->assertModelsAttributesEqual($scoutModel, $expectedModel);
    }

    /** @test */
    public function it_guards_against_not_allowed_fields(): void
    {
        $this->expectException(InvalidFieldQuery::class);

        $this
            ->createQueryFromFieldRequest(['test_models' => 'random-column'])
            ->setAllowedFields('name')
            ->build();
    }

    /** @test */
    public function it_guards_against_not_allowed_fields_from_an_included_resource(): void
    {
        $this->expectException(InvalidFieldQuery::class);

        $this
            ->createQueryFromFieldRequest(['related_models' => 'random_column'])
            ->setAllowedFields('related_models.name')
            ->build();
    }

    /** @test */
    public function it_can_fetch_only_requested_columns_from_an_included_model(): void
    {
        RelatedModel::create([
            'test_model_id' => $this->model->id,
            'name' => 'related',
        ]);

        $request = new Request([
            'fields' => [
                'test_models' => 'id',
                'related_models' => 'name',
            ],
            'include' => ['relatedModels'],
        ]);

        $scoutModelWizard = $this
            ->createQueryFromRequest($request)
            ->setAllowedFields('related_models.name', 'id')
            ->setAllowedIncludes('relatedModels')
            ->build();

        DB::enableQueryLog();

        $scoutModelWizard->first()->relatedModels;

        $this->assertQueryLogContains('select `test_models`.`id` from `test_models`');
        $this->assertQueryLogContains('select `name` from `related_models`');
    }

    /** @test */
    public function it_can_fetch_requested_columns_from_included_models_up_to_two_levels_deep(): void
    {
        RelatedModel::create([
            'test_model_id' => $this->model->id,
            'name' => 'related',
        ]);

        $request = new Request([
            'fields' => [
                'test_models' => 'id,name',
                'related_models.test_models' => 'id',
            ],
            'include' => ['relatedModels.testModel'],
        ]);

        $result = $this
            ->createQueryFromRequest($request)
            ->setAllowedFields('related_models.test_models.id', 'id', 'name')
            ->setAllowedIncludes('relatedModels.testModel')
            ->build()
            ->first();

        $this->assertArrayHasKey('name', $result);

        $this->assertEquals(['id' => $this->model->id], $result->relatedModels->first()->testModel->toArray());
    }

    /** @test */
    public function it_can_allow_specific_fields_on_an_included_model(): void
    {
        $request = new Request([
            'fields' => ['related_models' => 'id,name'],
            'include' => ['relatedModels'],
        ]);

        $scoutModelWizard = $this
            ->createQueryFromRequest($request)
            ->setAllowedFields(['related_models.id', 'related_models.name'])
            ->setAllowedIncludes('relatedModels')
            ->build();

        DB::enableQueryLog();

        $scoutModelWizard->first()->relatedModels;

        $this->assertQueryLogContains('select * from `test_models`');
        $this->assertQueryLogContains('select `id`, `name` from `related_models`');
    }

    /** @test */
    public function it_wont_use_sketchy_field_requests(): void
    {
        $request = new Request([
            'fields' => ['test_models' => 'id->"\')from test_models--injection'],
        ]);

        DB::enableQueryLog();

        $this->createQueryFromRequest($request)
            ->build()
            ->get();

        $this->assertQueryLogDoesntContain('--injection');
    }

    protected function createQueryFromFieldRequest(array $fields = []): ScoutQueryWizard
    {
        $request = new Request([
            'fields' => $fields,
        ]);

        return ScoutQueryWizard::for(TestModel::search(), $request);
    }

    protected function createQueryFromRequest(Request $request): ScoutQueryWizard
    {
        return ScoutQueryWizard::for(TestModel::search(), $request);
    }
}
