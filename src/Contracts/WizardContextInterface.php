<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Contracts;

use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\QueryParametersManager;
use Jackardios\QueryWizard\Schema\ResourceSchemaInterface;

/**
 * Interface for accessing wizard context (config, parameters, schema).
 *
 * Both BaseQueryWizard and ModelQueryWizard implement this interface.
 */
interface WizardContextInterface
{
    public function getConfig(): QueryWizardConfig;

    public function getParametersManager(): QueryParametersManager;

    public function getSchema(): ?ResourceSchemaInterface;
}
