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
use Jackardios\QueryWizard\Contracts\SortInterface;
use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Eloquent\EloquentInclude;
use Jackardios\QueryWizard\Eloquent\EloquentSort;
use Jackardios\QueryWizard\QueryParametersManager;
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
