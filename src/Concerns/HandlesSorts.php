<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Concerns;

use Jackardios\QueryWizard\Contracts\SortInterface;
use Jackardios\QueryWizard\Exceptions\MaxSortsCountExceeded;
use Jackardios\QueryWizard\Values\Sort;

/**
 * Shared sort handling logic for query wizards.
 */
trait HandlesSorts
{
    use RequiresWizardContext;

    /** @var array<SortInterface|string> */
    protected array $allowedSorts = [];

    protected bool $allowedSortsExplicitlySet = false;

    /** @var array<string> */
    protected array $disallowedSorts = [];

    /** @var array<string> */
    protected array $defaultSorts = [];

    /** @var array<string, SortInterface>|null */
    protected ?array $cachedEffectiveSorts = null;

    abstract protected function normalizeStringToSort(string $name): SortInterface;

    /**
     * Get effective sorts.
     *
     * If allowedSorts() was called explicitly, use those (even if empty).
     * Otherwise, fall back to schema sorts (if any).
     * Empty result means all sorts are forbidden.
     *
     * @return array<string, SortInterface>
     */
    protected function getEffectiveSorts(): array
    {
        if ($this->cachedEffectiveSorts !== null) {
            return $this->cachedEffectiveSorts;
        }

        $sorts = $this->allowedSortsExplicitlySet
            ? $this->allowedSorts
            : ($this->getSchema()?->sorts($this) ?? []);

        $disallowed = $this->disallowedSorts;
        $result = [];

        foreach ($sorts as $sort) {
            if (is_string($sort)) {
                $sort = $this->normalizeStringToSort($sort);
            }
            $name = $sort->getName();

            if (! empty($disallowed) && $this->isNameDisallowed($name, $disallowed)) {
                continue;
            }

            $result[$name] = $sort;
        }

        return $this->cachedEffectiveSorts = $result;
    }

    /**
     * Get effective default sorts.
     *
     * @return array<string>
     */
    protected function getEffectiveDefaultSorts(): array
    {
        return ! empty($this->defaultSorts)
            ? $this->defaultSorts
            : ($this->getSchema()?->defaultSorts($this) ?? []);
    }

    /**
     * Extract sort name from Sort object or string.
     */
    protected function extractSortName(string|Sort $sort): string
    {
        if ($sort instanceof Sort) {
            $prefix = $sort->getDirection() === 'desc' ? '-' : '';

            return $prefix.$sort->getField();
        }

        return $sort;
    }

    protected function validateSortsLimit(int $count): void
    {
        $limit = $this->getConfig()->getMaxSortsCount();
        if ($limit !== null && $count > $limit) {
            throw MaxSortsCountExceeded::create($count, $limit);
        }
    }

    protected function invalidateSortCache(): void
    {
        $this->cachedEffectiveSorts = null;
    }
}
