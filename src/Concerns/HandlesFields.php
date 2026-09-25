<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Concerns;

use Illuminate\Database\Eloquent\Model;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Exceptions\InvalidFieldQuery;
use Jackardios\QueryWizard\Support\DotNotationTreeBuilder;
use Jackardios\QueryWizard\Support\NamePolicy;

/**
 * Shared field handling logic for query wizards.
 */
trait HandlesFields
{
    use HandlesRelationAttributeValidation;
    use RequiresWizardContext;

    /** @var array<string> */
    protected array $allowedFields = [];

    protected bool $allowedFieldsExplicitlySet = false;

    /** @var array<string> */
    protected array $disallowedFields = [];

    /** @var array<string> */
    protected array $defaultFields = [];

    protected bool $defaultFieldsExplicitlySet = false;

    /**
     * Get the resource key for sparse fieldsets.
     */
    abstract public function getResourceKey(): string;

    /**
     * @return array<IncludeInterface>
     */
    abstract protected function getEffectiveIncludes(): array;

    abstract protected function resolveAppendAccessorModel(string $relationPath): ?Model;

    /**
     * Set allowed fields.
     *
     * Empty array means all fields are forbidden.
     * Use ['*'] to allow any fields requested by client.
     * Not calling this method falls back to schema fields (if any).
     *
     * @param  string|array<string>  ...$fields
     */
    public function allowedFields(string|array ...$fields): static
    {
        $this->invalidateBuild();
        $this->allowedFields = $this->flattenStringArray($fields);
        $this->allowedFieldsExplicitlySet = true;

        return $this;
    }

    /**
     * Set disallowed fields (to override schema).
     *
     * @param  string|array<string>  ...$names
     */
    public function disallowedFields(string|array ...$names): static
    {
        $this->invalidateBuild();
        $this->disallowedFields = $this->flattenStringArray($names);

        return $this;
    }

    /**
     * Set default fields.
     *
     * Replaces the schema defaults and the `fields.use_allowed_as_default` fallback;
     * call it without arguments for no defaults (all columns).
     *
     * Applied only when request parameter is completely absent.
     *
     * @param  string|array<string>  ...$fields
     */
    public function defaultFields(string|array ...$fields): static
    {
        $this->invalidateBuild();
        $this->defaultFields = $this->flattenStringArray($fields);
        $this->defaultFieldsExplicitlySet = true;

        return $this;
    }

    /**
     * Get effective fields (what client CAN request via ?fields).
     *
     * If allowedFields() was called explicitly, use those (even if empty).
     * Otherwise, fall back to schema fields (if any).
     * Empty result means client cannot use ?fields parameter.
     * Use ['*'] to allow any fields requested by client.
     *
     * @return array<string>
     */
    protected function getEffectiveFields(): array
    {
        if ($this->allowedFieldsExplicitlySet) {
            $fields = $this->allowedFields;
        } else {
            $schemaFields = $this->getSchema()?->fields($this);
            $fields = ! empty($schemaFields) ? $schemaFields : [];
        }

        return $this->removeDisallowedStrings(
            $this->normalizePublicPaths($fields),
            $this->disallowedFields
        );
    }

    /**
     * Get effective default fields.
     *
     * When 'fields.use_allowed_as_default' is enabled, falls back to allowed fields
     * if no explicit defaults are configured. Default fields belong to the root
     * resource, so a dotted one is a configuration error.
     *
     * @return array<string>
     *
     * @throws \InvalidArgumentException When a default field names a relation field
     */
    protected function getEffectiveDefaultFields(): array
    {
        if ($this->defaultFieldsExplicitlySet) {
            return $this->rootDefaultFields($this->defaultFields);
        }

        $schemaDefaults = $this->getSchema()?->defaultFields($this);
        if (! empty($schemaDefaults)) {
            return $this->rootDefaultFields($schemaDefaults);
        }

        if ($this->getConfig()->shouldUseAllowedFieldsAsDefault()) {
            return $this->extractRootFields($this->getEffectiveFields());
        }

        return [];
    }

    /**
     * @param  array<string>  $fields
     * @return array<string>
     */
    private function rootDefaultFields(array $fields): array
    {
        $fields = $this->normalizePublicPaths($fields);

        foreach ($fields as $field) {
            if (str_contains($field, '.')) {
                throw new \InvalidArgumentException(
                    "Default field `{$field}` names a relation field. Default fields apply to the root resource only."
                );
            }
        }

        return $fields;
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
     * Determine whether the current request explicitly targets the root resource fieldset.
     */
    protected function hasRequestedFieldsForResource(string $resourceKey): bool
    {
        $requestedFields = $this->getParametersManager()->getFields();

        return $requestedFields->has($resourceKey) || $requestedFields->has('');
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
        $includeNameToPathMap = $this->buildIncludeNameToPathMap($this->getEffectiveIncludes());
        $exceptionsDisabled = $this->getConfig()->isInvalidFieldQueryExceptionDisabled();

        $allowedRelationFieldList = $allFieldsAllowed
            ? []
            : $this->extractRelationFields($allowedFields);
        $policy = NamePolicy::allowing($allowedFields);
        $denyPolicy = $this->fieldDenyPolicy();
        $caseProtectedNames = [];
        $relationFieldMap = [];

        foreach ($requestedRelationFields as $requestedKey => $requestedFields) {
            $requestedKey = (string) $requestedKey;
            $normalizedRequestedFields = array_values(array_unique($requestedFields));

            $relationPath = $includeNameToPathMap[$requestedKey] ?? null;
            if ($relationPath === null) {
                if (! $exceptionsDisabled) {
                    throw InvalidFieldQuery::fieldsNotAllowed(
                        collect($this->prefixGroupFields($requestedKey, $normalizedRequestedFields)),
                        collect($allowedRelationFieldList)
                    );
                }

                continue;
            }

            $normalizedRequestedFields = $this->withoutMalformedFieldTokens(
                $normalizedRequestedFields,
                $allowedFields,
                $policy,
                $requestedKey,
                $exceptionsDisabled
            );

            $validFields = [];
            $invalidFields = [];
            $disallowedFound = false;

            foreach ($normalizedRequestedFields as $field) {
                if (! $allFieldsAllowed && ! $policy->allowsAttribute($requestedKey, $field)) {
                    $invalidFields[] = $field;
                } elseif (
                    $this->isFieldTokenDisallowed($denyPolicy, $requestedKey, $field)
                    || $this->isCaseVariantOfProtectedField($requestedKey, $field, $relationPath, $policy, $caseProtectedNames)
                ) {
                    $invalidFields[] = $field;
                    $disallowedFound = true;
                } else {
                    $validFields[] = $field;
                }
            }

            if (! empty($invalidFields)) {
                if (! $exceptionsDisabled) {
                    throw $this->fieldsNotAllowed(
                        $this->prefixGroupFields($requestedKey, $invalidFields),
                        $allowedRelationFieldList,
                        $disallowedFound
                    );
                }

                $normalizedRequestedFields = $validFields;
            }

            if (in_array('*', $normalizedRequestedFields, true)) {
                $relationFieldMap[$relationPath] = ['*'];

                continue;
            }

            if (empty($normalizedRequestedFields)) {
                $relationFieldMap[$relationPath] ??= [];

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
            $result[(string) $requestedKey] = $normalized;
        }

        return $result;
    }

    /**
     * Extract relation fields from allowed fields list (for error messages).
     *
     * @param  array<string>  $allowedFields
     * @return array<string>
     */
    protected function extractRelationFields(array $allowedFields): array
    {
        return array_values(array_filter(
            $allowedFields,
            static fn (string $field): bool => str_contains($field, '.')
        ));
    }

    /**
     * @param  array<string>  $fields
     * @return array<string>
     */
    protected function extractRootFields(array $fields): array
    {
        return array_values(array_filter(
            $fields,
            static fn (string $field): bool => ! str_contains($field, '.')
        ));
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
     * Build relation field tree for targeted recursive traversal.
     *
     * @param  array<string, array<string>>  $relationFieldMap
     * @return array{fields: array<string>, relations: array<string, mixed>}
     */
    protected function buildRelationFieldTree(array $relationFieldMap): array
    {
        /** @var array{fields: array<string>, relations: array<string, mixed>} */
        return DotNotationTreeBuilder::build($relationFieldMap, 'fields');
    }

    /**
     * Root attributes to keep visible: the requested fields, with runtime attributes under their own names.
     *
     * @param  array<string>  $fields
     * @param  array<string, string>  $runtimeAttributesByField
     * @return array<string>
     */
    protected function visibleRootFields(array $fields, array $runtimeAttributesByField): array
    {
        $visibleFields = array_values($runtimeAttributesByField);

        foreach ($fields as $field) {
            $visibleFields[] = $runtimeAttributesByField[$this->normalizePublicPath($field)] ?? $field;
        }

        return array_values(array_unique($visibleFields));
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

    /**
     * Resolve and validate root-level fields from request.
     *
     * Returns validated fields array or null if no field filtering should be applied.
     * Throws InvalidFieldQuery if validation fails and exceptions are enabled.
     * Without a root fieldset in the request the default fields apply, even
     * when fieldsets of relations are requested.
     *
     * @return array<string>|null Validated fields or null for no filtering
     *
     * @throws InvalidFieldQuery
     */
    protected function resolveValidatedRootFields(): ?array
    {
        $resourceKey = $this->getResourceKey();
        $requestAbsent = ! $this->hasRequestedFieldsForResource($resourceKey);

        if ($requestAbsent) {
            $fields = $this->getEffectiveDefaultFields();

            if ($fields === []) {
                return null;
            }
        } else {
            $fields = $this->getRequestedFieldsForResource($resourceKey);
        }

        $allowedFields = $this->getEffectiveFields();
        $exceptionsDisabled = $this->getConfig()->isInvalidFieldQueryExceptionDisabled();
        $policy = NamePolicy::allowing($allowedFields);
        $denyPolicy = $this->fieldDenyPolicy();
        $caseProtectedNames = [];

        if (! $requestAbsent) {
            $fields = $this->withoutMalformedFieldTokens(
                $fields,
                $allowedFields,
                $policy,
                '',
                $exceptionsDisabled
            );
        }

        $validFields = [];
        $invalidFields = [];
        $disallowedFound = false;

        foreach ($fields as $field) {
            if (! $policy->allowsAttribute('', $field)) {
                if (! $requestAbsent) {
                    $invalidFields[] = $field;
                }
            } elseif (
                $this->isFieldTokenDisallowed($denyPolicy, '', $field)
                || (! $requestAbsent && $this->isCaseVariantOfProtectedField('', $field, '', $policy, $caseProtectedNames))
            ) {
                if (! $requestAbsent) {
                    $invalidFields[] = $field;
                    $disallowedFound = true;
                }
            } else {
                $validFields[] = $field;
            }
        }

        if (! empty($invalidFields) && ! $exceptionsDisabled) {
            throw $this->fieldsNotAllowed($invalidFields, $allowedFields, $disallowedFound);
        }

        // If '*' was validated as allowed, return null (all fields)
        if (in_array('*', $validFields, true)) {
            return null;
        }

        if (! empty($validFields)) {
            return $validFields;
        }

        return $requestAbsent ? null : [];
    }

    /**
     * Whether disallowedFields() denies a token the allow-list permits.
     *
     * A requested `*` is never denied: it selects the same columns as sending
     * no fieldset, and disallowed fields are not hidden then either.
     */
    private function isFieldTokenDisallowed(?NamePolicy $denyPolicy, string $group, string $field): bool
    {
        return $denyPolicy !== null
            && $field !== '*'
            && $denyPolicy->denies($this->normalizePublicPath($group === '' ? $field : "{$group}.{$field}"));
    }

    private function fieldDenyPolicy(): ?NamePolicy
    {
        return $this->disallowedFields === [] ? null : $this->denyPolicyFor($this->disallowedFields);
    }

    /**
     * Whether a token only a wildcard allows names a disallowed or hidden field in another letter case.
     *
     * MySQL matches column names without regard to case and returns them as
     * written, so `NAME` would read the `name` column past disallowedFields()
     * and the model's hidden attributes, which both compare names exactly.
     *
     * @param  array<string, array<string, true|string>>  $caseProtectedNames  Filled per fieldset on demand
     */
    private function isCaseVariantOfProtectedField(
        string $group,
        string $field,
        string $relationPath,
        NamePolicy $policy,
        array &$caseProtectedNames
    ): bool {
        if ($field === '*' || $policy->allowsAttributeByName($group, $field)) {
            return false;
        }

        $caseProtectedNames[$group] ??= $this->caseProtectedNames($group, $relationPath);
        $name = $this->normalizePublicPath($field);
        $protected = $caseProtectedNames[$group][self::lowercase($name)] ?? null;

        return $protected === true || ($protected !== null && $protected !== $name);
    }

    /**
     * Protected names of one fieldset, lowercased: true for a disallowed name,
     * the name itself for a hidden attribute (which may be requested exactly).
     *
     * @return array<string, true|string>
     */
    private function caseProtectedNames(string $group, string $relationPath): array
    {
        $names = [];

        foreach ($this->resolveAppendAccessorModel($relationPath)?->getHidden() ?? [] as $hidden) {
            $names[self::lowercase($hidden)] = $hidden;
        }

        return ($this->lowercaseDisallowedFieldNamesByGroup()[$this->normalizePublicPath($group)] ?? []) + $names;
    }

    /**
     * Disallowed field names, lowercased and grouped by the fieldset they name.
     *
     * @return array<string, array<string, true>>
     */
    private function lowercaseDisallowedFieldNamesByGroup(): array
    {
        $byGroup = [];

        foreach ($this->normalizePublicPaths($this->disallowedFields) as $disallowed) {
            $dot = strrpos($disallowed, '.');
            $group = $dot === false ? '' : substr($disallowed, 0, $dot);
            $byGroup[$group][self::lowercase($dot === false ? $disallowed : substr($disallowed, $dot + 1))] = true;
        }

        return $byGroup;
    }

    private static function lowercase(string $name): string
    {
        return preg_match('/[\x80-\xff]/', $name) === 1 ? mb_strtolower($name) : strtolower($name);
    }

    /**
     * @param  array<string>  $fields
     * @param  array<string>  $allowedFields
     */
    private function fieldsNotAllowed(array $fields, array $allowedFields, bool $disallowed): InvalidFieldQuery
    {
        if (! $disallowed) {
            return InvalidFieldQuery::fieldsNotAllowed(collect($fields), collect($allowedFields));
        }

        $joinedFields = implode(', ', $fields);

        return new InvalidFieldQuery(
            collect($fields),
            collect($allowedFields),
            "Requested field(s) `{$joinedFields}` are not allowed."
        );
    }

    /**
     * Drop, or reject with a 400, requested tokens that can't be field names.
     *
     * A dotted token is never a field of the fieldset it was sent in. A token
     * that only a wildcard allows has to be an identifier, so it can't carry an
     * alias or an expression into the select. Names allowed explicitly pass,
     * and tokens no rule allows are left to the "not allowed" check.
     *
     * @param  array<string>  $fields
     * @param  array<string>  $allowedFields
     * @return array<string>
     */
    private function withoutMalformedFieldTokens(
        array $fields,
        array $allowedFields,
        NamePolicy $policy,
        string $group,
        bool $exceptionsDisabled
    ): array {
        $wellFormed = [];

        foreach ($fields as $field) {
            if ($this->isWellFormedFieldToken($field, $allowedFields, $policy, $group)) {
                $wellFormed[] = $field;

                continue;
            }

            if (! $exceptionsDisabled) {
                $token = $group === '' ? $field : "{$group}.{$field}";

                throw InvalidFieldQuery::invalidFormat("`{$token}` is not a valid field name.");
            }
        }

        return $wellFormed;
    }

    /**
     * @param  array<string>  $allowedFields
     */
    private function isWellFormedFieldToken(string $field, array $allowedFields, NamePolicy $policy, string $group): bool
    {
        if ($field === '*') {
            return true;
        }

        if (str_contains($field, '.')) {
            return false;
        }

        if (
            (! in_array('*', $allowedFields, true) && ! $policy->allowsAttribute($group, $field))
            || in_array($group === '' ? $field : "{$group}.{$field}", $allowedFields, true)
        ) {
            return true;
        }

        return preg_match('/^(?!\d+\z)[\p{L}\p{N}_$][\p{L}\p{M}\p{N}_$]*\z/u', $field) === 1;
    }
}
