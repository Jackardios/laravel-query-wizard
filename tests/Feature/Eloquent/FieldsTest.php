<?php

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Jackardios\QueryWizard\Exceptions\InvalidFieldQuery;
use Jackardios\QueryWizard\EloquentQueryWizard;
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

    /** @var string */
    protected string $modelTableName;

    public function setUp(): void
    {
        parent::setUp();

        $this->model = factory(TestModel::class)->create();
        $this->modelTableName = $this->model->getTable();
    }

    /** @test */
    public function it_can_accept_fields_as_string(): void
    {
        $fields = 'some.related.model.id,id,,,related.created_at,name,some.related.model.title,content,related.id';
        $wizard = EloquentQueryWizard::for(TestModel::class, new Request(['fields' => $fields]))
            ->setAllowedFields([
                'some.related.model.id',
                'id',
                'related.created_at',
                'name',
                'some.related.model.title',
                'content',
                'related.id'
            ]);

        $wizardFields = $wizard->getFields()->toArray();

        $this->assertEqualsCanonicalizing(['test_models', 'some.related.model', 'related'], array_keys($wizardFields));
        $this->assertEqualsCanonicalizing(['id', 'name', 'content'], $wizardFields['test_models']);
        $this->assertEqualsCanonicalizing(['id', 'title'], $wizardFields['some.related.model']);
        $this->assertEqualsCanonicalizing(['created_at', 'id'], $wizardFields['related']);
    }

    /** @test */
    public function it_can_accept_fields_as_associative_array(): void
    {
        $wizard = EloquentQueryWizard::for(TestModel::class, new Request(['fields' => [
            'test_models' => ['id', 'name', 'content'],
            'some.related.model' => ['id', 'title'],
            'related' => ['created_at', 'id']
        ]]))
            ->setAllowedFields([
                'some.related.model.id',
                'id',
                'related.created_at',
                'name',
                'some.related.model.title',
                'content',
                'related.id'
            ]);

        $wizardFields = $wizard->getFields()->toArray();

        $this->assertEqualsCanonicalizing(['test_models', 'some.related.model', 'related'], array_keys($wizardFields));
        $this->assertEqualsCanonicalizing(['id', 'name', 'content'], $wizardFields['test_models']);
        $this->assertEqualsCanonicalizing(['id', 'title'], $wizardFields['some.related.model']);
        $this->assertEqualsCanonicalizing(['created_at', 'id'], $wizardFields['related']);
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
        $query = $this->createWizardFromFieldRequest(['test_models' => 'name,id'])
            ->setAllowedFields(['name', 'id'])
            ->build()
            ->toSql();

        $expected = TestModel::query()
            ->select("{$this->modelTableName}.name", "{$this->modelTableName}.id")
            ->toSql();

        $this->assertEquals($expected, $query);
    }

    /** @test */
    public function it_replaces_selected_columns_on_the_query(): void
    {
        $query = $this->createWizardFromFieldRequest(['test_models' => 'name,id'])
            ->select(['id', 'is_visible'])
            ->setAllowedFields(['name', 'id'])
            ->build()
            ->toSql();

        $expected = TestModel::query()
            ->select("{$this->modelTableName}.name", "{$this->modelTableName}.id")
            ->toSql();

        $this->assertEquals($expected, $query);
        $this->assertStringNotContainsString('is_visible', $expected);
    }

    /** @test */
    public function it_wont_fetch_a_specific_column_if_its_not_allowed(): void
    {
        $query = $this->createWizardFromFieldRequest(['test_models' => 'random-column'])
            ->build()
            ->toSql();

        $expected = TestModel::query()->toSql();

        $this->assertEquals($expected, $query);
    }

    /** @test */
    public function it_can_fetch_sketchy_columns_if_they_are_allowed_fields(): void
    {
        $query = $this->createWizardFromFieldRequest(['test_models' => 'name->first,id'])
            ->setAllowedFields(['name->first', 'id'])
            ->build()
            ->toSql();

        $expected = TestModel::query()
            ->select("{$this->modelTableName}.name->first", "{$this->modelTableName}.id")
            ->toSql();

        $this->assertEquals($expected, $query);
    }

    /** @test */
    public function it_guards_against_not_allowed_fields(): void
    {
        $this->expectException(InvalidFieldQuery::class);

        $this->createWizardFromFieldRequest(['test_models' => 'random-column'])
            ->setAllowedFields('name')
            ->build();
    }

    /** @test */
    public function it_guards_against_not_allowed_fields_from_an_included_resource(): void
    {
        $this->expectException(InvalidFieldQuery::class);

        $this->createWizardFromFieldRequest(['related_models' => 'random_column'])
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

        $queryWizard = EloquentQueryWizard::for(TestModel::class, $request)
            ->setAllowedFields('related_models.name', 'id')
            ->setAllowedIncludes('relatedModels')
            ->build();

        DB::enableQueryLog();

        $queryWizard->first()->relatedModels;

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

        $result = EloquentQueryWizard::for(TestModel::class, $request)
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

        $queryWizard = EloquentQueryWizard::for(TestModel::class, $request)
            ->setAllowedFields(['related_models.id', 'related_models.name'])
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
        $request = new Request([
            'fields' => ['test_models' => 'id->"\')from test_models--injection'],
        ]);

        DB::enableQueryLog();

        EloquentQueryWizard::for(TestModel::class, $request)
            ->build()
            ->get();

        $this->assertQueryLogDoesntContain('--injection');
    }

    protected function createWizardFromFieldRequest(array $fields = []): EloquentQueryWizard
    {
        $request = new Request([
            'fields' => $fields,
        ]);

        return EloquentQueryWizard::for(TestModel::class, $request);
    }
}
