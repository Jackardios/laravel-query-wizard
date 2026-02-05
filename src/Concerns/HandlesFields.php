<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Concerns;

use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\QueryParametersManager;
use Jackardios\QueryWizard\Schema\ResourceSchemaInterface;

/**
 * Shared field handling logic for query wizards.
 *
 * This trait contains methods used by both BaseQueryWizard
 * and ModelQueryWizard for handling fields functionality.
 *
 * Classes using this trait must provide:
 * - getConfig(): QueryWizardConfig
 * - getParametersManager(): QueryParametersManager
 * - getSchema(): ?ResourceSchemaInterface
 * - getResourceKey(): string
 * - $allowedFields: array property
 * - $allowedFieldsExplicitlySet: bool property
 * - $disallowedFields: array property
 */
trait HandlesFields
{
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
     * Get requested fields for this resource.
     *
     * @return array<string>
     */
    protected function getRequestedFields(): array
    {
        $resourceKey = $this->getResourceKey();

        return $this->getParametersManager()->getFields()->get($resourceKey, []);
    }

    /**
     * Check if fields are using wildcard (allow any).
     *
     * @param  array<string>  $fields
     */
    protected function isFieldsWildcard(array $fields): bool
    {
        return in_array('*', $fields, true);
    }
}
