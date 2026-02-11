<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Support\Collection;
use Jackardios\QueryWizard\Tests\App\Models\AppendModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('eloquent')]
#[Group('batch')]
class BatchMethodsTest extends TestCase
{
    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();
        $this->models = AppendModel::factory()->count(10)->create();
    }

    // ========== Chunk Tests ==========
    #[Test]
    public function it_can_chunk_with_automatic_appends(): void
    {
        $processedModels = collect();

        $this
            ->createEloquentWizardFromQuery(['append' => 'fullName'], AppendModel::class)
            ->allowedAppends('fullName')
            ->chunk(3, function ($models) use ($processedModels) {
                $processedModels->push(...$models);
            });

        $this->assertCount(10, $processedModels);
        $this->assertTrue(in_array('fullName', $processedModels->first()->getAppends(), true));
    }

    #[Test]
    public function chunk_callback_can_stop_iteration(): void
    {
        $count = 0;

        $this
            ->createEloquentWizardFromQuery([], AppendModel::class)
            ->chunk(3, function () use (&$count) {
                $count++;

                return false;
            });

        $this->assertEquals(1, $count);
    }

    #[Test]
    public function chunk_returns_true_when_complete(): void
    {
        $result = $this
            ->createEloquentWizardFromQuery([], AppendModel::class)
            ->chunk(3, function () {
                // Continue
            });

        $this->assertTrue($result);
    }

    #[Test]
    public function chunk_returns_false_when_stopped(): void
    {
        $result = $this
            ->createEloquentWizardFromQuery([], AppendModel::class)
            ->chunk(3, function () {
                return false;
            });

        $this->assertFalse($result);
    }

    // ========== Lazy Tests ==========
    #[Test]
    public function it_can_lazy_with_automatic_appends(): void
    {
        $lazyCollection = $this
            ->createEloquentWizardFromQuery(['append' => 'fullName'], AppendModel::class)
            ->allowedAppends('fullName')
            ->lazy(3);

        $first = $lazyCollection->first();
        $this->assertTrue(in_array('fullName', $first->getAppends(), true));
    }

    #[Test]
    public function lazy_processes_all_models(): void
    {
        $processedModels = $this
            ->createEloquentWizardFromQuery(['append' => 'fullName'], AppendModel::class)
            ->allowedAppends('fullName')
            ->lazy(3)
            ->collect();

        $this->assertCount(10, $processedModels);
        foreach ($processedModels as $model) {
            $this->assertTrue(in_array('fullName', $model->getAppends(), true));
        }
    }

    #[Test]
    public function lazy_returns_lazy_collection(): void
    {
        $result = $this
            ->createEloquentWizardFromQuery([], AppendModel::class)
            ->lazy(3);

        $this->assertInstanceOf(\Illuminate\Support\LazyCollection::class, $result);
    }

    // ========== Cursor Tests ==========
    #[Test]
    public function it_can_cursor_with_automatic_appends(): void
    {
        $lazyCollection = $this
            ->createEloquentWizardFromQuery(['append' => 'fullName'], AppendModel::class)
            ->allowedAppends('fullName')
            ->cursor();

        $first = $lazyCollection->first();
        $this->assertTrue(in_array('fullName', $first->getAppends(), true));
    }

    #[Test]
    public function cursor_processes_all_models(): void
    {
        $processedModels = $this
            ->createEloquentWizardFromQuery(['append' => 'fullName'], AppendModel::class)
            ->allowedAppends('fullName')
            ->cursor()
            ->collect();

        $this->assertCount(10, $processedModels);
        foreach ($processedModels as $model) {
            $this->assertTrue(in_array('fullName', $model->getAppends(), true));
        }
    }

    #[Test]
    public function cursor_returns_lazy_collection(): void
    {
        $result = $this
            ->createEloquentWizardFromQuery([], AppendModel::class)
            ->cursor();

        $this->assertInstanceOf(\Illuminate\Support\LazyCollection::class, $result);
    }

    // ========== ChunkById Tests ==========
    #[Test]
    public function it_can_chunk_by_id_with_automatic_appends(): void
    {
        $processedModels = collect();

        $this
            ->createEloquentWizardFromQuery(['append' => 'fullName'], AppendModel::class)
            ->allowedAppends('fullName')
            ->chunkById(3, function ($models) use ($processedModels) {
                $processedModels->push(...$models);
            });

        $this->assertCount(10, $processedModels);
        $this->assertTrue(in_array('fullName', $processedModels->first()->getAppends(), true));
    }

    #[Test]
    public function chunk_by_id_callback_can_stop_iteration(): void
    {
        $count = 0;

        $this
            ->createEloquentWizardFromQuery([], AppendModel::class)
            ->chunkById(3, function () use (&$count) {
                $count++;

                return false;
            });

        $this->assertEquals(1, $count);
    }

    #[Test]
    public function chunk_by_id_accepts_custom_column(): void
    {
        $processedModels = collect();

        $result = $this
            ->createEloquentWizardFromQuery([], AppendModel::class)
            ->chunkById(3, function ($models) use ($processedModels) {
                $processedModels->push(...$models);
            }, 'id');

        $this->assertTrue($result);
        $this->assertCount(10, $processedModels);
    }

    // ========== Integration Tests ==========
    #[Test]
    public function batch_methods_work_with_filters(): void
    {
        $targetModel = $this->models->first();

        $processedModels = collect();

        $this
            ->createEloquentWizardFromQuery([
                'append' => 'fullName',
                'filter' => ['id' => $targetModel->id],
            ], AppendModel::class)
            ->allowedAppends('fullName')
            ->allowedFilters('id')
            ->chunk(3, function ($models) use ($processedModels) {
                $processedModels->push(...$models);
            });

        $this->assertCount(1, $processedModels);
        $this->assertEquals($targetModel->id, $processedModels->first()->id);
    }

    #[Test]
    public function batch_methods_work_with_sorts(): void
    {
        $sortedIds = $this
            ->createEloquentWizardFromQuery(['sort' => '-id'], AppendModel::class)
            ->allowedSorts('id')
            ->lazy(3)
            ->pluck('id')
            ->collect();

        $this->assertEquals(
            $this->models->sortByDesc('id')->pluck('id')->values()->all(),
            $sortedIds->all()
        );
    }

    #[Test]
    public function batch_methods_respect_build_state(): void
    {
        $wizard = $this
            ->createEloquentWizardFromQuery(['append' => 'fullName'], AppendModel::class)
            ->allowedAppends('fullName');

        $wizard->chunk(3, function () {
            // First execution
        });

        $result = $wizard->lazy(3)->first();
        $this->assertTrue(in_array('fullName', $result->getAppends(), true));
    }
}
