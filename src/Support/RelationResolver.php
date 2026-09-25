<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Resolves Eloquent relations by dot-notation path with caching.
 *
 * @internal
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

        $lastDot = strrpos($path, '.');

        if ($lastDot === false) {
            $model = $this->rootModel;
            $segment = $path;
        } else {
            $model = $this->resolve(substr($path, 0, $lastDot))?->getRelated();
            $segment = substr($path, $lastDot + 1);
        }

        if ($model === null || $segment === '' || (! method_exists($model, $segment) && $model->relationResolver($model::class, $segment) === null)) {
            return $this->cache[$path] = null;
        }

        try {
            $relation = $model->{$segment}();
        } catch (\Throwable) {
            return $this->cache[$path] = null;
        }

        return $this->cache[$path] = $relation instanceof Relation ? $relation : null;
    }

    public function getRootModel(): Model
    {
        return $this->rootModel;
    }
}
