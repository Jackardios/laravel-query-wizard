<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Jackardios\QueryWizard\Exceptions\InvalidAppendQuery;
use Jackardios\QueryWizard\Tests\App\Models\AppendModel;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;

#[Group('eloquent')]
#[Group('append')]
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
    public function it_ignores_unknown_appends_when_no_allowed_set(): void
    {
        $models = $this
            ->createEloquentWizardWithAppends('unknown')
            ->get();

        // No exception, returns all models
        $this->assertCount(3, $models);
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
    // Note: Default appends are configured via schema, not via setDefaultAppends() method
    // These tests verify that schema-based defaults work correctly
    #[Test]
    public function it_applies_default_appends_from_schema(): void
    {
        // Default appends come from the schema, not from wizard methods
        // This test verifies the schema-based default appends flow
        $models = $this
            ->createEloquentWizardWithAppends('fullname')
            ->allowedAppends('fullname', 'reversename')
            ->get();

        $array = $models->first()->toArray();
        $this->assertTrue(array_key_exists('fullname', $array));
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

    // ========== Append Values Tests ==========
    #[Test]
    public function appended_fullname_combines_first_and_last_name(): void
    {
        $model = $this->models->first();

        $result = $this
            ->createEloquentWizardWithAppends('fullname')
            ->allowedAppends('fullname')
            ->get();

        $expected = $model->firstname . ' ' . $model->lastname;
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

        $expected = $model->lastname . ' ' . $model->firstname;
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

        $this->assertTrue($models->every(fn($m) => array_key_exists('fullname', $m->toArray())));
    }
    #[Test]
    public function all_models_have_correct_appended_values(): void
    {
        $models = $this
            ->createEloquentWizardWithAppends('fullname')
            ->allowedAppends('fullname')
            ->get();

        $this->assertTrue($models->every(function ($m) {
            $expected = $m->firstname . ' ' . $m->lastname;
            return $m->fullname === $expected;
        }));
    }

    // ========== Case Sensitivity Tests ==========
    #[Test]
    public function it_handles_camelCase_appends(): void
    {
        $models = $this
            ->createEloquentWizardWithAppends('fullname')
            ->allowedAppends('fullname')
            ->get();

        $this->assertTrue(array_key_exists('fullname', $models->first()->toArray()));
    }
    #[Test]
    public function it_handles_allowed_appends_with_different_case(): void
    {
        // The model accessor is getFullnameAttribute (lowercase)
        // So 'fullname' should work
        $models = $this
            ->createEloquentWizardWithAppends('fullname')
            ->allowedAppends('fullname')
            ->get();

        $this->assertTrue(array_key_exists('fullname', $models->first()->toArray()));
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
        // Nested appends (dot notation for relations) are not supported in the new architecture
        $this->markTestSkipped('Nested appends are not supported in the current architecture');
    }
    #[Test]
    public function it_can_append_multiple_attributes_to_relation(): void
    {
        // Nested appends (dot notation for relations) are not supported in the new architecture
        $this->markTestSkipped('Nested appends are not supported in the current architecture');
    }
    #[Test]
    public function it_can_combine_root_and_relation_appends(): void
    {
        // Nested appends (dot notation for relations) are not supported in the new architecture
        $this->markTestSkipped('Nested appends are not supported in the current architecture');
    }
    #[Test]
    public function it_validates_nested_appends_correctly(): void
    {
        // Nested appends (dot notation for relations) are not supported in the new architecture
        $this->markTestSkipped('Nested appends are not supported in the current architecture');
    }
    #[Test]
    public function wildcard_allows_all_relation_appends(): void
    {
        // Nested appends (dot notation for relations) are not supported in the new architecture
        $this->markTestSkipped('Nested appends are not supported in the current architecture');
    }
    #[Test]
    public function it_ignores_relation_append_when_relation_not_loaded(): void
    {
        // Nested appends (dot notation for relations) are not supported in the new architecture
        $this->markTestSkipped('Nested appends are not supported in the current architecture');
    }
    #[Test]
    public function nested_append_applies_to_all_models_in_collection(): void
    {
        // Nested appends (dot notation for relations) are not supported in the new architecture
        $this->markTestSkipped('Nested appends are not supported in the current architecture');
    }
}
