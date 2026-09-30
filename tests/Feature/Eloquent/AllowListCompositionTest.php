<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Http\Request;
use Jackardios\QueryWizard\Contracts\QueryWizardInterface;
use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Eloquent\EloquentInclude;
use Jackardios\QueryWizard\Eloquent\EloquentSort;
use Jackardios\QueryWizard\Exceptions\InvalidAppendQuery;
use Jackardios\QueryWizard\Exceptions\InvalidFieldQuery;
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;
use Jackardios\QueryWizard\Exceptions\InvalidIncludeQuery;
use Jackardios\QueryWizard\Exceptions\InvalidSortQuery;
use Jackardios\QueryWizard\ModelQueryWizard;
use Jackardios\QueryWizard\QueryParametersManager;
use Jackardios\QueryWizard\Schema\ResourceSchema;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('eloquent')]
#[Group('schema')]
class AllowListCompositionTest extends TestCase
{
    private function schema(): ResourceSchema
    {
        return new class extends ResourceSchema
        {
            public function model(): string
            {
                return TestModel::class;
            }

            public function filters(QueryWizardInterface $wizard): array
            {
                return ['name'];
            }

            public function sorts(QueryWizardInterface $wizard): array
            {
                return ['name'];
            }

            public function includes(QueryWizardInterface $wizard): array
            {
                return ['relatedModels'];
            }

            public function fields(QueryWizardInterface $wizard): array
            {
                return ['id'];
            }

            public function appends(QueryWizardInterface $wizard): array
            {
                return [];
            }
        };
    }

    #[Test]
    public function repeated_disallowed_calls_add_to_the_list(): void
    {
        $wizard = $this->createEloquentWizardFromQuery(['filter' => ['name' => 'x']])
            ->allowedFilters('name', 'id')
            ->disallowedFilters('name')
            ->disallowedFilters('id');

        $this->expectException(InvalidFilterQuery::class);

        $wizard->get();
    }

    #[Test]
    public function repeated_disallowed_calls_add_to_the_list_for_every_kind(): void
    {
        $model = TestModel::factory()->create();
        $wizard = fn (array $query) => $this->createEloquentWizardFromQuery($query)
            ->allowedSorts('name', 'id')->disallowedSorts('name')->disallowedSorts('id')
            ->allowedIncludes('relatedModels', 'otherRelatedModels')->disallowedIncludes('relatedModels')->disallowedIncludes('otherRelatedModels')
            ->allowedFields('id', 'name')->disallowedFields('name')->disallowedFields('id')
            ->allowedAppends('fullname')->disallowedAppends('other')->disallowedAppends('fullname');

        $cases = [
            [['sort' => 'name'], InvalidSortQuery::class],
            [['include' => 'relatedModels'], InvalidIncludeQuery::class],
            [['fields' => ['testModel' => 'name']], InvalidFieldQuery::class],
            [['append' => 'fullname'], InvalidAppendQuery::class],
        ];

        foreach ($cases as [$query, $exception]) {
            try {
                $wizard($query)->get();
                $this->fail("Expected {$exception} for ".json_encode($query));
            } catch (\Throwable $e) {
                $this->assertInstanceOf($exception, $e);
            }
        }

        $this->assertSame([$model->id], $wizard([])->get()->pluck('id')->all());
    }

    #[Test]
    public function add_allowed_extends_the_schema_lists(): void
    {
        $model = TestModel::factory()->create(['name' => 'kept']);
        RelatedModel::factory()->create(['test_model_id' => $model->id]);

        $result = $this->createEloquentWizardFromQuery([
            'filter' => ['name' => 'kept', 'id' => $model->id],
            'sort' => '-id,name',
            'include' => 'relatedModels,otherRelatedModels',
            'fields' => ['testModel' => 'id,name'],
            'append' => 'fullname',
        ])
            ->schema($this->schema())
            ->addAllowedFilters(EloquentFilter::exact('id'))
            ->addAllowedSorts('id')
            ->addAllowedIncludes('otherRelatedModels')
            ->addAllowedFields('name')
            ->addAllowedAppends('fullname')
            ->get();

        $this->assertCount(1, $result);
        $this->assertTrue($result->first()->relationLoaded('relatedModels'));
        $this->assertTrue($result->first()->relationLoaded('otherRelatedModels'));
        $this->assertSame(['id', 'name', 'fullname'], array_keys($result->first()->withoutRelations()->toArray()));
    }

    #[Test]
    public function add_allowed_extends_an_explicit_list_until_allowed_replaces_it(): void
    {
        $model = TestModel::factory()->create(['name' => 'kept']);

        $extended = $this->createEloquentWizardFromQuery(['filter' => ['id' => $model->id]])
            ->allowedFilters('name')
            ->addAllowedFilters('id');

        $this->assertSame([$model->id], $extended->get()->pluck('id')->all());

        $replaced = $this->createEloquentWizardFromQuery(['filter' => ['id' => $model->id]])
            ->addAllowedFilters('id')
            ->allowedFilters('name');

        $this->expectException(InvalidFilterQuery::class);

        $replaced->get();
    }

    #[Test]
    public function model_wizard_with_only_added_includes_drops_relations_that_were_not_requested(): void
    {
        $model = TestModel::factory()->create();
        RelatedModel::factory()->create(['test_model_id' => $model->id]);
        $model->load('relatedModels', 'otherRelatedModels');

        $parameters = new QueryParametersManager(new Request(['include' => 'otherRelatedModels']));
        $processed = (new ModelQueryWizard($model, $parameters))->addAllowedIncludes('relatedModels', 'otherRelatedModels')->process();

        $this->assertFalse($processed->relationLoaded('relatedModels'));
        $this->assertTrue($processed->relationLoaded('otherRelatedModels'));
    }

    #[Test]
    public function definitions_sharing_a_public_name_throw(): void
    {
        $cases = [
            'filter' => fn () => $this->createEloquentWizardFromQuery()->allowedFilters('name', EloquentFilter::partial('title')->alias('name')),
            'sort' => fn () => $this->createEloquentWizardFromQuery()->allowedSorts('name', EloquentSort::field('title', 'name')),
            'include' => fn () => $this->createEloquentWizardFromQuery()->allowedIncludes('relatedModels', EloquentInclude::count('relatedModels')->alias('relatedModels')),
            'schema filter' => fn () => $this->createEloquentWizardFromQuery()->schema($this->schema())->addAllowedFilters(EloquentFilter::partial('name')),
        ];

        foreach ($cases as $kind => $wizard) {
            try {
                $wizard()->get();
                $this->fail("Expected a duplicate {$kind} to throw");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('is named `', $e->getMessage());
            }
        }
    }

    #[Test]
    public function an_empty_count_suffix_that_names_a_count_include_like_its_relationship_include_throws(): void
    {
        config()->set('query-wizard.includes.count_suffix', '');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('More than one allowed include is named `relatedModels`.');

        $this->createEloquentWizardFromQuery(['include' => 'relatedModels'])
            ->allowedIncludes('relatedModels', EloquentInclude::count('relatedModels'))
            ->get();
    }

    #[Test]
    public function disallowed_duplicates_do_not_count(): void
    {
        $model = TestModel::factory()->create();

        $result = $this->createEloquentWizardFromQuery(['filter' => ['id' => $model->id]])
            ->allowedFilters('name', EloquentFilter::partial('title')->alias('name'), 'id')
            ->disallowedFilters('name')
            ->get();

        $this->assertSame([$model->id], $result->pluck('id')->all());
    }
}
