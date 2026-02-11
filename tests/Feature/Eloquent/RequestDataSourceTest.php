<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Http\Request;
use Jackardios\QueryWizard\Eloquent\EloquentQueryWizard;
use Jackardios\QueryWizard\QueryParametersManager;
use Jackardios\QueryWizard\Tests\App\Models\AppendModel;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('eloquent')]
#[Group('request-data-source')]
class RequestDataSourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $models = TestModel::factory()->count(3)->create();

        $models->each(function (TestModel $model): void {
            RelatedModel::factory()->count(2)->create([
                'test_model_id' => $model->id,
            ]);
        });

        AppendModel::factory()->count(2)->create();
    }

    #[Test]
    public function it_reads_filters_sorts_includes_and_fields_from_request_body_when_configured(): void
    {
        config()->set('query-wizard.request_data_source', 'body');

        $targetModel = TestModel::query()->firstOrFail();

        $request = Request::create('/wizard', 'POST', [
            'filter' => ['id' => (string) $targetModel->id],
            'sort' => '-id',
            'include' => 'relatedModels',
            'fields' => [
                'testModel' => 'id,name',
                'relatedModels' => 'id',
            ],
        ]);

        $wizard = new EloquentQueryWizard(
            TestModel::query(),
            new QueryParametersManager($request),
        );

        $models = $wizard
            ->allowedFilters('id')
            ->allowedSorts('id')
            ->allowedIncludes('relatedModels')
            ->allowedFields('id', 'name', 'relatedModels.id')
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals($targetModel->id, $models->first()->id);
        $this->assertTrue($models->first()->relationLoaded('relatedModels'));

        $rootAttributes = array_keys($models->first()->getAttributes());
        $this->assertContains('id', $rootAttributes);
        $this->assertContains('name', $rootAttributes);
        $this->assertNotContains('created_at', $rootAttributes);

        $relatedAttributes = array_keys($models->first()->relatedModels->first()->toArray());
        $this->assertContains('id', $relatedAttributes);
        $this->assertNotContains('name', $relatedAttributes);
        $this->assertNotContains('test_model_id', $relatedAttributes);
    }

    #[Test]
    public function it_reads_appends_from_request_body_when_configured(): void
    {
        config()->set('query-wizard.request_data_source', 'body');

        $request = Request::create('/wizard', 'POST', [
            'append' => 'fullname',
        ]);

        $wizard = new EloquentQueryWizard(
            AppendModel::query(),
            new QueryParametersManager($request),
        );

        $models = $wizard
            ->allowedAppends('fullname')
            ->get();

        $this->assertArrayHasKey('fullname', $models->first()->toArray());
    }
}
