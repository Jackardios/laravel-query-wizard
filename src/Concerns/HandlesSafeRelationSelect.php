<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\QueryParametersManager;

/**
 * Builds a reusable "safe relation select" plan.
 *
 * The plan constrains eager-load queries for relation sparse fields while
 * auto-injecting technical keys required by Eloquent relation matching.
 * Only relation types with predictable key requirements are optimized.
 */
trait HandlesSafeRelationSelect
{
    /** @var array<string, array<string>> */
    protected array $safeRelationSelectColumnsByPath = [];

    /** @var array<string> */
    protected array $safeRootRequiredFields = [];

    /**
     * Get the configuration instance.
     */
    abstract protected function getConfig(): QueryWizardConfig;

    /**
     * Get the parameters manager.
     */
    abstract protected function getParametersManager(): QueryParametersManager;

    /**
     * Build validated relation sparse-fields map from request.
     *
     * @return array<string, array<string>>
     */
    abstract protected function buildValidatedRelationFieldMap(): array;

    /**
     * Get effective default appends.
     *
     * @return array<string>
     */
    abstract protected function getEffectiveDefaultAppends(): array;

    protected function resetSafeRelationSelectState(): void
    {
        $this->safeRelationSelectColumnsByPath = [];
        $this->safeRootRequiredFields = [];
    }

    /**
     * @param  array<string>  $fields
     * @return array<string>
     */
    protected function applySafeRootFieldRequirements(array $fields): array
    {
        if (empty($fields) || in_array('*', $fields, true) || empty($this->safeRootRequiredFields)) {
            return $fields;
        }

        $result = $fields;

        foreach ($this->safeRootRequiredFields as $requiredField) {
            if (! in_array($requiredField, $result, true)) {
                $result[] = $requiredField;
            }
        }

        return $result;
    }

    /**
     * @return array<string>|null
     */
    protected function getSafeRelationSelectColumns(string $relationPath): ?array
    {
        return $this->safeRelationSelectColumnsByPath[$relationPath] ?? null;
    }

    /**
     * Prepare safe relation-select plan from validated relationship includes.
     *
     * @param  array<string>  $requestedRelationshipPaths
     */
    protected function prepareSafeRelationSelectPlan(Model $rootModel, array $requestedRelationshipPaths): void
    {
        $this->resetSafeRelationSelectState();

        if (! $this->getConfig()->isSafeRelationSelectEnabled()) {
            return;
        }

        $requestedRelationshipPaths = array_values(array_unique(array_filter(
            $requestedRelationshipPaths,
            static fn (mixed $path): bool => is_string($path) && $path !== ''
        )));

        if (empty($requestedRelationshipPaths)) {
            return;
        }

        $requestedRelationshipPathIndex = array_fill_keys($requestedRelationshipPaths, true);
        $requestedAppendRelationPathIndex = array_fill_keys($this->resolveRequestedAppendRelationPaths(), true);

        /** @var array<string, Relation<Model, Model, mixed>|null> $relationCache */
        $relationCache = [];

        /**
         * Resolve a relation by dot-notation path.
         *
         * Note: Assumes $rootModel is immutable during resolution.
         * Eloquent relation methods should not mutate model state.
         *
         * @return Relation<Model, Model, mixed>|null
         */
        $resolveRelation = function (string $path) use (&$relationCache, $rootModel): ?Relation {
            if (array_key_exists($path, $relationCache)) {
                return $relationCache[$path];
            }

            $model = $rootModel;
            $relation = null;

            foreach (explode('.', $path) as $segment) {
                if ($segment === '' || ! method_exists($model, $segment)) {
                    return $relationCache[$path] = null;
                }

                try {
                    $relation = $model->{$segment}();
                } catch (\Throwable) {
                    return $relationCache[$path] = null;
                }

                if (! $relation instanceof Relation) {
                    return $relationCache[$path] = null;
                }

                $model = $relation->getRelated();
            }

            return $relationCache[$path] = $relation;
        };

        $topLevelRelations = [];
        foreach ($requestedRelationshipPaths as $path) {
            $topLevelRelations[Str::before($path, '.')] = true;
        }

        foreach (array_keys($topLevelRelations) as $topLevelPath) {
            $relation = $resolveRelation($topLevelPath);
            if ($relation === null) {
                continue;
            }

            $this->appendColumns($this->safeRootRequiredFields, $this->resolveParentRequiredColumns($relation), true);
        }

        foreach ($this->buildValidatedRelationFieldMap() as $relationPath => $fields) {
            if (! isset($requestedRelationshipPathIndex[$relationPath])) {
                continue;
            }

            if (in_array('*', $fields, true)) {
                continue;
            }

            if (isset($requestedAppendRelationPathIndex[$relationPath])) {
                continue;
            }

            $relation = $resolveRelation($relationPath);
            if ($relation === null || ! $this->isSafeRelationSelectable($relation)) {
                continue;
            }

            // Skip SQL SELECT for models with built-in $appends — accessors may need all attributes
            if ($this->relationHasModelAppends($relation)) {
                continue;
            }

            $columns = [];
            $this->appendColumns($columns, $fields);
            $this->appendColumns($columns, $this->resolveRelatedRequiredColumns($relation), true);

            foreach ($this->collectDirectChildRelationPaths($relationPath, $requestedRelationshipPaths) as $childRelationPath) {
                $childRelation = $resolveRelation($childRelationPath);
                if ($childRelation === null) {
                    continue;
                }

                $this->appendColumns($columns, $this->resolveParentRequiredColumns($childRelation), true);
            }

            if (! empty($columns)) {
                $this->safeRelationSelectColumnsByPath[$relationPath] = $columns;
            }
        }
    }

    /**
     * @param  array<string>  $requestedRelationshipPaths
     * @return array<string>
     */
    protected function collectDirectChildRelationPaths(string $parentPath, array $requestedRelationshipPaths): array
    {
        $prefix = $parentPath.'.';
        $directChildren = [];

        foreach ($requestedRelationshipPaths as $path) {
            if (! Str::startsWith($path, $prefix)) {
                continue;
            }

            $remaining = Str::after($path, $prefix);
            if ($remaining === '') {
                continue;
            }

            $childSegment = Str::before($remaining, '.');
            if ($childSegment === '') {
                continue;
            }

            $directChildren[$parentPath.'.'.$childSegment] = true;
        }

        return array_keys($directChildren);
    }

    /**
     * @return array<string>
     */
    protected function resolveRequestedAppendRelationPaths(): array
    {
        $requestedAppends = array_merge(
            $this->getEffectiveDefaultAppends(),
            $this->getParametersManager()->getAppends()->all()
        );

        $paths = [];

        foreach ($requestedAppends as $appendPath) {
            if (! is_string($appendPath) || ! str_contains($appendPath, '.')) {
                continue;
            }

            $relationPath = Str::beforeLast($appendPath, '.');
            if ($relationPath === '') {
                continue;
            }

            $paths[$relationPath] = true;
        }

        return array_keys($paths);
    }

    /**
     * @param  Relation<Model, Model, mixed>  $relation
     */
    protected function isSafeRelationSelectable(Relation $relation): bool
    {
        if ($relation instanceof MorphTo) {
            return false;
        }

        return $relation instanceof BelongsTo
            || $relation instanceof HasOneOrMany;
    }

    /**
     * Check if a relation's model has built-in appends that require all attributes.
     *
     * When a model has $appends defined, accessors may depend on attributes
     * that aren't in the sparse fieldset. Using SELECT * + makeHidden is safer.
     *
     * @param  Relation<Model, Model, mixed>  $relation
     */
    protected function relationHasModelAppends(Relation $relation): bool
    {
        return ! empty($relation->getRelated()->getAppends());
    }

    /**
     * Columns that must exist on parent models to load this relation.
     *
     * @param  Relation<Model, Model, mixed>  $relation
     * @return array<string>
     */
    protected function resolveParentRequiredColumns(Relation $relation): array
    {
        if ($relation instanceof MorphTo) {
            return [
                $relation->getForeignKeyName(),
                $relation->getMorphType(),
            ];
        }

        if ($relation instanceof BelongsTo) {
            return [$relation->getForeignKeyName()];
        }

        if ($relation instanceof HasOneOrMany) {
            return [$relation->getLocalKeyName()];
        }

        if (method_exists($relation, 'getParentKeyName')) {
            return [(string) $relation->getParentKeyName()];
        }

        if (method_exists($relation, 'getLocalKeyName')) {
            return [(string) $relation->getLocalKeyName()];
        }

        return [];
    }

    /**
     * Columns that must exist in relation select for eager matching.
     *
     * @param  Relation<Model, Model, mixed>  $relation
     * @return array<string>
     */
    protected function resolveRelatedRequiredColumns(Relation $relation): array
    {
        if ($relation instanceof MorphTo) {
            return [$relation->getOwnerKeyName()];
        }

        if ($relation instanceof BelongsTo) {
            return [$relation->getOwnerKeyName()];
        }

        if ($relation instanceof MorphOneOrMany) {
            return [
                $relation->getForeignKeyName(),
                $relation->getMorphType(),
            ];
        }

        if ($relation instanceof HasOneOrMany) {
            return [$relation->getForeignKeyName()];
        }

        return [];
    }

    /**
     * @param  array<string>  $target
     * @param  array<string>  $source
     */
    protected function appendColumns(array &$target, array $source, bool $normalize = false): void
    {
        foreach ($source as $column) {
            if (! is_string($column)) {
                continue;
            }

            $column = trim($column);
            if ($column === '' || $column === '*') {
                continue;
            }

            if ($normalize) {
                $column = $this->normalizeColumnName($column);
            }

            if ($column === '' || in_array($column, $target, true)) {
                continue;
            }

            $target[] = $column;
        }
    }

    /**
     * Extract column name from qualified column (e.g., "users.id" → "id").
     *
     * Used internally to normalize FK columns returned by Eloquent relation methods
     * which may include table qualification. The table context is already known
     * at the point of use (within eager load constraints).
     */
    protected function normalizeColumnName(string $column): string
    {
        if (! str_contains($column, '.')) {
            return $column;
        }

        return Str::afterLast($column, '.');
    }
}
