<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Includes;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Contracts\Definitions\IncludeDefinitionInterface;
use Jackardios\QueryWizard\Contracts\IncludeStrategyInterface;

class CountIncludeStrategy implements IncludeStrategyInterface
{
    /**
     * @param Builder<\Illuminate\Database\Eloquent\Model> $subject
     * @param array<string> $fields
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public function apply(mixed $subject, IncludeDefinitionInterface $include, array $fields = []): mixed
    {
        $subject->withCount($include->getRelation());

        return $subject;
    }
}
