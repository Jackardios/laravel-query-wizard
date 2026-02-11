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
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Support\RelationResolver;

/**
 * Builds a reusable "safe relation select" plan.
 *
 * The plan constrains eager-load queries for relation sparse fields while
 * auto-injecting technical keys required by Eloquent relation matching.
 * Only relation types with predictable key requirements are optimized.
 */
trait HandlesSafeRelationSelect
{
    use RequiresWizardContext;

    /** @var array<string, array<string>> */
    protected array $safeRelationSelectColumnsByPath = [];

    /** @var array<string> */
    protected array $safeRootRequiredFields = [];

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

    /**
     * @return array<IncludeInterface>
     */
    abstract protected function getEffectiveIncludes(): array;

    /**
     * @param  array<IncludeInterface>  $effectiveIncludes
     * @return array<string, string>
     */
    abstract protected function buildIncludeNameToPathMap(array $effectiveIncludes): array;

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

        $paths = $this->normalizeRelationPaths($requestedRelationshipPaths);
        if (empty($paths)) {
            return;
        }

        $resolver = new RelationResolver($rootModel);
        $pathIndex = array_fill_keys($paths, true);
        $appendPathIndex = array_fill_keys($this->resolveRequestedAppendRelationPaths(), true);

        $this->computeRootRequiredFields($paths, $resolver);
        $this->computeRelationSelectColumns($paths, $pathIndex, $appendPathIndex, $resolver);
    }

    /**
     * Normalize and deduplicate relation paths.
     *
     * @param  array<string>  $paths
     * @return array<string>
     */
    protected function normalizeRelationPaths(array $paths): array
    {
        return array_values(array_unique(array_filter(
            $paths,
            static fn (mixed $path): bool => is_string($path) && $path !== ''
        )));
    }

    /**
     * Compute required FK columns for root model.
     *
     * @param  array<string>  $paths
     */
    protected function computeRootRequiredFields(array $paths, RelationResolver $resolver): void
    {
        $topLevelRelations = [];
        foreach ($paths as $path) {
            $topLevelRelations[Str::before($path, '.')] = true;
        }

        foreach (array_keys($topLevelRelations) as $topLevelPath) {
            $relation = $resolver->resolve($topLevelPath);
            if ($relation === null) {
                continue;
            }

            $this->appendColumns($this->safeRootRequiredFields, $this->resolveParentRequiredColumns($relation), true);
        }
    }

    /**
     * Compute SELECT columns for each relation path.
     *
     * @param  array<string>  $paths
     * @param  array<string, bool>  $pathIndex
     * @param  array<string, bool>  $appendPathIndex
     */
    protected function computeRelationSelectColumns(
        array $paths,
        array $pathIndex,
        array $appendPathIndex,
        RelationResolver $resolver
    ): void {
        foreach ($this->buildValidatedRelationFieldMap() as $relationPath => $fields) {
            if (! $this->shouldComputeSelectForPath($relationPath, $fields, $pathIndex, $appendPathIndex)) {
                continue;
            }

            $relation = $resolver->resolve($relationPath);
            if ($relation === null || ! $this->isSafeRelationSelectable($relation)) {
                continue;
            }

            if ($this->relationHasModelAppends($relation)) {
                continue;
            }

            $columns = $this->buildRelationColumns($fields, $relationPath, $paths, $relation, $resolver);
            if (! empty($columns)) {
                $this->safeRelationSelectColumnsByPath[$relationPath] = $columns;
            }
        }
    }

    /**
     * Check if SELECT should be computed for this path.
     *
     * @param  array<string>  $fields
     * @param  array<string, bool>  $pathIndex
     * @param  array<string, bool>  $appendPathIndex
     */
    protected function shouldComputeSelectForPath(
        string $relationPath,
        array $fields,
        array $pathIndex,
        array $appendPathIndex
    ): bool {
        if (! isset($pathIndex[$relationPath])) {
            return false;
        }

        if (in_array('*', $fields, true)) {
            return false;
        }

        return ! isset($appendPathIndex[$relationPath]);
    }

    /**
     * Build columns array for a relation.
     *
     * @param  array<string>  $fields
     * @param  array<string>  $allPaths
     * @param  Relation<Model, Model, mixed>  $relation
     * @return array<string>
     */
    protected function buildRelationColumns(
        array $fields,
        string $relationPath,
        array $allPaths,
        Relation $relation,
        RelationResolver $resolver
    ): array {
        $columns = [];
        $this->appendColumns($columns, $fields);
        $this->appendColumns($columns, $this->resolveRelatedRequiredColumns($relation), true);

        foreach ($this->collectDirectChildRelationPaths($relationPath, $allPaths) as $childPath) {
            $childRelation = $resolver->resolve($childPath);
            if ($childRelation !== null) {
                $this->appendColumns($columns, $this->resolveParentRequiredColumns($childRelation), true);
            }
        }

        return $columns;
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
        $paths = [];
        $requestedAppends = $this->getParametersManager()->getAppends();
        $useDefaults = $requestedAppends->isEmpty();

        $grouped = [];
        if ($useDefaults) {
            foreach ($this->getEffectiveDefaultAppends() as $appendPath) {
                if (! is_string($appendPath)) {
                    continue;
                }

                $relationKey = Str::contains($appendPath, '.')
                    ? Str::beforeLast($appendPath, '.')
                    : '';
                $appendName = Str::contains($appendPath, '.')
                    ? Str::afterLast($appendPath, '.')
                    : $appendPath;

                $grouped[$relationKey][] = $appendName;
            }
        } else {
            $grouped = $requestedAppends->all();
        }

        if (empty($grouped)) {
            return [];
        }

        $includeNameToPathMap = $this->buildIncludeNameToPathMap($this->getEffectiveIncludes());

        foreach ($grouped as $requestedKey => $appends) {
            $requestedKey = (string) $requestedKey;
            if ($requestedKey === '' || empty($appends)) {
                continue;
            }

            $relationPath = $includeNameToPathMap[$requestedKey] ?? null;
            if ($relationPath !== null) {
                $paths[$relationPath] = true;
            }
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
