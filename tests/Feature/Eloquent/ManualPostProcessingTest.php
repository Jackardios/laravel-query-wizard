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
#[Group('post-processing')]
class ManualPostProcessingTest extends TestCase
{
    protected Collection $appendModels;

    protected Collection $testModels;

    protected function setUp(): void
    {
        parent::setUp();

        DB::enableQueryLog();

        $this->appendModels = AppendModel::factory()->count(5)->create();

        $this->testModels = TestModel::factory()->count(3)->create();
        $this->testModels->each(function (TestModel $model) {
            RelatedModel::factory()->count(2)->create(['test_model_id' => $model->id]);
        });
    }

    // ========== Appends Tests ==========

    #[Test]
    public function it_applies_appends_with_chunk(): void
    {
        $wizard = $this
            ->createEloquentWizardWithAppends('fullname')
            ->allowedAppends('fullname');

        $processed = collect();
        $wizard->toQuery()->chunk(2, function ($chunk) use ($wizard, $processed) {
            $wizard->applyPostProcessingTo($chunk);
            $processed->push(...$chunk);
        });

        $this->assertCount(5, $processed);
        $this->assertTrue($processed->every(fn ($m) => array_key_exists('fullname', $m->toArray())));
    }

    #[Test]
    public function it_applies_appends_with_cursor(): void
    {
        $wizard = $this
            ->createEloquentWizardWithAppends('fullname')
            ->allowedAppends('fullname');

        $processed = collect();
        foreach ($wizard->toQuery()->cursor() as $model) {
            $wizard->applyPostProcessingTo($model);
            $processed->push($model);
        }

        $this->assertCount(5, $processed);
        $this->assertTrue($processed->every(fn ($m) => array_key_exists('fullname', $m->toArray())));
    }

    #[Test]
    public function it_applies_appends_to_single_model(): void
    {
        $wizard = $this
            ->createEloquentWizardWithAppends('fullname')
            ->allowedAppends('fullname');

        $model = AppendModel::first();
        $wizard->applyPostProcessingTo($model);

        $this->assertTrue(array_key_exists('fullname', $model->toArray()));
        $this->assertEquals($model->firstname.' '.$model->lastname, $model->fullname);
    }

    // ========== Relation Field Masking Tests ==========

    #[Test]
    public function it_applies_relation_field_masking_with_chunk(): void
    {
        $wizard = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'fields' => [
                    'testModel' => 'id,name',
                    'relatedModels' => 'id',
                ],
            ], TestModel::query())
            ->allowedIncludes('relatedModels')
            ->allowedFields('id', 'name', 'relatedModels.id');

        $processed = collect();
        $wizard->toQuery()->chunk(2, function ($chunk) use ($wizard, $processed) {
            $wizard->applyPostProcessingTo($chunk);
            $processed->push(...$chunk);
        });

        $this->assertCount(3, $processed);
        $model = $processed->first();
        $this->assertTrue($model->relationLoaded('relatedModels'));

        $relatedArray = $model->relatedModels->first()->toArray();
        $this->assertArrayHasKey('id', $relatedArray);
        $this->assertArrayNotHasKey('name', $relatedArray);
        $this->assertArrayNotHasKey('test_model_id', $relatedArray);
    }

    #[Test]
    public function it_applies_relation_field_masking_to_manually_loaded_relations(): void
    {
        $wizard = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'fields' => [
                    'testModel' => 'id,name',
                    'relatedModels' => 'id,name',
                ],
            ], TestModel::query())
            ->allowedIncludes('relatedModels')
            ->allowedFields('id', 'name', 'relatedModels.id', 'relatedModels.name');

        // Manually fetch and load relations (simulating cursor + manual load scenario)
        $models = TestModel::with('relatedModels')->get();
        $wizard->applyPostProcessingTo($models);

        $model = $models->first();
        $this->assertTrue($model->relationLoaded('relatedModels'));

        $relatedArray = $model->relatedModels->first()->toArray();
        $this->assertArrayHasKey('id', $relatedArray);
        $this->assertArrayHasKey('name', $relatedArray);
        $this->assertArrayNotHasKey('test_model_id', $relatedArray);
    }

    // ========== Combined Fields + Appends Tests ==========

    #[Test]
    public function it_applies_both_relation_fields_and_appends(): void
    {
        $wizard = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'fields' => [
                    'testModel' => 'id,name',
                    'relatedModels' => 'id',
                ],
                'append' => 'relatedModels.formattedName',
            ], TestModel::query())
            ->allowedIncludes('relatedModels')
            ->allowedFields('id', 'name', 'relatedModels.id')
            ->allowedAppends('relatedModels.formattedName');

        $processed = collect();
        $wizard->toQuery()->chunk(2, function ($chunk) use ($wizard, $processed) {
            $wizard->applyPostProcessingTo($chunk);
            $processed->push(...$chunk);
        });

        $this->assertCount(3, $processed);
        $model = $processed->first();

        $relatedArray = $model->relatedModels->first()->toArray();
        $this->assertArrayHasKey('id', $relatedArray);
        $this->assertArrayHasKey('formattedName', $relatedArray);
        $this->assertArrayNotHasKey('test_model_id', $relatedArray);
    }

    // ========== Edge Cases ==========

    #[Test]
    public function it_handles_empty_collection(): void
    {
        $wizard = $this
            ->createEloquentWizardWithAppends('fullname')
            ->allowedAppends('fullname');

        $empty = collect();
        $result = $wizard->applyPostProcessingTo($empty);

        $this->assertCount(0, $result);
    }

    #[Test]
    public function it_returns_the_same_results_reference(): void
    {
        $wizard = $this
            ->createEloquentWizardWithAppends('fullname')
            ->allowedAppends('fullname');

        $models = AppendModel::all();
        $result = $wizard->applyPostProcessingTo($models);

        $this->assertSame($models, $result);
    }

    #[Test]
    public function to_query_get_does_not_apply_post_processing(): void
    {
        $wizard = $this
            ->createEloquentWizardWithAppends('fullname')
            ->allowedAppends('fullname');

        // toQuery()->get() bypasses wizard's post-processing logic
        $models = $wizard->toQuery()->get();

        $this->assertCount(5, $models);
        $this->assertFalse(array_key_exists('fullname', $models->first()->toArray()));
    }

    #[Test]
    public function to_query_get_does_not_validate_appends(): void
    {
        $wizard = $this
            ->createEloquentWizardWithAppends('notAllowed')
            ->allowedAppends('fullname');

        // toQuery()->get() bypasses append validation (validation is lazy, happens in post-processing)
        // This would throw InvalidAppendQuery if using $wizard->get() directly
        $models = $wizard->toQuery()->get();

        $this->assertCount(5, $models);
        $this->assertFalse(array_key_exists('notAllowed', $models->first()->toArray()));
    }
}
