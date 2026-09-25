<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\PostgresConnection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use LogicException;

/**
 * Base test case for filter tests with common setup.
 */
abstract class EloquentFilterTestCase extends TestCase
{
    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        DB::enableQueryLog();
        $this->models = TestModel::factory()->count(5)->create();
    }

    /**
     * A TestModel query compiled by the PostgreSQL grammar, for asserting SQL; it never runs.
     *
     * @return Builder<TestModel>
     */
    protected function postgresQuery(): Builder
    {
        $connection = new PostgresConnection(static fn () => throw new LogicException('This query is not meant to run.'));
        $model = new TestModel;

        return $model->newEloquentBuilder($connection->query())->setModel($model);
    }
}
