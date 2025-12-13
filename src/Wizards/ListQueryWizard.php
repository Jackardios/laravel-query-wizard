<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Wizards;

use Closure;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Jackardios\QueryWizard\Wizards\Concerns\HandlesAppends;
use Jackardios\QueryWizard\Wizards\Concerns\HandlesFields;
use Jackardios\QueryWizard\Wizards\Concerns\HandlesFilters;
use Jackardios\QueryWizard\Wizards\Concerns\HandlesIncludes;
use Jackardios\QueryWizard\Wizards\Concerns\HandlesSorts;

class ListQueryWizard extends BaseQueryWizard
{
    use HandlesFilters;
    use HandlesSorts;
    use HandlesIncludes;
    use HandlesFields;
    use HandlesAppends;

    protected function getContextMode(): string
    {
        return 'list';
    }

    /**
     * Set a custom base query
     *
     * @param Builder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model> $builder
     */
    public function query(Builder|Relation $builder): static
    {
        $this->subject = $builder;
        return $this;
    }

    /**
     * @param Closure(Builder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model>): void $callback
     */
    public function modifyQuery(Closure $callback): static
    {
        // Ensure subject is prepared before modification
        $this->subject = $this->driver->prepareSubject($this->subject);
        $callback($this->subject);
        return $this;
    }

    /**
     * @return Builder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model>
     */
    public function build(): mixed
    {
        // Ensure subject is prepared for execution
        $this->subject = $this->driver->prepareSubject($this->subject);

        $this->applyFilters();
        $this->applyIncludes();
        $this->applySorts();
        $this->applyFields();
        $this->validateAppends();

        return $this->subject;
    }

    /**
     * Build and get results with appends applied
     *
     * @return Collection<int, mixed>
     */
    public function get(): Collection
    {
        $result = $this->build()->get();
        return $this->applyAppendsToResult($result);
    }

    /**
     * Build and get first result with appends applied
     */
    public function first(): mixed
    {
        $result = $this->build()->first();

        if ($result !== null) {
            $this->driver->applyAppends($result, $this->getValidRequestedAppends());
        }

        return $result;
    }

    /**
     * Build and paginate with appends applied
     *
     * @return LengthAwarePaginator<\Illuminate\Database\Eloquent\Model>
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<\Illuminate\Database\Eloquent\Model> $result */
        $result = $this->build()->paginate($perPage);
        $this->driver->applyAppends($result->getCollection(), $this->getValidRequestedAppends());

        return $result;
    }

    /**
     * Build and simple paginate with appends applied
     *
     * @return Paginator<\Illuminate\Database\Eloquent\Model>
     */
    public function simplePaginate(int $perPage = 15): Paginator
    {
        /** @var Paginator<\Illuminate\Database\Eloquent\Model> $result */
        $result = $this->build()->simplePaginate($perPage);
        /** @phpstan-ignore method.notFound */
        $this->driver->applyAppends($result->getCollection(), $this->getValidRequestedAppends());

        return $result;
    }

    /**
     * Build and cursor paginate with appends applied
     *
     * @return CursorPaginator<\Illuminate\Database\Eloquent\Model>
     */
    public function cursorPaginate(int $perPage = 15): CursorPaginator
    {
        /** @var CursorPaginator<\Illuminate\Database\Eloquent\Model> $result */
        $result = $this->build()->cursorPaginate($perPage);
        $this->driver->applyAppends($result->items(), $this->getValidRequestedAppends());

        return $result;
    }

    /**
     * Get the requested includes
     *
     * @return Collection<int, string>
     */
    public function getIncludes(): Collection
    {
        return $this->parameters->getIncludes();
    }

    /**
     * Get the requested sorts
     *
     * @return Collection<int, \Jackardios\QueryWizard\Values\Sort>
     */
    public function getSorts(): Collection
    {
        return $this->parameters->getSorts();
    }

    /**
     * Get the prepared filter values from request
     *
     * @return Collection<string, mixed>
     */
    public function getFilters(): Collection
    {
        $filters = $this->getEffectiveFilters();

        $result = collect();

        foreach ($filters as $filter) {
            $name = $filter->getName();
            $value = $this->parameters->getFilterValue($name) ?? $filter->getDefault();

            if ($value !== null) {
                $result[$name] = $filter->prepareValue($value);
            }
        }

        return $result;
    }

    /**
     * Proxy method calls to the built query
     *
     * @param string $name
     * @param array<int, mixed> $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments): mixed
    {
        return $this->build()->$name(...$arguments);
    }
}
