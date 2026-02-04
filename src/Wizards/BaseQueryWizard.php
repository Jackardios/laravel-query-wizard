<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Wizards;

use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\Contracts\DriverInterface;
use Jackardios\QueryWizard\Contracts\ResourceSchemaInterface;
use Jackardios\QueryWizard\Contracts\SchemaContextInterface;
use Jackardios\QueryWizard\QueryParametersManager;

abstract class BaseQueryWizard
{
    use Concerns\ResolvesEffectiveDefinitions;

    protected mixed $subject;
    protected DriverInterface $driver;
    protected QueryParametersManager $parameters;
    protected QueryWizardConfig $config;
    protected ?ResourceSchemaInterface $schema = null;

    // Context cache to avoid repeated calls
    protected ?SchemaContextInterface $resolvedContextCache = null;
    protected bool $contextResolved = false;

    public function __construct(
        mixed $subject,
        DriverInterface $driver,
        QueryParametersManager $parameters,
        QueryWizardConfig $config,
        ?ResourceSchemaInterface $schema = null
    ) {
        $this->subject = $subject;
        $this->driver = $driver;
        $this->parameters = $parameters;
        $this->config = $config;
        $this->schema = $schema;
    }

    /**
     * Get the underlying subject
     */
    public function getSubject(): mixed
    {
        return $this->subject;
    }

    /**
     * Get the driver
     */
    public function getDriver(): DriverInterface
    {
        return $this->driver;
    }

    /**
     * Get the parameters manager
     */
    public function getParameters(): QueryParametersManager
    {
        return $this->parameters;
    }

    /**
     * Get resource key for fields
     */
    public function getResourceKey(): string
    {
        return $this->schema?->type() ?? $this->driver->getResourceKey($this->subject);
    }

    /**
     * Get context mode ('list' or 'item')
     */
    abstract protected function getContextMode(): string;

    /**
     * Resolve the schema context based on mode (cached)
     */
    protected function resolveContext(): ?SchemaContextInterface
    {
        if ($this->contextResolved) {
            return $this->resolvedContextCache;
        }

        $this->contextResolved = true;

        if ($this->schema === null) {
            return null;
        }

        $this->resolvedContextCache = $this->getContextMode() === 'list'
            ? $this->schema->forList()
            : $this->schema->forItem();

        return $this->resolvedContextCache;
    }

    /**
     * @template T
     * @param array<T> $items
     * @param array<string> $disallowed
     * @param callable(T): string $getName
     * @return array<T>
     */
    protected function removeDisallowed(array $items, array $disallowed, callable $getName): array
    {
        return array_values(array_filter($items, function ($item) use ($disallowed, $getName) {
            $name = $getName($item);

            foreach ($disallowed as $d) {
                // Exact match or prefix match (with dot or Count suffix)
                if ($name === $d
                    || str_starts_with($name, $d . '.')
                    || $name === $d . 'Count') {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * Flatten definitions array (handle variadic with nested arrays)
     *
     * @template T
     * @param array<array-key, T|array<array-key, T>> $items
     * @return array<int, T>
     */
    protected function flattenDefinitions(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                foreach ($item as $i) {
                    // Skip null, empty strings, and empty arrays
                    if ($i !== null && $i !== '' && $i !== []) {
                        $result[] = $i;
                    }
                }
            } elseif ($item !== null && $item !== '') {
                $result[] = $item;
            }
        }
        return $result;
    }

    /**
     * Flatten an array of strings or string arrays into a single array of strings
     *
     * @param array<string|array<string>> $items
     * @return array<string>
     */
    protected function flattenStringArray(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                foreach ($item as $i) {
                    if (is_string($i)) {
                        $result[] = $i;
                    }
                }
            } elseif (is_string($item)) {
                $result[] = $item;
            }
        }
        return $result;
    }

    /**
     * Clone the wizard (including subject)
     */
    public function __clone(): void
    {
        if (is_object($this->subject)) {
            $this->subject = clone $this->subject;
        }
        // Reset context cache for cloned instance
        $this->contextResolved = false;
        $this->resolvedContextCache = null;
    }

    /**
     * Clone method for fluent API
     */
    public function clone(): static
    {
        return clone $this;
    }
}
