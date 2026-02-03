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

    protected bool $subjectPrepared = false;
    protected bool $wizardApplied = false;

    protected function getContextMode(): string
    {
        return 'list';
    }

    /**
     * Ensure subject is converted to Builder/Relation (done once).
     */
    protected function ensureSubjectPrepared(): void
    {
        if (!$this->subjectPrepared) {
            $this->subject = $this->driver->prepareSubject($this->subject);
            $this->subjectPrepared = true;
        }
    }

    /**
     * Apply all wizard operations (filters, includes, sorts, fields) once.
     */
    protected function applyWizardOperations(): void
    {
        if ($this->wizardApplied) {
            return;
        }

        $this->ensureSubjectPrepared();

        $this->applyFilters();
        $this->applyIncludes();
        $this->applySorts();
        $this->applyFields();
        $this->validateAppends();

        $this->wizardApplied = true;
    }

    /**
     * Set a custom base query.
     *
     * Note: This resets the wizard state, so filters/sorts/includes will be re-applied.
     *
     * @param Builder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed> $builder
     */
    public function query(Builder|Relation $builder): static
    {
        $this->subject = $builder;
        $this->subjectPrepared = true; // Already a Builder/Relation
        $this->wizardApplied = false;  // Need to re-apply wizard operations
        return $this;
    }

    /**
     * Modify the underlying query builder directly.
     *
     * Use this for adding custom WHERE clauses, joins, etc.
     * The callback receives the prepared Builder/Relation.
     *
     * @param Closure(Builder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>): void $callback
     */
    public function modifyQuery(Closure $callback): static
    {
        $this->ensureSubjectPrepared();
        $callback($this->subject);
        return $this;
    }

    /**
     * Build the query by applying all wizard operations.
     *
     * Returns the underlying Builder/Relation with filters, includes, sorts, and fields applied.
     * Safe to call multiple times - operations are only applied once.
     *
     * @return Builder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>
     */
    public function build(): mixed
    {
        $this->applyWizardOperations();
        return $this->subject;
    }

    /**
     * Build and get results with appends applied.
     *
     * @return Collection<int, mixed>
     */
    public function get(): Collection
    {
        $result = $this->build()->get();
        return $this->applyAppendsToResult($result);
    }

    /**
     * Build and get first result with appends applied.
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
     * Build and paginate with appends applied.
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        $result = $this->build()->paginate($perPage);
        $this->driver->applyAppends($result->getCollection(), $this->getValidRequestedAppends());

        return $result;
    }

    /**
     * Build and simple paginate with appends applied.
     */
    public function simplePaginate(int $perPage = 15): Paginator
    {
        $result = $this->build()->simplePaginate($perPage);
        $this->driver->applyAppends($result->getCollection(), $this->getValidRequestedAppends());

        return $result;
    }

    /**
     * Build and cursor paginate with appends applied.
     */
    public function cursorPaginate(int $perPage = 15): CursorPaginator
    {
        $result = $this->build()->cursorPaginate($perPage);
        $this->driver->applyAppends($result->items(), $this->getValidRequestedAppends());

        return $result;
    }

    /**
     * Get the requested includes.
     *
     * @return Collection<int, string>
     */
    public function getIncludes(): Collection
    {
        return $this->parameters->getIncludes();
    }

    /**
     * Get the requested sorts.
     *
     * @return Collection<int, \Jackardios\QueryWizard\Values\Sort>
     */
    public function getSorts(): Collection
    {
        return $this->parameters->getSorts();
    }

    /**
     * Get the prepared filter values from request.
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
     * Proxy method calls to the underlying query builder.
     *
     * This allows chaining Eloquent methods directly on the wizard:
     *
     *     $wizard->where('active', true)->orderBy('name')->get();
     *
     * Methods that modify the builder (where, orderBy, etc.) return $this for chaining.
     * Terminal methods (count, exists, etc.) return their result directly.
     *
     * Note: Wizard operations (filters, sorts, includes from request) are applied
     * when you call a terminal method like get(), first(), paginate(), or build().
     *
     * @param string $name
     * @param array<int, mixed> $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments): mixed
    {
        $this->ensureSubjectPrepared();

        $result = $this->subject->$name(...$arguments);

        // If the method returns the same builder instance (mutating methods like where, orderBy),
        // return $this to allow continued chaining on the wizard
        if ($result === $this->subject) {
            return $this;
        }

        // If the method returns a new Builder/Relation (some methods clone),
        // update our subject and return $this for chaining
        if ($result instanceof Builder || $result instanceof Relation) {
            $this->subject = $result;
            return $this;
        }

        // For terminal methods (count, exists, sum, etc.), return the result directly
        return $result;
    }

    /**
     * Clone the wizard.
     */
    public function __clone(): void
    {
        parent::__clone();

        // Reset preparation flags for the cloned instance
        // so it can be modified independently
        $this->subjectPrepared = is_object($this->subject)
            && ($this->subject instanceof Builder || $this->subject instanceof Relation);
        $this->wizardApplied = false;
    }
}
