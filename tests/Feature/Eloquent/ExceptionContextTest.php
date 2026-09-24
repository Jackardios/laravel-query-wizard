<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Http\Request;
use Jackardios\QueryWizard\Eloquent\EloquentQueryWizard;
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;
use Jackardios\QueryWizard\Exceptions\InvalidSortQuery;
use Jackardios\QueryWizard\QueryParametersManager;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('eloquent')]
class ExceptionContextTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('query-wizard.parameters.filters', 'where');
        config()->set('query-wizard.parameters.sorts', 'order');
    }

    #[Test]
    public function errors_name_the_configured_parameter(): void
    {
        try {
            $this->wizard(['where' => ['name' => ['nested' => 'x']]])->allowedFilters('name')->get();
            $this->fail('Expected InvalidFilterQuery');
        } catch (InvalidFilterQuery $exception) {
            $this->assertSame('invalid_filter_format', $exception->errorCode);
            $this->assertSame('where', $exception->parameter);
            $this->assertStringStartsWith('Invalid `where` parameter format.', $exception->getMessage());
        }

        try {
            $this->wizard(['order' => ''])->allowedSorts('name')->get();
            $this->fail('Expected InvalidSortQuery');
        } catch (InvalidSortQuery $exception) {
            $this->assertSame('invalid_sort_format', $exception->errorCode);
            $this->assertSame('order', $exception->parameter);
            $this->assertSame(
                'The `order` parameter has an invalid format. The `order` parameter must contain at least one sort field when present.',
                $exception->getMessage()
            );
        }
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function wizard(array $query): EloquentQueryWizard
    {
        return new EloquentQueryWizard(TestModel::query(), new QueryParametersManager(new Request($query)));
    }
}
