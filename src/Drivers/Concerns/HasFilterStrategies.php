<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers\Concerns;

use InvalidArgumentException;
use Jackardios\QueryWizard\Contracts\Definitions\FilterDefinitionInterface;
use Jackardios\QueryWizard\Contracts\FilterStrategyInterface;

/**
 * Provides filter strategy registration, caching, and resolution for drivers.
 *
 * Usage:
 * ```php
 * class MyDriver implements DriverInterface
 * {
 *     use HasFilterStrategies;
 *
 *     public function __construct()
 *     {
 *         $this->filterStrategies = [
 *             'exact' => MyExactFilterStrategy::class,
 *             'partial' => MyPartialFilterStrategy::class,
 *         ];
 *     }
 * }
 * ```
 */
trait HasFilterStrategies
{
    /** @var array<string, class-string<FilterStrategyInterface>> */
    protected array $filterStrategies = [];

    /** @var array<string, FilterStrategyInterface> */
    protected array $filterStrategyInstances = [];

    /**
     * Register a custom filter strategy
     *
     * @param class-string<FilterStrategyInterface> $strategyClass
     */
    public function registerFilterStrategy(string $type, string $strategyClass): void
    {
        $this->filterStrategies[$type] = $strategyClass;
        // Clear cached instance if exists
        unset($this->filterStrategyInstances[$type]);
    }

    /**
     * Check if driver supports a specific filter type
     */
    public function supportsFilterType(string $type): bool
    {
        return isset($this->filterStrategies[$type]) || $type === 'custom';
    }

    /**
     * Get all supported filter types
     *
     * @return array<string>
     */
    public function getSupportedFilterTypes(): array
    {
        return array_keys($this->filterStrategies);
    }

    /**
     * Resolve filter strategy instance (with caching for built-in types)
     */
    protected function resolveFilterStrategy(FilterDefinitionInterface $filter): FilterStrategyInterface
    {
        $type = $filter->getType();

        // Custom strategies are not cached (may have state specific to definition)
        if ($type === 'custom' && $filter->getStrategyClass() !== null) {
            $class = $filter->getStrategyClass();
            return new $class();
        }

        if (!isset($this->filterStrategies[$type])) {
            throw new InvalidArgumentException("Unknown filter type: $type");
        }

        // Cache and reuse stateless strategy instances
        return $this->filterStrategyInstances[$type]
            ??= new $this->filterStrategies[$type]();
    }
}
