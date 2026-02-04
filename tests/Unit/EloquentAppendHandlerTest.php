<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Jackardios\QueryWizard\Drivers\Eloquent\EloquentAppendHandler;
use Jackardios\QueryWizard\Tests\App\Models\AppendModel;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;

class EloquentAppendHandlerTest extends TestCase
{
    private EloquentAppendHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new EloquentAppendHandler();
    }

    // ========== Empty Appends Tests ==========
    #[Test]
    public function it_returns_result_unchanged_when_no_appends(): void
    {
        $model = AppendModel::factory()->create();

        $result = $this->handler->applyAppends($model, []);

        $this->assertSame($model, $result);
        $this->assertFalse(array_key_exists('fullname', $model->toArray()));
    }

    // ========== Single Model Tests ==========
    #[Test]
    public function it_applies_appends_to_single_model(): void
    {
        $model = AppendModel::factory()->create();

        $this->handler->applyAppends($model, ['fullname']);

        $this->assertTrue(array_key_exists('fullname', $model->toArray()));
    }

    #[Test]
    public function it_applies_multiple_appends_to_single_model(): void
    {
        $model = AppendModel::factory()->create();

        $this->handler->applyAppends($model, ['fullname', 'reversename']);

        $array = $model->toArray();
        $this->assertTrue(array_key_exists('fullname', $array));
        $this->assertTrue(array_key_exists('reversename', $array));
    }

    // ========== Collection Tests ==========
    #[Test]
    public function it_applies_appends_to_collection(): void
    {
        $models = AppendModel::factory()->count(3)->create();

        $this->handler->applyAppends($models, ['fullname']);

        foreach ($models as $model) {
            $this->assertTrue(array_key_exists('fullname', $model->toArray()));
        }
    }

    #[Test]
    public function it_applies_appends_to_eloquent_collection(): void
    {
        AppendModel::factory()->count(3)->create();
        $models = AppendModel::all();

        $this->handler->applyAppends($models, ['fullname']);

        foreach ($models as $model) {
            $this->assertTrue(array_key_exists('fullname', $model->toArray()));
        }
    }

    // ========== Relation Appends Tests ==========
    #[Test]
    public function it_applies_appends_to_loaded_relation(): void
    {
        $testModel = TestModel::factory()->create();
        RelatedModel::factory()->count(2)->create([
            'test_model_id' => $testModel->id,
        ]);

        $testModel->load('relatedModels');

        $this->handler->applyAppends($testModel, ['relatedModels.formattedName']);

        foreach ($testModel->relatedModels as $related) {
            $this->assertTrue(array_key_exists('formattedName', $related->toArray()));
        }
    }

    #[Test]
    public function it_ignores_relation_appends_when_relation_not_loaded(): void
    {
        $testModel = TestModel::factory()->create();
        RelatedModel::factory()->create([
            'test_model_id' => $testModel->id,
        ]);

        // Don't load the relation
        $this->handler->applyAppends($testModel, ['relatedModels.formattedName']);

        // Should not throw, just silently ignore
        $this->assertFalse($testModel->relationLoaded('relatedModels'));
    }

    #[Test]
    public function it_applies_root_and_relation_appends_separately(): void
    {
        $testModel = TestModel::factory()->create();
        RelatedModel::factory()->count(2)->create([
            'test_model_id' => $testModel->id,
        ]);

        $testModel->load('relatedModels');

        // Root model (TestModel) doesn't have 'fullname', only RelatedModel has 'formattedName'
        $this->handler->applyAppends($testModel, ['relatedModels.formattedName']);

        foreach ($testModel->relatedModels as $related) {
            $this->assertTrue(array_key_exists('formattedName', $related->toArray()));
        }
    }

    #[Test]
    public function it_applies_multiple_appends_to_relation(): void
    {
        $testModel = TestModel::factory()->create();
        RelatedModel::factory()->count(2)->create([
            'test_model_id' => $testModel->id,
        ]);

        $testModel->load('relatedModels');

        $this->handler->applyAppends($testModel, [
            'relatedModels.formattedName',
            'relatedModels.upperName',
        ]);

        foreach ($testModel->relatedModels as $related) {
            $array = $related->toArray();
            $this->assertTrue(array_key_exists('formattedName', $array));
            $this->assertTrue(array_key_exists('upperName', $array));
        }
    }

    // ========== Collection with Relations Tests ==========
    #[Test]
    public function it_applies_relation_appends_to_collection(): void
    {
        $testModels = TestModel::factory()->count(2)->create();
        foreach ($testModels as $testModel) {
            RelatedModel::factory()->count(2)->create([
                'test_model_id' => $testModel->id,
            ]);
        }

        $models = TestModel::with('relatedModels')->get();

        $this->handler->applyAppends($models, ['relatedModels.formattedName']);

        foreach ($models as $model) {
            foreach ($model->relatedModels as $related) {
                $this->assertTrue(array_key_exists('formattedName', $related->toArray()));
            }
        }
    }

    // ========== applyAppendsToModels Direct Tests ==========
    #[Test]
    public function apply_appends_to_models_handles_single_model(): void
    {
        $model = AppendModel::factory()->create();

        $this->handler->applyAppendsToModels($model, ['fullname']);

        $this->assertTrue(array_key_exists('fullname', $model->toArray()));
    }

    #[Test]
    public function apply_appends_to_models_handles_collection(): void
    {
        $models = AppendModel::factory()->count(3)->create();

        $this->handler->applyAppendsToModels($models, ['fullname']);

        foreach ($models as $model) {
            $this->assertTrue(array_key_exists('fullname', $model->toArray()));
        }
    }

    #[Test]
    public function apply_appends_to_models_handles_array(): void
    {
        $models = AppendModel::factory()->count(3)->create()->all();

        $this->handler->applyAppendsToModels($models, ['fullname']);

        foreach ($models as $model) {
            $this->assertTrue(array_key_exists('fullname', $model->toArray()));
        }
    }

    // ========== applyAppendsToRelation Direct Tests ==========
    #[Test]
    public function apply_appends_to_relation_handles_single_model(): void
    {
        $testModel = TestModel::factory()->create();
        RelatedModel::factory()->create([
            'test_model_id' => $testModel->id,
        ]);

        $testModel->load('relatedModels');

        $this->handler->applyAppendsToRelation($testModel, 'relatedModels', ['formattedName']);

        foreach ($testModel->relatedModels as $related) {
            $this->assertTrue(array_key_exists('formattedName', $related->toArray()));
        }
    }

    #[Test]
    public function apply_appends_to_relation_handles_collection(): void
    {
        $testModels = TestModel::factory()->count(2)->create();
        foreach ($testModels as $testModel) {
            RelatedModel::factory()->create([
                'test_model_id' => $testModel->id,
            ]);
        }

        $models = TestModel::with('relatedModels')->get();

        $this->handler->applyAppendsToRelation($models, 'relatedModels', ['formattedName']);

        foreach ($models as $model) {
            foreach ($model->relatedModels as $related) {
                $this->assertTrue(array_key_exists('formattedName', $related->toArray()));
            }
        }
    }

    // ========== Edge Cases ==========
    #[Test]
    public function it_handles_non_model_items_in_iterable(): void
    {
        $mixed = [
            AppendModel::factory()->create(),
            'not a model',
            123,
            null,
        ];

        // Should not throw
        $this->handler->applyAppendsToModels($mixed, ['fullname']);

        // Only the model should have the append
        $this->assertTrue(array_key_exists('fullname', $mixed[0]->toArray()));
    }

    #[Test]
    public function it_handles_belongs_to_relation(): void
    {
        $testModel = TestModel::factory()->create();
        $relatedModel = RelatedModel::factory()->create([
            'test_model_id' => $testModel->id,
        ]);

        $relatedModel->load('testModel');

        // This relation is singular (belongsTo), not a collection
        $this->handler->applyAppendsToRelation($relatedModel, 'testModel', []);

        // Should not throw, just work correctly
        $this->assertTrue($relatedModel->relationLoaded('testModel'));
    }
}
