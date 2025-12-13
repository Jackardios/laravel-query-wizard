<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Wizards;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\Contracts\DriverInterface;
use Jackardios\QueryWizard\Contracts\ResourceSchemaInterface;
use Jackardios\QueryWizard\QueryParametersManager;
use Jackardios\QueryWizard\Wizards\Concerns\HandlesAppends;
use Jackardios\QueryWizard\Wizards\Concerns\HandlesFields;
use Jackardios\QueryWizard\Wizards\Concerns\HandlesIncludes;

class ItemQueryWizard extends BaseQueryWizard
{
    use HandlesIncludes;
    use HandlesFields;
    use HandlesAppends;

    protected int|string|Model $key;

    public function __construct(
        ResourceSchemaInterface $schema,
        int|string|Model $key,
        DriverInterface $driver,
        QueryParametersManager $parameters,
        QueryWizardConfig $config
    ) {
        $modelClass = $schema->model();
        parent::__construct($modelClass, $driver, $parameters, $config, $schema);
        $this->key = $key;
    }

    protected function getContextMode(): string
    {
        return 'item';
    }

    /**
     * Get the model (null if not found)
     */
    public function get(): ?Model
    {
        if ($this->key instanceof Model) {
            return $this->processLoadedModel($this->key);
        }

        return $this->loadModel($this->key);
    }

    /**
     * Get the model or throw ModelNotFoundException
     *
     * @throws ModelNotFoundException
     */
    public function getOrFail(): Model
    {
        $model = $this->get();

        if ($model === null) {
            /** @var class-string<Model> $modelClass */
            $modelClass = $this->schema->model();
            throw (new ModelNotFoundException())->setModel($modelClass, [$this->key]);
        }

        return $model;
    }

    /**
     * Load model from database with includes and fields applied
     */
    protected function loadModel(int|string $key): ?Model
    {
        $this->subject = $this->driver->prepareSubject($this->subject);

        $this->applyIncludes();
        $this->applyFields();
        $this->validateAppends();

        $model = $this->subject->find($key);

        if ($model !== null) {
            $this->applyAppendsToResult($model);
        }

        return $model;
    }

    /**
     * Process an already loaded model:
     * - Remove unwanted relations
     * - Hide unwanted fields
     * - Load missing includes
     * - Apply appends
     */
    protected function processLoadedModel(Model $model): Model
    {
        $this->validateAppends();

        $effectiveIncludes = $this->getEffectiveIncludes();
        $requestedIncludes = $this->getMergedRequestedIncludes();

        $this->cleanRelations($model, $effectiveIncludes);
        $this->loadMissingIncludes($model, $requestedIncludes);
        $this->hideDisallowedFields($model);
        $this->applyAppendsToResult($model);

        return $model;
    }

    /**
     * Get merged requested includes (default + requested)
     *
     * @return array<string>
     */
    protected function getMergedRequestedIncludes(): array
    {
        $defaultIncludes = $this->getEffectiveDefaultIncludes();
        $requestedIncludes = $this->parameters->getIncludes()->all();

        return array_unique(array_merge($defaultIncludes, $requestedIncludes));
    }

    /**
     * Build an allowed tree from includes for efficient nested checking
     *
     * @param array<\Jackardios\QueryWizard\Contracts\Definitions\IncludeDefinitionInterface> $includes
     * @return array<string, mixed>
     */
    protected function buildAllowedTree(array $includes): array
    {
        /** @var array<string, mixed> $tree */
        $tree = [];

        foreach ($includes as $include) {
            $name = $include->getRelation();
            $parts = explode('.', $name);
            /** @var array<string, mixed> $current */
            $current = &$tree;

            foreach ($parts as $part) {
                if (!isset($current[$part])) {
                    $current[$part] = [];
                }
                /** @var array<string, mixed> $current */
                $current = &$current[$part];
            }
        }

        return $tree;
    }

    /**
     * Clean unwanted relations from model (recursively)
     *
     * @param array<\Jackardios\QueryWizard\Contracts\Definitions\IncludeDefinitionInterface> $allowedIncludes
     */
    protected function cleanRelations(Model $model, array $allowedIncludes): void
    {
        $allowedTree = $this->buildAllowedTree($allowedIncludes);
        $this->cleanRelationsWithTree($model, $allowedTree);
    }

    /**
     * Recursively clean relations using the allowed tree
     *
     * @param array<string, array<string, mixed>> $allowedTree
     */
    protected function cleanRelationsWithTree(Model $model, array $allowedTree): void
    {
        $loadedRelations = array_keys($model->getRelations());

        foreach ($loadedRelations as $relationName) {
            if (!isset($allowedTree[$relationName])) {
                $model->unsetRelation($relationName);
                continue;
            }

            $nestedAllowed = $allowedTree[$relationName];
            $relatedData = $model->getRelation($relationName);

            if ($relatedData instanceof Collection) {
                $relatedData->each(fn($item) => $this->cleanRelationsWithTree($item, $nestedAllowed));
            } elseif ($relatedData instanceof Model) {
                $this->cleanRelationsWithTree($relatedData, $nestedAllowed);
            }
        }
    }

    /**
     * Load missing includes on the model
     *
     * @param array<string> $requestedIncludes
     */
    protected function loadMissingIncludes(Model $model, array $requestedIncludes): void
    {
        $loadedRelations = array_keys($model->getRelations());
        $effectiveIncludes = $this->getEffectiveIncludes();

        $allowedIndex = [];
        foreach ($effectiveIncludes as $include) {
            $allowedIndex[$include->getName()] = $include;
        }

        $missingIncludes = [];
        foreach ($requestedIncludes as $includeName) {
            if (!in_array($includeName, $loadedRelations, true) && isset($allowedIndex[$includeName])) {
                $include = $allowedIndex[$includeName];
                if ($include->getType() === 'relationship') {
                    $missingIncludes[] = $include->getRelation();
                }
            }
        }

        if (!empty($missingIncludes)) {
            $model->load($missingIncludes);
        }
    }

    /**
     * Hide fields that are not in the requested fields list
     */
    protected function hideDisallowedFields(Model $model): void
    {
        $resourceKey = $this->getResourceKey();
        $requestedFields = $this->getFields()->get($resourceKey, []);

        if (empty($requestedFields) || in_array('*', $requestedFields, true)) {
            return;
        }

        $allAttributes = array_keys($model->getAttributes());
        $fieldsToHide = array_diff($allAttributes, $requestedFields);

        if (!empty($fieldsToHide)) {
            $model->makeHidden(array_values($fieldsToHide));
        }

        $this->hideFieldsOnRelations($model);
    }

    /**
     * Recursively hide fields on loaded relations
     */
    protected function hideFieldsOnRelations(Model $model): void
    {
        $allFields = $this->getFields();

        foreach ($model->getRelations() as $relationName => $relatedData) {
            $relationFields = $allFields->get($relationName, []);

            if (empty($relationFields) || in_array('*', $relationFields, true)) {
                continue;
            }

            if ($relatedData instanceof Collection) {
                $relatedData->each(function (Model $item) use ($relationFields) {
                    $this->hideFieldsOnModel($item, $relationFields);
                });
            } elseif ($relatedData instanceof Model) {
                $this->hideFieldsOnModel($relatedData, $relationFields);
            }
        }
    }

    /**
     * Hide fields on a single model
     *
     * @param array<string> $visibleFields
     */
    protected function hideFieldsOnModel(Model $model, array $visibleFields): void
    {
        $allAttributes = array_keys($model->getAttributes());
        $fieldsToHide = array_diff($allAttributes, $visibleFields);

        if (!empty($fieldsToHide)) {
            $model->makeHidden(array_values($fieldsToHide));
        }
    }
}
