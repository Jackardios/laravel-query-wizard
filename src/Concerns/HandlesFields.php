<?php

namespace Jackardios\QueryWizard\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Jackardios\QueryWizard\Exceptions\RootFieldsKeyIsNotDefined;
use Jackardios\QueryWizard\Exceptions\InvalidFieldQuery;

trait HandlesFields
{
    private ?Collection $allowedFields = null;
    private ?Collection $preparedFields = null;
    protected ?string $rootFieldsKey = null;

    /**
     * @return string[]
     */
    protected function allowedFields(): array
    {
        return [];
    }

    public function getAllowedFields(): Collection
    {
        if (!($this->allowedFields instanceof Collection)) {
            $allowedFieldsFromCallback = $this->allowedFields();

            if ($allowedFieldsFromCallback) {
                $this->setAllowedFields($allowedFieldsFromCallback);
            } else {
                return collect();
            }
        }

        return $this->allowedFields;
    }

    public function setAllowedFields($fields): static
    {
        $fields = is_array($fields) ? $fields : func_get_args();

        $this->allowedFields = collect($fields)
            ->map(function (string $field) {
                return $this->prependFieldWithKey($field);
            })
            ->filter()
            ->unique()
            ->values();

        return $this;
    }

    public function rootFieldsKey(): string
    {
        return "";
    }

    public function getRootFieldsKey(): string
    {
        if ($this->rootFieldsKey === null) {
            $rootFieldsKeyFromCallback = $this->rootFieldsKey();
            if (!$rootFieldsKeyFromCallback) {
                throw new RootFieldsKeyIsNotDefined();
            }
            $this->setRootFieldsKey($rootFieldsKeyFromCallback);
        }

        return $this->rootFieldsKey;
    }

    public function setRootFieldsKey(string $key): static
    {
        $this->rootFieldsKey = $key;

        return $this;
    }

    public function getFields(): Collection
    {
        if ($this->preparedFields instanceof Collection) {
            return $this->preparedFields;
        }

        if ($this->getAllowedFields()->isEmpty()) {
            return $this->preparedFields = collect();
        }

        $formattedFields = $this->getFormattedFields();
        $this->ensureAllFieldsAllowed($formattedFields);

        return $this->preparedFields = $formattedFields;
    }

    public function getFieldsByKey(string $key): array
    {
        return $this->getFields()->get($key, []);
    }

    public function getRootFields(): array
    {
        return $this->getFieldsByKey($this->getRootFieldsKey());
    }

    protected function getFormattedFields(): Collection
    {
        $requestedFields = $this->parametersManager->getFields();
        $formattedFields = collect();

        /**
         * @var mixed $key
         * @var array $fields
         */
        foreach ($requestedFields as $key => $fields) {
            if (is_string($key)) {
                $formattedFields[$key] = $fields;
                continue;
            }

            foreach($fields as $rawField) {
                [$key,$field] = $this->splitField($rawField);

                $newFields = $formattedFields[$key] ?? [];
                $newFields[] = $field;

                $formattedFields[$key] = $newFields;
            }
        }

        return $formattedFields;
    }

    protected function splitField(string $rawField): array {
        $parts = explode('.', $rawField);
        $field = array_pop($parts);
        $key = empty($parts) ? $this->getRootFieldsKey() : implode('.', $parts);

        return [$key, $field];
    }

    protected function ensureAllFieldsAllowed(Collection $formattedFields): static
    {
        $requestedFields = $formattedFields
            ->map(fn ($fields, $key) => $this->prependFieldsWithKey($fields, $key))
            ->flatten()
            ->unique()
            ->values();

        $unknownFields = $requestedFields->diff($this->allowedFields);

        if ($unknownFields->isNotEmpty()) {
            throw InvalidFieldQuery::fieldsNotAllowed($unknownFields, $this->allowedFields);
        }

        return $this;
    }

    public function prependFieldsWithKey(array $fields, ?string $defaultKey = null): array
    {
        return array_map(fn ($field) => $this->prependFieldWithKey($field, $defaultKey), $fields);
    }

    public function prependFieldWithKey(string $field, ?string $defaultKey = null): string
    {
        if (Str::contains($field, '.')) {
            // Already prepended

            return $field;
        }

        $defaultKey = $defaultKey ?? $this->getRootFieldsKey();

        return "{$defaultKey}.{$field}";
    }
}
