<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Jackardios\QueryWizard\Exceptions\InvalidAppendQuery;
use Jackardios\QueryWizard\Tests\App\Models\AppendModel;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('eloquent')]
#[Group('append')]
class AppendTest extends TestCase
{
    protected Collection $models;

    protected Collection $testModels;

    protected function setUp(): void
    {
        parent::setUp();

        DB::enableQueryLog();

        // Create AppendModels for basic tests
        $this->models = AppendModel::factory()->count(3)->create();

        // Create TestModels with RelatedModels for nested appends tests
        $this->testModels = TestModel::factory()->count(3)->create();
        $this->testModels->each(function (TestModel $model) {
            RelatedModel::factory()->count(2)->create([
                'test_model_id' => $model->id,
            ]);
        });
    }

    // ========== Basic Append Tests ==========
    #[Test]
    public function it_does_not_append_attributes_by_default(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery([], AppendModel::class)
            ->get();

        $this->assertFalse(array_key_exists('fullname', $models->first()->toArray()));
    }

    #[Test]
    public function it_can_append_attribute(): void
    {
        $models = $this
            ->createEloquentWizardWithAppends('fullname')
            ->allowedAppends('fullname')
            ->get();

        $this->assertTrue(array_key_exists('fullname', $models->first()->toArray()));
    }

    #[Test]
    public function it_can_append_multiple_attributes(): void
    {
        $models = $this
            ->createEloquentWizardWithAppends('fullname,reversename')
            ->allowedAppends('fullname', 'reversename')
            ->get();

        $array = $models->first()->toArray();
        $this->assertTrue(array_key_exists('fullname', $array));
        $this->assertTrue(array_key_exists('reversename', $array));
    }

    #[Test]
    public function it_can_append_attributes_as_array(): void
    {
        $models = $this
            ->createEloquentWizardWithAppends(['fullname', 'reversename'])
            ->allowedAppends('fullname', 'reversename')
            ->get();

        $array = $models->first()->toArray();
        $this->assertTrue(array_key_exists('fullname', $array));
        $this->assertTrue(array_key_exists('reversename', $array));
    }

    // ========== Validation Tests ==========
    #[Test]
    public function it_throws_exception_for_not_allowed_append(): void
    {
        $this->expectException(InvalidAppendQuery::class);

        $this
            ->createEloquentWizardWithAppends('not_allowed')
            ->allowedAppends('fullname')
            ->get();
    }

    #[Test]
    public function it_throws_exception_when_no_allowed_appends_set(): void
    {
        // When no allowed appends are set (and no schema), treat as "forbid all"
        $this->expectException(InvalidAppendQuery::class);

        $this
            ->createEloquentWizardWithAppends('unknown')
            ->get();
    }

    #[Test]
    public function it_throws_exception_with_empty_allowed_appends_array(): void
    {
        // Empty allowed array means nothing is allowed - strict validation
        $this->expectException(InvalidAppendQuery::class);

        $this
            ->createEloquentWizardWithAppends('fullname')
            ->allowedAppends([])
            ->get();
    }

    #[Test]
    public function it_ignores_not_allowed_append_when_exception_disabled(): void
    {
        config()->set('query-wizard.disable_invalid_append_query_exception', true);

        $models = $this
            ->createEloquentWizardWithAppends('not_allowed')
            ->allowedAppends('fullname')
            ->get();

        // No exception, returns all models without the invalid append
        $this->assertCount(3, $models);
        $this->assertFalse(array_key_exists('not_allowed', $models->first()->toArray()));
    }

    #[Test]
    public function it_ignores_appends_with_empty_array_when_exception_disabled(): void
    {
        config()->set('query-wizard.disable_invalid_append_query_exception', true);

        $models = $this
            ->createEloquentWizardWithAppends('fullname')
            ->allowedAppends([])
            ->get();

        // No exception, returns all models without appends
        $this->assertCount(3, $models);
        $this->assertFalse(array_key_exists('fullname', $models->first()->toArray()));
    }

    // ========== Default Appends Tests ==========
    #[Test]
    public function it_applies_default_appends_from_schema(): void
    {
        $schema = new class extends \Jackardios\QueryWizard\Schema\ResourceSchema
        {
            public function model(): string
            {
                return AppendModel::class;
            }

            public function appends(\Jackardios\QueryWizard\Contracts\QueryWizardInterface $wizard): array
            {
                return ['fullname', 'reversename'];
            }

            public function defaultAppends(\Jackardios\QueryWizard\Contracts\QueryWizardInterface $wizard): array
            {
                return ['fullname'];
            }
        };

        $models = $this
            ->createEloquentWizardFromQuery([], AppendModel::class)
            ->schema($schema)
            ->get();

        $this->assertTrue(array_key_exists('fullname', $models->first()->toArray()));
        $this->assertFalse(array_key_exists('reversename', $models->first()->toArray()));
    }

    // ========== Case Sensitivity Tests ==========
    #[Test]
    public function it_rejects_append_with_wrong_case(): void
    {
        $this->expectException(InvalidAppendQuery::class);

        $this
            ->createEloquentWizardWithAppends('Fullname')
            ->allowedAppends('fullname')
            ->get();
    }

    // ========== Edge Cases ==========
    #[Test]
    public function it_handles_empty_append_string(): void
    {
        $models = $this
            ->createEloquentWizardWithAppends('')
            ->allowedAppends('fullname')
            ->get();

        // Empty append = no appends
        $this->assertFalse(array_key_exists('fullname', $models->first()->toArray()));
    }

    #[Test]
    public function it_handles_append_with_trailing_comma(): void
    {
        $models = $this
            ->createEloquentWizardWithAppends('fullname,')
            ->allowedAppends('fullname')
            ->get();

        $this->assertTrue(array_key_exists('fullname', $models->first()->toArray()));
    }

    #[Test]
    public function it_removes_duplicate_appends(): void
    {
        $models = $this
            ->createEloquentWizardWithAppends('fullname,fullname')
            ->allowedAppends('fullname')
            ->get();

        $this->assertTrue(array_key_exists('fullname', $models->first()->toArray()));
    }

    // ========== Integration with Other Features ==========
    #[Test]
    public function it_works_with_filtering(): void
    {
        $model = $this->models->first();

        $models = $this
            ->createEloquentWizardFromQuery([
                'filter' => ['firstname' => $model->firstname],
                'append' => 'fullname',
            ], AppendModel::class)
            ->allowedFilters('firstname')
            ->allowedAppends('fullname')
            ->get();

        $this->assertCount(1, $models);
        $this->assertTrue(array_key_exists('fullname', $models->first()->toArray()));
    }

    #[Test]
    public function it_works_with_sorting(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery([
                'sort' => 'firstname',
                'append' => 'fullname',
            ], AppendModel::class)
            ->allowedSorts('firstname')
            ->allowedAppends('fullname')
            ->get();

        $this->assertTrue(array_key_exists('fullname', $models->first()->toArray()));
    }

    #[Test]
    public function it_works_with_fields(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery([
                'fields' => ['appendModels' => 'firstname,lastname'],
                'append' => 'fullname',
            ], AppendModel::class)
            ->allowedFields('firstname', 'lastname')
            ->allowedAppends('fullname')
            ->get();

        $array = $models->first()->toArray();
        $this->assertTrue(array_key_exists('fullname', $array));
    }

    #[Test]
    public function it_works_with_pagination(): void
    {
        // Use wizard's paginate() method to get appends applied
        $result = $this
            ->createEloquentWizardWithAppends('fullname')
            ->allowedAppends('fullname')
            ->paginate(2);

        $this->assertTrue(array_key_exists('fullname', $result->first()->toArray()));
        $this->assertEquals(3, $result->total());
    }

    #[Test]
    public function it_works_with_first(): void
    {
        // Use wizard's first() method to get appends applied
        $model = $this
            ->createEloquentWizardWithAppends('fullname')
            ->allowedAppends('fullname')
            ->first();

        $this->assertTrue(array_key_exists('fullname', $model->toArray()));
    }

    #[Test]
    public function it_works_with_first_or_fail(): void
    {
        // Use wizard's firstOrFail() method to get appends applied
        $model = $this
            ->createEloquentWizardWithAppends('fullname')
            ->allowedAppends('fullname')
            ->firstOrFail();

        $this->assertTrue(array_key_exists('fullname', $model->toArray()));
    }

    // ========== Append Values Tests ==========
    #[Test]
    public function appended_fullname_combines_first_and_last_name(): void
    {
        $model = $this->models->first();

        $result = $this
            ->createEloquentWizardWithAppends('fullname')
            ->allowedAppends('fullname')
            ->get();

        $expected = $model->firstname.' '.$model->lastname;
        $this->assertEquals($expected, $result->first()->fullname);
    }

    #[Test]
    public function appended_reversename_combines_last_and_first_name(): void
    {
        $model = $this->models->first();

        $result = $this
            ->createEloquentWizardWithAppends('reversename')
            ->allowedAppends('reversename')
            ->get();

        $expected = $model->lastname.' '.$model->firstname;
        $this->assertEquals($expected, $result->first()->reversename);
    }

    // ========== Append on All Models Tests ==========
    #[Test]
    public function all_models_have_appended_attribute(): void
    {
        $models = $this
            ->createEloquentWizardWithAppends('fullname')
            ->allowedAppends('fullname')
            ->get();

        $this->assertTrue($models->every(fn ($m) => array_key_exists('fullname', $m->toArray())));
    }

    #[Test]
    public function all_models_have_correct_appended_values(): void
    {
        $models = $this
            ->createEloquentWizardWithAppends('fullname')
            ->allowedAppends('fullname')
            ->get();

        $this->assertTrue($models->every(function ($m) {
            $expected = $m->firstname.' '.$m->lastname;

            return $m->fullname === $expected;
        }));
    }

    // ========== Combining Multiple Operations ==========
    #[Test]
    public function it_works_with_all_features_combined(): void
    {
        $model = $this->models->first();

        $result = $this
            ->createEloquentWizardFromQuery([
                'filter' => ['firstname' => $model->firstname],
                'sort' => 'lastname',
                'fields' => ['appendModels' => 'firstname,lastname'],
                'append' => 'fullname,reversename',
            ], AppendModel::class)
            ->allowedFilters('firstname')
            ->allowedSorts('lastname')
            ->allowedFields('firstname', 'lastname')
            ->allowedAppends('fullname', 'reversename')
            ->get();

        $this->assertCount(1, $result);
        $array = $result->first()->toArray();
        $this->assertTrue(array_key_exists('fullname', $array));
        $this->assertTrue(array_key_exists('reversename', $array));
    }

    // ========== Nested Appends (Dot Notation) Tests ==========
    #[Test]
    public function it_can_append_to_relation_with_dot_notation(): void
    {
        $result = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'append' => 'relatedModels.formattedName',
            ], TestModel::class)
            ->allowedIncludes('relatedModels')
            ->allowedAppends('relatedModels.formattedName')
            ->first();

        $array = $result->toArray();
        $this->assertArrayHasKey('related_models', $array);
        $this->assertNotEmpty($array['related_models']);
        // Dynamic append() keeps the key name as provided (camelCase)
        $this->assertArrayHasKey('formattedName', $array['related_models'][0]);
    }

    #[Test]
    public function it_can_append_multiple_attributes_to_relation(): void
    {
        $result = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'append' => 'relatedModels.formattedName,relatedModels.upperName',
            ], TestModel::class)
            ->allowedIncludes('relatedModels')
            ->allowedAppends('relatedModels.formattedName', 'relatedModels.upperName')
            ->first();

        $array = $result->toArray();
        $this->assertArrayHasKey('formattedName', $array['related_models'][0]);
        $this->assertArrayHasKey('upperName', $array['related_models'][0]);
    }

    #[Test]
    public function it_can_combine_root_and_relation_appends(): void
    {
        $result = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'append' => 'fullname,relatedModels.formattedName',
            ], TestModel::class)
            ->allowedIncludes('relatedModels')
            ->allowedAppends('fullname', 'relatedModels.formattedName')
            ->first();

        $array = $result->toArray();
        $this->assertArrayHasKey('fullname', $array);
        $this->assertArrayHasKey('formattedName', $array['related_models'][0]);
    }

    #[Test]
    public function it_validates_nested_appends_correctly(): void
    {
        $this->expectException(InvalidAppendQuery::class);

        $this
            ->createEloquentWizardFromQuery([
                'append' => 'relatedModels.unknownAttr',
            ], TestModel::class)
            ->allowedAppends('relatedModels.formattedName')
            ->get();
    }

    #[Test]
    public function wildcard_allows_all_relation_appends(): void
    {
        $result = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'append' => 'relatedModels.formattedName,relatedModels.upperName',
            ], TestModel::class)
            ->allowedIncludes('relatedModels')
            ->allowedAppends('relatedModels.*')
            ->first();

        $array = $result->toArray();
        $this->assertArrayHasKey('formattedName', $array['related_models'][0]);
        $this->assertArrayHasKey('upperName', $array['related_models'][0]);
    }

    #[Test]
    public function global_wildcard_allows_all_appends(): void
    {
        $models = $this
            ->createEloquentWizardWithAppends('fullname,reversename')
            ->allowedAppends('*')
            ->get();

        $array = $models->first()->toArray();
        $this->assertTrue(array_key_exists('fullname', $array));
        $this->assertTrue(array_key_exists('reversename', $array));
    }

    #[Test]
    public function global_wildcard_allows_nested_appends(): void
    {
        $result = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'append' => 'relatedModels.formattedName',
            ], TestModel::class)
            ->allowedIncludes('relatedModels')
            ->allowedAppends('*')
            ->first();

        $array = $result->toArray();
        $this->assertArrayHasKey('formattedName', $array['related_models'][0]);
    }

    #[Test]
    public function it_ignores_relation_append_when_relation_not_loaded(): void
    {
        // No include, so relation is not loaded
        $result = $this
            ->createEloquentWizardFromQuery([
                'append' => 'relatedModels.formattedName',
            ], TestModel::class)
            ->allowedAppends('relatedModels.formattedName')
            ->first();

        $array = $result->toArray();
        // Relation not loaded, so no related_models key
        $this->assertArrayNotHasKey('related_models', $array);
    }

    #[Test]
    public function nested_append_applies_to_all_models_in_collection(): void
    {
        $results = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'append' => 'relatedModels.formattedName',
            ], TestModel::class)
            ->allowedIncludes('relatedModels')
            ->allowedAppends('relatedModels.formattedName')
            ->get();

        $this->assertCount(3, $results);

        foreach ($results as $model) {
            $array = $model->toArray();
            if (! empty($array['related_models'])) {
                foreach ($array['related_models'] as $related) {
                    $this->assertArrayHasKey('formattedName', $related);
                }
            }
        }
    }

    #[Test]
    public function nested_append_respects_depth_limit(): void
    {
        config()->set('query-wizard.limits.max_append_depth', 1);

        $this->expectException(\Jackardios\QueryWizard\Exceptions\MaxAppendDepthExceeded::class);

        $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'append' => 'relatedModels.formattedName',
            ], TestModel::class)
            ->allowedIncludes('relatedModels')
            ->allowedAppends('relatedModels.formattedName')
            ->get();
    }

    #[Test]
    public function nested_append_works_with_belongs_to_relation(): void
    {
        // Create a RelatedModel with a parent TestModel
        $testModel = TestModel::factory()->create();
        $relatedModel = RelatedModel::factory()->create(['test_model_id' => $testModel->id]);

        $result = $this
            ->createEloquentWizardFromQuery([
                'include' => 'testModel',
                'append' => 'testModel.fullname',
            ], RelatedModel::class)
            ->allowedIncludes('testModel')
            ->allowedAppends('testModel.fullname')
            ->where('id', $relatedModel->id)
            ->first();

        $array = $result->toArray();
        $this->assertArrayHasKey('test_model', $array);
        $this->assertArrayHasKey('fullname', $array['test_model']);
    }

    #[Test]
    public function nested_append_handles_empty_relation(): void
    {
        // Create a TestModel without any RelatedModels
        $testModel = TestModel::factory()->create();

        $result = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'append' => 'relatedModels.formattedName',
            ], TestModel::class)
            ->allowedIncludes('relatedModels')
            ->allowedAppends('relatedModels.formattedName')
            ->where('id', $testModel->id)
            ->first();

        $array = $result->toArray();
        // Relation should be loaded but empty
        $this->assertArrayHasKey('related_models', $array);
        $this->assertEmpty($array['related_models']);
    }

    // ========== applyAppendsTo Workaround Tests ==========

    #[Test]
    public function apply_appends_to_can_be_used_with_chunk(): void
    {
        $wizard = $this
            ->createEloquentWizardWithAppends('fullname')
            ->allowedAppends('fullname');

        $processed = collect();
        $wizard->toQuery()->chunk(2, function ($chunk) use ($wizard, $processed) {
            $wizard->applyAppendsTo($chunk);
            $processed->push(...$chunk);
        });

        $this->assertCount(3, $processed);
        $this->assertTrue($processed->every(fn ($m) => array_key_exists('fullname', $m->toArray())));
    }

    // ========== Circular Reference Protection Tests ==========
    #[Test]
    public function it_handles_circular_references_in_appends(): void
    {
        // Create TestModel with RelatedModels that have back-references
        $testModel = TestModel::factory()->create();
        $relatedModel = RelatedModel::factory()->create(['test_model_id' => $testModel->id]);

        // Load circular relations: testModel -> relatedModels -> testModel
        $testModel->load(['relatedModels.testModel']);

        // Manually set up a circular reference by setting the loaded testModel
        // on the relatedModel's relation (simulating what would happen with eager loading)
        $testModel->relatedModels->first()->setRelation('testModel', $testModel);

        // This should not cause infinite recursion
        $wizard = $this->createModelWizardFromQuery([
            'append' => 'fullname,relatedModels.formattedName',
        ], $testModel);

        $result = $wizard
            ->allowedAppends('fullname', 'relatedModels.formattedName', 'relatedModels.testModel.fullname')
            ->process();

        // Should complete without stack overflow
        $this->assertArrayHasKey('fullname', $result->toArray());
    }

    #[Test]
    public function it_prevents_infinite_loop_with_self_referencing_models(): void
    {
        // Create a TestModel
        $testModel = TestModel::factory()->create();

        // Manually set up a self-referential relation (model points to itself)
        $testModel->setRelation('relatedModels', collect([$testModel]));

        // This should not cause infinite recursion when applying appends
        $wizard = $this->createModelWizardFromQuery([
            'append' => 'relatedModels.fullname',
        ], $testModel);

        $result = $wizard
            ->allowedAppends('relatedModels.fullname')
            ->process();

        // Should complete without stack overflow
        $this->assertInstanceOf(TestModel::class, $result);
    }
}
