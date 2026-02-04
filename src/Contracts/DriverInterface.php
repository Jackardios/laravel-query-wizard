<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Contracts;

interface DriverInterface
{
    /**
     * Unique driver name
     */
    public function name(): string;

    /**
     * Check if driver supports given subject
     */
    public function supports(mixed $subject): bool;

    /**
     * Returns list of supported capabilities
     *
     * @return array<string> Array of capability values from Capability enum
     * @see \Jackardios\QueryWizard\Enums\Capability
     */
    public function capabilities(): array;

    /**
     * Normalize a filter definition (string to FilterInterface)
     */
    public function normalizeFilter(FilterInterface|string $filter): FilterInterface;

    /**
     * Normalize an include definition (string to IncludeInterface)
     */
    public function normalizeInclude(IncludeInterface|string $include): IncludeInterface;

    /**
     * Normalize a sort definition (string to SortInterface)
     */
    public function normalizeSort(SortInterface|string $sort): SortInterface;

    /**
     * Apply filter to subject
     */
    public function applyFilter(mixed $subject, FilterInterface $filter, mixed $value): mixed;

    /**
     * Apply include to subject
     *
     * @param array<string> $fields
     */
    public function applyInclude(mixed $subject, IncludeInterface $include, array $fields = []): mixed;

    /**
     * Apply sort to subject
     */
    public function applySort(mixed $subject, SortInterface $sort, string $direction): mixed;

    /**
     * Apply field selection to subject
     *
     * @param array<string> $fields
     */
    public function applyFields(mixed $subject, array $fields): mixed;

    /**
     * Apply appends to result
     *
     * @param array<string> $appends
     */
    public function applyAppends(mixed $result, array $appends): mixed;

    /**
     * Get resource key for root fields (table name or resource type)
     */
    public function getResourceKey(mixed $subject): string;

    /**
     * Prepare subject for execution (e.g., convert class name to Builder)
     */
    public function prepareSubject(mixed $subject): mixed;

    /**
     * Check if driver supports a specific filter type
     */
    public function supportsFilterType(string $type): bool;

    /**
     * Check if driver supports a specific include type
     */
    public function supportsIncludeType(string $type): bool;

    /**
     * Check if driver supports a specific sort type
     */
    public function supportsSortType(string $type): bool;

    /**
     * Get all supported filter types
     *
     * @return array<string>
     */
    public function getSupportedFilterTypes(): array;

    /**
     * Get all supported include types
     *
     * @return array<string>
     */
    public function getSupportedIncludeTypes(): array;

    /**
     * Get all supported sort types
     *
     * @return array<string>
     */
    public function getSupportedSortTypes(): array;
}
