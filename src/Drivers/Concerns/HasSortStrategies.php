<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers\Concerns;

use InvalidArgumentException;
use Jackardios\QueryWizard\Contracts\Definitions\SortDefinitionInterface;
use Jackardios\QueryWizard\Contracts\SortStrategyInterface;

/**
 * Provides sort strategy registration, caching, and resolution for drivers.
 *
 * Usage:
 * ```php
 * class MyDriver implements DriverInterface
 * {
 *     use HasSortStrategies;
 *
 *     public function __construct()
 *     {
 *         $this->sortStrategies = [
 *             'field' => MyFieldSortStrategy::class,
 *             'callback' => MyCallbackSortStrategy::class,
 *         ];
 *     }
 * }
 * ```
 */
trait HasSortStrategies
{
    /** @var array<string, class-string<SortStrategyInterface>> */
    protected array $sortStrategies = [];

    /** @var array<string, SortStrategyInterface> */
    protected array $sortStrategyInstances = [];

    /**
     * Register a custom sort strategy
     *
     * @param class-string<SortStrategyInterface> $strategyClass
     */
    public function registerSortStrategy(string $type, string $strategyClass): void
    {
        $this->sortStrategies[$type] = $strategyClass;
        // Clear cached instance if exists
        unset($this->sortStrategyInstances[$type]);
    }

    /**
     * Check if driver supports a specific sort type
     */
    public function supportsSortType(string $type): bool
    {
        return isset($this->sortStrategies[$type]) || $type === 'custom';
    }

    /**
     * Get all supported sort types
     *
     * @return array<string>
     */
    public function getSupportedSortTypes(): array
    {
        return array_keys($this->sortStrategies);
    }

    /**
     * Resolve sort strategy instance (with caching for built-in types)
     */
    protected function resolveSortStrategy(SortDefinitionInterface $sort): SortStrategyInterface
    {
        $type = $sort->getType();

        // Custom strategies are not cached (may have state specific to definition)
        if ($type === 'custom' && $sort->getStrategyClass() !== null) {
            $class = $sort->getStrategyClass();
            return new $class();
        }

        if (!isset($this->sortStrategies[$type])) {
            throw new InvalidArgumentException("Unknown sort type: $type");
        }

        // Cache and reuse stateless strategy instances
        return $this->sortStrategyInstances[$type]
            ??= new $this->sortStrategies[$type]();
    }
}
