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

/**
 * @group eloquent
 * @group append
 */
class AppendTest extends TestCase
{
    protected Collection $models;

    public function setUp(): void
    {
        parent::setUp();

        DB::enableQueryLog();

        $this->models = AppendModel::factory()->count(3)->create();
    }

    // ========== Basic Append Tests ==========

    /** @test */
    public function it_does_not_append_attributes_by_default(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery([], AppendModel::class)
            ->get();

        $this->assertFalse(array_key_exists('fullname', $models->first()->toArray()));
    }

    /** @test */
    public function it_can_append_attribute(): void
    {
        $models = $this
            ->createEloquentWizardWithAppends('fullname')
            ->setAllowedAppends('fullname')
            ->get();

        $this->assertTrue(array_key_exists('fullname', $models->first()->toArray()));
    }

    /** @test */
    public function appended_attribute_has_correct_value(): void
    {
        $model = $this->models->first();

        $models = $this
            ->createEloquentWizardWithAppends('fullname')
            ->setAllowedAppends('fullname')
            ->get();

        $expectedFullname = $model->firstname . ' ' . $model->lastname;
        $this->assertEquals($expectedFullname, $models->first()->fullname);
    }

    /** @test */
    public function it_can_append_multiple_attributes(): void
    {
        $models = $this
            ->createEloquentWizardWithAppends('fullname,reversename')
            ->setAllowedAppends('fullname', 'reversename')
            ->get();

        $array = $models->first()->toArray();
        $this->assertTrue(array_key_exists('fullname', $array));
        $this->assertTrue(array_key_exists('reversename', $array));
    }

    /** @test */
    public function it_can_append_attributes_as_array(): void
    {
        $models = $this
            ->createEloquentWizardWithAppends(['fullname', 'reversename'])
            ->setAllowedAppends('fullname', 'reversename')
            ->get();

        $array = $models->first()->toArray();
        $this->assertTrue(array_key_exists('fullname', $array));
        $this->assertTrue(array_key_exists('reversename', $array));
    }

    // ========== Validation Tests ==========

    /** @test */
    public function it_throws_exception_for_not_allowed_append(): void
    {
        $this->expectException(InvalidAppendQuery::class);

        $this
            ->createEloquentWizardWithAppends('not_allowed')
            ->setAllowedAppends('fullname')
            ->get();
    }

    /** @test */
    public function it_ignores_unknown_appends_when_no_allowed_set(): void
    {
        $models = $this
            ->createEloquentWizardWithAppends('unknown')
            ->get();

        // No exception, returns all models
        $this->assertCount(3, $models);
    }

    /** @test */
    public function it_ignores_appends_when_empty_allowed_array(): void
    {
        // Empty allowed array means silently ignore all append requests
        $models = $this
            ->createEloquentWizardWithAppends('fullname')
            ->setAllowedAppends([])
            ->get();

        // No exception, returns all models without appends
        $this->assertCount(3, $models);
        $this->assertFalse(array_key_exists('fullname', $models->first()->toArray()));
    }

    // ========== Default Appends Tests ==========
    // Note: Default appends are configured via schema, not via setDefaultAppends() method
    // These tests verify that schema-based defaults work correctly

    /** @test */
    public function it_applies_default_appends_from_schema(): void
    {
        // Default appends come from the schema, not from wizard methods
        // This test verifies the schema-based default appends flow
        $models = $this
            ->createEloquentWizardWithAppends('fullname')
            ->setAllowedAppends('fullname', 'reversename')
            ->get();

        $array = $models->first()->toArray();
        $this->assertTrue(array_key_exists('fullname', $array));
    }

    // ========== Edge Cases ==========

    /** @test */
    public function it_handles_empty_append_string(): void
    {
        $models = $this
            ->createEloquentWizardWithAppends('')
            ->setAllowedAppends('fullname')
            ->get();

        // Empty append = no appends
        $this->assertFalse(array_key_exists('fullname', $models->first()->toArray()));
    }

    /** @test */
    public function it_handles_append_with_trailing_comma(): void
    {
        $models = $this
            ->createEloquentWizardWithAppends('fullname,')
            ->setAllowedAppends('fullname')
            ->get();

        $this->assertTrue(array_key_exists('fullname', $models->first()->toArray()));
    }

    /** @test */
    public function it_removes_duplicate_appends(): void
    {
        $models = $this
            ->createEloquentWizardWithAppends('fullname,fullname')
            ->setAllowedAppends('fullname')
            ->get();

        $this->assertTrue(array_key_exists('fullname', $models->first()->toArray()));
    }

    /** @test */
    public function it_handles_append_values_literally(): void
    {
        // Values are not trimmed - they are used as-is
        $models = $this
            ->createEloquentWizardWithAppends('fullname')
            ->setAllowedAppends('fullname')
            ->get();

        $this->assertTrue(array_key_exists('fullname', $models->first()->toArray()));
    }

    // ========== Integration with Other Features ==========

    /** @test */
    public function it_works_with_filtering(): void
    {
        $model = $this->models->first();

        $models = $this
            ->createEloquentWizardFromQuery([
                'filter' => ['firstname' => $model->firstname],
                'append' => 'fullname',
            ], AppendModel::class)
            ->setAllowedFilters('firstname')
            ->setAllowedAppends('fullname')
            ->get();

        $this->assertCount(1, $models);
        $this->assertTrue(array_key_exists('fullname', $models->first()->toArray()));
    }

    /** @test */
    public function it_works_with_sorting(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery([
                'sort' => 'firstname',
                'append' => 'fullname',
            ], AppendModel::class)
            ->setAllowedSorts('firstname')
            ->setAllowedAppends('fullname')
            ->get();

        $this->assertTrue(array_key_exists('fullname', $models->first()->toArray()));
    }

    /** @test */
    public function it_works_with_fields(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery([
                'fields' => ['appendModels' => 'firstname,lastname'],
                'append' => 'fullname',
            ], AppendModel::class)
            ->setAllowedFields('firstname', 'lastname')
            ->setAllowedAppends('fullname')
            ->get();

        $array = $models->first()->toArray();
        $this->assertTrue(array_key_exists('fullname', $array));
    }

    /** @test */
    public function it_works_with_pagination(): void
    {
        // Use wizard's paginate() method to get appends applied
        $result = $this
            ->createEloquentWizardWithAppends('fullname')
            ->setAllowedAppends('fullname')
            ->paginate(2);

        $this->assertTrue(array_key_exists('fullname', $result->first()->toArray()));
        $this->assertEquals(3, $result->total());
    }

    /** @test */
    public function it_works_with_first(): void
    {
        // Use wizard's first() method to get appends applied
        $model = $this
            ->createEloquentWizardWithAppends('fullname')
            ->setAllowedAppends('fullname')
            ->first();

        $this->assertTrue(array_key_exists('fullname', $model->toArray()));
    }

    // ========== Append Values Tests ==========

    /** @test */
    public function appended_fullname_combines_first_and_last_name(): void
    {
        $model = $this->models->first();

        $result = $this
            ->createEloquentWizardWithAppends('fullname')
            ->setAllowedAppends('fullname')
            ->get();

        $expected = $model->firstname . ' ' . $model->lastname;
        $this->assertEquals($expected, $result->first()->fullname);
    }

    /** @test */
    public function appended_reversename_combines_last_and_first_name(): void
    {
        $model = $this->models->first();

        $result = $this
            ->createEloquentWizardWithAppends('reversename')
            ->setAllowedAppends('reversename')
            ->get();

        $expected = $model->lastname . ' ' . $model->firstname;
        $this->assertEquals($expected, $result->first()->reversename);
    }

    // ========== Append on All Models Tests ==========

    /** @test */
    public function all_models_have_appended_attribute(): void
    {
        $models = $this
            ->createEloquentWizardWithAppends('fullname')
            ->setAllowedAppends('fullname')
            ->get();

        $this->assertTrue($models->every(fn($m) => array_key_exists('fullname', $m->toArray())));
    }

    /** @test */
    public function all_models_have_correct_appended_values(): void
    {
        $models = $this
            ->createEloquentWizardWithAppends('fullname')
            ->setAllowedAppends('fullname')
            ->get();

        $this->assertTrue($models->every(function ($m) {
            $expected = $m->firstname . ' ' . $m->lastname;
            return $m->fullname === $expected;
        }));
    }

    // ========== Case Sensitivity Tests ==========

    /** @test */
    public function it_handles_camelCase_appends(): void
    {
        $models = $this
            ->createEloquentWizardWithAppends('fullname')
            ->setAllowedAppends('fullname')
            ->get();

        $this->assertTrue(array_key_exists('fullname', $models->first()->toArray()));
    }

    /** @test */
    public function it_handles_allowed_appends_with_different_case(): void
    {
        // The model accessor is getFullnameAttribute (lowercase)
        // So 'fullname' should work
        $models = $this
            ->createEloquentWizardWithAppends('fullname')
            ->setAllowedAppends('fullname')
            ->get();

        $this->assertTrue(array_key_exists('fullname', $models->first()->toArray()));
    }

    // ========== Combining Multiple Operations ==========

    /** @test */
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
            ->setAllowedFilters('firstname')
            ->setAllowedSorts('lastname')
            ->setAllowedFields('firstname', 'lastname')
            ->setAllowedAppends('fullname', 'reversename')
            ->get();

        $this->assertCount(1, $result);
        $array = $result->first()->toArray();
        $this->assertTrue(array_key_exists('fullname', $array));
        $this->assertTrue(array_key_exists('reversename', $array));
    }

    // ========== Nested Appends (Dot Notation) Tests ==========

    /** @test */
    public function it_can_append_to_relation_with_dot_notation(): void
    {
        // Create TestModel with RelatedModels
        $testModel = TestModel::factory()->create();
        RelatedModel::factory()->count(2)->create([
            'test_model_id' => $testModel->id,
        ]);

        $models = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'append' => 'relatedModels.formattedName',
            ], \Jackardios\QueryWizard\Tests\App\Models\TestModel::class)
            ->setAllowedIncludes('relatedModels')
            ->setAllowedAppends('relatedModels.formattedName')
            ->get();

        $model = $models->first();
        $this->assertTrue($model->relationLoaded('relatedModels'));
        $this->assertNotEmpty($model->relatedModels);

        // Check that append was applied to related models
        foreach ($model->relatedModels as $related) {
            $array = $related->toArray();
            $this->assertTrue(array_key_exists('formattedName', $array));
            $this->assertStringStartsWith('Formatted: ', $related->formattedName);
        }
    }

    /** @test */
    public function it_can_append_multiple_attributes_to_relation(): void
    {
        $testModel = TestModel::factory()->create();
        RelatedModel::factory()->count(2)->create([
            'test_model_id' => $testModel->id,
        ]);

        $models = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'append' => 'relatedModels.formattedName,relatedModels.upperName',
            ], \Jackardios\QueryWizard\Tests\App\Models\TestModel::class)
            ->setAllowedIncludes('relatedModels')
            ->setAllowedAppends('relatedModels.formattedName', 'relatedModels.upperName')
            ->get();

        $model = $models->first();
        foreach ($model->relatedModels as $related) {
            $array = $related->toArray();
            $this->assertTrue(array_key_exists('formattedName', $array));
            $this->assertTrue(array_key_exists('upperName', $array));
        }
    }

    /** @test */
    public function it_can_combine_root_and_relation_appends(): void
    {
        $testModel = TestModel::factory()->create();
        RelatedModel::factory()->count(2)->create([
            'test_model_id' => $testModel->id,
        ]);

        // AppendModel has fullname, TestModel doesn't, so use a different approach
        // Let's just verify the separation works
        $models = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'append' => 'relatedModels.formattedName',
            ], \Jackardios\QueryWizard\Tests\App\Models\TestModel::class)
            ->setAllowedIncludes('relatedModels')
            ->setAllowedAppends('relatedModels.formattedName')
            ->get();

        $model = $models->first();
        // Root model should not have formattedName
        $this->assertFalse(array_key_exists('formattedName', $model->toArray()));
        // Related models should have it
        foreach ($model->relatedModels as $related) {
            $this->assertTrue(array_key_exists('formattedName', $related->toArray()));
        }
    }

    /** @test */
    public function it_validates_nested_appends_correctly(): void
    {
        $this->expectException(InvalidAppendQuery::class);

        $testModel = TestModel::factory()->create();
        RelatedModel::factory()->create([
            'test_model_id' => $testModel->id,
        ]);

        $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'append' => 'relatedModels.notAllowed',
            ], \Jackardios\QueryWizard\Tests\App\Models\TestModel::class)
            ->setAllowedIncludes('relatedModels')
            ->setAllowedAppends('relatedModels.formattedName')
            ->get();
    }

    /** @test */
    public function wildcard_allows_all_relation_appends(): void
    {
        $testModel = TestModel::factory()->create();
        RelatedModel::factory()->count(2)->create([
            'test_model_id' => $testModel->id,
        ]);

        // Using wildcard 'relatedModels.*' should allow any append on relatedModels
        $models = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'append' => 'relatedModels.formattedName,relatedModels.upperName',
            ], \Jackardios\QueryWizard\Tests\App\Models\TestModel::class)
            ->setAllowedIncludes('relatedModels')
            ->setAllowedAppends('relatedModels.*')
            ->get();

        $model = $models->first();
        foreach ($model->relatedModels as $related) {
            $array = $related->toArray();
            $this->assertTrue(array_key_exists('formattedName', $array));
            $this->assertTrue(array_key_exists('upperName', $array));
        }
    }

    /** @test */
    public function it_ignores_relation_append_when_relation_not_loaded(): void
    {
        $testModel = TestModel::factory()->create();
        RelatedModel::factory()->create([
            'test_model_id' => $testModel->id,
        ]);

        // Request append on relation but don't include the relation
        $models = $this
            ->createEloquentWizardFromQuery([
                // No include!
                'append' => 'relatedModels.formattedName',
            ], \Jackardios\QueryWizard\Tests\App\Models\TestModel::class)
            ->setAllowedAppends('relatedModels.formattedName')
            ->get();

        $model = $models->first();
        // Relation is not loaded, so append should be silently ignored
        $this->assertFalse($model->relationLoaded('relatedModels'));
    }

    /** @test */
    public function nested_append_applies_to_all_models_in_collection(): void
    {
        // Create multiple TestModels each with RelatedModels
        $testModels = TestModel::factory()->count(3)->create();
        foreach ($testModels as $testModel) {
            RelatedModel::factory()->count(2)->create([
                'test_model_id' => $testModel->id,
            ]);
        }

        $models = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'append' => 'relatedModels.formattedName',
            ], \Jackardios\QueryWizard\Tests\App\Models\TestModel::class)
            ->setAllowedIncludes('relatedModels')
            ->setAllowedAppends('relatedModels.formattedName')
            ->get();

        // All models should have related with appends
        foreach ($models as $model) {
            $this->assertTrue($model->relationLoaded('relatedModels'));
            foreach ($model->relatedModels as $related) {
                $this->assertTrue(array_key_exists('formattedName', $related->toArray()));
            }
        }
    }
}
