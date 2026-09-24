<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Jackardios\QueryWizard\Eloquent\EloquentQueryWizard;
use Jackardios\QueryWizard\Tests\App\Models\AppendModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Methods that execute the query return post-processed models, whether the
 * wizard wraps them or proxies them to the builder.
 */
#[Group('eloquent')]
class ExecutingMethodsTest extends TestCase
{
    private AppendModel $appendModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->appendModel = AppendModel::factory()->count(2)->create()->first();
        TestModel::factory()->count(3)->create();
    }

    /**
     * @return array<string, array{Closure(EloquentQueryWizard, int): mixed}>
     */
    public static function finders(): array
    {
        return [
            'find' => [fn ($wizard, $id) => $wizard->find($id)],
            'find with a list' => [fn ($wizard, $id) => $wizard->find([$id])->first()],
            'findMany' => [fn ($wizard, $id) => $wizard->findMany([$id])->first()],
            'findOrFail' => [fn ($wizard, $id) => $wizard->findOrFail($id)],
            'findOr' => [fn ($wizard, $id) => $wizard->findOr($id, fn () => null)],
            'findSole' => [fn ($wizard, $id) => $wizard->findSole($id)],
            'sole' => [fn ($wizard, $id) => $wizard->whereKey($id)->sole()],
            'firstWhere' => [fn ($wizard, $id) => $wizard->firstWhere('id', $id)],
            'firstWhere with a closure' => [fn ($wizard, $id) => $wizard->firstWhere(fn ($query) => $query->whereKey($id))],
            'firstOr' => [fn ($wizard, $id) => $wizard->whereKey($id)->firstOr(fn () => null)],
        ];
    }

    /**
     * @param  Closure(EloquentQueryWizard, int): mixed  $find
     */
    #[Test]
    #[DataProvider('finders')]
    public function proxied_finders_return_post_processed_models(Closure $find): void
    {
        $model = $find($this->appendWizard(), $this->appendModel->id);

        $this->assertInstanceOf(AppendModel::class, $model);
        $this->assertSame(['firstname', 'fullname'], array_keys($model->toArray()));
    }

    #[Test]
    public function fallback_results_are_returned_untouched(): void
    {
        $fallback = new AppendModel(['firstname' => 'John', 'lastname' => 'Doe']);

        $fromFindOr = $this->appendWizard()->findOr(0, fn () => $fallback);
        $fromFirstOr = $this->appendWizard()->whereKey(0)->firstOr(fn () => $fallback);

        $this->assertSame($fallback, $fromFindOr);
        $this->assertSame($fallback, $fromFirstOr);
        $this->assertSame(['firstname' => 'John', 'lastname' => 'Doe'], $fallback->toArray());
    }

    #[Test]
    public function methods_creating_models_or_returning_values_are_not_post_processed(): void
    {
        $new = $this->appendWizard()->firstOrNew(['firstname' => 'Nobody'], ['lastname' => 'Else']);
        $value = $this->appendWizard()->whereKey($this->appendModel->id)->value('lastname');

        $this->assertSame(['firstname' => 'Nobody', 'lastname' => 'Else'], $new->toArray());
        $this->assertSame($this->appendModel->lastname, $value);
    }

    #[Test]
    public function lazy_by_id_selects_its_column_and_post_processes(): void
    {
        $ascending = $this->nameWizard()->lazyById(2)->all();
        $descending = $this->nameWizard()->lazyByIdDesc(2)->all();

        $ids = TestModel::query()->orderBy('id')->pluck('id')->all();

        $this->assertSame($ids, $this->ids($ascending));
        $this->assertSame(array_reverse($ids), $this->ids($descending));
        $this->assertSame(['name'], array_keys($ascending[0]->toArray()));
        $this->assertSame(['name'], array_keys($descending[0]->toArray()));
    }

    #[Test]
    public function chunk_by_id_desc_selects_its_column_and_post_processes(): void
    {
        $models = [];

        $this->nameWizard()->chunkByIdDesc(2, function ($chunk) use (&$models): void {
            array_push($models, ...$chunk->all());
        });

        $this->assertSame(TestModel::query()->orderByDesc('id')->pluck('id')->all(), $this->ids($models));
        $this->assertSame(['name'], array_keys($models[0]->toArray()));
    }

    #[Test]
    public function each_by_id_passes_running_keys_and_post_processed_models(): void
    {
        $seen = [];

        $this->nameWizard()->eachById(function (TestModel $model, int $key) use (&$seen): void {
            $seen[$key] = array_keys($model->toArray());
        }, 2);

        $this->assertSame([0 => ['name'], 1 => ['name'], 2 => ['name']], $seen);
    }

    #[Test]
    public function each_and_chunk_map_post_process_models(): void
    {
        $seen = [];

        $this->appendWizard()->each(function (AppendModel $model) use (&$seen): void {
            $seen[] = array_keys($model->toArray());
        }, 1);
        $mapped = $this->appendWizard()->chunkMap(fn (AppendModel $model) => array_keys($model->toArray()), 1);

        $this->assertSame([['firstname', 'fullname'], ['firstname', 'fullname']], $seen);
        $this->assertSame($seen, $mapped->all());
    }

    #[Test]
    public function each_stops_when_the_callback_returns_false(): void
    {
        $calls = 0;

        $completed = $this->appendWizard()->each(function () use (&$calls): bool {
            $calls++;

            return false;
        });

        $this->assertFalse($completed);
        $this->assertSame(1, $calls);
    }

    private function appendWizard(): EloquentQueryWizard
    {
        return $this
            ->createEloquentWizardFromQuery(['append' => 'fullname', 'fields' => ['appendModel' => 'firstname']], AppendModel::class)
            ->allowedAppends('fullname')
            ->allowedFields('firstname');
    }

    private function nameWizard(): EloquentQueryWizard
    {
        return $this
            ->createEloquentWizardWithFields(['testModel' => 'name'])
            ->allowedFields('name');
    }

    /**
     * @param  array<int, Model>  $models
     * @return array<int, mixed>
     */
    private function ids(array $models): array
    {
        return array_map(fn (Model $model) => $model->getAttribute('id'), $models);
    }
}
