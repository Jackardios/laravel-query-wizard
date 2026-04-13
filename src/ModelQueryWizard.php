<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Jackardios\QueryWizard\Concerns\HandlesAppends;
use Jackardios\QueryWizard\Concerns\HandlesConfiguration;
use Jackardios\QueryWizard\Concerns\HandlesFields;
use Jackardios\QueryWizard\Concerns\HandlesIncludes;
use Jackardios\QueryWizard\Concerns\HandlesParameterScope;
use Jackardios\QueryWizard\Concerns\HandlesRelationPostProcessing;
use Jackardios\QueryWizard\Concerns\HandlesSafeRelationSelect;
use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Contracts\QueryWizardInterface;
use Jackardios\QueryWizard\Contracts\WizardContextInterface;
use Jackardios\QueryWizard\Eloquent\Includes\RelationshipInclude;
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
class ModelQueryWizard implements QueryWizardInterface, WizardContextInterface
{
    use HandlesAppends;
    use HandlesConfiguration;
    use HandlesFields;
    use HandlesIncludes;
    use HandlesParameterScope;
    use HandlesRelationPostProcessing;
    use HandlesSafeRelationSelect;

    protected Model $model;

    protected QueryParametersManager $parameters;

    protected QueryWizardConfig $config;

    protected ?ResourceSchemaInterface $schema = null;

    protected bool $processed = false;

    /**
     * Processing scope signature (parameters manager + request identity).
     */
    protected ?string $processedScopeSignature = null;

    public function __construct(
        Model $model,
        ?QueryParametersManager $parameters = null,
        ?QueryWizardConfig $config = null,
        ?ResourceSchemaInterface $schema = null
    ) {
        $this->model = $model;
        $this->resolveParametersFromContainer = $parameters === null;
        $this->parameters = $parameters ?? app(QueryParametersManager::class);
        $this->config = $config ?? app(QueryWizardConfig::class);
        $this->schema = $schema;
    }

    /**
     * Create a wizard for a model instance.
     */
    public static function for(Model $model): static
    {
        return new static($model);
    }

    /**
     * Set the resource schema for configuration.
     *
     * @param  class-string<ResourceSchemaInterface>|ResourceSchemaInterface  $schema
     */
    public function schema(string|ResourceSchemaInterface $schema): static
    {
        $this->ensureMutableBeforeProcessing();
        $this->schema = is_string($schema) ? app($schema) : $schema;
        $this->invalidateProcessedState(true);

        return $this;
    }

    /**
     * Set allowed includes.
     *
     * @param  IncludeInterface|string|array<IncludeInterface|string>  ...$includes
     */
    public function allowedIncludes(IncludeInterface|string|array ...$includes): static
    {
        $this->ensureMutableBeforeProcessing();
        $this->allowedIncludes = $this->flattenDefinitions($includes);
        $this->allowedIncludesExplicitlySet = true;
        $this->invalidateProcessedState(true);

        return $this;
    }

    /**
     * Set disallowed includes.
     *
     * @param  string|array<string>  ...$names
     */
    public function disallowedIncludes(string|array ...$names): static
    {
        $this->ensureMutableBeforeProcessing();
        $this->disallowedIncludes = $this->flattenStringArray($names);
        $this->invalidateProcessedState(true);

        return $this;
    }

    /**
     * Set default includes.
     *
     * @param  string|array<string>  ...$names
     */
    public function defaultIncludes(string|array ...$names): static
    {
        $this->ensureMutableBeforeProcessing();
        $this->defaultIncludes = $this->flattenStringArray($names);
        $this->invalidateProcessedState(true);

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
        $this->ensureMutableBeforeProcessing();
        $this->allowedFields = $this->flattenStringArray($fields);
        $this->allowedFieldsExplicitlySet = true;
        $this->invalidateProcessedState();

        return $this;
    }

    /**
     * Set disallowed fields.
     *
     * @param  string|array<string>  ...$names
     */
    public function disallowedFields(string|array ...$names): static
    {
        $this->ensureMutableBeforeProcessing();
        $this->disallowedFields = $this->flattenStringArray($names);
        $this->invalidateProcessedState();

        return $this;
    }

    /**
     * Set default fields.
     *
     * Applied only when request parameter is completely absent.
     *
     * @param  string|array<string>  ...$fields
     */
    public function defaultFields(string|array ...$fields): static
    {
        $this->ensureMutableBeforeProcessing();
        $this->defaultFields = $this->flattenStringArray($fields);
        $this->invalidateProcessedState();

        return $this;
    }

    /**
     * Set allowed appends.
     *
     * @param  string|array<string>  ...$appends
     */
    public function allowedAppends(string|array ...$appends): static
    {
        $this->ensureMutableBeforeProcessing();
        $this->allowedAppends = $this->flattenStringArray($appends);
        $this->allowedAppendsExplicitlySet = true;
        $this->invalidateProcessedState();

        return $this;
    }

    /**
     * Set disallowed appends.
     *
     * @param  string|array<string>  ...$names
     */
    public function disallowedAppends(string|array ...$names): static
    {
        $this->ensureMutableBeforeProcessing();
        $this->disallowedAppends = $this->flattenStringArray($names);
        $this->invalidateProcessedState();

        return $this;
    }

    /**
     * Set default appends.
     *
     * @param  string|array<string>  ...$appends
     */
    public function defaultAppends(string|array ...$appends): static
    {
        $this->ensureMutableBeforeProcessing();
        $this->defaultAppends = $this->flattenStringArray($appends);
        $this->invalidateProcessedState();

        return $this;
    }

    /**
     * Process the model (apply includes, fields, appends).
     *
     * The wizard is request-bound after processing because it mutates
     * an in-memory model graph; reusing the same instance across requests
     * is considered invalid and throws a LogicException.
     */
    public function process(): Model
    {
        $currentScopeSignature = $this->resolveProcessingScopeSignature();

        if ($this->processed) {
            if ($this->processedScopeSignature !== $currentScopeSignature) {
                throw new \LogicException(
                    'ModelQueryWizard instance cannot be reused across request boundaries or after '
                    .'request parameters or manually injected parameters change. Create a new wizard '
                    .'instance per request.'
                );
            }

            return $this->model;
        }

        $effectiveIncludes = $this->getEffectiveIncludes();
        $requestedIncludeNames = $this->resolveRequestedIncludeNames($effectiveIncludes);
        $this->cleanUnwantedRelations($effectiveIncludes, $requestedIncludeNames);
        $this->loadMissingIncludes($effectiveIncludes, $requestedIncludeNames);
        $this->hideDisallowedFields();
        $this->applyRelationPostProcessing();

        $this->processed = true;
        $this->processedScopeSignature = $currentScopeSignature;

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
     * @param  array<string>  $requestedIncludeNames
     */
    protected function cleanUnwantedRelations(array $effectiveIncludes, array $requestedIncludeNames): void
    {
        if (! $this->allowedIncludesExplicitlySet && $this->schema === null) {
            if (empty($this->disallowedIncludes)) {
                return;
            }

            $visited = [];
            $this->cleanDisallowedRelations($this->model, '', $visited);

            return;
        }

        $allowedTree = $this->buildRequestedIncludeTree($effectiveIncludes, $requestedIncludeNames);
        $visited = [];
        $this->cleanRelationsWithTree($this->model, $allowedTree, $visited);
    }

    /**
     * Build tree from includes for nested checking.
     *
     * @param  array<IncludeInterface>  $includes
     * @param  array<string>  $requestedIncludeNames
     * @return array<string, mixed>
     */
    protected function buildRequestedIncludeTree(array $includes, array $requestedIncludeNames): array
    {
        $requestedRelationPaths = $this->resolveRequestedRelationPaths($includes, $requestedIncludeNames);
        foreach (array_keys($this->buildValidatedRelationFieldMap()) as $relationPath) {
            $requestedRelationPaths[$relationPath] = true;
        }

        /** @var array<string, mixed> $tree */
        $tree = [];
        foreach (array_keys($requestedRelationPaths) as $name) {
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
     * @param  array<IncludeInterface>  $includes
     * @param  array<string>  $requestedIncludeNames
     * @return array<string, true>
     */
    protected function resolveRequestedRelationPaths(array $includes, array $requestedIncludeNames): array
    {
        $requestedLookup = array_fill_keys($requestedIncludeNames, true);
        $paths = [];

        foreach ($includes as $include) {
            if ($include->getType() !== 'relationship') {
                continue;
            }

            $includeName = $this->normalizePublicPath($include->getName());
            if (! isset($requestedLookup[$includeName])) {
                continue;
            }

            $paths[$include->getRelation()] = true;
        }

        return $paths;
    }

    /**
     * @param  array<int, bool>  $visited
     */
    protected function cleanDisallowedRelations(Model $model, string $prefix, array &$visited): void
    {
        $objectId = spl_object_id($model);
        if (isset($visited[$objectId])) {
            return;
        }
        $visited[$objectId] = true;

        foreach (array_keys($model->getRelations()) as $relationName) {
            $path = $prefix === '' ? $relationName : "{$prefix}.{$relationName}";

            if ($this->isNameDisallowed($path, $this->disallowedIncludes)) {
                $model->unsetRelation($relationName);

                continue;
            }

            $relatedData = $model->getRelation($relationName);
            if ($relatedData instanceof Model) {
                $this->cleanDisallowedRelations($relatedData, $path, $visited);
            } elseif (is_iterable($relatedData)) {
                foreach ($relatedData as $item) {
                    if ($item instanceof Model) {
                        $this->cleanDisallowedRelations($item, $path, $visited);
                    }
                }
            }
        }
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

            /** @var array<string, mixed> $nestedAllowed */
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
     * @param  array<string>  $requestedIncludeNames
     */
    protected function loadMissingIncludes(array $effectiveIncludes, array $requestedIncludeNames): void
    {
        $requested = $requestedIncludeNames;
        $loaded = array_keys($this->model->getRelations());

        $this->validateIncludesLimit(count($requested));

        $allowedIndex = $this->buildIncludesIndex($effectiveIncludes);

        /** @var array<int, string> $relationshipRequests */
        $relationshipRequests = [];
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
                $relationshipRequests[] = $include->getRelation();
            }
        }

        $relationshipPaths = array_values(array_unique($relationshipRequests));
        $this->prepareSafeRelationSelectPlan($this->model, $relationshipPaths);

        $relationsToLoad = [];
        foreach ($relationshipRequests as $relationPath) {
            $columns = $this->getSafeRelationSelectColumns($relationPath);

            if ($columns === null) {
                $relationsToLoad[] = $relationPath;

                continue;
            }

            $relationsToLoad[$relationPath] = static function ($query) use ($columns): void {
                $query->select($columns);
            };
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
        $validFields = $this->resolveValidatedRootFields();

        if ($validFields !== null) {
            $this->hideModelAttributesExcept($this->model, $validFields);
        }
    }

    /**
     * Apply relation sparse fields and appends in a single recursive traversal.
     */
    protected function applyRelationPostProcessing(): void
    {
        $relationFieldTree = $this->buildRelationFieldTree($this->buildValidatedRelationFieldMap());

        $appendTree = $this->getValidRequestedAppendsTree();

        $this->applyRelationPostProcessingToResults($this->model, $appendTree, $relationFieldTree);
    }

    /**
     * @param  array<IncludeInterface>  $effectiveIncludes
     * @return array<string>
     */
    protected function resolveRequestedIncludeNames(array $effectiveIncludes): array
    {
        $requested = $this->getMergedRequestedIncludes();
        if (empty($requested)) {
            return [];
        }

        $allowedIndex = $this->buildIncludesIndex($effectiveIncludes);
        $allowedIncludeNames = array_keys($allowedIndex);
        $usingDefaults = $this->isIncludesRequestEmpty();
        $defaults = $usingDefaults ? $this->getEffectiveDefaultIncludes() : [];
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

        /** @var array<string> */
        return array_values(array_intersect($requested, $allowedIncludeNames));
    }

    /**
     * Get the configuration instance.
     */
    public function getConfig(): QueryWizardConfig
    {
        return $this->config;
    }

    /**
     * Get the parameters manager.
     */
    public function getParametersManager(): QueryParametersManager
    {
        $this->parameters = $this->syncParametersManager($this->parameters);

        return $this->parameters;
    }

    protected function resolveProcessingScopeSignature(): string
    {
        return $this->resolveParametersScopeSignature($this->getParametersManager());
    }

    protected function invalidateProcessedState(bool $invalidateIncludeCache = false): void
    {
        if ($this->processed) {
            throw new \LogicException(
                'ModelQueryWizard cannot be reconfigured after process() has been called. '
                .'Create a new wizard instance for a different configuration.'
            );
        }

        if ($invalidateIncludeCache) {
            $this->invalidateIncludeCache();
        }

        $this->resetSafeRelationSelectState();
        $this->processed = false;
        $this->processedScopeSignature = null;
    }

    protected function ensureMutableBeforeProcessing(): void
    {
        if (! $this->processed) {
            return;
        }

        throw new \LogicException(
            'ModelQueryWizard cannot be reconfigured after process() has been called. '
            .'Create a new wizard instance for a different configuration.'
        );
    }

    /**
     * Get the schema instance.
     */
    public function getSchema(): ?ResourceSchemaInterface
    {
        return $this->schema;
    }

    /**
     * Normalize a string include to an IncludeInterface instance.
     */
    protected function normalizeStringToInclude(string $name): IncludeInterface
    {
        return RelationshipInclude::fromString($name, $this->config->getCountSuffix(), $this->config->getExistsSuffix());
    }

    /**
     * Get resource key for sparse fieldsets.
     */
    public function getResourceKey(): string
    {
        if ($this->schema !== null) {
            return $this->normalizePublicName($this->schema->type());
        }

        return $this->normalizePublicName(Str::camel(class_basename($this->model)));
    }
}
