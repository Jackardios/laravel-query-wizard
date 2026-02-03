<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers\Eloquent;

use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\Contracts\DefinitionNormalizerInterface;
use Jackardios\QueryWizard\Contracts\Definitions\FilterDefinitionInterface;
use Jackardios\QueryWizard\Contracts\Definitions\IncludeDefinitionInterface;
use Jackardios\QueryWizard\Contracts\Definitions\SortDefinitionInterface;
use Jackardios\QueryWizard\Drivers\Eloquent\Definitions\FilterDefinition;
use Jackardios\QueryWizard\Drivers\Eloquent\Definitions\IncludeDefinition;
use Jackardios\QueryWizard\Drivers\Eloquent\Definitions\SortDefinition;

class EloquentDefinitionNormalizer implements DefinitionNormalizerInterface
{
    public function __construct(
        protected QueryWizardConfig $config
    ) {}

    /**
     * Normalize a filter definition (string to FilterDefinition)
     */
    public function normalizeFilter(FilterDefinitionInterface|string $filter): FilterDefinitionInterface
    {
        if ($filter instanceof FilterDefinitionInterface) {
            return $filter;
        }

        return FilterDefinition::exact($filter);
    }

    /**
     * Normalize an include definition (string to IncludeDefinition)
     */
    public function normalizeInclude(IncludeDefinitionInterface|string $include): IncludeDefinitionInterface
    {
        $countSuffix = $this->config->getCountSuffix();

        if ($include instanceof IncludeDefinitionInterface) {
            // For count includes without alias, set the alias to relation + suffix
            if ($include->getType() === 'count' && $include->getAlias() === null) {
                return IncludeDefinition::count($include->getRelation(), $include->getRelation() . $countSuffix);
            }
            return $include;
        }

        if (str_ends_with($include, $countSuffix)) {
            $relation = substr($include, 0, -strlen($countSuffix));
            return IncludeDefinition::count($relation, $include);
        }

        return IncludeDefinition::relationship($include);
    }

    /**
     * Normalize a sort definition (string to SortDefinition)
     */
    public function normalizeSort(SortDefinitionInterface|string $sort): SortDefinitionInterface
    {
        if ($sort instanceof SortDefinitionInterface) {
            return $sort;
        }

        $property = ltrim($sort, '-');
        return SortDefinition::field($property, $sort);
    }
}
