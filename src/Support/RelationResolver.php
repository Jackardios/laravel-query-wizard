<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Resolves Eloquent relations by dot-notation path with caching.
 */
final class RelationResolver
{
    /** @var array<string, Relation<Model, Model, mixed>|null> */
    private array $cache = [];

    public function __construct(
        private Model $rootModel
    ) {}

    /**
     * Resolve a relation by dot-notation path.
     *
     * @return Relation<Model, Model, mixed>|null
     */
    public function resolve(string $path): ?Relation
    {
        if (array_key_exists($path, $this->cache)) {
            return $this->cache[$path];
        }

        $model = $this->rootModel;
        $relation = null;

        foreach (explode('.', $path) as $segment) {
            if ($segment === '' || ! method_exists($model, $segment)) {
                return $this->cache[$path] = null;
            }

            try {
                $relation = $model->{$segment}();
            } catch (\Throwable) {
                return $this->cache[$path] = null;
            }

            if (! $relation instanceof Relation) {
                return $this->cache[$path] = null;
            }

            $model = $relation->getRelated();
        }

        return $this->cache[$path] = $relation;
    }

    /**
     * Clear the cache.
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }
}
