<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
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

    /** @var array<IncludeInterface|string> */
    protected array $allowedIncludes = [];

    protected bool $allowedIncludesExplicitlySet = false;

    /** @var array<string> */
    protected array $disallowedIncludes = [];

    /** @var array<string> */
    protected array $defaultIncludes = [];

    /** @var array<string> */
    protected array $allowedFields = [];

    protected bool $allowedFieldsExplicitlySet = false;

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

        return $this;
    }

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

    protected function cleanUnwantedRelations(): void
    {
        if (! $this->allowedIncludesExplicitlySet && $this->schema === null) {
            return;
        }

        $allowedIncludes = $this->getEffectiveIncludes();
        $allowedTree = $this->buildAllowedTree($allowedIncludes);
        $this->cleanRelationsWithTree($this->model, $allowedTree);
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
     * @param  array<string, mixed>  $allowedTree
     */
    protected function cleanRelationsWithTree(Model $model, array $allowedTree): void
    {
        foreach (array_keys($model->getRelations()) as $relationName) {
            if (! isset($allowedTree[$relationName])) {
                $model->unsetRelation($relationName);

                continue;
            }

            $nestedAllowed = $allowedTree[$relationName];
            $relatedData = $model->getRelation($relationName);

            if ($relatedData instanceof Collection) {
                $relatedData->each(fn ($item) => $this->cleanRelationsWithTree($item, $nestedAllowed));
            } elseif ($relatedData instanceof Model) {
                $this->cleanRelationsWithTree($relatedData, $nestedAllowed);
            }
        }
    }

    protected function loadMissingIncludes(): void
    {
        $requested = $this->getMergedRequestedIncludes();
        $allowedIncludes = $this->getEffectiveIncludes();
        $loaded = array_keys($this->model->getRelations());

        $allowedIndex = $this->buildIncludesIndex($allowedIncludes);

        if (! empty($requested)) {
            $allowedIncludeNames = array_keys($allowedIndex);
            $invalidIncludes = array_diff($requested, $allowedIncludeNames);
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

        if (! empty($relationsToLoad)) {
            $this->model->loadMissing($relationsToLoad);
        }
        if (! empty($countsToLoad)) {
            $this->model->loadCount($countsToLoad);
        }
    }

    protected function hideDisallowedFields(): void
    {
        $resourceKey = $this->getResourceKey();
        $requestedFields = $this->parameters->getFields()->get($resourceKey, []);

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

        $allAttributes = array_keys($this->model->getAttributes());
        $fieldsToHide = array_diff($allAttributes, $visibleFields);

        if (! empty($fieldsToHide)) {
            $this->model->makeHidden(array_values($fieldsToHide));
        }

        $this->hideFieldsOnRelations();
    }

    /**
     * Hide fields on loaded relations based on requested fields.
     *
     * Only processes relations that are:
     * 1. Already loaded on the model
     * 2. Have field restrictions defined in the request
     *
     * Relations not matching these criteria are silently skipped.
     * Wildcard ('*') in fields means all fields are visible.
     */
    protected function hideFieldsOnRelations(): void
    {
        $allFields = $this->parameters->getFields();

        foreach ($this->model->getRelations() as $relationName => $relatedData) {
            $relationFields = $allFields->get($relationName, []);

            if (empty($relationFields) || in_array('*', $relationFields)) {
                continue;
            }

            if ($relatedData instanceof Collection) {
                $relatedData->each(fn (Model $item) => $this->hideFieldsOnModel($item, $relationFields));
            } elseif ($relatedData instanceof Model) {
                $this->hideFieldsOnModel($relatedData, $relationFields);
            }
        }
    }

    /**
     * @param  array<string>  $visibleFields
     */
    protected function hideFieldsOnModel(Model $model, array $visibleFields): void
    {
        $allAttributes = array_keys($model->getAttributes());
        $fieldsToHide = array_diff($allAttributes, $visibleFields);

        if (! empty($fieldsToHide)) {
            $model->makeHidden(array_values($fieldsToHide));
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
