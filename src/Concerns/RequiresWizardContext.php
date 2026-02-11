<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Concerns;

use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\QueryParametersManager;
use Jackardios\QueryWizard\Schema\ResourceSchemaInterface;

/**
 * Declares abstract methods for accessing wizard context.
 *
 * Used by Handles* traits to avoid repeating the same abstract declarations.
 */
trait RequiresWizardContext
{
    abstract protected function getConfig(): QueryWizardConfig;

    abstract protected function getParametersManager(): QueryParametersManager;

    abstract protected function getSchema(): ?ResourceSchemaInterface;
}
