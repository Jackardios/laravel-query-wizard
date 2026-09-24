<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Eloquent\EloquentInclude;
use Jackardios\QueryWizard\Eloquent\EloquentSort;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Callbacks that return something other than the subject keep the subject.
 */
#[Group('eloquent')]
class CallbackReturnTest extends TestCase
{
    #[Test]
    public function a_callback_filter_returning_another_value_keeps_the_subject(): void
    {
        $query = $this
            ->createEloquentWizardWithFilters(['name' => 'alpha'])
            ->allowedFilters(EloquentFilter::callback('name', function (Builder $query, string $value): bool {
                $query->where('name', $value);

                return true;
            }))
            ->toQuery();

        $this->assertSame('select * from "test_models" where "name" = ?', $query->toSql());
    }

    #[Test]
    public function a_callback_sort_returning_another_value_keeps_the_subject(): void
    {
        $query = $this
            ->createEloquentWizardFromQuery(['sort' => '-name'])
            ->allowedSorts(EloquentSort::callback('name', function (Builder $query, string $direction): string {
                $query->orderBy('name', $direction);

                return $direction;
            }))
            ->toQuery();

        $this->assertSame('select * from "test_models" order by "name" desc', $query->toSql());
    }

    #[Test]
    public function a_callback_include_returning_another_value_keeps_the_subject(): void
    {
        TestModel::factory()->create();

        $models = $this
            ->createEloquentWizardFromQuery(['include' => 'related'])
            ->allowedIncludes(EloquentInclude::callback('related', function (Builder $query): array {
                $query->with('relatedModels');

                return [];
            }))
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
    }
}
