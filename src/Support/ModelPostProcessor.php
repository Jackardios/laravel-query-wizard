<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Applies sparse fieldsets and appends to loaded models and their relations.
 *
 * @internal
 */
final class ModelPostProcessor
{
    /**
     * @return array{appends: array<string>, relations: array<string, mixed>}
     */
    public static function emptyAppendTree(): array
    {
        return [
            'appends' => [],
            'relations' => [],
        ];
    }

    /**
     * @return array{fields: array<string>, relations: array<string, mixed>}
     */
    public static function emptyFieldTree(): array
    {
        return [
            'fields' => [],
            'relations' => [],
        ];
    }

    /**
     * Hide all model attributes except explicitly visible ones.
     *
     * @param  array<string>  $visibleFields
     */
    public static function hideAttributesExcept(Model $model, array $visibleFields): void
    {
        $fieldsToHide = array_keys(array_diff_key($model->getAttributes(), array_flip($visibleFields)));

        if ($fieldsToHide !== []) {
            $model->makeHidden($fieldsToHide);
        }
    }

    /**
     * Keep runtime attributes visible in the relation fieldsets that narrow their models.
     *
     * @param  array{fields: array<string>, relations: array<string, mixed>}  $fieldTree
     * @param  array<string, array<string, string>>  $attributesByOwner  Relation path => requested name => attribute
     * @return array{fields: array<string>, relations: array<string, mixed>}
     */
    public static function withRuntimeAttributes(array $fieldTree, array $attributesByOwner): array
    {
        foreach ($attributesByOwner as $relationPath => $attributes) {
            if ($relationPath !== '') {
                $fieldTree = self::withFieldsInNode($fieldTree, explode('.', $relationPath), array_values($attributes));
            }
        }

        return $fieldTree;
    }

    /**
     * @param  array{appends: array<string>, relations: array<string, mixed>}  $appendTree
     * @param  array{fields: array<string>, relations: array<string, mixed>}  $fieldTree
     */
    public static function hasRelationWork(array $appendTree, array $fieldTree): bool
    {
        return ! empty($fieldTree['relations'])
            || ! empty($appendTree['appends'])
            || ! empty($appendTree['relations']);
    }

    /**
     * Apply appends and relation sparse fieldsets in a single traversal.
     *
     * @param  Model|\Traversable<mixed>|array<mixed>  $results
     * @param  array{appends: array<string>, relations: array<string, mixed>}  $appendTree
     * @param  array{fields: array<string>, relations: array<string, mixed>}  $fieldTree
     */
    public static function applyToRelations(mixed $results, array $appendTree, array $fieldTree): void
    {
        if (! self::hasRelationWork($appendTree, $fieldTree)) {
            return;
        }

        // Tracked across all items, so a model shared by several of them is processed once.
        $visited = [];

        if ($results instanceof Model) {
            self::applyRecursively($results, $appendTree, $fieldTree, $visited);

            return;
        }

        foreach ($results as $item) {
            if ($item instanceof Model) {
                self::applyRecursively($item, $appendTree, $fieldTree, $visited);
            }
        }
    }

    /**
     * @param  array{appends: array<string>, relations: array<string, mixed>}  $appendNode
     * @param  array{fields: array<string>, relations: array<string, mixed>}  $fieldNode
     * @param  array<int, bool>  $visited
     */
    private static function applyRecursively(Model $model, array $appendNode, array $fieldNode, array &$visited): void
    {
        $objectId = spl_object_id($model);
        if (isset($visited[$objectId])) {
            return;
        }
        $visited[$objectId] = true;

        if (! empty($appendNode['appends'])) {
            $model->append($appendNode['appends']);
        }

        foreach ($model->getRelations() as $relationName => $relatedData) {
            /** @var array{appends: array<string>, relations: array<string, mixed>}|null $childAppendNode */
            $childAppendNode = $appendNode['relations'][$relationName] ?? null;
            /** @var array{fields: array<string>, relations: array<string, mixed>}|null $childFieldNode */
            $childFieldNode = $fieldNode['relations'][$relationName] ?? null;

            if ($childAppendNode === null && $childFieldNode === null) {
                continue;
            }

            $visibleFields = $childFieldNode['fields'] ?? [];
            $shouldHideFields = $childFieldNode !== null && ! in_array('*', $visibleFields, true);

            $nextAppendNode = $childAppendNode ?? self::emptyAppendTree();
            $nextFieldNode = $childFieldNode ?? self::emptyFieldTree();

            if ($relatedData instanceof Model) {
                if ($shouldHideFields) {
                    self::hideAttributesExcept($relatedData, $visibleFields);
                }

                self::applyRecursively($relatedData, $nextAppendNode, $nextFieldNode, $visited);

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
                    self::hideAttributesExcept($item, $visibleFields);
                }

                self::applyRecursively($item, $nextAppendNode, $nextFieldNode, $visited);
            }
        }
    }

    /**
     * Add fields to the node at a relation path, unless that node is missing
     * or already takes every field.
     *
     * @param  array{fields: array<string>, relations: array<string, mixed>}  $node
     * @param  list<string>  $segments
     * @param  list<string>  $fields
     * @return array{fields: array<string>, relations: array<string, mixed>}
     */
    private static function withFieldsInNode(array $node, array $segments, array $fields): array
    {
        $segment = array_shift($segments);

        if ($segment === null) {
            if (! in_array('*', $node['fields'], true)) {
                $node['fields'] = array_values(array_unique(array_merge($node['fields'], $fields)));
            }

            return $node;
        }

        $child = $node['relations'][$segment] ?? null;

        if (! self::isFieldTreeNode($child)) {
            return $node;
        }

        $node['relations'][$segment] = self::withFieldsInNode($child, $segments, $fields);

        return $node;
    }

    /**
     * @phpstan-assert-if-true array{fields: array<string>, relations: array<string, mixed>} $node
     */
    private static function isFieldTreeNode(mixed $node): bool
    {
        return is_array($node) && is_array($node['fields'] ?? null) && is_array($node['relations'] ?? null);
    }
}
