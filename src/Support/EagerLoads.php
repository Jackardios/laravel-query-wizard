<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Registers eager loads without discarding constraints that are already registered.
 *
 * `with('a.b')` registers an empty constraint for `a` and `with('a')` replaces
 * the constraint of `a`, so a later include used to wipe out a constraint set
 * by the developer, by a callback include or by an earlier include.
 *
 * @internal
 */
final class EagerLoads
{
    /**
     * Eager load `$path`, keeping every constraint already registered on it.
     *
     * Missing intermediate relations get an empty constraint. When `$path` is
     * already registered, the new constraint runs after the existing one on
     * the same relation query.
     *
     * @param  Builder<Model>|Relation<Model, Model, mixed>  $subject
     */
    public static function merge(Builder|Relation $subject, string $path, ?Closure $constraint = null): void
    {
        $builder = EloquentSubject::builder($subject);

        /** @var array<string, Closure> $parsed */
        $parsed = (fn (array $relations): array => $this->parseWithRelations($relations))
            ->call($builder, $constraint === null ? [$path] : [$path => $constraint]);

        $eagerLoads = $builder->getEagerLoads();
        $leaf = array_key_last($parsed);
        $constrainsLeaf = $constraint !== null || str_contains($path, ':');

        foreach ($parsed as $name => $closure) {
            if (! isset($eagerLoads[$name])) {
                $eagerLoads[$name] = $closure;

                continue;
            }

            if ($name !== $leaf || ! $constrainsLeaf) {
                continue;
            }

            $existing = $eagerLoads[$name];
            $eagerLoads[$name] = static function ($query) use ($existing, $closure): void {
                $existing($query);
                $closure($query);
            };
        }

        $builder->setEagerLoads($eagerLoads);
    }
}
