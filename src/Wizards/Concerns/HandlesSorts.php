<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Wizards\Concerns;

use Jackardios\QueryWizard\Contracts\Definitions\SortDefinitionInterface;
use Jackardios\QueryWizard\Enums\Capability;
use Jackardios\QueryWizard\Exceptions\InvalidSortQuery;
use Jackardios\QueryWizard\Exceptions\MaxSortsCountExceeded;
use Jackardios\QueryWizard\Values\Sort;

trait HandlesSorts
{
    /** @var array<SortDefinitionInterface|string> */
    protected array $allowedSorts = [];

    /** @var array<string> */
    protected array $defaultSorts = [];

    protected bool $sortsApplied = false;

    /**
     * Set allowed sorts
     *
     * @param SortDefinitionInterface|string|array<SortDefinitionInterface|string> ...$sorts
     */
    public function setAllowedSorts(SortDefinitionInterface|string|array ...$sorts): static
    {
        $this->allowedSorts = $this->flattenDefinitions($sorts);
        return $this;
    }

    /**
     * Set default sorts
     *
     * @param string|Sort|array<string|Sort> ...$sorts
     */
    public function setDefaultSorts(string|Sort|array ...$sorts): static
    {
        $flatSorts = [];
        foreach ($sorts as $sort) {
            if (is_array($sort)) {
                foreach ($sort as $s) {
                    $flatSorts[] = $this->extractSortName($s);
                }
            } else {
                $flatSorts[] = $this->extractSortName($sort);
            }
        }
        $this->defaultSorts = $flatSorts;
        return $this;
    }

    /**
     * Get effective sorts (schema + context applied)
     *
     * @return array<SortDefinitionInterface>
     */
    protected function getEffectiveSorts(): array
    {
        $context = $this->resolveContext();

        $sorts = $this->resolveAllowedDefinitions(
            $this->allowedSorts,
            fn() => $this->schema?->sorts() ?? [],
            $context !== null ? fn() => $context->getAllowedSorts() : null,
            $context !== null ? fn() => $context->getDisallowedSorts() : null,
            fn($item) => $item instanceof SortDefinitionInterface ? $item->getName() : ltrim($item, '-')
        );

        return $this->normalizeSorts($sorts);
    }

    /**
     * Get effective default sorts
     *
     * @return array<string>
     */
    protected function getEffectiveDefaultSorts(): array
    {
        $context = $this->resolveContext();

        return $this->resolveEffectiveDefaults(
            $this->defaultSorts,
            $context !== null ? fn() => $context->getDefaultSorts() : null,
            fn() => $this->schema?->defaultSorts() ?? []
        );
    }

    /**
     * Apply sorts to subject
     */
    protected function applySorts(): void
    {
        if ($this->sortsApplied) {
            return;
        }

        if (!in_array(Capability::SORTS->value, $this->driver->capabilities(), true)) {
            $this->sortsApplied = true;
            return;
        }

        $sorts = $this->getEffectiveSorts();
        $requestedSorts = $this->parameters->getSorts();
        $defaultSorts = $this->getEffectiveDefaultSorts();

        if (empty($sorts)) {
            $this->sortsApplied = true;
            return;
        }

        $sortsIndex = [];
        foreach ($sorts as $sort) {
            $name = $sort->getName();
            $normalizedName = ltrim($name, '-');
            $sortsIndex[$normalizedName] = $sort;
        }

        $effectiveSorts = $requestedSorts->isEmpty()
            ? collect($defaultSorts)->map(fn($s) => new Sort($s))
            : $requestedSorts;

        $allowedSortNames = array_keys($sortsIndex);
        foreach ($effectiveSorts as $sortValue) {
            /** @var Sort $sortValue */
            if (!isset($sortsIndex[$sortValue->getField()])) {
                throw InvalidSortQuery::sortsNotAllowed(collect([$sortValue->getField()]), collect($allowedSortNames));
            }
        }

        $appliedFields = [];
        $uniqueSorts = $effectiveSorts->filter(function (Sort $sortValue) use (&$appliedFields): bool {
            $field = $sortValue->getField();
            if (isset($appliedFields[$field])) {
                return false;
            }
            $appliedFields[$field] = true;
            return true;
        });

        // Validate sort count limit
        $maxSortsCount = $this->config->getMaxSortsCount();
        if ($maxSortsCount !== null && $uniqueSorts->count() > $maxSortsCount) {
            throw MaxSortsCountExceeded::create($uniqueSorts->count(), $maxSortsCount);
        }

        foreach ($uniqueSorts as $sortValue) {
            /** @var Sort $sortValue */
            $sort = $sortsIndex[$sortValue->getField()];
            $this->subject = $this->driver->applySort($this->subject, $sort, $sortValue->getDirection());
        }

        $this->sortsApplied = true;
    }

    /**
     * Normalize sorts to SortDefinitionInterface
     *
     * @param array<SortDefinitionInterface|string> $sorts
     * @return array<SortDefinitionInterface>
     */
    protected function normalizeSorts(array $sorts): array
    {
        return array_map(
            fn($sort) => $this->driver->normalizeSort($sort),
            $sorts
        );
    }

    /**
     * Extract sort name from Sort object or string
     */
    protected function extractSortName(string|Sort $sort): string
    {
        if ($sort instanceof Sort) {
            $prefix = $sort->getDirection() === 'desc' ? '-' : '';
            return $prefix . $sort->getField();
        }
        return $sort;
    }
}
