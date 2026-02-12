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
 */
final class ModelQueryWizard implements QueryWizardInterface, WizardContextInterface
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
                    'ModelQueryWizard instance cannot be reused across request boundaries. '
                    .'Create a new wizard instance per request.'
                );
            }

            return $this->model;
        }

        $effectiveIncludes = $this->getEffectiveIncludes();
        $this->cleanUnwantedRelations($effectiveIncludes);
        $this->loadMissingIncludes($effectiveIncludes);
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
        $usingDefaults = $this->isIncludesRequestEmpty();
        $loaded = array_keys($this->model->getRelations());

        $this->validateIncludesLimit(count($requested));

        $allowedIndex = $this->buildIncludesIndex($effectiveIncludes);

        if (! empty($requested)) {
            $allowedIncludeNames = array_keys($allowedIndex);

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
            $requested = array_intersect($requested, $allowedIncludeNames);
        }

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
        if ($invalidateIncludeCache) {
            $this->invalidateIncludeCache();
        }

        $this->resetSafeRelationSelectState();
        $this->processed = false;
        $this->processedScopeSignature = null;
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
            return $this->schema->type();
        }

        return Str::camel(class_basename($this->model));
    }
}
