<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers\Eloquent\Includes;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Includes\AbstractInclude;

class CountInclude extends AbstractInclude
{
    public static function make(string $relation, ?string $alias = null): static
    {
        return new static($relation, $alias);
    }

    public function getType(): string
    {
        return 'count';
    }

    /**
     * @param Builder<\Illuminate\Database\Eloquent\Model> $subject
     * @param array<string> $fields
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public function apply(mixed $subject, array $fields = []): mixed
    {
        $subject->withCount($this->relation);

        return $subject;
    }
}
