<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Http\Request;
use Jackardios\QueryWizard\Eloquent\EloquentQueryWizard;
use Jackardios\QueryWizard\Exceptions\InvalidRequestBody;
use Jackardios\QueryWizard\QueryParametersManager;
use Jackardios\QueryWizard\Tests\App\Models\AppendModel;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[Test]
    public function it_ignores_query_string_when_body_mode_is_enabled(): void
    {
        config()->set('query-wizard.request_data_source', 'body');

        $targetModel = TestModel::query()->firstOrFail();

        $request = Request::create(
            '/wizard?filter[id]=999999&include=otherRelatedModels',
            'POST',
            [
                'filter' => ['id' => (string) $targetModel->id],
                'include' => 'relatedModels',
            ],
        );

        $wizard = new EloquentQueryWizard(
            TestModel::query(),
            new QueryParametersManager($request),
        );

        $models = $wizard
            ->allowedFilters('id')
            ->allowedIncludes('relatedModels', 'otherRelatedModels')
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals($targetModel->id, $models->first()->id);
        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
        $this->assertFalse($models->first()->relationLoaded('otherRelatedModels'));
    }

    #[Test]
    public function it_reads_json_request_body_without_query_fallback_when_configured(): void
    {
        config()->set('query-wizard.request_data_source', 'body');

        $targetModel = AppendModel::query()->firstOrFail();

        $request = Request::create(
            '/wizard?filter[id]=999999',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'filter' => ['id' => (string) $targetModel->id],
                'append' => 'fullname',
            ], JSON_THROW_ON_ERROR),
        );

        $wizard = new EloquentQueryWizard(
            AppendModel::query(),
            new QueryParametersManager($request),
        );

        $models = $wizard
            ->allowedFilters('id')
            ->allowedAppends('fullname')
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals($targetModel->id, $models->first()->id);
        $this->assertArrayHasKey('fullname', $models->first()->toArray());
    }

    #[Test]
    #[DataProvider('bodiesThatAreNotObjects')]
    public function it_rejects_a_json_body_that_is_not_an_object(string $body, string $message): void
    {
        config()->set('query-wizard.request_data_source', 'body');

        try {
            $this->wizardForJsonBody($body)->allowedFilters('id')->get();

            $this->fail('Expected InvalidRequestBody to be thrown');
        } catch (InvalidRequestBody $e) {
            $this->assertSame(400, $e->getStatusCode());
            $this->assertSame('invalid_request_body', $e->errorCode);
            $this->assertNull($e->parameter);
            $this->assertStringStartsWith($message, $e->getMessage());
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function bodiesThatAreNotObjects(): array
    {
        return [
            'truncated' => ['{"filter": {"id": "1"', 'The request body is not valid JSON: Syntax error.'],
            'list' => ['[{"filter": {"id": "1"}}]', 'The request body must be a JSON object.'],
            'empty list' => ['[]', 'The request body must be a JSON object.'],
            'string' => ['"filter"', 'The request body must be a JSON object.'],
            'number' => ['5', 'The request body must be a JSON object.'],
        ];
    }

    #[Test]
    #[DataProvider('emptyObjectBodies')]
    public function it_reads_an_empty_json_body_as_no_parameters(string $body): void
    {
        config()->set('query-wizard.request_data_source', 'body');

        $models = $this->wizardForJsonBody($body)->allowedFilters('id')->get();

        $this->assertCount(AppendModel::query()->count(), $models);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function emptyObjectBodies(): array
    {
        return [
            'no body' => [''],
            'whitespace' => [" \n"],
            'empty object' => ['{}'],
        ];
    }

    #[Test]
    public function the_query_string_mode_ignores_the_body(): void
    {
        $models = $this->wizardForJsonBody('{"filter": ')->allowedFilters('id')->get();

        $this->assertCount(AppendModel::query()->count(), $models);
    }

    private function wizardForJsonBody(string $body): EloquentQueryWizard
    {
        $request = Request::create('/wizard', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body);

        return new EloquentQueryWizard(AppendModel::query(), new QueryParametersManager($request));
    }
}
