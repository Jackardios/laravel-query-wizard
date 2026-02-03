<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Wizards\Concerns;

use Illuminate\Support\Collection;
use Jackardios\QueryWizard\Enums\Capability;
use Jackardios\QueryWizard\Exceptions\InvalidFieldQuery;

trait HandlesFields
{
    /** @var array<string> */
    protected array $allowedFields = [];

    protected bool $fieldsApplied = false;

    /**
     * Set allowed fields
     *
     * @param string|array<string> ...$fields
     */
    public function setAllowedFields(string|array ...$fields): static
    {
        $this->allowedFields = $this->flattenStringArray($fields);
        return $this;
    }

    /**
     * Resolve allowed fields after applying schema and context
     *
     * @return array<string>
     */
    protected function resolveAllowedFields(): array
    {
        $fields = !empty($this->allowedFields)
            ? $this->allowedFields
            : ($this->schema?->fields() ?? []);

        $context = $this->resolveContext();
        if ($context !== null) {
            if ($context->getAllowedFields() !== null) {
                $fields = $context->getAllowedFields();
            }

            $disallowed = $context->getDisallowedFields();
            if (!empty($disallowed)) {
                $fields = array_filter($fields, function (string $field) use ($disallowed): bool {
                    foreach ($disallowed as $d) {
                        if ($field === $d || str_starts_with($field, $d . '.')) {
                            return false;
                        }
                    }
                    return true;
                });
            }
        }

        return array_values($fields);
    }

    /**
     * Get effective fields (schema + context applied, root fields only)
     *
     * @return array<string>
     */
    protected function getEffectiveFields(): array
    {
        return array_values(array_filter(
            $this->resolveAllowedFields(),
            fn($field) => !str_contains($field, '.')
        ));
    }

    /**
     * Get all allowed fields as raw array (with dots)
     *
     * @return array<string>
     */
    protected function getAllAllowedFields(): array
    {
        return $this->resolveAllowedFields();
    }

    /**
     * Get allowed fields for a specific resource key (relation name)
     *
     * @return array<string>
     */
    protected function getAllowedFieldsForResource(string $resourceKey): array
    {
        $allFields = $this->getAllAllowedFields();
        $result = [];

        foreach ($allFields as $field) {
            $lastDotPos = strrpos($field, '.');
            if ($lastDotPos !== false) {
                $fieldResource = substr($field, 0, $lastDotPos);
                $fieldName = substr($field, $lastDotPos + 1);

                if ($fieldResource === $resourceKey) {
                    $result[] = $fieldName;
                }
            }
        }

        return $result;
    }

    /**
     * Get effective default fields
     *
     * @return array<string>
     */
    protected function getEffectiveDefaultFields(): array
    {
        $context = $this->resolveContext();
        if ($context?->getDefaultFields() !== null) {
            return $context->getDefaultFields();
        }

        return $this->schema?->defaultFields() ?? ['*'];
    }

    /**
     * Apply fields selection to subject
     */
    protected function applyFields(): void
    {
        if ($this->fieldsApplied) {
            return;
        }

        if (!in_array(Capability::FIELDS->value, $this->driver->capabilities(), true)) {
            $this->fieldsApplied = true;
            return;
        }

        $allAllowedFields = $this->getAllAllowedFields();
        $allowedRootFields = $this->getEffectiveFields();
        $requestedFields = $this->getFields();
        $resourceKey = $this->getResourceKey();

        if (empty($allAllowedFields)) {
            $this->fieldsApplied = true;
            return;
        }

        foreach ($requestedFields as $requestResourceKey => $resourceFields) {
            $resourceFields = (array) $resourceFields;

            if (in_array('*', $resourceFields, true)) {
                continue;
            }

            $allowedForResource = ($requestResourceKey === $resourceKey)
                ? $allowedRootFields
                : $this->getAllowedFieldsForResource($requestResourceKey);

            if (in_array('*', $allowedForResource, true)) {
                continue;
            }

            if (!empty($allowedForResource)) {
                $invalidFields = array_diff($resourceFields, $allowedForResource);
                if (!empty($invalidFields)) {
                    throw InvalidFieldQuery::fieldsNotAllowed(collect($invalidFields), collect($allowedForResource));
                }
            }
        }

        $fields = $requestedFields->get($resourceKey);
        if ($fields !== null && !empty($allowedRootFields)) {
            $this->subject = $this->driver->applyFields($this->subject, (array) $fields);
        }

        $this->fieldsApplied = true;
    }

    /**
     * Get the requested fields
     *
     * @return Collection<string, array<string>>
     */
    public function getFields(): Collection
    {
        $fields = $this->parameters->getFields();
        $resourceKey = $this->getResourceKey();

        if ($fields->has('')) {
            $rootFields = $fields->get('');
            $fields = $fields->forget('');

            if ($fields->has($resourceKey)) {
                /** @var array<string> $existing */
                $existing = $fields->get($resourceKey);
                /** @var array<int, string> $merged */
                $merged = array_unique(array_merge($existing, $rootFields));
                $fields = $fields->put($resourceKey, array_values($merged));
            } else {
                $fields = $fields->put($resourceKey, $rootFields);
            }

            /** @var Collection<string, array<string>> $reordered */
            $reordered = collect([$resourceKey => $fields->get($resourceKey)]);
            foreach ($fields as $key => $value) {
                if ($key !== $resourceKey) {
                    $reordered[$key] = $value;
                }
            }
            $fields = $reordered;
        }

        /** @var Collection<string, array<string>> $fields */
        return $fields;
    }

    /**
     * Get fields for a specific key (for nested relations)
     *
     * @return array<string>|null
     */
    public function getFieldsByKey(string $key): ?array
    {
        $allFields = $this->getFields();
        /** @var array<string>|null $fields */
        $fields = $allFields->get($key);
        return $fields;
    }

    /**
     * Get resource key for fields
     */
    abstract public function getResourceKey(): string;
}
