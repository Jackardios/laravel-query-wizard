<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature;

use ArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Jackardios\QueryWizard\BaseQueryWizard;
use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\Contracts\FilterInterface;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Contracts\QueryWizardInterface;
use Jackardios\QueryWizard\Contracts\SortInterface;
use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Eloquent\EloquentInclude;
use Jackardios\QueryWizard\Eloquent\EloquentSort;
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;
use Jackardios\QueryWizard\Filters\AbstractFilter;
use Jackardios\QueryWizard\Filters\CallbackFilter;
use Jackardios\QueryWizard\QueryParametersManager;
use Jackardios\QueryWizard\Schema\ResourceSchema;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class CustomWizardTest extends TestCase
{
    #[Test]
    public function a_subclass_initializes_through_the_base_constructor(): void
    {
        $parameters = new QueryParametersManager(new Request(['fields' => ['testModel' => 'name']]));
        $config = new QueryWizardConfig(['limits' => ['max_filters_count' => 3]]);
        $wizard = new ArrayWizard(new ArrayObject(['seed']), $parameters, $config);

        $this->assertSame($parameters, $wizard->getParametersManager());
        $this->assertSame(3, $wizard->getConfig()->getMaxFiltersCount());
        $this->assertNull($wizard->getSchema());

        $subject = $wizard->allowedFields('name')->build();

        $this->assertSame(['seed', 'fields:name'], $subject->getArrayCopy());
    }

    #[Test]
    public function is_built_follows_the_build_and_invalidation(): void
    {
        $wizard = new ArrayWizard(new ArrayObject, new QueryParametersManager(new Request(['fields' => ['testModel' => 'name']])));

        $this->assertFalse($wizard->built());

        $wizard->allowedFields('name')->build();
        $this->assertTrue($wizard->built());

        $wizard->allowedFields('name', 'id');
        $this->assertFalse($wizard->built());
    }

    #[Test]
    public function a_rebuild_starts_from_a_copy_of_the_constructor_subject(): void
    {
        $original = new ArrayObject(['seed']);
        $wizard = new ArrayWizard($original, new QueryParametersManager(new Request(['fields' => ['testModel' => 'name']])));

        $wizard->allowedFields('name')->build();
        $subject = $wizard->allowedFields('name', 'id')->build();

        $this->assertSame(['seed', 'fields:name'], $subject->getArrayCopy());
        $this->assertNotSame($original, $subject);
    }

    #[Test]
    public function the_default_append_accessor_model_follows_the_resource_model_and_its_relations(): void
    {
        $wizard = new ArrayWizard(new ArrayObject, model: new TestModel);

        $this->assertInstanceOf(TestModel::class, $wizard->appendAccessorModel(''));
        $this->assertInstanceOf(RelatedModel::class, $wizard->appendAccessorModel('relatedModels'));
        $this->assertNull($wizard->appendAccessorModel('missingRelation'));
    }

    #[Test]
    public function without_a_resource_model_there_is_no_append_accessor_model(): void
    {
        $wizard = new ArrayWizard(new ArrayObject);

        $this->assertNull($wizard->appendAccessorModel(''));
        $this->assertNull($wizard->appendAccessorModel('relatedModels'));
    }

    #[Test]
    public function a_schema_default_keyed_by_a_leaf_of_a_composite_filter_applies(): void
    {
        $wizard = GroupWizard::withSchema(new GroupSchema(['status' => 'published']));

        $this->assertSame(['status:published'], $wizard->build()->getArrayCopy());
    }

    #[Test]
    public function a_schema_default_keyed_by_a_composite_filter_itself_throws(): void
    {
        $wizard = GroupWizard::withSchema(new GroupSchema(['advanced' => 'x']));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Schema defaultFilters() names no allowed filter: `advanced`.');

        $wizard->build();
    }

    #[Test]
    public function disallowing_a_leaf_of_a_composite_filter_rejects_its_key_and_drops_its_default(): void
    {
        $defaultOnly = GroupWizard::withSchema(new GroupSchema(['status' => 'published']));
        $defaultOnly->disallowedFilters('status');

        $this->assertSame([], $defaultOnly->build()->getArrayCopy());

        $otherLeaf = GroupWizard::withSchema(new GroupSchema([]), ['filter' => ['priority' => '1']]);
        $otherLeaf->disallowedFilters('status');

        $this->assertSame(['priority:1'], $otherLeaf->build()->getArrayCopy());

        $requested = GroupWizard::withSchema(new GroupSchema([]), ['filter' => ['status' => 'draft']]);
        $requested->disallowedFilters('status');

        $this->expectException(InvalidFilterQuery::class);

        $requested->build();
    }

    #[Test]
    public function the_base_constructor_rejects_a_schema_of_another_model(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('describes '.TestModel::class.', but the wizard queries '.RelatedModel::class.'.');

        new GroupWizard(new ArrayObject, new QueryParametersManager(new Request), new GroupSchema([]), new RelatedModel);
    }
}

/**
 * @extends BaseQueryWizard<ArrayObject<int, string>>
 */
final class ArrayWizard extends BaseQueryWizard
{
    /**
     * @param  ArrayObject<int, string>  $subject
     */
    public function __construct(
        ArrayObject $subject,
        ?QueryParametersManager $parameters = null,
        ?QueryWizardConfig $config = null,
        private readonly ?Model $model = null,
    ) {
        parent::__construct($subject, $parameters, $config);
    }

    public function built(): bool
    {
        return $this->isBuilt();
    }

    public function appendAccessorModel(string $relationPath): ?Model
    {
        return $this->resolveAppendAccessorModel($relationPath);
    }

    public function getResourceKey(): string
    {
        return 'testModel';
    }

    protected function resourceModel(): ?Model
    {
        return $this->model;
    }

    protected function applyFields(array $fields): void
    {
        $this->subject->append('fields:'.implode(',', $fields));
    }

    protected function applyValidatedIncludes(array $validRequestedIncludes, array $includesIndex): void {}

    protected function normalizeStringToFilter(string $name): FilterInterface
    {
        return EloquentFilter::exact($name);
    }

    protected function normalizeStringToSort(string $name): SortInterface
    {
        return EloquentSort::field($name);
    }

    protected function normalizeStringToInclude(string $name): IncludeInterface
    {
        return EloquentInclude::relationship($name);
    }
}

/**
 * A wizard whose `advanced` filter groups two leaves: the request and the schema
 * defaults address the leaves, never the group.
 *
 * @extends BaseQueryWizard<ArrayObject<int, string>>
 */
final class GroupWizard extends BaseQueryWizard
{
    /**
     * @param  ArrayObject<int, string>  $subject
     */
    public function __construct(
        ArrayObject $subject,
        QueryParametersManager $parameters,
        ResourceSchema $schema,
        private readonly ?Model $model = null,
    ) {
        parent::__construct($subject, $parameters, null, $schema);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public static function withSchema(ResourceSchema $schema, array $query = []): self
    {
        return new self(new ArrayObject, new QueryParametersManager(new Request($query)), $schema);
    }

    public function getResourceKey(): string
    {
        return 'testModel';
    }

    protected function resourceModel(): ?Model
    {
        return $this->model;
    }

    protected function resolveAllowedFilterNames(array $filters): array
    {
        $names = [];

        foreach ($filters as $name => $filter) {
            array_push($names, ...($filter instanceof FilterGroup ? array_keys($filter->leaves) : [$name]));
        }

        return $names;
    }

    protected function resolvePreparedFilterValue(FilterInterface $filter): mixed
    {
        if (! $filter instanceof FilterGroup) {
            return parent::resolvePreparedFilterValue($filter);
        }

        $values = [];

        foreach ($filter->leaves as $name => $leaf) {
            $value = $this->resolvePreparedFilterValue($leaf);

            if ($value !== null) {
                $values[$name] = $value;
            }
        }

        return $values === [] ? null : $values;
    }

    protected function applyFields(array $fields): void {}

    protected function applyValidatedIncludes(array $validRequestedIncludes, array $includesIndex): void {}

    protected function normalizeStringToFilter(string $name): FilterInterface
    {
        return EloquentFilter::exact($name);
    }

    protected function normalizeStringToSort(string $name): SortInterface
    {
        return EloquentSort::field($name);
    }

    protected function normalizeStringToInclude(string $name): IncludeInterface
    {
        return EloquentInclude::relationship($name);
    }
}

final class FilterGroup extends AbstractFilter
{
    /** @var array<string, FilterInterface> */
    public array $leaves = [];

    public static function of(string $name, FilterInterface ...$leaves): self
    {
        $group = new self($name);

        foreach ($leaves as $leaf) {
            $group->leaves[$leaf->getName()] = $leaf;
        }

        return $group;
    }

    public function apply(mixed $subject, mixed $value): mixed
    {
        foreach ($value as $name => $leafValue) {
            $subject = $this->leaves[$name]->apply($subject, $leafValue);
        }

        return $subject;
    }
}

final class GroupSchema extends ResourceSchema
{
    /**
     * @param  array<string, mixed>  $defaults
     */
    public function __construct(private readonly array $defaults) {}

    public function model(): string
    {
        return TestModel::class;
    }

    public function filters(QueryWizardInterface $wizard): array
    {
        $leaf = static fn (string $name): CallbackFilter => CallbackFilter::make(
            $name,
            static fn (ArrayObject $subject, mixed $value) => $subject->append("{$name}:{$value}")
        );

        return [FilterGroup::of('advanced', $leaf('status'), $leaf('priority'))];
    }

    public function defaultFilters(QueryWizardInterface $wizard): array
    {
        return $this->defaults;
    }
}
