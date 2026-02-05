<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Jackardios\QueryWizard\Concerns\HandlesConfiguration;
use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Contracts\QueryWizardInterface;
use Jackardios\QueryWizard\Eloquent\Includes\RelationshipInclude;
use Jackardios\QueryWizard\Exceptions\InvalidAppendQuery;
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
    use HandlesConfiguration;

    protected Model $model;
    protected QueryParametersManager $parameters;
    protected QueryWizardConfig $config;
    protected ?ResourceSchemaInterface $schema = null;

    // Configuration
    /** @var array<IncludeInterface|string> */
    protected array $allowedIncludes = [];
    protected bool $allowedIncludesExplicitlySet = false;
    /** @var array<string> */
    protected array $disallowedIncludes = [];
    /** @var array<string> */
    protected array $defaultIncludes = [];
    /** @var array<string> */
    protected array $allowedFields = [];
    /** @var array<string> */
    protected array $disallowedFields = [];
    /** @var array<string> */
    protected array $allowedAppends = [];
    protected bool $allowedAppendsExplicitlySet = false;
    /** @var array<string> */
    protected array $disallowedAppends = [];
    /** @var array<string> */
    protected array $defaultAppends = [];

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
        return new static($model);
    }

    /**
     * Create a wizard from a resource schema.
     *
     * @param class-string<ResourceSchemaInterface>|ResourceSchemaInterface $schema
     */
    public static function forSchema(string|ResourceSchemaInterface $schema, Model $model): static
    {
        $schema = is_string($schema) ? app($schema) : $schema;

        return new static(
            $model,
            app(QueryParametersManager::class),
            app(QueryWizardConfig::class),
            $schema
        );
    }

    // === Configuration API ===

    /**
     * Set allowed includes.
     *
     * @param IncludeInterface|string|array<IncludeInterface|string> ...$includes
     */
    public function allowedIncludes(IncludeInterface|string|array ...$includes): static
    {
        $this->allowedIncludes = $this->flattenDefinitions($includes);
        $this->allowedIncludesExplicitlySet = true;
        return $this;
    }

    /**
     * Set disallowed includes.
     *
     * @param string|array<string> ...$names
     */
    public function disallowedIncludes(string|array ...$names): static
    {
        $this->disallowedIncludes = $this->flattenStringArray($names);
        return $this;
    }

    /**
     * Set default includes.
     *
     * @param string|array<string> ...$names
     */
    public function defaultIncludes(string|array ...$names): static
    {
        $this->defaultIncludes = $this->flattenStringArray($names);
        return $this;
    }

    /**
     * Set allowed fields.
     *
     * @param string|array<string> ...$fields
     */
    public function allowedFields(string|array ...$fields): static
    {
        $this->allowedFields = $this->flattenStringArray($fields);
        return $this;
    }

    /**
     * Set disallowed fields.
     *
     * @param string|array<string> ...$names
     */
    public function disallowedFields(string|array ...$names): static
    {
        $this->disallowedFields = $this->flattenStringArray($names);
        return $this;
    }

    /**
     * Set allowed appends.
     *
     * @param string|array<string> ...$appends
     */
    public function allowedAppends(string|array ...$appends): static
    {
        $this->allowedAppends = $this->flattenStringArray($appends);
        $this->allowedAppendsExplicitlySet = true;
        return $this;
    }

    /**
     * Set disallowed appends.
     *
     * @param string|array<string> ...$names
     */
    public function disallowedAppends(string|array ...$names): static
    {
        $this->disallowedAppends = $this->flattenStringArray($names);
        return $this;
    }

    /**
     * Set default appends.
     *
     * @param string|array<string> ...$appends
     */
    public function defaultAppends(string|array ...$appends): static
    {
        $this->defaultAppends = $this->flattenStringArray($appends);
        return $this;
    }

    // === Execution ===

    /**
     * Process the model (apply includes, fields, appends).
     */
    public function process(): Model
    {
        $this->cleanUnwantedRelations();
        $this->loadMissingIncludes();
        $this->hideDisallowedFields();
        $this->applyAppends();

        return $this->model;
    }

    /**
     * Get the model instance.
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    // === Protected: Clean Relations ===

    protected function cleanUnwantedRelations(): void
    {
        $allowedIncludes = $this->getEffectiveIncludes();
        $allowedTree = $this->buildAllowedTree($allowedIncludes);
        $this->cleanRelationsWithTree($this->model, $allowedTree);
    }

    /**
     * Build tree from includes for nested checking.
     *
     * @param array<IncludeInterface> $includes
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
     * Recursively clean relations using allowed tree.
     *
     * @param array<string, mixed> $allowedTree
     */
    protected function cleanRelationsWithTree(Model $model, array $allowedTree): void
    {
        foreach (array_keys($model->getRelations()) as $relationName) {
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

    // === Protected: Load Missing ===

    protected function loadMissingIncludes(): void
    {
        $requested = $this->getMergedRequestedIncludes();
        $allowedIncludes = $this->getEffectiveIncludes();
        $loaded = array_keys($this->model->getRelations());

        // Build index for lookup
        $allowedIndex = [];
        foreach ($allowedIncludes as $include) {
            $allowedIndex[$include->getName()] = $include;
        }

        // Also add implicit count includes for relationships (consistent with BaseQueryWizard)
        $countSuffix = $this->config->getCountSuffix();
        foreach ($allowedIncludes as $include) {
            if ($include->getType() === 'relationship') {
                $countName = $include->getRelation() . $countSuffix;
                if (!isset($allowedIndex[$countName])) {
                    $countInclude = RelationshipInclude::fromString($countName, $countSuffix);
                    $allowedIndex[$countName] = $countInclude;
                }
            }
        }

        // Validate includes when explicitly set
        if ($this->allowedIncludesExplicitlySet && !empty($requested)) {
            $allowedIncludeNames = array_keys($allowedIndex);
            $invalidIncludes = array_diff($requested, $allowedIncludeNames);
            if (!empty($invalidIncludes) && !$this->config->isInvalidIncludeQueryExceptionDisabled()) {
                throw InvalidIncludeQuery::includesNotAllowed(
                    collect($invalidIncludes),
                    collect($allowedIncludeNames)
                );
            }
            // Filter out invalid includes
            $requested = array_intersect($requested, $allowedIncludeNames);
        }

        $relationsToLoad = [];
        $countsToLoad = [];

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
            } elseif ($include->getType() === 'relationship') {
                $relationsToLoad[] = $include->getRelation();
            }
        }

        if (!empty($relationsToLoad)) {
            $this->model->loadMissing($relationsToLoad);
        }
        if (!empty($countsToLoad)) {
            $this->model->loadCount($countsToLoad);
        }
    }

    /**
     * Get merged requested includes (defaults + request).
     *
     * @return array<string>
     */
    protected function getMergedRequestedIncludes(): array
    {
        $defaults = $this->getEffectiveDefaultIncludes();
        $requested = $this->parameters->getIncludes()->all();
        return array_unique(array_merge($defaults, $requested));
    }

    // === Protected: Hide Fields ===

    protected function hideDisallowedFields(): void
    {
        $resourceKey = $this->getResourceKey();
        $requestedFields = $this->parameters->getFields()->get($resourceKey, []);

        // No requested fields = allow all
        if (empty($requestedFields) || in_array('*', $requestedFields)) {
            return;
        }

        $allowedFields = $this->getEffectiveFields();

        // Filter requested by allowed
        $visibleFields = in_array('*', $allowedFields)
            ? $requestedFields
            : array_intersect($requestedFields, $allowedFields);

        $allAttributes = array_keys($this->model->getAttributes());
        $fieldsToHide = array_diff($allAttributes, $visibleFields);

        if (!empty($fieldsToHide)) {
            $this->model->makeHidden(array_values($fieldsToHide));
        }

        $this->hideFieldsOnRelations();
    }

    protected function hideFieldsOnRelations(): void
    {
        $allFields = $this->parameters->getFields();

        foreach ($this->model->getRelations() as $relationName => $relatedData) {
            $relationFields = $allFields->get($relationName, []);

            if (empty($relationFields) || in_array('*', $relationFields)) {
                continue;
            }

            if ($relatedData instanceof Collection) {
                $relatedData->each(fn(Model $item) => $this->hideFieldsOnModel($item, $relationFields));
            } elseif ($relatedData instanceof Model) {
                $this->hideFieldsOnModel($relatedData, $relationFields);
            }
        }
    }

    /**
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

    // === Protected: Apply Appends ===

    protected function applyAppends(): void
    {
        $appends = $this->getValidRequestedAppends();
        if (!empty($appends)) {
            $this->model->append($appends);
        }
    }

    // === Protected: Resolution ===

    /**
     * Get the configuration instance.
     */
    protected function getConfig(): QueryWizardConfig
    {
        return $this->config;
    }

    /**
     * Get effective includes.
     *
     * @return array<IncludeInterface>
     */
    protected function getEffectiveIncludes(): array
    {
        $includes = !empty($this->allowedIncludes)
            ? $this->allowedIncludes
            : ($this->schema?->includes($this) ?? []);

        $includes = $this->normalizeIncludes($includes);
        return $this->removeDisallowedIncludes($includes, $this->disallowedIncludes);
    }

    /**
     * Normalize mixed includes array to IncludeInterface[].
     *
     * @param array<IncludeInterface|string> $includes
     * @return array<IncludeInterface>
     */
    protected function normalizeIncludes(array $includes): array
    {
        $countSuffix = $this->config->getCountSuffix();

        $result = [];
        foreach ($includes as $include) {
            if (is_string($include)) {
                $include = RelationshipInclude::fromString($include, $countSuffix);
            }

            // For count includes without alias, auto-apply count suffix
            if ($include->getType() === 'count' && $include->getAlias() === null) {
                $include = $include->alias($include->getRelation() . $countSuffix);
            }

            $result[] = $include;
        }
        return $result;
    }

    /**
     * Remove disallowed includes.
     *
     * @param array<IncludeInterface> $includes
     * @param array<string> $disallowed
     * @return array<IncludeInterface>
     */
    protected function removeDisallowedIncludes(array $includes, array $disallowed): array
    {
        return $this->removeDisallowedByName(
            $includes,
            $disallowed,
            static fn(IncludeInterface $i) => $i->getName(),
            $this->config->getCountSuffix()
        );
    }

    /**
     * Get effective default includes.
     *
     * @return array<string>
     */
    protected function getEffectiveDefaultIncludes(): array
    {
        return !empty($this->defaultIncludes)
            ? $this->defaultIncludes
            : ($this->schema?->defaultIncludes($this) ?? []);
    }

    /**
     * Get effective fields.
     *
     * @return array<string>
     */
    protected function getEffectiveFields(): array
    {
        $fields = !empty($this->allowedFields)
            ? $this->allowedFields
            : ($this->schema?->fields($this) ?? ['*']);

        return $this->removeDisallowedStrings($fields, $this->disallowedFields);
    }

    /**
     * Get effective appends (allowed appends after removing disallowed).
     *
     * @return array<string>
     */
    protected function getEffectiveAppends(): array
    {
        $appends = !empty($this->allowedAppends)
            ? $this->allowedAppends
            : ($this->schema?->appends($this) ?? []);

        return $this->removeDisallowedStrings($appends, $this->disallowedAppends);
    }

    /**
     * Get effective default appends.
     *
     * @return array<string>
     */
    protected function getEffectiveDefaultAppends(): array
    {
        return !empty($this->defaultAppends)
            ? $this->defaultAppends
            : ($this->schema?->defaultAppends($this) ?? []);
    }

    /**
     * Get valid requested appends (defaults + validated requested).
     *
     * @return array<string>
     */
    protected function getValidRequestedAppends(): array
    {
        $allowed = $this->getEffectiveAppends();
        $requested = $this->parameters->getAppends()->all();
        $defaults = $this->getEffectiveDefaultAppends();

        // Validate appends when explicitly set
        if ($this->allowedAppendsExplicitlySet && !empty($requested)) {
            $invalidAppends = array_diff($requested, $allowed);
            if (!empty($invalidAppends) && !$this->config->isInvalidAppendQueryExceptionDisabled()) {
                throw InvalidAppendQuery::appendsNotAllowed(
                    collect($invalidAppends),
                    collect($allowed)
                );
            }
        }

        $validRequested = array_intersect($requested, $allowed);

        return array_unique(array_merge($defaults, $validRequested));
    }

    /**
     * Get resource key for sparse fieldsets.
     */
    protected function getResourceKey(): string
    {
        if ($this->schema !== null) {
            return $this->schema->type();
        }
        return Str::camel(class_basename($this->model));
    }

}
