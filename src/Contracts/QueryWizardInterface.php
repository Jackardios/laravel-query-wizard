<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Contracts;

/**
 * Common interface for query wizards.
 *
 * Defines the shared configuration API between different wizard types.
 * Both BaseQueryWizard (for building queries) and ModelQueryWizard
 * (for processing loaded models) implement this interface.
 */
interface QueryWizardInterface
{
    /**
     * Set allowed includes.
     *
     * @param  IncludeInterface|string|array<IncludeInterface|string>  ...$includes
     */
    public function allowedIncludes(IncludeInterface|string|array ...$includes): static;

    /**
     * Set disallowed includes (to override schema).
     *
     * @param  string|array<string>  ...$names
     */
    public function disallowedIncludes(string|array ...$names): static;

    /**
     * Set default includes.
     *
     * @param  string|array<string>  ...$names
     */
    public function defaultIncludes(string|array ...$names): static;

    /**
     * Set allowed fields.
     *
     * @param  string|array<string>  ...$fields
     */
    public function allowedFields(string|array ...$fields): static;

    /**
     * Set disallowed fields (to override schema).
     *
     * @param  string|array<string>  ...$names
     */
    public function disallowedFields(string|array ...$names): static;

    /**
     * Set allowed appends.
     *
     * @param  string|array<string>  ...$appends
     */
    public function allowedAppends(string|array ...$appends): static;

    /**
     * Set disallowed appends (to override schema).
     *
     * @param  string|array<string>  ...$names
     */
    public function disallowedAppends(string|array ...$names): static;

    /**
     * Set default appends.
     *
     * @param  string|array<string>  ...$appends
     */
    public function defaultAppends(string|array ...$appends): static;
}
