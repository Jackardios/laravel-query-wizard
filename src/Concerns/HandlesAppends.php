<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Concerns;

use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\Exceptions\InvalidAppendQuery;
use Jackardios\QueryWizard\Exceptions\MaxAppendsCountExceeded;
use Jackardios\QueryWizard\QueryParametersManager;
use Jackardios\QueryWizard\Schema\ResourceSchemaInterface;

/**
 * Shared append handling logic for query wizards.
 *
 * This trait contains methods used by both BaseQueryWizard
 * and ModelQueryWizard for handling appends functionality.
 *
 * Classes using this trait must provide:
 * - getConfig(): QueryWizardConfig
 * - $parameters: QueryParametersManager property
 * - $schema: ?ResourceSchemaInterface property
 * - $allowedAppends: array property
 * - $disallowedAppends: array property
 * - $defaultAppends: array property
 * - $allowedAppendsExplicitlySet: bool property
 */
trait HandlesAppends
{
    /**
     * Get the configuration instance.
     */
    abstract protected function getConfig(): QueryWizardConfig;

    /**
     * Apply appends to a collection of results.
     *
     * Call this after executing the query to apply allowed appends.
     *
     * @template T of \Traversable<mixed>|array<mixed>
     *
     * @param  T  $results
     * @return T
     */
    public function applyAppendsTo(mixed $results): mixed
    {
        $appends = $this->getValidRequestedAppends();
        if (empty($appends)) {
            return $results;
        }

        foreach ($results as $item) {
            if (is_object($item)) {
                $this->applyAppendsRecursively($item, $appends);
            }
        }

        return $results;
    }

    /**
     * Apply appends recursively to model and its loaded relations.
     *
     * @param  array<string>  $appends
     * @param  array<int, bool>  $visited  Object IDs already processed (prevents circular reference loops)
     */
    protected function applyAppendsRecursively(object $model, array $appends, array &$visited = []): void
    {
        // Prevent circular reference infinite loops
        $objectId = spl_object_id($model);
        if (isset($visited[$objectId])) {
            return;
        }
        $visited[$objectId] = true;

        $rootAppends = [];
        /** @var array<string, array<string>> $nestedAppends */
        $nestedAppends = [];

        foreach ($appends as $append) {
            if (str_contains($append, '.')) {
                [$relation, $rest] = explode('.', $append, 2);
                $nestedAppends[$relation][] = $rest;
            } else {
                $rootAppends[] = $append;
            }
        }

        // Apply root appends
        if (! empty($rootAppends) && method_exists($model, 'append')) {
            $model->append($rootAppends);
        }

        // Apply nested appends to loaded relations
        foreach ($nestedAppends as $relation => $relationAppends) {
            if (! method_exists($model, 'relationLoaded') || ! $model->relationLoaded($relation)) {
                continue;
            }

            if (! method_exists($model, 'getRelation')) {
                continue;
            }

            /** @var mixed $related */
            $related = $model->getRelation($relation);
            if ($related === null) {
                continue;
            }

            if ($related instanceof \Traversable || is_array($related)) {
                foreach ($related as $item) {
                    if (is_object($item)) {
                        $this->applyAppendsRecursively($item, $relationAppends, $visited);
                    }
                }
            } elseif (is_object($related)) {
                $this->applyAppendsRecursively($related, $relationAppends, $visited);
            }
        }
    }

    /**
     * Check if an append name is allowed.
     *
     * @param  string  $append  Requested append name
     * @param  array<string>  $allowed  Allowed appends list
     */
    protected function isAppendAllowed(string $append, array $allowed): bool
    {
        // Exact match
        if (in_array($append, $allowed, true)) {
            return true;
        }

        // Wildcard support: if 'relation.*' is allowed, allow 'relation.anything'
        $parts = explode('.', $append);
        for ($i = count($parts) - 1; $i > 0; $i--) {
            $wildcardPattern = implode('.', array_slice($parts, 0, $i)).'.*';
            if (in_array($wildcardPattern, $allowed, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get valid requested appends.
     *
     * @return array<string>
     */
    protected function getValidRequestedAppends(): array
    {
        $allowed = $this->getEffectiveAppends();
        $requested = $this->getParametersManager()->getAppends()->all();
        $defaults = $this->getEffectiveDefaultAppends();

        // Validate appends count limit
        $this->validateAppendsLimit(count($requested));

        // Validate requested appends - if none allowed but some requested, throw exception
        if (! empty($requested)) {
            $invalidAppends = array_filter($requested, fn ($r) => ! $this->isAppendAllowed($r, $allowed));
            if (! empty($invalidAppends) && ! $this->getConfig()->isInvalidAppendQueryExceptionDisabled()) {
                throw InvalidAppendQuery::appendsNotAllowed(
                    collect($invalidAppends),
                    collect($allowed)
                );
            }
        }

        // Filter by allowed and depth
        $maxDepth = $this->getConfig()->getMaxAppendDepth();

        $validRequested = array_values(array_filter($requested, function ($append) use ($allowed, $maxDepth) {
            // Check allowed
            if (! $this->isAppendAllowed($append, $allowed)) {
                return false;
            }
            // Check depth
            if ($maxDepth !== null) {
                $depth = substr_count($append, '.') + 1;
                if ($depth > $maxDepth) {
                    return false;
                }
            }

            return true;
        }));

        return array_unique(array_merge($defaults, $validRequested));
    }

    /**
     * Validate appends count limit.
     */
    protected function validateAppendsLimit(int $count): void
    {
        $limit = $this->getConfig()->getMaxAppendsCount();
        if ($limit !== null && $count > $limit) {
            throw MaxAppendsCountExceeded::create($count, $limit);
        }
    }

    /**
     * Get effective appends.
     *
     * If allowedAppends() was called explicitly, use those (even if empty).
     * Otherwise, fall back to schema appends (if any).
     * Empty result means all appends are forbidden.
     *
     * @return array<string>
     */
    protected function getEffectiveAppends(): array
    {
        $appends = $this->allowedAppendsExplicitlySet
            ? $this->allowedAppends
            : ($this->getSchema()?->appends($this) ?? []);

        return $this->removeDisallowedStrings($appends, $this->disallowedAppends);
    }

    /**
     * Get effective default appends.
     *
     * @return array<string>
     */
    protected function getEffectiveDefaultAppends(): array
    {
        return ! empty($this->defaultAppends)
            ? $this->defaultAppends
            : ($this->getSchema()?->defaultAppends($this) ?? []);
    }

    /**
     * Get the parameters manager.
     */
    abstract protected function getParametersManager(): QueryParametersManager;

    /**
     * Get the schema instance.
     */
    abstract protected function getSchema(): ?ResourceSchemaInterface;
}
