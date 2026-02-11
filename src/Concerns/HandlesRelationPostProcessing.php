<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Shared recursive post-processing for relation sparse fields and appends.
 */
trait HandlesRelationPostProcessing
{
    /**
     * @return array{appends: array<string>, relations: array<string, mixed>}
     */
    protected function emptyAppendTree(): array
    {
        return [
            'appends' => [],
            'relations' => [],
        ];
    }

    /**
     * @return array{fields: array<string>, relations: array<string, mixed>}
     */
    protected function emptyRelationFieldTree(): array
    {
        return [
            'fields' => [],
            'relations' => [],
        ];
    }

    /**
     * @param  array{appends: array<string>, relations: array<string, mixed>}  $appendTree
     * @param  array{fields: array<string>, relations: array<string, mixed>}  $fieldTree
     */
    protected function hasRelationPostProcessingWork(array $appendTree, array $fieldTree): bool
    {
        return ! empty($fieldTree['relations'])
            || ! empty($appendTree['appends'])
            || ! empty($appendTree['relations']);
    }

    /**
     * @param  Model|\Traversable<mixed>|array<mixed>  $results
     * @param  array{appends: array<string>, relations: array<string, mixed>}  $appendTree
     * @param  array{fields: array<string>, relations: array<string, mixed>}  $fieldTree
     */
    protected function applyRelationPostProcessingToResults(
        mixed $results,
        array $appendTree,
        array $fieldTree
    ): void {
        if (! $this->hasRelationPostProcessingWork($appendTree, $fieldTree)) {
            return;
        }

        // Use global visited tracking across all items to prevent redundant processing
        // when the same model instance appears in multiple places (e.g., shared relations).
        $visited = [];

        if ($results instanceof Model) {
            $this->applyRelationPostProcessingRecursively($results, $appendTree, $fieldTree, $visited);

            return;
        }

        foreach ($results as $item) {
            if (! $item instanceof Model) {
                continue;
            }

            $this->applyRelationPostProcessingRecursively($item, $appendTree, $fieldTree, $visited);
        }
    }

    /**
     * @param  array{appends: array<string>, relations: array<string, mixed>}  $appendNode
     * @param  array{fields: array<string>, relations: array<string, mixed>}  $fieldNode
     * @param  array<int, bool>  $visited
     */
    protected function applyRelationPostProcessingRecursively(
        Model $model,
        array $appendNode,
        array $fieldNode,
        array &$visited
    ): void {
        $objectId = spl_object_id($model);
        if (isset($visited[$objectId])) {
            return;
        }
        $visited[$objectId] = true;

        $currentAppends = $appendNode['appends'];
        if (! empty($currentAppends)) {
            $model->append($currentAppends);
        }

        $emptyAppendNode = $this->emptyAppendTree();
        $emptyFieldNode = $this->emptyRelationFieldTree();

        foreach ($model->getRelations() as $relationName => $relatedData) {
            /** @var array{appends: array<string>, relations: array<string, mixed>}|null $childAppendNode */
            $childAppendNode = $appendNode['relations'][$relationName] ?? null;
            /** @var array{fields: array<string>, relations: array<string, mixed>}|null $childFieldNode */
            $childFieldNode = $fieldNode['relations'][$relationName] ?? null;

            if ($childAppendNode === null && $childFieldNode === null) {
                continue;
            }

            $visibleFields = $childFieldNode['fields'] ?? [];
            $shouldHideFields = ! empty($visibleFields) && ! in_array('*', $visibleFields, true);

            $nextAppendNode = $childAppendNode ?? $emptyAppendNode;
            $nextFieldNode = $childFieldNode ?? $emptyFieldNode;

            if ($relatedData instanceof Model) {
                if ($shouldHideFields) {
                    $this->hideModelAttributesExcept($relatedData, $visibleFields);
                }

                $this->applyRelationPostProcessingRecursively($relatedData, $nextAppendNode, $nextFieldNode, $visited);

                continue;
            }

            if (! is_iterable($relatedData)) {
                continue;
            }

            foreach ($relatedData as $item) {
                if (! $item instanceof Model) {
                    continue;
                }

                if ($shouldHideFields) {
                    $this->hideModelAttributesExcept($item, $visibleFields);
                }

                $this->applyRelationPostProcessingRecursively($item, $nextAppendNode, $nextFieldNode, $visited);
            }
        }
    }

    /**
     * @param  array<string>  $visibleFields
     */
    abstract protected function hideModelAttributesExcept(Model $model, array $visibleFields): void;
}
