<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Schema;

use Illuminate\Database\Eloquent\Model;
use Jackardios\QueryWizard\Contracts\FilterInterface;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Contracts\QueryWizardInterface;
use Jackardios\QueryWizard\Contracts\SortInterface;

/**
 * Interface for resource schemas.
 *
 * A schema defines what filters, sorts, includes, fields, and appends
 * are allowed for a resource. Schemas can be shared between different
 * wizard types (EloquentQueryWizard, ModelQueryWizard).
 *
 * All methods accept a $wizard parameter to allow customization
 * based on the wizard type being used.
 */
interface ResourceSchemaInterface
{
    /**
     * Get the model class name.
     *
     * @return class-string<Model>
     */
    public function model(): string;

    /**
     * Get the resource type for sparse fieldsets.
     *
     * Used as the key in ?fields[type]=id,name
     */
    public function type(): string;

    // === Common (used by ALL wizards including ModelQueryWizard) ===

    /**
     * Get allowed includes.
     *
     * @param QueryWizardInterface $wizard The wizard requesting includes (for conditional logic)
     * @return array<IncludeInterface|string>
     */
    public function includes(QueryWizardInterface $wizard): array;

    /**
     * Get allowed fields.
     *
     * @param QueryWizardInterface $wizard The wizard requesting fields (for conditional logic)
     * @return array<string>
     */
    public function fields(QueryWizardInterface $wizard): array;

    /**
     * Get allowed appends.
     *
     * @param QueryWizardInterface $wizard The wizard requesting appends (for conditional logic)
     * @return array<string>
     */
    public function appends(QueryWizardInterface $wizard): array;

    /**
     * Get default includes to always load.
     *
     * @param QueryWizardInterface $wizard The wizard requesting default includes (for conditional logic)
     * @return array<string>
     */
    public function defaultIncludes(QueryWizardInterface $wizard): array;

    /**
     * Get default appends to always add.
     *
     * @param QueryWizardInterface $wizard The wizard requesting default appends (for conditional logic)
     * @return array<string>
     */
    public function defaultAppends(QueryWizardInterface $wizard): array;

    // === Specific to List wizards (ignored by ModelQueryWizard) ===

    /**
     * Get allowed filters.
     *
     * @param QueryWizardInterface $wizard The wizard requesting filters (for conditional logic)
     * @return array<FilterInterface|string>
     */
    public function filters(QueryWizardInterface $wizard): array;

    /**
     * Get allowed sorts.
     *
     * @param QueryWizardInterface $wizard The wizard requesting sorts (for conditional logic)
     * @return array<SortInterface|string>
     */
    public function sorts(QueryWizardInterface $wizard): array;

    /**
     * Get default sorts to apply when none requested.
     *
     * @param QueryWizardInterface $wizard The wizard requesting default sorts (for conditional logic)
     * @return array<string>
     */
    public function defaultSorts(QueryWizardInterface $wizard): array;
}
