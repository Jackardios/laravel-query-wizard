<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Concerns;

use Illuminate\Database\Eloquent\Model;
use Jackardios\QueryWizard\Support\ModelPostProcessor;

/**
 * Shared recursive post-processing for relation sparse fields and appends.
 *
 * @internal
 */
trait HandlesRelationPostProcessing
{
    /**
     * @return array{appends: array<string>, relations: array<string, mixed>}
     */
    protected function emptyAppendTree(): array
    {
        return ModelPostProcessor::emptyAppendTree();
    }

    /**
     * @return array{fields: array<string>, relations: array<string, mixed>}
     */
    protected function emptyRelationFieldTree(): array
    {
        return ModelPostProcessor::emptyFieldTree();
    }

    /**
     * Keep runtime attributes visible in the relation fieldsets that narrow their models.
     *
     * @param  array{fields: array<string>, relations: array<string, mixed>}  $fieldTree
     * @param  array<string, array<string, string>>  $attributesByOwner  Relation path => requested name => attribute
     * @return array{fields: array<string>, relations: array<string, mixed>}
     */
    protected function withRuntimeAttributesInFieldTree(array $fieldTree, array $attributesByOwner): array
    {
        return ModelPostProcessor::withRuntimeAttributes($fieldTree, $attributesByOwner);
    }

    /**
     * @param  Model|\Traversable<mixed>|array<mixed>  $results
     * @param  array{appends: array<string>, relations: array<string, mixed>}  $appendTree
     * @param  array{fields: array<string>, relations: array<string, mixed>}  $fieldTree
     */
    protected function applyRelationPostProcessingToResults(mixed $results, array $appendTree, array $fieldTree): void
    {
        ModelPostProcessor::applyToRelations($results, $appendTree, $fieldTree);
    }
}
