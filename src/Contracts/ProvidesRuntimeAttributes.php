<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Contracts;

/**
 * An include that adds attributes to the models it is attached to, such as a
 * custom count, which sparse fieldsets must keep visible.
 *
 * The attributes belong to the level that owns the include's relation: the
 * root models for `prices`, the `sides` models for `sides.prices`.
 */
interface ProvidesRuntimeAttributes
{
    /**
     * @return list<string>
     */
    public function runtimeAttributes(): array;
}
