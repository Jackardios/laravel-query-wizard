<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Schema;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Jackardios\QueryWizard\Contracts\FilterInterface;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Contracts\QueryWizardInterface;
use Jackardios\QueryWizard\Contracts\SortInterface;

/**
 * Base class for resource schemas.
 *
 * A schema defines what filters, sorts, includes, fields, and appends
 * are allowed for a resource. Schemas can be shared between different
 * wizard types (EloquentQueryWizard, ModelQueryWizard).
 *
 * All methods accept a $wizard parameter to allow conditional
 * logic based on the wizard type. For example:
 *
 * ```php
 * public function filters(QueryWizardInterface $wizard): array
 * {
 *     if ($wizard instanceof ModelQueryWizard) {
 *         return []; // No filters for loaded models
 *     }
 *     return [
 *         EloquentFilter::exact('status'),
 *         EloquentFilter::partial('name'),
 *     ];
 * }
 * ```
 */
abstract class ResourceSchema implements ResourceSchemaInterface
{
    /**
     * Get the model class name.
     *
     * @return class-string<Model>
     */
    abstract public function model(): string;

    /**
     * Get the resource type for sparse fieldsets.
     *
     * Defaults to camelCase of model basename.
     * Used as the key in ?fields[type]=id,name
     */
    public function type(): string
    {
        return Str::camel(class_basename($this->model()));
    }

    /**
     * Get allowed includes.
     *
     * @param  QueryWizardInterface  $wizard  The wizard requesting includes (for conditional logic)
     * @return array<IncludeInterface|string>
     */
    public function includes(QueryWizardInterface $wizard): array
    {
        return [];
    }

    /**
     * Get allowed fields.
     *
     * Return empty array to forbid client from requesting specific fields.
     * Return ['*'] to allow any fields requested by client.
     * Return specific field names to restrict to those fields only.
     *
     * @param  QueryWizardInterface  $wizard  The wizard requesting fields (for conditional logic)
     * @return array<string>
     */
    public function fields(QueryWizardInterface $wizard): array
    {
        return [];
    }

    /**
     * Get allowed appends.
     *
     * @param  QueryWizardInterface  $wizard  The wizard requesting appends (for conditional logic)
     * @return array<string>
     */
    public function appends(QueryWizardInterface $wizard): array
    {
        return [];
    }

    /**
     * Get default includes to always load.
     *
     * @param  QueryWizardInterface  $wizard  The wizard requesting default includes (for conditional logic)
     * @return array<string>
     */
    public function defaultIncludes(QueryWizardInterface $wizard): array
    {
        return [];
    }

    /**
     * Get default appends to always add.
     *
     * @param  QueryWizardInterface  $wizard  The wizard requesting default appends (for conditional logic)
     * @return array<string>
     */
    public function defaultAppends(QueryWizardInterface $wizard): array
    {
        return [];
    }

    /**
     * Get allowed filters.
     *
     * @param  QueryWizardInterface  $wizard  The wizard requesting filters (for conditional logic)
     * @return array<FilterInterface|string>
     */
    public function filters(QueryWizardInterface $wizard): array
    {
        return [];
    }

    /**
     * Get allowed sorts.
     *
     * @param  QueryWizardInterface  $wizard  The wizard requesting sorts (for conditional logic)
     * @return array<SortInterface|string>
     */
    public function sorts(QueryWizardInterface $wizard): array
    {
        return [];
    }

    /**
     * Get default sorts to apply when none requested.
     *
     * @param  QueryWizardInterface  $wizard  The wizard requesting default sorts (for conditional logic)
     * @return array<string>
     */
    public function defaultSorts(QueryWizardInterface $wizard): array
    {
        return [];
    }

    /**
     * Get default fields when none requested.
     *
     * @param  QueryWizardInterface  $wizard  The wizard requesting default fields (for conditional logic)
     * @return array<string>
     */
    public function defaultFields(QueryWizardInterface $wizard): array
    {
        return [];
    }

    /**
     * Get default filter values to apply when not present in request.
     *
     * Returns an associative array of filter name => default value.
     *
     * @param  QueryWizardInterface  $wizard  The wizard requesting default filters (for conditional logic)
     * @return array<string, mixed>
     */
    public function defaultFilters(QueryWizardInterface $wizard): array
    {
        return [];
    }
}
