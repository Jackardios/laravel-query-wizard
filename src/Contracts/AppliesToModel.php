<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * An include that ModelQueryWizard can apply to a model that is already loaded.
 *
 * Relationship, count and exists includes are loaded by the wizard itself; any
 * other include must implement this to be requested through ModelQueryWizard.
 *
 * @api
 */
interface AppliesToModel extends IncludeInterface
{
    public function applyToModel(Model $model): void;
}
