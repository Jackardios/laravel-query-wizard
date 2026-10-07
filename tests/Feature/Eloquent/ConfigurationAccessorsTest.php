<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Http\Request;
use Jackardios\QueryWizard\Contracts\QueryWizardInterface;
use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Eloquent\EloquentInclude;
use Jackardios\QueryWizard\Eloquent\EloquentQueryWizard;
use Jackardios\QueryWizard\Eloquent\EloquentSort;
use Jackardios\QueryWizard\Eloquent\Filters\ExactFilter;
use Jackardios\QueryWizard\Eloquent\Filters\PartialFilter;
use Jackardios\QueryWizard\Eloquent\Includes\CountInclude;
use Jackardios\QueryWizard\Eloquent\Includes\RelationshipInclude;
use Jackardios\QueryWizard\Eloquent\Sorts\CountSort;
use Jackardios\QueryWizard\Eloquent\Sorts\FieldSort;
use Jackardios\QueryWizard\ModelQueryWizard;
use Jackardios\QueryWizard\QueryParametersManager;
use Jackardios\QueryWizard\Schema\ResourceSchema;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * The wizards report the configuration a request is checked against, and the filter names it carries.
 */
#[Group('eloquent')]
class ConfigurationAccessorsTest extends TestCase
{
    #[Test]
    public function the_allowed_lists_are_keyed_by_public_name_without_the_disallowed_ones(): void
    {
        $wizard = $this->createEloquentWizardFromQuery()
            ->allowedFilters('name', EloquentFilter::partial('title')->alias('q'), 'secret')
            ->addAllowedFilters('id')
            ->disallowedFilters('secret')
            ->allowedSorts('name', EloquentSort::count('relatedModels')->alias('popularity'), 'secret')
            ->disallowedSorts('secret')
            ->allowedIncludes('relatedModels', 'relatedModelsCount', EloquentInclude::relationship('otherRelatedModels')->alias('others'), 'morphModels')
            ->disallowedIncludes('morphModels')
            ->allowedFields('id', 'name', 'relatedModels.*', 'secret')
            ->disallowedFields('secret')
            ->allowedAppends('fullname', 'relatedModels.formattedName', 'secret')
            ->disallowedAppends('secret');

        $this->assertSame(
            ['name' => ExactFilter::class, 'q' => PartialFilter::class, 'id' => ExactFilter::class],
            array_map(get_class(...), $wizard->getAllowedFilters())
        );
        $this->assertSame(
            ['name' => FieldSort::class, 'popularity' => CountSort::class],
            array_map(get_class(...), $wizard->getAllowedSorts())
        );
        $this->assertSame(
            ['relatedModels' => RelationshipInclude::class, 'relatedModelsCount' => CountInclude::class, 'others' => RelationshipInclude::class],
            array_map(get_class(...), $wizard->getAllowedIncludes())
        );
        $this->assertSame(['id', 'name', 'relatedModels.*'], $wizard->getAllowedFields());
        $this->assertSame(['fullname', 'relatedModels.formattedName'], $wizard->getAllowedAppends());
    }

    #[Test]
    public function the_allowed_lists_fall_back_to_the_schema_and_follow_the_naming_convention(): void
    {
        config()->set('query-wizard.naming.convert_parameters_to_snake_case', true);

        $wizard = EloquentQueryWizard::forSchema($this->schema());

        $this->assertSame(['first_name'], array_keys($wizard->getAllowedFilters()));
        $this->assertSame(['created_at'], array_keys($wizard->getAllowedSorts()));
        $this->assertSame(['related_models'], array_keys($wizard->getAllowedIncludes()));
        $this->assertSame(['id', 'first_name'], $wizard->getAllowedFields());
        $this->assertSame(['full_name'], $wizard->getAllowedAppends());
    }

    #[Test]
    public function reading_the_allowed_lists_does_not_freeze_the_wizard(): void
    {
        $wizard = $this->createEloquentWizardFromQuery(['filter' => ['id' => '1']])->allowedFilters('name');

        $this->assertSame(['name'], array_keys($wizard->getAllowedFilters()));

        $wizard->addAllowedFilters('id');

        $this->assertSame(['name', 'id'], array_keys($wizard->getAllowedFilters()));
        $this->assertSame('select * from "test_models" where "test_models"."id" = ?', $wizard->toQuery()->toSql());
    }

    #[Test]
    public function the_model_wizard_reports_its_allowed_lists(): void
    {
        $wizard = $this->createModelWizardFromQuery([], TestModel::factory()->create())
            ->allowedIncludes('relatedModels', 'morphModels')
            ->disallowedIncludes('morphModels')
            ->allowedFields('id', 'name')
            ->allowedAppends('fullname');

        $wizard->process();

        $this->assertSame(['relatedModels'], array_keys($wizard->getAllowedIncludes()));
        $this->assertSame(['id', 'name'], $wizard->getAllowedFields());
        $this->assertSame(['fullname'], $wizard->getAllowedAppends());
    }

    #[Test]
    public function a_schema_method_cannot_read_the_list_it_describes(): void
    {
        $schema = new class extends ResourceSchema
        {
            public function model(): string
            {
                return TestModel::class;
            }

            public function filters(QueryWizardInterface $wizard): array
            {
                return array_keys($wizard->getAllowedFilters());
            }
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('getAllowedFilters() was called from a schema method or callback that getAllowedFilters() itself runs');

        EloquentQueryWizard::forSchema($schema)->getAllowedFilters();
    }

    #[Test]
    public function a_schema_method_cannot_read_the_list_it_describes_during_the_build_or_through_another_method(): void
    {
        $schema = new class extends ResourceSchema
        {
            public function model(): string
            {
                return TestModel::class;
            }

            public function includes(QueryWizardInterface $wizard): array
            {
                return $wizard->getAllowedFields() === [] ? [] : ['relatedModels'];
            }

            public function fields(QueryWizardInterface $wizard): array
            {
                return isset($wizard->getAllowedIncludes()['relatedModels']) ? ['id', 'relatedModels.id'] : ['id'];
            }
        };

        try {
            EloquentQueryWizard::forSchema($schema)->toQuery();
            $this->fail('The build read a schema whose methods read each other.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('getAllowedFields() was called from a schema method or callback that getAllowedFields() itself runs', $exception->getMessage());
        }
    }

    #[Test]
    public function a_schema_method_can_read_the_lists_it_does_not_describe(): void
    {
        $schema = new class extends ResourceSchema
        {
            public function model(): string
            {
                return TestModel::class;
            }

            public function filters(QueryWizardInterface $wizard): array
            {
                return ['name', 'id'];
            }

            public function includes(QueryWizardInterface $wizard): array
            {
                return ['relatedModels'];
            }

            public function fields(QueryWizardInterface $wizard): array
            {
                return isset($wizard->getAllowedIncludes()['relatedModels']) ? ['id', 'name', 'relatedModels.id'] : ['id'];
            }

            public function defaultSorts(QueryWizardInterface $wizard): array
            {
                return $wizard->getRequestedFilterNames() === ['name'] ? ['-id'] : [];
            }

            public function sorts(QueryWizardInterface $wizard): array
            {
                return ['id'];
            }
        };

        $wizard = new EloquentQueryWizard(TestModel::query(), new QueryParametersManager(new Request(['filter' => ['name' => 'Ann']])));
        $wizard->schema($schema);

        $this->assertSame(['id', 'name', 'relatedModels.id'], $wizard->getAllowedFields());
        $this->assertSame(
            'select * from "test_models" where "test_models"."name" = ? order by "test_models"."id" desc',
            $wizard->toQuery()->toSql()
        );
    }

    #[Test]
    public function a_name_that_is_a_number_is_an_integer_key_of_the_allowed_lists(): void
    {
        $wizard = $this->createEloquentWizardWithFilters(['5' => 'Ann'])
            ->allowedFilters(EloquentFilter::exact('name')->alias('5'), 'id')
            ->allowedSorts(EloquentSort::field('name')->alias('7'));

        $this->assertSame([5, 'id'], array_keys($wizard->getAllowedFilters()));
        $this->assertSame([7], array_keys($wizard->getAllowedSorts()));
        $this->assertSame(['5'], $wizard->getRequestedFilterNames());
        $this->assertArrayHasKey($wizard->getRequestedFilterNames()[0], $wizard->getAllowedFilters());
    }

    #[Test]
    public function the_allowed_lists_hand_out_copies_of_the_definitions(): void
    {
        $wizard = $this->createEloquentWizardFromQuery(['sort' => 'name', 'include' => 'relatedModels'])
            ->allowedFilters('name')
            ->allowedSorts('name')
            ->allowedIncludes('relatedModels');

        $wizard->getAllowedFilters()['name']->default('zzz')->alias('renamed');
        $wizard->getAllowedSorts()['name']->alias('renamed');
        $wizard->getAllowedIncludes()['relatedModels']->alias('renamed');

        $this->assertSame(['name'], array_keys($wizard->getAllowedFilters()));
        $this->assertSame(['name'], array_keys($wizard->getAllowedSorts()));
        $this->assertSame(['relatedModels'], array_keys($wizard->getAllowedIncludes()));
        $this->assertSame('select * from "test_models" order by "test_models"."name" asc', $wizard->toQuery()->toSql());
        $this->assertSame(['relatedModels'], array_keys($wizard->toQuery()->getEagerLoads()));
    }

    #[Test]
    public function requested_filter_names_are_the_names_the_build_reads(): void
    {
        $wizard = $this->createEloquentWizardWithFilters([
            'name' => ['first' => 'Ann', 'last' => 'Lee'],
            'price' => ['min' => '1', 'max' => '5'],
            'unknown' => ['deep' => 'x'],
            'id' => '1,2',
        ])->allowedFilters(
            EloquentFilter::callback('name', fn () => null),
            EloquentFilter::callback('name.first', fn () => null),
            EloquentFilter::range('price'),
            'id',
        );

        $this->assertSame(['name.first', 'name', 'price', 'unknown.deep', 'id'], $wizard->getRequestedFilterNames());
    }

    #[Test]
    public function requested_filter_names_follow_the_naming_convention(): void
    {
        config()->set('query-wizard.naming.convert_parameters_to_snake_case', true);

        $wizard = $this->createEloquentWizardWithFilters(['firstName' => 'Ann'])->allowedFilters('first_name');

        $this->assertSame(['first_name'], $wizard->getRequestedFilterNames());
        $this->assertSame([], $this->createEloquentWizardFromQuery()->allowedFilters('name')->getRequestedFilterNames());
    }

    #[Test]
    public function unsplit_filters_round_trip_through_the_filters_parameter(): void
    {
        $parameters = new QueryParametersManager(new Request(['filter' => ['name' => 'a,b', 'id' => '1,2']]));

        $this->assertSame(['name' => 'a,b', 'id' => '1,2'], $parameters->getUnsplitFilters()->all());

        $parameters->setFiltersParameter($parameters->getUnsplitFilters()->except('id')->all());

        $this->assertSame(['name' => ['a', 'b']], $parameters->getFilters()->all());
        $this->assertSame('a,b', $parameters->getFilterValue('name', false));
    }

    #[Test]
    #[DataProvider('readsThatRunTheFiltersOfTheSchema')]
    public function a_schema_filters_method_cannot_call_what_reads_the_filters(\Closure $read, string $message): void
    {
        $schema = new class extends ResourceSchema
        {
            public ?\Closure $read = null;

            public int $calls = 0;

            public function model(): string
            {
                return TestModel::class;
            }

            public function filters(QueryWizardInterface $wizard): array
            {
                if (++$this->calls > 5) {
                    throw new \RuntimeException('filters() keeps being called.');
                }

                ($this->read)($wizard);

                return ['name'];
            }
        };
        $schema->read = $read;

        try {
            $read(EloquentQueryWizard::forSchema($schema));
            $this->fail('The schema read was accepted.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        }
    }

    /**
     * @return array<string, array{\Closure(EloquentQueryWizard): mixed, string}>
     */
    public static function readsThatRunTheFiltersOfTheSchema(): array
    {
        return [
            'getRequestedFilterNames' => [
                static fn (EloquentQueryWizard $wizard) => $wizard->getRequestedFilterNames(),
                'getRequestedFilterNames() was called from a schema method or callback that getRequestedFilterNames() itself runs',
            ],
            'getPassthroughFilters' => [
                static fn (EloquentQueryWizard $wizard) => $wizard->getPassthroughFilters(),
                'getPassthroughFilters() was called from a schema method or callback that getPassthroughFilters() itself runs',
            ],
        ];
    }

    #[Test]
    public function passthrough_filters_are_read_before_and_after_the_build(): void
    {
        $wizard = $this->createEloquentWizardWithFilters(['q' => 'Ann'])->allowedFilters(EloquentFilter::passthrough('q'));

        $this->assertSame(['q' => 'Ann'], $wizard->getPassthroughFilters()->all());
        $this->assertSame(['q' => 'Ann'], $wizard->getPassthroughFilters()->all());

        $wizard->toQuery();

        $this->assertSame(['q' => 'Ann'], $wizard->getPassthroughFilters()->all());
    }

    #[Test]
    public function a_schema_method_cannot_process_the_model_wizard_it_describes(): void
    {
        $schema = new class extends ResourceSchema
        {
            public int $calls = 0;

            public function model(): string
            {
                return TestModel::class;
            }

            public function includes(QueryWizardInterface $wizard): array
            {
                if (++$this->calls > 5) {
                    throw new \RuntimeException('includes() keeps being called.');
                }

                if ($wizard instanceof ModelQueryWizard) {
                    $wizard->process();
                }

                return ['relatedModels'];
            }
        };
        $wizard = $this->createModelWizardFromQuery([], TestModel::factory()->create())->schema($schema);

        try {
            $wizard->process();
            $this->fail('The schema processed the wizard that was reading it.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('process() was called from a schema method or callback that process() itself runs', $exception->getMessage());
        }

        $clone = clone $wizard;
        $this->assertSame(['relatedModels'], array_keys($clone->allowedIncludes('relatedModels')->getAllowedIncludes()));
        $this->assertTrue($clone->process()->is($wizard->getModel()));
    }

    #[Test]
    public function a_subclass_can_ask_whether_a_filter_name_is_disallowed(): void
    {
        $wizard = new class(TestModel::query(), new QueryParametersManager(new Request)) extends EloquentQueryWizard
        {
            /**
             * @return array<string, bool>
             */
            public function disallowedLeaves(string ...$names): array
            {
                return array_combine($names, array_map(fn (string $name): bool => $this->isFilterNameDisallowed($name), $names));
            }
        };

        $this->assertSame(['name' => false], $wizard->disallowedLeaves('name'));

        $wizard->disallowedFilters('group.secret', 'other');

        $this->assertSame(
            ['name' => false, 'group.name' => false, 'group.secret' => true, 'other' => true, 'other.leaf' => true],
            $wizard->disallowedLeaves('name', 'group.name', 'group.secret', 'other', 'other.leaf')
        );
    }

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
                return ['firstName'];
            }

            public function sorts(QueryWizardInterface $wizard): array
            {
                return ['createdAt'];
            }

            public function includes(QueryWizardInterface $wizard): array
            {
                return ['relatedModels'];
            }

            public function fields(QueryWizardInterface $wizard): array
            {
                return ['id', 'firstName'];
            }

            public function appends(QueryWizardInterface $wizard): array
            {
                return ['fullName'];
            }
        };
    }
}
