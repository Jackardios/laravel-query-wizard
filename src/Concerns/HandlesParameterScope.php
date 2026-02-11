<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Concerns;

use Jackardios\QueryWizard\QueryParametersManager;

/**
 * Shared request-scope helpers for parameter manager resolution.
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

        /** @var QueryParametersManager $scopedManager */
        $scopedManager = app(QueryParametersManager::class);

        return $parameters === $scopedManager ? $parameters : $scopedManager;
    }

    protected function resolveParametersScopeSignature(QueryParametersManager $parameters): string
    {
        $request = $parameters->getRequest();

        return spl_object_id($parameters).':'.($request !== null ? spl_object_id($request) : 0);
    }
}
