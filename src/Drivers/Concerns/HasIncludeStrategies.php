<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers\Concerns;

use InvalidArgumentException;
use Jackardios\QueryWizard\Contracts\Definitions\IncludeDefinitionInterface;
use Jackardios\QueryWizard\Contracts\IncludeStrategyInterface;

/**
 * Provides include strategy registration, caching, and resolution for drivers.
 *
 * Usage:
 * ```php
 * class MyDriver implements DriverInterface
 * {
 *     use HasIncludeStrategies;
 *
 *     public function __construct()
 *     {
 *         $this->includeStrategies = [
 *             'relationship' => MyRelationshipIncludeStrategy::class,
 *             'count' => MyCountIncludeStrategy::class,
 *         ];
 *     }
 * }
 * ```
 */
trait HasIncludeStrategies
{
    /** @var array<string, class-string<IncludeStrategyInterface>> */
    protected array $includeStrategies = [];

    /** @var array<string, IncludeStrategyInterface> */
    protected array $includeStrategyInstances = [];

    /**
     * Register a custom include strategy
     *
     * @param class-string<IncludeStrategyInterface> $strategyClass
     */
    public function registerIncludeStrategy(string $type, string $strategyClass): void
    {
        $this->includeStrategies[$type] = $strategyClass;
        // Clear cached instance if exists
        unset($this->includeStrategyInstances[$type]);
    }

    /**
     * Check if driver supports a specific include type
     */
    public function supportsIncludeType(string $type): bool
    {
        return isset($this->includeStrategies[$type]) || $type === 'custom';
    }

    /**
     * Get all supported include types
     *
     * @return array<string>
     */
    public function getSupportedIncludeTypes(): array
    {
        return array_keys($this->includeStrategies);
    }

    /**
     * Resolve include strategy instance (with caching for built-in types)
     */
    protected function resolveIncludeStrategy(IncludeDefinitionInterface $include): IncludeStrategyInterface
    {
        $type = $include->getType();

        // Custom strategies are not cached (may have state specific to definition)
        if ($type === 'custom' && $include->getStrategyClass() !== null) {
            $class = $include->getStrategyClass();
            return new $class();
        }

        if (!isset($this->includeStrategies[$type])) {
            throw new InvalidArgumentException("Unknown include type: $type");
        }

        // Cache and reuse stateless strategy instances
        return $this->includeStrategyInstances[$type]
            ??= new $this->includeStrategies[$type]();
    }
}
