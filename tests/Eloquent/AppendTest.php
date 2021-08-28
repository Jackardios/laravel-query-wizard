<?php

namespace Jackardios\QueryWizard\Tests\Eloquent;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Jackardios\QueryWizard\Exceptions\InvalidAppendQuery;
use Jackardios\QueryWizard\EloquentQueryWizard;
use Jackardios\QueryWizard\Tests\TestCase;
use Jackardios\QueryWizard\Tests\TestClasses\Models\AppendModel;

/**
 * @group eloquent
 * @group append
 */
class AppendTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        factory(AppendModel::class, 5)->create();
    }

    /** @test */
    public function it_does_not_require_appends(): void
    {
        $models = EloquentQueryWizard::for(AppendModel::class, new Request())
            ->setAllowedAppends('fullname')
            ->build()
            ->get();

        $this->assertCount(AppendModel::count(), $models);
    }

    /** @test */
    public function it_can_append_attributes(): void
    {
        $model = $this
            ->createQueryFromAppendRequest('fullname')
            ->setAllowedAppends('fullname')
            ->build()
            ->first();

        $this->assertAttributeLoaded($model, 'fullname');
    }

    /** @test */
    public function it_cannot_append_case_insensitive(): void
    {
        $this->expectException(InvalidAppendQuery::class);

        $this
            ->createQueryFromAppendRequest('FullName')
            ->setAllowedAppends('fullname')
            ->build()
            ->first();
    }

    /** @test */
    public function it_can_append_collections(): void
    {
        $models = $this
            ->createQueryFromAppendRequest('FullName')
            ->setAllowedAppends('FullName')
            ->build()
            ->get();

        $this->assertCollectionAttributeLoaded($models, 'FullName');
    }

    /** @test */
    public function it_can_append_paginates(): void
    {
        $models = $this
            ->createQueryFromAppendRequest('FullName')
            ->setAllowedAppends('FullName')
            ->build()
            ->paginate();

        $this->assertPaginateAttributeLoaded($models, 'FullName');
    }

    /** @test */
    public function it_can_append_simple_paginates(): void
    {
        $models = $this
            ->createQueryFromAppendRequest('FullName')
            ->setAllowedAppends('FullName')
            ->build()
            ->simplePaginate();

        $this->assertPaginateAttributeLoaded($models, 'FullName');
    }

    /** @test */
    public function it_can_append_cursor_paginates(): void
    {
        $models = $this
            ->createQueryFromAppendRequest('FullName')
            ->setAllowedAppends('FullName')
            ->build()
            ->cursorPaginate();

        $this->assertPaginateAttributeLoaded($models, 'FullName');
    }

    /** @test */
    public function it_guards_against_invalid_appends(): void
    {
        $this->expectException(InvalidAppendQuery::class);

        $this
            ->createQueryFromAppendRequest('random-attribute-to-append')
            ->setAllowedAppends('attribute-to-append')
            ->build();
    }

    /** @test */
    public function it_can_allow_multiple_appends(): void
    {
        $model = $this
            ->createQueryFromAppendRequest('fullname')
            ->setAllowedAppends('fullname', 'randomAttribute')
            ->build()
            ->first();

        $this->assertAttributeLoaded($model, 'fullname');
    }

    /** @test */
    public function it_can_allow_multiple_appends_as_an_array(): void
    {
        $model = $this
            ->createQueryFromAppendRequest('fullname')
            ->setAllowedAppends(['fullname', 'randomAttribute'])
            ->build()
            ->first();

        $this->assertAttributeLoaded($model, 'fullname');
    }

    /** @test */
    public function it_can_append_multiple_attributes(): void
    {
        $model = $this
            ->createQueryFromAppendRequest('fullname,reversename')
            ->setAllowedAppends(['fullname', 'reversename'])
            ->build()
            ->first();

        $this->assertAttributeLoaded($model, 'fullname');
        $this->assertAttributeLoaded($model, 'reversename');
    }

    /** @test */
    public function an_invalid_append_query_exception_contains_the_not_allowed_and_allowed_appends(): void
    {
        $exception = new InvalidAppendQuery(collect(['not allowed append']), collect(['allowed append']));

        $this->assertEquals(['not allowed append'], $exception->unknownAppends->all());
        $this->assertEquals(['allowed append'], $exception->allowedAppends->all());
    }

    protected function createQueryFromAppendRequest(string $appends): EloquentQueryWizard
    {
        $request = new Request([
            'append' => $appends,
        ]);

        return EloquentQueryWizard::for(AppendModel::class, $request);
    }

    protected function assertAttributeLoaded(Model $model, string $attribute): void
    {
        $this->assertArrayHasKey($attribute, $model->toArray());
    }

    protected function assertCollectionAttributeLoaded(Collection $collection, string $attribute): void
    {
        $hasModelWithoutAttributeLoaded = $collection
            ->contains(function (Model $model) use ($attribute) {
                return ! array_key_exists($attribute, $model->toArray());
            });

        $this->assertFalse($hasModelWithoutAttributeLoaded, "The `$attribute` attribute was expected but not loaded.");
    }

    /**
     * @param LengthAwarePaginator|Paginator|CursorPaginator $collection
     * @param string $attribute
     */
    protected function assertPaginateAttributeLoaded($collection, string $attribute): void
    {
        $hasModelWithoutAttributeLoaded = $collection
            ->contains(function (Model $model) use ($attribute) {
                return ! array_key_exists($attribute, $model->toArray());
            });

        $this->assertFalse($hasModelWithoutAttributeLoaded, "The `$attribute` attribute was expected but not loaded.");
    }
}
