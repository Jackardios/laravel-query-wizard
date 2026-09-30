<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Contracts;

use Jackardios\QueryWizard\Schema\ResourceSchemaInterface;

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
     * Get the resource key for sparse fieldsets.
     *
     * Used as the key in ?fields[type]=id,name
     */
    public function getResourceKey(): string;

    /**
     * Set the resource schema the wizard falls back to for what is not configured explicitly.
     *
     * @param  class-string<ResourceSchemaInterface>|ResourceSchemaInterface  $schema
     *
     * @throws \InvalidArgumentException When the schema describes another model
     */
    public function schema(string|ResourceSchemaInterface $schema): static;

    public function getSchema(): ?ResourceSchemaInterface;

    /**
     * Set allowed includes, replacing the schema's and any earlier call; addAllowedIncludes() adds instead.
     *
     * @param  IncludeInterface|string|array<IncludeInterface|string>  ...$includes
     */
    public function allowedIncludes(IncludeInterface|string|array ...$includes): static;

    /**
     * Add to the allowed includes: the list set with allowedIncludes(), or the schema's when none was set.
     *
     * @param  IncludeInterface|string|array<IncludeInterface|string>  ...$includes
     */
    public function addAllowedIncludes(IncludeInterface|string|array ...$includes): static;

    /**
     * Disallow includes, including the schema's; repeated calls add to the list.
     *
     * @param  string|array<string>  ...$names
     */
    public function disallowedIncludes(string|array ...$names): static;

    /**
     * Set default includes.
     *
     * Replaces the schema defaults; call it without arguments for no defaults.
     *
     * @param  string|array<string>  ...$names
     */
    public function defaultIncludes(string|array ...$names): static;

    /**
     * Set allowed fields, replacing the schema's and any earlier call; addAllowedFields() adds instead.
     *
     * @param  string|array<string>  ...$fields
     */
    public function allowedFields(string|array ...$fields): static;

    /**
     * Add to the allowed fields: the list set with allowedFields(), or the schema's when none was set.
     *
     * @param  string|array<string>  ...$fields
     */
    public function addAllowedFields(string|array ...$fields): static;

    /**
     * Disallow fields, including the schema's; repeated calls add to the list.
     *
     * @param  string|array<string>  ...$names
     */
    public function disallowedFields(string|array ...$names): static;

    /**
     * Set default fields.
     *
     * Replaces the schema defaults and the `fields.use_allowed_as_default` fallback;
     * call it without arguments for no defaults (all columns).
     *
     * @param  string|array<string>  ...$fields
     */
    public function defaultFields(string|array ...$fields): static;

    /**
     * Set allowed appends, replacing the schema's and any earlier call; addAllowedAppends() adds instead.
     *
     * @param  string|array<string>  ...$appends
     */
    public function allowedAppends(string|array ...$appends): static;

    /**
     * Add to the allowed appends: the list set with allowedAppends(), or the schema's when none was set.
     *
     * @param  string|array<string>  ...$appends
     */
    public function addAllowedAppends(string|array ...$appends): static;

    /**
     * Disallow appends, including the schema's; repeated calls add to the list.
     *
     * @param  string|array<string>  ...$names
     */
    public function disallowedAppends(string|array ...$names): static;

    /**
     * Set default appends.
     *
     * Replaces the schema defaults; call it without arguments for no defaults.
     *
     * @param  string|array<string>  ...$appends
     */
    public function defaultAppends(string|array ...$appends): static;
}
