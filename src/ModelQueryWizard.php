<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard;

use Illuminate\Database\Eloquent\Model;
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
use Jackardios\QueryWizard\Schema\ResourceSchemaInterface;
use Jackardios\QueryWizard\Support\RelationResolver;

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

    /** @var array<string, array<string, string>> */
    private array $runtimeAttributesByOwner = [];

    /**
     * The request validated before the model is touched.
     *
     * @var array{rootFields: array<string>|null, relationFieldMap: array<string, array<string>>, appendTree: array{appends: array<string>, relations: array<string, mixed>}}|null
     */
    private ?array $validatedRequest = null;

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

        $this->forgetConfigurationMemo();
        $this->validatedRequest = null;
        $effectiveIncludes = $this->getEffectiveIncludes();
        $requestedIncludeNames = $this->resolveIncludesToApply()[0] ?? [];
        $this->validatedRequest();
        $this->cleanUnwantedRelations($effectiveIncludes, $requestedIncludeNames);
        $this->loadMissingIncludes($effectiveIncludes, $requestedIncludeNames);
        $this->hideDisallowedFields();
        $this->applyRelationPostProcessing();

        $this->processed = true;
        $this->processedScopeSignature = $currentScopeSignature;

        return $this->model;
    }

    /**
     * Validate every part of the request, so an invalid one fails before
     * relations are removed or loaded.
     *
     * @return array{rootFields: array<string>|null, relationFieldMap: array<string, array<string>>, appendTree: array{appends: array<string>, relations: array<string, mixed>}}
     */
    private function validatedRequest(): array
    {
        return $this->validatedRequest ??= [
            'rootFields' => $this->resolveValidatedRootFields(),
            'relationFieldMap' => $this->buildValidatedRelationFieldMap(),
            'appendTree' => $this->getValidRequestedAppendsTree(),
        ];
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

        $allowedIndex = $this->buildIncludesIndex($effectiveIncludes);
        $this->runtimeAttributesByOwner = $this->resolveRuntimeAttributesByOwner($requested, $allowedIndex);

        /** @var array<int, string> $relationshipRequests */
        $relationshipRequests = [];
        $countsToLoad = [];
        $existsToLoad = [];
        $callbackIncludes = [];

        foreach ($requested as $includeName) {
            if (in_array($includeName, $loaded)) {
                continue;
            }

            $include = $allowedIndex[$includeName] ?? null;
            if ($include === null) {
                continue;
            }

            if ($include->getType() === 'count') {
                $countsToLoad[] = $include->getRelation();
            } elseif ($include->getType() === 'exists') {
                $existsToLoad[] = $include->getRelation();
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

            $relationsToLoad[$relationPath] = function ($query) use ($columns): void {
                $this->applySafeRelationSelectToQuery($query, $columns);
            };
        }

        foreach ($callbackIncludes as $include) {
            $include->apply($this->model);
        }
        if (! empty($relationsToLoad)) {
            $this->model->loadMissing($relationsToLoad);
        }
        if (! empty($countsToLoad)) {
            $this->model->loadCount($countsToLoad);
        }
        if (! empty($existsToLoad)) {
            $this->model->loadExists($existsToLoad);
        }
    }

    protected function hideDisallowedFields(): void
    {
        $validFields = $this->validatedRequest()['rootFields'];

        if ($validFields === null) {
            return;
        }

        $runtimeAttributes = $this->runtimeAttributesByOwner[''] ?? [];
        $visibleFields = array_values($runtimeAttributes);

        foreach ($validFields as $field) {
            $visibleFields[] = $runtimeAttributes[$this->normalizePublicPath($field)] ?? $field;
        }

        $this->hideModelAttributesExcept($this->model, array_values(array_unique($visibleFields)));
    }

    /**
     * Apply relation sparse fields and appends in a single recursive traversal.
     */
    protected function applyRelationPostProcessing(): void
    {
        $validatedRequest = $this->validatedRequest();
        $relationFieldTree = $this->withRuntimeAttributesInFieldTree(
            $this->buildRelationFieldTree($validatedRequest['relationFieldMap']),
            $this->runtimeAttributesByOwner
        );

        $appendTree = $validatedRequest['appendTree'];

        $this->applyRelationPostProcessingToResults($this->model, $appendTree, $relationFieldTree);
    }

    /**
     * Get the configuration, as of the current build.
     */
    public function getConfig(): QueryWizardConfig
    {
        return $this->configSnapshot($this->config);
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

    /**
     * Clear what the last configuration resolved; refused once process() has run.
     */
    protected function invalidateBuild(): void
    {
        if ($this->processed) {
            throw new \LogicException(
                'ModelQueryWizard cannot be reconfigured after process() has been called. '
                .'Create a new wizard instance for a different configuration.'
            );
        }

        $this->invalidateIncludeCache();
        $this->resetSafeRelationSelectState();
        $this->forgetConfigurationMemo();
        $this->validatedRequest = null;
    }

    /**
     * Get the schema instance.
     */
    public function getSchema(): ?ResourceSchemaInterface
    {
        return $this->schema;
    }

    protected function resolveAppendAccessorModel(string $relationPath): ?Model
    {
        return $relationPath === ''
            ? $this->model
            : (new RelationResolver($this->model))->resolve($relationPath)?->getRelated();
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
        return $this->resolveDefaultResourceKey($this->model);
    }
}
