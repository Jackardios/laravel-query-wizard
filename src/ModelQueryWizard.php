<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Jackardios\QueryWizard\Concerns\HandlesAppends;
use Jackardios\QueryWizard\Concerns\HandlesConfiguration;
use Jackardios\QueryWizard\Concerns\HandlesFields;
use Jackardios\QueryWizard\Concerns\HandlesIncludes;
use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Contracts\QueryWizardInterface;
use Jackardios\QueryWizard\Eloquent\Includes\RelationshipInclude;
use Jackardios\QueryWizard\Exceptions\InvalidFieldQuery;
use Jackardios\QueryWizard\Exceptions\InvalidIncludeQuery;
use Jackardios\QueryWizard\Schema\ResourceSchemaInterface;

/**
 * Wizard for processing already-loaded Model instances.
 *
 * Handles: includes (load missing), fields (hide), appends
 * Does NOT handle: filters, sorts (these are for queries, not loaded models)
 *
 * @phpstan-consistent-constructor
 */
final class ModelQueryWizard implements QueryWizardInterface
{
    use HandlesAppends;
    use HandlesConfiguration;
    use HandlesFields;
    use HandlesIncludes;

    protected Model $model;

    protected QueryParametersManager $parameters;

    protected QueryWizardConfig $config;

    protected ?ResourceSchemaInterface $schema = null;

    protected bool $processed = false;

    public function __construct(
        Model $model,
        ?QueryParametersManager $parameters = null,
        ?QueryWizardConfig $config = null,
        ?ResourceSchemaInterface $schema = null
    ) {
        $this->model = $model;
        $this->parameters = $parameters ?? app(QueryParametersManager::class);
        $this->config = $config ?? app(QueryWizardConfig::class);
        $this->schema = $schema;
    }

    /**
     * Create a wizard for a model instance.
     */
    public static function for(Model $model): static
    {
        return new self($model);
    }

    /**
     * Set the resource schema for configuration.
     *
     * @param  class-string<ResourceSchemaInterface>|ResourceSchemaInterface  $schema
     */
    public function schema(string|ResourceSchemaInterface $schema): static
    {
        $this->schema = is_string($schema) ? app($schema) : $schema;
        $this->invalidateIncludeCache();
        $this->processed = false;

        return $this;
    }

    /**
     * Set allowed includes.
     *
     * @param  IncludeInterface|string|array<IncludeInterface|string>  ...$includes
     */
    public function allowedIncludes(IncludeInterface|string|array ...$includes): static
    {
        $this->allowedIncludes = $this->flattenDefinitions($includes);
        $this->allowedIncludesExplicitlySet = true;
        $this->invalidateIncludeCache();
        $this->processed = false;

        return $this;
    }

    /**
     * Set disallowed includes.
     *
     * @param  string|array<string>  ...$names
     */
    public function disallowedIncludes(string|array ...$names): static
    {
        $this->disallowedIncludes = $this->flattenStringArray($names);
        $this->invalidateIncludeCache();
        $this->processed = false;

        return $this;
    }

    /**
     * Set default includes.
     *
     * @param  string|array<string>  ...$names
     */
    public function defaultIncludes(string|array ...$names): static
    {
        $this->defaultIncludes = $this->flattenStringArray($names);
        $this->invalidateIncludeCache();
        $this->processed = false;

        return $this;
    }

    /**
     * Set allowed fields.
     *
     * Empty array means client cannot request specific fields.
     * Use ['*'] to allow any fields requested by client.
     *
     * @param  string|array<string>  ...$fields
     */
    public function allowedFields(string|array ...$fields): static
    {
        $this->allowedFields = $this->flattenStringArray($fields);
        $this->allowedFieldsExplicitlySet = true;
        $this->processed = false;

        return $this;
    }

    /**
     * Set disallowed fields.
     *
     * @param  string|array<string>  ...$names
     */
    public function disallowedFields(string|array ...$names): static
    {
        $this->disallowedFields = $this->flattenStringArray($names);
        $this->processed = false;

        return $this;
    }

    /**
     * Set allowed appends.
     *
     * @param  string|array<string>  ...$appends
     */
    public function allowedAppends(string|array ...$appends): static
    {
        $this->allowedAppends = $this->flattenStringArray($appends);
        $this->allowedAppendsExplicitlySet = true;
        $this->processed = false;

        return $this;
    }

    /**
     * Set disallowed appends.
     *
     * @param  string|array<string>  ...$names
     */
    public function disallowedAppends(string|array ...$names): static
    {
        $this->disallowedAppends = $this->flattenStringArray($names);
        $this->processed = false;

        return $this;
    }

    /**
     * Set default appends.
     *
     * @param  string|array<string>  ...$appends
     */
    public function defaultAppends(string|array ...$appends): static
    {
        $this->defaultAppends = $this->flattenStringArray($appends);
        $this->processed = false;

        return $this;
    }

    /**
     * Process the model (apply includes, fields, appends).
     */
    public function process(): Model
    {
        if ($this->processed) {
            return $this->model;
        }

        $effectiveIncludes = $this->getEffectiveIncludes();
        $this->cleanUnwantedRelations($effectiveIncludes);
        $this->loadMissingIncludes($effectiveIncludes);
        $this->hideDisallowedFields();
        $this->hideFieldsOnRelations();
        $this->applyAppends();

        $this->processed = true;

        return $this->model;
    }

    /**
     * Get the model instance.
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * @param  array<IncludeInterface>  $effectiveIncludes
     */
    protected function cleanUnwantedRelations(array $effectiveIncludes): void
    {
        if (! $this->allowedIncludesExplicitlySet && $this->schema === null) {
            return;
        }

        $allowedTree = $this->buildAllowedTree($effectiveIncludes);
        $visited = [];
        $this->cleanRelationsWithTree($this->model, $allowedTree, $visited);
    }

    /**
     * Build tree from includes for nested checking.
     *
     * @param  array<IncludeInterface>  $includes
     * @return array<string, mixed>
     */
    protected function buildAllowedTree(array $includes): array
    {
        /** @var array<string, mixed> $tree */
        $tree = [];
        foreach ($includes as $include) {
            if ($include->getType() === 'count') {
                continue;
            }

            $name = $include->getRelation();
            $parts = explode('.', $name);
            /** @var array<string, mixed> $current */
            $current = &$tree;

            foreach ($parts as $part) {
                if (! isset($current[$part])) {
                    $current[$part] = [];
                }
                /** @var array<string, mixed> $current */
                $current = &$current[$part];
            }
        }

        return $tree;
    }

    /**
     * Recursively clean relations using allowed tree.
     *
     * Uses $visited to prevent infinite recursion with circular references.
     *
     * @param  array<string, mixed>  $allowedTree
     * @param  array<int, bool>  $visited
     */
    protected function cleanRelationsWithTree(Model $model, array $allowedTree, array &$visited): void
    {
        $objectId = spl_object_id($model);
        if (isset($visited[$objectId])) {
            return;
        }
        $visited[$objectId] = true;

        foreach (array_keys($model->getRelations()) as $relationName) {
            if (! isset($allowedTree[$relationName])) {
                $model->unsetRelation($relationName);

                continue;
            }

            $nestedAllowed = $allowedTree[$relationName];
            $relatedData = $model->getRelation($relationName);

            if ($relatedData instanceof Model) {
                $this->cleanRelationsWithTree($relatedData, $nestedAllowed, $visited);
            } elseif (is_iterable($relatedData)) {
                foreach ($relatedData as $item) {
                    if ($item instanceof Model) {
                        $this->cleanRelationsWithTree($item, $nestedAllowed, $visited);
                    }
                }
            }
        }
    }

    /**
     * @param  array<IncludeInterface>  $effectiveIncludes
     */
    protected function loadMissingIncludes(array $effectiveIncludes): void
    {
        $requested = $this->getMergedRequestedIncludes();
        $loaded = array_keys($this->model->getRelations());

        $this->validateIncludesLimit(count($requested));

        $allowedIndex = $this->buildIncludesIndex($effectiveIncludes);

        if (! empty($requested)) {
            $allowedIncludeNames = array_keys($allowedIndex);

            $defaults = $this->getEffectiveDefaultIncludes();
            $defaultsIndex = array_flip($defaults);

            $invalidIncludes = array_filter(
                array_diff($requested, $allowedIncludeNames),
                fn ($name) => ! isset($defaultsIndex[$name])
            );

            if (! empty($invalidIncludes) && ! $this->config->isInvalidIncludeQueryExceptionDisabled()) {
                throw InvalidIncludeQuery::includesNotAllowed(
                    collect($invalidIncludes),
                    collect($allowedIncludeNames)
                );
            }
            $requested = array_intersect($requested, $allowedIncludeNames);
        }

        $relationsToLoad = [];
        $countsToLoad = [];
        $callbackIncludes = [];

        foreach ($requested as $includeName) {
            if (in_array($includeName, $loaded)) {
                continue;
            }

            $include = $allowedIndex[$includeName] ?? null;
            if ($include === null) {
                continue;
            }

            $this->validateIncludeDepth($include);

            if ($include->getType() === 'count') {
                $countsToLoad[] = $include->getRelation();
            } elseif ($include->getType() === 'callback') {
                $callbackIncludes[] = $include;
            } elseif ($include->getType() === 'relationship') {
                $relationsToLoad[] = $include->getRelation();
            }
        }

        if (! empty($relationsToLoad)) {
            $this->model->loadMissing($relationsToLoad);
        }
        if (! empty($countsToLoad)) {
            $this->model->loadCount($countsToLoad);
        }
        foreach ($callbackIncludes as $include) {
            $include->apply($this->model);
        }
    }

    protected function hideDisallowedFields(): void
    {
        $resourceKey = $this->getResourceKey();
        $requestedFields = $this->getRequestedFieldsForResource($resourceKey);

        if (empty($requestedFields) || in_array('*', $requestedFields)) {
            return;
        }

        $allowedFields = $this->getEffectiveFields();

        if (in_array('*', $allowedFields, true)) {
            $visibleFields = $requestedFields;
        } elseif (empty($allowedFields)) {
            if (! $this->config->isInvalidFieldQueryExceptionDisabled()) {
                throw InvalidFieldQuery::fieldsNotAllowed(
                    collect($requestedFields),
                    collect([])
                );
            }

            return;
        } else {
            $invalidFields = array_diff($requestedFields, $allowedFields);
            if (! empty($invalidFields)) {
                if (! $this->config->isInvalidFieldQueryExceptionDisabled()) {
                    throw InvalidFieldQuery::fieldsNotAllowed(
                        collect($invalidFields),
                        collect($allowedFields)
                    );
                }
            }
            $visibleFields = array_intersect($requestedFields, $allowedFields);
        }

        $this->hideModelAttributesExcept($this->model, $visibleFields);
    }

    /**
     * Hide fields on loaded relations based on requested fields.
     * Recursively processes nested relations with circular reference protection.
     */
    protected function hideFieldsOnRelations(): void
    {
        $relationFieldMap = $this->buildValidatedRelationFieldMap();
        if (empty($relationFieldMap)) {
            return;
        }

        $visited = [];
        $this->hideFieldsOnRelationsRecursively($this->model, $relationFieldMap, $visited);
    }

    /**
     * @param  array<string, array<string>>  $relationFieldMap
     * @param  array<int, bool>  $visited
     */
    protected function hideFieldsOnRelationsRecursively(
        Model $model,
        array $relationFieldMap,
        array &$visited,
        string $prefix = ''
    ): void {
        $objectId = spl_object_id($model);
        if (isset($visited[$objectId])) {
            return;
        }
        $visited[$objectId] = true;

        foreach ($model->getRelations() as $relationName => $relatedData) {
            $relationPath = $prefix === '' ? $relationName : $prefix.'.'.$relationName;
            $relationFields = $relationFieldMap[$relationPath] ?? [];
            $shouldHideFields = ! empty($relationFields) && ! in_array('*', $relationFields, true);

            if ($relatedData instanceof Model) {
                if ($shouldHideFields) {
                    $this->hideModelAttributesExcept($relatedData, $relationFields);
                }

                $this->hideFieldsOnRelationsRecursively($relatedData, $relationFieldMap, $visited, $relationPath);

                continue;
            }

            if (! is_iterable($relatedData)) {
                continue;
            }

            foreach ($relatedData as $item) {
                if (! $item instanceof Model) {
                    continue;
                }
                if ($shouldHideFields) {
                    $this->hideModelAttributesExcept($item, $relationFields);
                }
                $this->hideFieldsOnRelationsRecursively($item, $relationFieldMap, $visited, $relationPath);
            }
        }
    }

    protected function applyAppends(): void
    {
        $appends = $this->getValidRequestedAppends();
        if (! empty($appends)) {
            $this->applyAppendsRecursively($this->model, $appends);
        }
    }

    /**
     * Get the configuration instance.
     */
    protected function getConfig(): QueryWizardConfig
    {
        return $this->config;
    }

    /**
     * Get the parameters manager.
     */
    protected function getParametersManager(): QueryParametersManager
    {
        return $this->parameters;
    }

    /**
     * Get the schema instance.
     */
    protected function getSchema(): ?ResourceSchemaInterface
    {
        return $this->schema;
    }

    /**
     * Normalize a string include to an IncludeInterface instance.
     */
    protected function normalizeStringToInclude(string $name): IncludeInterface
    {
        return RelationshipInclude::fromString($name, $this->config->getCountSuffix());
    }

    /**
     * Get resource key for sparse fieldsets.
     */
    public function getResourceKey(): string
    {
        if ($this->schema !== null) {
            return $this->schema->type();
        }

        return Str::camel(class_basename($this->model));
    }
}
