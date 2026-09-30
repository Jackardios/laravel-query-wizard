<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Concerns;

use Jackardios\QueryWizard\QueryParametersManager;

/**
 * Shared request-scope helpers for parameter manager resolution.
 *
 * @internal Wiring for the wizards: the public methods and `@api` members it gives them are supported through the
 *           wizards; using the trait in another class is not.
 */
trait HandlesParameterScope
{
    /**
     * Resolve QueryParametersManager from container on each access.
     *
     * Enabled when wizard is created without explicit manager injection.
     */
    protected bool $resolveParametersFromContainer = false;

    protected function syncParametersManager(QueryParametersManager $parameters): QueryParametersManager
    {
        if (! $this->resolveParametersFromContainer) {
            return $parameters;
        }

        return app(QueryParametersManager::class);
    }

    protected function resolveParametersScopeSignature(QueryParametersManager $parameters): string
    {
        return (string) $parameters->getStateVersion();
    }
}
