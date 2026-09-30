<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Contracts;

/**
 * An include whose apply() eager loads the relation getRelation() names.
 *
 * The wizard treats it like a relationship include: `fields[relation]` narrows
 * the eager load's select after apply() ran, relation fields and appends are
 * validated against it, and disallowing the relation path denies it under any alias.
 *
 * @api
 */
interface EagerLoadsRelation extends IncludeInterface {}
