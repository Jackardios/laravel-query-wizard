<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Exceptions\InvalidFieldQuery;
use Jackardios\QueryWizard\QueryParametersManager;
use Jackardios\QueryWizard\Schema\ResourceSchemaInterface;

/**
 * Shared field handling logic for query wizards.
 */
trait HandlesFields
{
    /** @var array<string> */
    protected array $allowedFields = [];

    protected bool $allowedFieldsExplicitlySet = false;

    /** @var array<string> */
    protected array $disallowedFields = [];

    /**
     * Get the configuration instance.
     */
    abstract protected function getConfig(): QueryWizardConfig;

    /**
     * Get the parameters manager.
     */
    abstract protected function getParametersManager(): QueryParametersManager;

    /**
     * Get the schema instance.
     */
    abstract protected function getSchema(): ?ResourceSchemaInterface;

    /**
     * Get the resource key for sparse fieldsets.
     */
    abstract public function getResourceKey(): string;

    /**
     * @return array<IncludeInterface>
     */
    abstract protected function getEffectiveIncludes(): array;

    /**
     * Get effective fields.
     *
     * If allowedFields() was called explicitly, use those (even if empty).
     * Otherwise, fall back to schema fields (if any).
     * Empty result means client cannot request specific fields.
     * Use ['*'] to allow any fields requested by client.
     *
     * @return array<string>
     */
    protected function getEffectiveFields(): array
    {
        $fields = $this->allowedFieldsExplicitlySet
            ? $this->allowedFields
            : ($this->getSchema()?->fields($this) ?? []);

        return $this->removeDisallowedStrings($fields, $this->disallowedFields);
    }

    /**
     * Get requested fields for the current resource.
     *
     * Supports both:
     * - Resource-keyed format: ?fields[user]=id,name
     * - Root shorthand: ?fields=id,name
     *
     * If both are present, the resource-keyed value takes precedence.
     *
     * @return array<string>
     */
    protected function getRequestedFieldsForResource(string $resourceKey): array
    {
        $requestedFields = $this->getParametersManager()->getFields();

        $resourceFields = $requestedFields->get($resourceKey);
        if (is_array($resourceFields)) {
            return $resourceFields;
        }

        $rootFields = $requestedFields->get('');

        return is_array($rootFields) ? $rootFields : [];
    }

    /**
     * Build validated relation field map from request.
     *
     * @return array<string, array<string>>
     */
    protected function buildValidatedRelationFieldMap(): array
    {
        $requestedRelationFields = $this->getRequestedRelationFields();
        if (empty($requestedRelationFields)) {
            return [];
        }

        $allowedFields = $this->getEffectiveFields();
        $allFieldsAllowed = in_array('*', $allowedFields, true);
        $relationshipAliasMap = $this->buildRelationshipAliasMap();
        $allowedGroups = $this->buildAllowedRelationFieldGroups($allowedFields, $relationshipAliasMap);
        $exceptionsDisabled = $this->getConfig()->isInvalidFieldQueryExceptionDisabled();

        if (! $allFieldsAllowed && empty($allowedGroups)) {
            if (! $exceptionsDisabled) {
                throw InvalidFieldQuery::fieldsNotAllowed(
                    collect($this->flattenRelationFieldGroups($requestedRelationFields)),
                    collect([])
                );
            }

            return [];
        }

        $allowedRelationFields = $allFieldsAllowed
            ? []
            : $this->flattenRelationFieldGroups($allowedGroups);
        $relationFieldMap = [];

        foreach ($requestedRelationFields as $requestedKey => $requestedFields) {
            $normalizedRequestedFields = array_values(array_unique($requestedFields));
            $relationPath = $this->resolveRelationKeyToPath(
                $requestedKey,
                $allowedGroups,
                $relationshipAliasMap,
                $allFieldsAllowed
            );

            if ($relationPath === null) {
                if (! $exceptionsDisabled) {
                    throw InvalidFieldQuery::fieldsNotAllowed(
                        collect($this->prefixGroupFields($requestedKey, $normalizedRequestedFields)),
                        collect($allowedRelationFields)
                    );
                }

                continue;
            }

            if (! $allFieldsAllowed) {
                $allowedForPath = $allowedGroups[$relationPath] ?? [];

                if (empty($allowedForPath)) {
                    if (! $exceptionsDisabled) {
                        throw InvalidFieldQuery::fieldsNotAllowed(
                            collect($this->prefixGroupFields($requestedKey, $normalizedRequestedFields)),
                            collect([])
                        );
                    }

                    continue;
                }

                $invalidFields = array_values(array_diff($normalizedRequestedFields, $allowedForPath));

                if (! empty($invalidFields)) {
                    if (! $exceptionsDisabled) {
                        throw InvalidFieldQuery::fieldsNotAllowed(
                            collect($this->prefixGroupFields($requestedKey, $invalidFields)),
                            collect($this->prefixGroupFields($requestedKey, $allowedForPath)),
                        );
                    }

                    $normalizedRequestedFields = array_values(array_intersect($normalizedRequestedFields, $allowedForPath));
                }
            }

            if (in_array('*', $normalizedRequestedFields, true)) {
                $relationFieldMap[$relationPath] = ['*'];

                continue;
            }

            if (empty($normalizedRequestedFields)) {
                continue;
            }

            $current = $relationFieldMap[$relationPath] ?? [];
            if (in_array('*', $current, true)) {
                continue;
            }

            $relationFieldMap[$relationPath] = array_values(array_unique(array_merge(
                $current,
                $normalizedRequestedFields
            )));
        }

        return $relationFieldMap;
    }

    /**
     * @return array<string, array<string>>
     */
    protected function getRequestedRelationFields(): array
    {
        $requestedFields = $this->getParametersManager()
            ->getFields()
            ->except([$this->getResourceKey(), '']);

        $result = [];

        foreach ($requestedFields as $requestedKey => $fields) {
            $normalized = array_values(array_filter(
                $fields,
                static fn (mixed $field): bool => is_string($field) && $field !== ''
            ));

            if (! empty($normalized)) {
                $result[(string) $requestedKey] = $normalized;
            }
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    protected function buildRelationshipAliasMap(): array
    {
        $map = [];

        foreach ($this->getEffectiveIncludes() as $include) {
            if ($include->getType() !== 'relationship') {
                continue;
            }

            $map[$include->getName()] = $include->getRelation();
        }

        return $map;
    }

    /**
     * @param  array<string>  $allowedFields
     * @param  array<string, string>  $relationshipAliasMap
     * @return array<string, array<string>>
     */
    protected function buildAllowedRelationFieldGroups(array $allowedFields, array $relationshipAliasMap): array
    {
        $groups = [];

        foreach ($allowedFields as $allowedField) {
            if (! str_contains($allowedField, '.')) {
                continue;
            }

            $group = Str::beforeLast($allowedField, '.');
            $field = Str::afterLast($allowedField, '.');

            if ($field === '') {
                continue;
            }

            if (isset($relationshipAliasMap[$group])) {
                $group = $relationshipAliasMap[$group];
            }

            $groups[$group][] = $field;
        }

        foreach ($groups as $group => $fields) {
            $groups[$group] = array_values(array_unique($fields));
        }

        return $groups;
    }

    /**
     * Resolve request key (relation name/path/alias) to canonical relation path.
     *
     * @param  array<string, array<string>>  $allowedGroups
     * @param  array<string, string>  $relationshipAliasMap
     */
    protected function resolveRelationKeyToPath(
        string $requestedKey,
        array $allowedGroups,
        array $relationshipAliasMap,
        bool $allFieldsAllowed
    ): ?string {
        if (isset($relationshipAliasMap[$requestedKey])) {
            return $relationshipAliasMap[$requestedKey];
        }

        if (isset($allowedGroups[$requestedKey])) {
            return $requestedKey;
        }

        $matchedAllowedGroups = array_values(array_filter(
            array_keys($allowedGroups),
            static fn (string $group): bool => Str::afterLast($group, '.') === $requestedKey
        ));
        $matchedAllowedGroups = array_values(array_unique($matchedAllowedGroups));

        if (count($matchedAllowedGroups) === 1) {
            return $matchedAllowedGroups[0];
        }

        $mappedRelationPaths = array_values(array_unique(array_values($relationshipAliasMap)));
        $matchedRelationPaths = array_values(array_filter(
            $mappedRelationPaths,
            static fn (string $path): bool => Str::afterLast($path, '.') === $requestedKey
        ));
        $matchedRelationPaths = array_values(array_unique($matchedRelationPaths));

        if (count($matchedRelationPaths) === 1) {
            return $matchedRelationPaths[0];
        }

        if ($allFieldsAllowed) {
            return $requestedKey;
        }

        return null;
    }

    /**
     * @param  array<string>  $fields
     * @return array<string>
     */
    protected function prefixGroupFields(string $group, array $fields): array
    {
        return array_map(
            static fn (string $field): string => $group.'.'.$field,
            $fields
        );
    }

    /**
     * Flatten grouped relation fields to dotted notation list.
     *
     * @param  array<string, array<string>>  $groups
     * @return array<string>
     */
    protected function flattenRelationFieldGroups(array $groups): array
    {
        $flattened = [];
        $seen = [];

        foreach ($groups as $group => $fields) {
            foreach ($fields as $field) {
                $value = $group.'.'.$field;
                if (isset($seen[$value])) {
                    continue;
                }

                $seen[$value] = true;
                $flattened[] = $value;
            }
        }

        return $flattened;
    }

    /**
     * Build relation field tree for targeted recursive traversal.
     *
     * @param  array<string, array<string>>  $relationFieldMap
     * @return array{fields: array<string>, relations: array<string, mixed>}
     */
    protected function buildRelationFieldTree(array $relationFieldMap): array
    {
        $tree = [
            'fields' => [],
            'relations' => [],
        ];

        foreach ($relationFieldMap as $relationPath => $fields) {
            if ($relationPath === '') {
                continue;
            }

            $segments = explode('.', $relationPath);

            /** @var array{fields: array<string>, relations: array<string, mixed>} $node */
            $node = &$tree;

            foreach ($segments as $segment) {
                if (! isset($node['relations'][$segment])) {
                    $node['relations'][$segment] = [
                        'fields' => [],
                        'relations' => [],
                    ];
                }

                /** @var array{fields: array<string>, relations: array<string, mixed>} $node */
                $node = &$node['relations'][$segment];
            }

            if (in_array('*', $fields, true)) {
                $node['fields'] = ['*'];
                unset($node);

                continue;
            }

            if (in_array('*', $node['fields'], true)) {
                unset($node);

                continue;
            }

            foreach ($fields as $field) {
                if (! in_array($field, $node['fields'], true)) {
                    $node['fields'][] = $field;
                }
            }

            unset($node);
        }

        return $tree;
    }

    /**
     * Hide all model attributes except explicitly visible ones.
     *
     * @param  array<string>  $visibleFields
     */
    protected function hideModelAttributesExcept(Model $model, array $visibleFields): void
    {
        $attributeKeys = array_keys($model->getAttributes());
        if (empty($attributeKeys)) {
            return;
        }

        $visibleFieldsMap = array_flip($visibleFields);
        $fieldsToHide = array_filter(
            $attributeKeys,
            static fn (string $key): bool => ! isset($visibleFieldsMap[$key])
        );

        if (! empty($fieldsToHide)) {
            $model->makeHidden($fieldsToHide);
        }
    }
}
