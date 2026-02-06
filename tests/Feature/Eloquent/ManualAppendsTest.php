<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Jackardios\QueryWizard\Tests\App\Models\AppendModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('eloquent')]
#[Group('append')]
class ManualAppendsTest extends TestCase
{
    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        DB::enableQueryLog();

        $this->models = AppendModel::factory()->count(5)->create();
    }

    #[Test]
    public function chunk_with_apply_appends_to(): void
    {
        $wizard = $this
            ->createEloquentWizardWithAppends('fullname')
            ->allowedAppends('fullname');

        $processed = collect();
        $wizard->toQuery()->chunk(2, function ($chunk) use ($wizard, $processed) {
            $wizard->applyAppendsTo($chunk);
            $processed->push(...$chunk);
        });

        $this->assertCount(5, $processed);
        $this->assertTrue($processed->every(fn ($m) => array_key_exists('fullname', $m->toArray())));
    }

    #[Test]
    public function cursor_with_apply_appends_to(): void
    {
        $wizard = $this
            ->createEloquentWizardWithAppends('fullname')
            ->allowedAppends('fullname');

        $processed = collect();
        foreach ($wizard->toQuery()->cursor() as $model) {
            $wizard->applyAppendsTo($model);
            $processed->push($model);
        }

        $this->assertCount(5, $processed);
        $this->assertTrue($processed->every(fn ($m) => array_key_exists('fullname', $m->toArray())));
    }

    #[Test]
    public function to_query_get_does_not_apply_appends(): void
    {
        $wizard = $this
            ->createEloquentWizardWithAppends('fullname')
            ->allowedAppends('fullname');

        // toQuery()->get() bypasses wizard's append logic
        $models = $wizard->toQuery()->get();

        $this->assertCount(5, $models);
        $this->assertFalse(array_key_exists('fullname', $models->first()->toArray()));
    }

    #[Test]
    public function apply_appends_to_empty_collection_does_not_error(): void
    {
        $wizard = $this
            ->createEloquentWizardWithAppends('fullname')
            ->allowedAppends('fullname');

        $empty = collect();
        $result = $wizard->applyAppendsTo($empty);

        $this->assertCount(0, $result);
    }

    #[Test]
    public function apply_appends_to_single_model(): void
    {
        $wizard = $this
            ->createEloquentWizardWithAppends('fullname')
            ->allowedAppends('fullname');

        $model = AppendModel::first();
        $wizard->applyAppendsTo($model);

        $this->assertTrue(array_key_exists('fullname', $model->toArray()));
        $this->assertEquals($model->firstname.' '.$model->lastname, $model->fullname);
    }
}
