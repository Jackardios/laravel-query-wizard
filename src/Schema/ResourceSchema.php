<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Schema;

use Illuminate\Support\Str;
use Jackardios\QueryWizard\Contracts\FilterInterface;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Contracts\ResourceSchemaInterface;
use Jackardios\QueryWizard\Contracts\SchemaContextInterface;
use Jackardios\QueryWizard\Contracts\SortInterface;

abstract class ResourceSchema implements ResourceSchemaInterface
{
    /**
     * Model class (for automatic subject creation)
     *
     * @return class-string
     */
    abstract public function model(): string;

    /**
     * Resource type (used as key in fields)
     * Defaults to camelCase of model basename
     */
    public function type(): string
    {
        return Str::camel(class_basename($this->model()));
    }

    /**
     * Driver name to use
     */
    public function driver(): string
    {
        return 'eloquent';
    }

    /**
     * Allowed filters (strings or FilterInterface)
     *
     * @return array<FilterInterface|string>
     */
    public function filters(): array
    {
        return [];
    }

    /**
     * Allowed includes (strings or IncludeInterface)
     *
     * @return array<IncludeInterface|string>
     */
    public function includes(): array
    {
        return [];
    }

    /**
     * Allowed sorts (strings or SortInterface)
     *
     * @return array<SortInterface|string>
     */
    public function sorts(): array
    {
        return [];
    }

    /**
     * Allowed fields
     *
     * @return array<string>
     */
    public function fields(): array
    {
        return [];
    }

    /**
     * Allowed appends
     *
     * @return array<string>
     */
    public function appends(): array
    {
        return [];
    }

    /**
     * Default fields (applied when no fields specified in request)
     * Defaults to ['*'] meaning all fields
     *
     * @return array<string>
     */
    public function defaultFields(): array
    {
        return ['*'];
    }

    /**
     * Default includes (always applied unless overridden by request)
     *
     * @return array<string>
     */
    public function defaultIncludes(): array
    {
        return [];
    }

    /**
     * Default sorts (applied when no sorts specified in request)
     *
     * @return array<string>
     */
    public function defaultSorts(): array
    {
        return [];
    }

    /**
     * Default appends (applied when no appends specified in request)
     *
     * @return array<string>
     */
    public function defaultAppends(): array
    {
        return [];
    }

    /**
     * Get context configuration for list operations
     * Override in subclass to customize list behavior
     */
    public function forList(): ?SchemaContextInterface
    {
        return null;
    }

    /**
     * Get context configuration for item operations
     * Override in subclass to customize item behavior
     */
    public function forItem(): ?SchemaContextInterface
    {
        return null;
    }
}
