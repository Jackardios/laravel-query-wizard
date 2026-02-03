<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Contracts;

use Jackardios\QueryWizard\Contracts\Definitions\FilterDefinitionInterface;
use Jackardios\QueryWizard\Contracts\Definitions\IncludeDefinitionInterface;
use Jackardios\QueryWizard\Contracts\Definitions\SortDefinitionInterface;

/**
 * Normalizes string definitions to their corresponding Definition objects.
 *
 * Each driver can have its own normalizer that converts string shortcuts
 * to proper Definition objects specific to that driver.
 */
interface DefinitionNormalizerInterface
{
    /**
     * Normalize a filter definition.
     *
     * Converts string filter names to FilterDefinition objects.
     * If already a FilterDefinitionInterface, may apply driver-specific transformations.
     */
    public function normalizeFilter(FilterDefinitionInterface|string $filter): FilterDefinitionInterface;

    /**
     * Normalize an include definition.
     *
     * Converts string include names to IncludeDefinition objects.
     * May detect special patterns (e.g., count suffix) and create appropriate types.
     */
    public function normalizeInclude(IncludeDefinitionInterface|string $include): IncludeDefinitionInterface;

    /**
     * Normalize a sort definition.
     *
     * Converts string sort names to SortDefinition objects.
     * May handle direction prefixes (e.g., "-name" for descending).
     */
    public function normalizeSort(SortDefinitionInterface|string $sort): SortDefinitionInterface;
}
