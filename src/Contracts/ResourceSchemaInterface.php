<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Contracts;

use Jackardios\QueryWizard\Contracts\Definitions\FilterDefinitionInterface;
use Jackardios\QueryWizard\Contracts\Definitions\IncludeDefinitionInterface;
use Jackardios\QueryWizard\Contracts\Definitions\SortDefinitionInterface;

interface ResourceSchemaInterface
{
    /**
     * Model class (for automatic subject creation)
     *
     * @return class-string
     */
    public function model(): string;

    /**
     * Resource type (used as key in fields)
     */
    public function type(): string;

    /**
     * Driver name to use
     */
    public function driver(): string;

    /**
     * Allowed filters (strings or FilterDefinitionInterface)
     *
     * @return array<FilterDefinitionInterface|string>
     */
    public function filters(): array;

    /**
     * Allowed includes (strings or IncludeDefinitionInterface)
     *
     * @return array<IncludeDefinitionInterface|string>
     */
    public function includes(): array;

    /**
     * Allowed sorts (strings or SortDefinitionInterface)
     *
     * @return array<SortDefinitionInterface|string>
     */
    public function sorts(): array;

    /**
     * Allowed fields
     *
     * @return array<string>
     */
    public function fields(): array;

    /**
     * Allowed appends
     *
     * @return array<string>
     */
    public function appends(): array;

    /**
     * Default fields (applied when no fields specified in request)
     *
     * @return array<string>
     */
    public function defaultFields(): array;

    /**
     * Default includes (always applied unless overridden by request)
     *
     * @return array<string>
     */
    public function defaultIncludes(): array;

    /**
     * Default sorts (applied when no sorts specified in request)
     *
     * @return array<string>
     */
    public function defaultSorts(): array;

    /**
     * Default appends (applied when no appends specified in request)
     *
     * @return array<string>
     */
    public function defaultAppends(): array;

    /**
     * Get context configuration for list operations
     */
    public function forList(): ?SchemaContextInterface;

    /**
     * Get context configuration for item operations
     */
    public function forItem(): ?SchemaContextInterface;
}
