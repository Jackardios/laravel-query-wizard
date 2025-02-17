<?php

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Support\Facades\DB;
use Jackardios\QueryWizard\Exceptions\InvalidFieldQuery;
use Jackardios\QueryWizard\Eloquent\EloquentQueryWizard;
use Jackardios\QueryWizard\Tests\TestCase;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;

/**
 * @group eloquent
 * @group fields
 * @group eloquent-fields
 */
class FieldsTest extends TestCase
{
    /** @var TestModel */
    protected $model;

    public function setUp(): void
    {
        parent::setUp();

        $this->model = factory(TestModel::class)->create();
    }

    /** @test */
    public function it_can_accept_fields_as_string(): void
    {
        $wizard = $this->createEloquentWizardWithFields('some.related.model.id,id,,,related.created_at,name,some.related.model.title,content,related.id')
            ->setAllowedFields([
                'some.related.model.id',
                'id',
                'related.created_at',
                'name',
                'some.related.model.title',
                'content',
                'related.id'
            ]);

        $this->assertEquals([
            'testModel' => ['id', 'name', 'content'],
            'some.related.model' => ['id', 'title'],
            'related' => ['created_at', 'id']
        ], $wizard->getFields()->toArray());
    }

    /** @test */
    public function it_can_accept_fields_as_associative_array(): void
    {
        $wizard = $this->createEloquentWizardWithFields([
            'testModel' => ['id', 'name', 'content'],
            'some.related.model' => ['id', 'title'],
            'related' => ['created_at', 'id']
        ])
            ->setAllowedFields([
                'some.related.model.id',
                'id',
                'related.created_at',
                'name',
                'some.related.model.title',
                'content',
                'related.id'
            ]);

        $this->assertEquals([
            'testModel' => ['id', 'name', 'content'],
            'some.related.model' => ['id', 'title'],
            'related' => ['created_at', 'id']
        ], $wizard->getFields()->toArray());
    }

    /** @test */
    public function it_fetches_all_columns_if_no_field_was_requested(): void
    {
        $query = EloquentQueryWizard::for(TestModel::class)
            ->build()
            ->toSql();

        $expected = TestModel::query()->toSql();

        $this->assertEquals($expected, $query);
    }

    /** @test */
    public function it_fetches_all_columns_if_no_field_was_requested_but_allowed_fields_were_specified(): void
    {
        $query = EloquentQueryWizard::for(TestModel::class)
            ->setAllowedFields('id', 'name')
            ->build()
            ->toSql();

        $expected = TestModel::query()->toSql();

        $this->assertEquals($expected, $query);
    }

    /** @test */
    public function it_can_fetch_specific_columns(): void
    {
        $query = $this->createEloquentWizardWithFields(['testModel' => 'name,id'])
            ->setAllowedFields(['name', 'id'])
            ->build()
            ->toSql();

        $expected = TestModel::query()
            ->select("name", "id")
            ->toSql();

        $this->assertEquals($expected, $query);
    }

    /** @test */
    public function it_replaces_selected_columns_on_the_query(): void
    {
        $query = $this->createEloquentWizardWithFields(['testModel' => 'name,id'])
            ->select(['id', 'is_visible'])
            ->setAllowedFields(['name', 'id'])
            ->build()
            ->toSql();

        $expected = TestModel::query()
            ->select("name", "id")
            ->toSql();

        $this->assertEquals($expected, $query);
        $this->assertStringNotContainsString('is_visible', $expected);
    }

    /** @test */
    public function it_wont_fetch_a_specific_column_if_its_not_allowed(): void
    {
        $query = $this->createEloquentWizardWithFields(['testModel' => 'random-column'])
            ->build()
            ->toSql();

        $expected = TestModel::query()->toSql();

        $this->assertEquals($expected, $query);
    }

    /** @test */
    public function it_can_fetch_sketchy_columns_if_they_are_allowed_fields(): void
    {
        $query = $this->createEloquentWizardWithFields(['testModel' => 'name->first,id'])
            ->setAllowedFields(['name->first', 'id'])
            ->build()
            ->toSql();

        $expected = TestModel::query()
            ->select("name->first", "id")
            ->toSql();

        $this->assertEquals($expected, $query);
    }

    /** @test */
    public function it_guards_against_not_allowed_fields(): void
    {
        $this->expectException(InvalidFieldQuery::class);

        $this->createEloquentWizardWithFields(['testModel' => 'random-column'])
            ->setAllowedFields('name')
            ->build();
    }

    /** @test */
    public function it_guards_against_not_allowed_fields_from_an_included_resource(): void
    {
        $this->expectException(InvalidFieldQuery::class);

        $this->createEloquentWizardWithFields(['relatedModels' => 'random_column'])
            ->setAllowedFields('relatedModels.name')
            ->build();
    }

    /** @test */
    public function it_can_fetch_only_requested_columns_from_an_included_model(): void
    {
        RelatedModel::create([
            'test_model_id' => $this->model->id,
            'name' => 'related',
        ]);

        $queryWizard = $this->createEloquentWizardFromQuery([
            'fields' => [
                'testModel' => 'id',
                'relatedModels' => 'name',
            ],
            'include' => ['relatedModels'],
        ])
            ->setAllowedFields('relatedModels.name', 'id')
            ->setAllowedIncludes('relatedModels')
            ->build();

        DB::enableQueryLog();

        $queryWizard->first()->relatedModels;

        $this->assertQueryLogContains('select `id` from `test_models`');
        $this->assertQueryLogContains('select `name` from `related_models`');
    }

    /** @test */
    public function it_can_fetch_requested_columns_from_included_models_up_to_two_levels_deep(): void
    {
        RelatedModel::create([
            'test_model_id' => $this->model->id,
            'name' => 'related',
        ]);

        $result = $this->createEloquentWizardFromQuery([
            'fields' => [
                'testModel' => 'id,name',
                'relatedModels.testModel' => 'id',
            ],
            'include' => ['relatedModels.testModel'],
        ])
            ->setAllowedFields('relatedModels.testModel.id', 'id', 'name')
            ->setAllowedIncludes('relatedModels.testModel')
            ->build()
            ->first();

        $this->assertArrayHasKey('name', $result);

        $this->assertEquals(['id' => $this->model->id], $result->relatedModels->first()->testModel->toArray());
    }

    /** @test */
    public function it_can_allow_specific_fields_on_an_included_model(): void
    {
        $queryWizard = $this->createEloquentWizardFromQuery([
            'fields' => ['relatedModels' => 'id,name'],
            'include' => ['relatedModels'],
        ])
            ->setAllowedFields(['relatedModels.id', 'relatedModels.name'])
            ->setAllowedIncludes('relatedModels')
            ->build();

        DB::enableQueryLog();

        $queryWizard->first()->relatedModels;

        $this->assertQueryLogContains('select * from `test_models`');
        $this->assertQueryLogContains('select `id`, `name` from `related_models`');
    }

    /** @test */
    public function it_wont_use_sketchy_field_requests(): void
    {
        DB::enableQueryLog();

        $this->createEloquentWizardWithFields(['testModel' => 'id->"\')from test_models--injection'])
            ->build()
            ->get();

        $this->assertQueryLogDoesntContain('--injection');
    }
}
