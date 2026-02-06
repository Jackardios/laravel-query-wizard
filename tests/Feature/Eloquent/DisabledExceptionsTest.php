<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Jackardios\QueryWizard\Tests\App\Models\AppendModel;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('eloquent')]
#[Group('disabled-exceptions')]
class DisabledExceptionsTest extends TestCase
{
    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        DB::enableQueryLog();

        $this->models = TestModel::factory()->count(3)->create();
        $this->models->each(function (TestModel $model) {
            RelatedModel::factory()->count(2)->create([
                'test_model_id' => $model->id,
            ]);
        });

        AppendModel::factory()->count(3)->create();
    }

    // ========== Filters ==========

    #[Test]
    public function invalid_filter_is_ignored_and_valid_filter_applies(): void
    {
        config()->set('query-wizard.disable_invalid_filter_query_exception', true);

        $target = $this->models->first();

        $models = $this
            ->createEloquentWizardWithFilters([
                'name' => $target->name,
                'not_real' => 'value',
            ])
            ->allowedFilters('name')
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals($target->name, $models->first()->name);
    }

    // ========== Sorts ==========

    #[Test]
    public function invalid_sort_is_ignored_and_valid_sort_applies(): void
    {
        config()->set('query-wizard.disable_invalid_sort_query_exception', true);

        $models = $this
            ->createEloquentWizardWithSorts('not_real,-name')
            ->allowedSorts('name')
            ->get();

        $this->assertCount(3, $models);
        // Valid sort -name should still be applied (descending)
        $names = $models->pluck('name')->toArray();
        $sorted = $names;
        rsort($sorted);
        $this->assertEquals($sorted, $names);
    }

    // ========== Includes ==========

    #[Test]
    public function invalid_include_is_ignored_and_valid_include_loads(): void
    {
        config()->set('query-wizard.disable_invalid_include_query_exception', true);

        $models = $this
            ->createEloquentWizardWithIncludes('notReal,relatedModels')
            ->allowedIncludes('relatedModels')
            ->get();

        $this->assertCount(3, $models);
        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
        $this->assertFalse($models->first()->relationLoaded('notReal'));
    }

    // ========== Fields ==========

    #[Test]
    public function invalid_fields_are_silently_intersected(): void
    {
        config()->set('query-wizard.disable_invalid_field_query_exception', true);

        $models = $this
            ->createEloquentWizardWithFields(['testModel' => 'id,name,secret_field'])
            ->allowedFields('id', 'name')
            ->get();

        $this->assertCount(3, $models);
        $attributes = array_keys($models->first()->getAttributes());
        $this->assertContains('id', $attributes);
        $this->assertContains('name', $attributes);
        $this->assertNotContains('secret_field', $attributes);
    }

    // ========== Appends ==========

    #[Test]
    public function invalid_appends_are_filtered_out(): void
    {
        config()->set('query-wizard.disable_invalid_append_query_exception', true);

        $models = $this
            ->createEloquentWizardWithAppends('fullname,not_real')
            ->allowedAppends('fullname')
            ->get();

        $this->assertCount(3, $models);
        $this->assertTrue(array_key_exists('fullname', $models->first()->toArray()));
        $this->assertFalse(array_key_exists('not_real', $models->first()->toArray()));
    }

    // ========== Combined ==========

    #[Test]
    public function all_exceptions_disabled_with_mixed_valid_and_invalid(): void
    {
        config()->set('query-wizard.disable_invalid_filter_query_exception', true);
        config()->set('query-wizard.disable_invalid_sort_query_exception', true);
        config()->set('query-wizard.disable_invalid_include_query_exception', true);
        config()->set('query-wizard.disable_invalid_field_query_exception', true);
        config()->set('query-wizard.disable_invalid_append_query_exception', true);

        $target = $this->models->first();

        $models = $this
            ->createEloquentWizardFromQuery([
                'filter' => ['name' => $target->name, 'fake' => 'x'],
                'sort' => 'fake,-name',
                'include' => 'relatedModels,fakeRelation',
                'fields' => ['testModel' => 'id,name,secret'],
            ])
            ->allowedFilters('name')
            ->allowedSorts('name')
            ->allowedIncludes('relatedModels')
            ->allowedFields('id', 'name')
            ->get();

        $this->assertCount(1, $models);
        $this->assertTrue($models->first()->relationLoaded('relatedModels'));

        $attributes = array_keys($models->first()->getAttributes());
        $this->assertContains('id', $attributes);
        $this->assertContains('name', $attributes);
        $this->assertNotContains('secret', $attributes);
    }
}
