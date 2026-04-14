<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Concerns;

use Illuminate\Database\Eloquent\Model;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Exceptions\InvalidFieldQuery;
use Jackardios\QueryWizard\Support\DotNotationTreeBuilder;

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

    /**
     * Get the resource key for sparse fieldsets.
     */
    abstract public function getResourceKey(): string;

    /**
     * @return array<IncludeInterface>
     */
    abstract protected function getEffectiveIncludes(): array;

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
     * if no explicit defaults are configured.
     *
     * @return array<string>
     */
    protected function getEffectiveDefaultFields(): array
    {
        if (! empty($this->defaultFields)) {
            return $this->normalizePublicPaths($this->defaultFields);
        }

        $schemaDefaults = $this->getSchema()?->defaultFields($this);
        if (! empty($schemaDefaults)) {
            return $this->normalizePublicPaths($schemaDefaults);
        }

        if ($this->getConfig()->shouldUseAllowedFieldsAsDefault()) {
            return $this->extractRootFields($this->getEffectiveFields());
        }

        return [];
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
     * Check if fields request parameter is completely absent (for defaults logic).
     */
    protected function isFieldsRequestEmpty(): bool
    {
        return ! $this->getParametersManager()->hasSimpleParameter('fields');
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
        $relationFieldMap = [];

        foreach ($requestedRelationFields as $requestedKey => $requestedFields) {
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

            if (! $allFieldsAllowed) {
                $validFields = [];
                $invalidFields = [];

                foreach ($normalizedRequestedFields as $field) {
                    if ($this->isAttributeAllowed($requestedKey, $field, $allowedFields)) {
                        $validFields[] = $field;
                    } else {
                        $invalidFields[] = $field;
                    }
                }

                if (! empty($invalidFields)) {
                    if (! $exceptionsDisabled) {
                        throw InvalidFieldQuery::fieldsNotAllowed(
                            collect($this->prefixGroupFields($requestedKey, $invalidFields)),
                            collect($allowedRelationFieldList),
                        );
                    }

                    $normalizedRequestedFields = $validFields;
                }
            }

            if (in_array('*', $normalizedRequestedFields, true)) {
                $relationFieldMap[$relationPath] = ['*'];

                continue;
            }

            if (empty($normalizedRequestedFields)) {
                $relationFieldMap[$relationPath] = [];

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
     *
     * @return array<string>|null Validated fields or null for no filtering
     *
     * @throws InvalidFieldQuery
     */
    protected function resolveValidatedRootFields(): ?array
    {
        $resourceKey = $this->getResourceKey();
        $requestedFields = $this->getRequestedFieldsForResource($resourceKey);
        $requestAbsent = $this->isFieldsRequestEmpty();
        $usingDefaults = false;

        if ($requestAbsent) {
            $defaultFields = $this->getEffectiveDefaultFields();
            if (! empty($defaultFields)) {
                $fields = $defaultFields;
                $usingDefaults = true;
            } else {
                return null;
            }
        } else {
            if (! $this->hasRequestedFieldsForResource($resourceKey)) {
                return null;
            }

            $fields = $requestedFields;
        }

        $allowedFields = $this->getEffectiveFields();

        // Global wildcard in allowed - permit any requested fields
        if (in_array('*', $allowedFields, true)) {
            // If client requested '*', return null (all fields)
            if (in_array('*', $fields, true)) {
                return null;
            }

            return $fields;
        }

        if (empty($allowedFields)) {
            if (
                ! $requestAbsent
                && $fields !== []
                && ! $this->getConfig()->isInvalidFieldQueryExceptionDisabled()
            ) {
                throw InvalidFieldQuery::fieldsNotAllowed(
                    collect($fields),
                    collect([])
                );
            }

            return $requestAbsent ? null : [];
        }

        $validFields = [];
        $invalidFields = [];

        foreach ($fields as $field) {
            if ($this->isAttributeAllowed('', $field, $allowedFields)) {
                $validFields[] = $field;
            } elseif (! $requestAbsent) {
                $invalidFields[] = $field;
            }
        }

        if (! empty($invalidFields) && ! $this->getConfig()->isInvalidFieldQueryExceptionDisabled()) {
            throw InvalidFieldQuery::fieldsNotAllowed(
                collect($invalidFields),
                collect($allowedFields)
            );
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
}
