<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('eloquent')]
#[Group('filter')]
class SnakeCaseFilterNamesTest extends EloquentFilterTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('query-wizard.naming.convert_parameters_to_snake_case', true);
    }

    #[Test]
    public function range_keys_are_read_as_sent(): void
    {
        $query = $this
            ->createEloquentWizardWithFilters(['modelId' => ['minId' => '2', 'maxId' => '3']])
            ->allowedFilters(EloquentFilter::range('id')->alias('model_id')->minKey('minId')->maxKey('maxId'))
            ->toQuery();

        $this->assertSame([2, 3], $query->getBindings());
    }

    #[Test]
    public function callback_payload_keys_are_passed_as_sent(): void
    {
        $received = null;

        $this
            ->createEloquentWizardWithFilters(['createdAt' => ['fromDate' => '2024-01-01', 'nested' => ['toDate' => '2024-02-01']]])
            ->allowedFilters(EloquentFilter::callback('created_at', function ($query, $value) use (&$received) {
                $received = $value;
            })->allowStructuredInput())
            ->toQuery();

        $this->assertSame(['fromDate' => '2024-01-01', 'nested' => ['toDate' => '2024-02-01']], $received);
    }

    #[Test]
    public function passthrough_payload_keys_are_kept_as_sent(): void
    {
        $passthrough = $this
            ->createEloquentWizardWithFilters(['searchQuery' => ['textValue' => 'a']])
            ->allowedFilters(EloquentFilter::passthrough('search_query')->allowStructuredInput())
            ->getPassthroughFilters();

        $this->assertSame(['search_query' => ['textValue' => 'a']], $passthrough->all());
    }

    #[Test]
    public function nested_filter_names_are_matched_level_by_level(): void
    {
        $name = $this->models->first()->name;

        $models = $this
            ->createEloquentWizardWithFilters(['relatedModels' => ['testModel' => ['modelName' => $name]]])
            ->allowedFilters(EloquentFilter::exact('name')->alias('relatedModels.testModel.modelName'))
            ->get();

        $this->assertSame([$this->models->first()->id], $models->modelKeys());
    }

    #[Test]
    public function unknown_nested_names_are_reported_in_snake_case(): void
    {
        $this->expectException(InvalidFilterQuery::class);
        $this->expectExceptionMessage('related_models.bad_name');

        $this
            ->createEloquentWizardWithFilters(['relatedModels' => ['badName' => 'x']])
            ->allowedFilters(EloquentFilter::exact('related_models.name'))
            ->toQuery();
    }

    #[Test]
    public function a_filter_does_not_receive_the_keys_of_filters_nested_under_it(): void
    {
        $received = null;

        $query = $this
            ->createEloquentWizardWithFilters(['meta' => ['isVisible' => '1', 'extraKey' => 'x']])
            ->allowedFilters(
                EloquentFilter::callback('meta', function ($query, $value) use (&$received) {
                    $received = $value;
                })->allowStructuredInput(),
                EloquentFilter::exact('is_visible')->alias('meta.isVisible'),
            )
            ->toQuery();

        $this->assertSame(['extraKey' => 'x'], $received);
        $this->assertStringEndsWith('"is_visible" = ?', $query->toSql());
    }
}
