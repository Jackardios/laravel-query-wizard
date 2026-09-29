<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Concerns;

use Illuminate\Database\Eloquent\Model;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Support\RelationResolver;
use Jackardios\QueryWizard\Support\SafeRelationSelect;

/**
 * Picks the relation fieldsets that eager-load queries may be narrowed to.
 *
 * @internal
 */
trait HandlesSafeRelationSelect
{
    use RequiresWizardContext;

    /**
     * Build validated relation sparse-fields map from request.
     *
     * @return array<string, array<string>>
     */
    abstract protected function buildValidatedRelationFieldMap(): array;

    /**
     * @return array<string, array<string>>
     */
    abstract protected function parseDefaultAppendsToGrouped(): array;

    /**
     * @return array<IncludeInterface>
     */
    abstract protected function getIncludesInUse(): array;

    /**
     * @param  array<IncludeInterface>  $effectiveIncludes
     * @return array<string, string>
     */
    abstract protected function buildIncludeNameToPathMap(array $effectiveIncludes): array;

    /**
     * The validated relation fieldsets of the current build.
     *
     * @return array<string, array<string>>
     */
    protected function validatedRelationFieldMap(): array
    {
        return $this->buildValidatedRelationFieldMap();
    }

    /**
     * Validated fieldsets of the requested relation paths that may be narrowed:
     * not `*`, and without appends requested on the relation.
     *
     * @param  array<string>  $paths
     * @param  array<string, array<string>>|null  $fieldMap  The validated relation fieldsets, when already at hand
     * @return array<string, array<string>>
     */
    protected function safeRelationFieldsByPath(array $paths, ?array $fieldMap = null): array
    {
        if ($paths === []) {
            return [];
        }

        $fieldMap ??= $this->validatedRelationFieldMap();
        if ($fieldMap === []) {
            return [];
        }

        $pathIndex = array_fill_keys($paths, true);
        $appendPathIndex = array_fill_keys($this->resolveRequestedAppendRelationPaths(), true);
        $result = [];

        foreach ($fieldMap as $path => $fields) {
            if (isset($pathIndex[$path]) && ! isset($appendPathIndex[$path]) && ! in_array('*', $fields, true)) {
                $result[$path] = $fields;
            }
        }

        return $result;
    }

    /**
     * Narrow an eager-load relation query to the fieldset and the keys it needs.
     *
     * @param  array<string>  $fields
     */
    protected function applyLazySafeRelationSelect(mixed $query, array $fields): void
    {
        SafeRelationSelect::apply($query, $fields);
    }

    /**
     * The resolver for relations of the given root model.
     *
     * A wizard that resolves the same relations elsewhere during a build can
     * return a shared resolver, so each relation is built once.
     */
    protected function relationResolverFor(Model $rootModel): RelationResolver
    {
        return new RelationResolver($rootModel);
    }

    /**
     * @return array<string>
     */
    protected function resolveRequestedAppendRelationPaths(): array
    {
        $paths = [];
        $parameters = $this->getParametersManager();
        $grouped = $parameters->hasSimpleParameter('appends')
            ? $parameters->getAppends()->all()
            : $this->parseDefaultAppendsToGrouped();

        if (empty($grouped)) {
            return [];
        }

        $includeNameToPathMap = $this->buildIncludeNameToPathMap($this->getIncludesInUse());

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
}
