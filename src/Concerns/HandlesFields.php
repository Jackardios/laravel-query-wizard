<?php

namespace Jackardios\QueryWizard\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Jackardios\QueryWizard\Exceptions\DefaultFieldKeyIsNotDefined;
use Jackardios\QueryWizard\Exceptions\InvalidFieldQuery;

trait HandlesFields
{
    protected ?Collection $allowedFields = null;
    protected ?string $defaultFieldKey = null;

    protected function allowedFields(): array
    {
        return [];
    }

    protected function defaultFieldKey(): string
    {
        return "";
    }

    public function getDefaultFieldKey(): string
    {
        if (!($this->defaultFieldKey instanceof Collection)) {
            $defaultFieldKeyFromCallback = $this->defaultFieldKey();
            if (!$defaultFieldKeyFromCallback) {
                throw new DefaultFieldKeyIsNotDefined();
            }
            $this->setDefaultFieldKey($defaultFieldKeyFromCallback);
        }

        return $this->defaultFieldKey;
    }

    public function setDefaultFieldKey(string $key): self
    {
        $this->defaultFieldKey = $key;

        return $this;
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

    public function setAllowedFields($fields): self
    {
        $fields = is_array($fields) ? $fields : func_get_args();

        $this->allowedFields = collect($fields)
            ->map(function (string $field) {
                return $this->prependFieldWithKey($field);
            })
            ->filter()
            ->unique()
            ->values();

        $this->ensureAllFieldsAllowed();

        return $this;
    }

    public function getFields(): Collection
    {
        $this->getAllowedFields();
        return $this->request->fields();
    }

    public function getFieldsByKey(string $key): Collection
    {
        return $this->getFields()->get($key, collect());
    }

    protected function ensureAllFieldsAllowed(): self
    {
        $requestedFields = $this->request->fields()
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

    protected function prependFieldsWithKey(array $fields, ?string $key = null): array
    {
        return array_map(static fn ($field) => $this->prependFieldWithKey($field, $key), $fields);
    }

    protected function prependFieldWithKey(string $field, ?string $key = null): string
    {
        if (Str::contains($field, '.')) {
            // Already prepended

            return $field;
        }

        $key = $key ?? $this->getDefaultFieldKey();

        return "{$key}.{$field}";
    }
}
