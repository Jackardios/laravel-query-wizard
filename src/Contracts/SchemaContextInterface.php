<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Contracts;

interface SchemaContextInterface
{
    /**
     * @return array<\Jackardios\QueryWizard\Contracts\Definitions\FilterDefinitionInterface|string>|null
     */
    public function getAllowedFilters(): ?array;

    /**
     * @return array<\Jackardios\QueryWizard\Contracts\Definitions\SortDefinitionInterface|string>|null
     */
    public function getAllowedSorts(): ?array;

    /**
     * @return array<\Jackardios\QueryWizard\Contracts\Definitions\IncludeDefinitionInterface|string>|null
     */
    public function getAllowedIncludes(): ?array;

    /**
     * Get allowed fields override (null = use schema)
     *
     * @return array<string>|null
     */
    public function getAllowedFields(): ?array;

    /**
     * Get allowed appends override (null = use schema)
     *
     * @return array<string>|null
     */
    public function getAllowedAppends(): ?array;

    /**
     * Get disallowed filters (to be removed from allowed list)
     *
     * @return array<string>
     */
    public function getDisallowedFilters(): array;

    /**
     * Get disallowed sorts (to be removed from allowed list)
     *
     * @return array<string>
     */
    public function getDisallowedSorts(): array;

    /**
     * Get disallowed includes (to be removed from allowed list)
     *
     * @return array<string>
     */
    public function getDisallowedIncludes(): array;

    /**
     * Get disallowed fields (to be removed from allowed list)
     *
     * @return array<string>
     */
    public function getDisallowedFields(): array;

    /**
     * Get disallowed appends (to be removed from allowed list)
     *
     * @return array<string>
     */
    public function getDisallowedAppends(): array;

    /**
     * Get default fields override (null = use schema)
     *
     * @return array<string>|null
     */
    public function getDefaultFields(): ?array;

    /**
     * Get default includes override (null = use schema)
     *
     * @return array<string>|null
     */
    public function getDefaultIncludes(): ?array;

    /**
     * Get default sorts override (null = use schema)
     *
     * @return array<string>|null
     */
    public function getDefaultSorts(): ?array;

    /**
     * Get default appends override (null = use schema)
     *
     * @return array<string>|null
     */
    public function getDefaultAppends(): ?array;
}
