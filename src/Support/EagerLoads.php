<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use ReflectionClass;
use ReflectionFunction;

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
    private const MAX_CAPTURE_DEPTH = 8;

    private static ?string $builderFile = null;

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

    /**
     * Run `$apply` on the subject, keeping the constraints it replaced with ones Laravel generated.
     *
     * `with('a.b')`, `with('a')` and `with('a:id')` replace the constraint of `a`
     * with a closure of their own, discarding the existing one by accident. Those
     * closures now run after the existing constraint. A closure written by the
     * developer (including one wrapped by `with(['a' => fn])`) replaces the
     * existing constraint as before.
     *
     * @param  Builder<Model>|Relation<Model, Model, mixed>  $subject
     * @param  Closure(Builder<Model>|Relation<Model, Model, mixed>): mixed  $apply
     */
    public static function preserving(Builder|Relation $subject, Closure $apply): mixed
    {
        $before = EloquentSubject::builder($subject)->getEagerLoads();
        $result = $apply($subject);

        if ($before === [] || (! $result instanceof Builder && ! $result instanceof Relation)) {
            return $result;
        }

        $builder = EloquentSubject::builder($result);
        $after = $builder->getEagerLoads();
        $changed = false;

        foreach ($after as $name => $closure) {
            $existing = $before[$name] ?? null;

            if ($existing === null || $existing === $closure || ! self::isFrameworkGenerated($closure)) {
                continue;
            }

            $after[$name] = static function ($query) use ($existing, $closure): void {
                $existing($query);
                $closure($query);
            };
            $changed = true;
        }

        if ($changed) {
            $builder->setEagerLoads($after);
        }

        return $result;
    }

    /**
     * Whether the closure was created by the Eloquent builder itself and captures nothing else.
     */
    public static function isFrameworkGenerated(Closure $closure, int $depth = 0): bool
    {
        if ($depth > self::MAX_CAPTURE_DEPTH) {
            return false;
        }

        $reflection = new ReflectionFunction($closure);

        if (
            $reflection->getClosureScopeClass()?->getName() !== Builder::class
            || $reflection->getFileName() !== self::builderFile()
        ) {
            return false;
        }

        foreach ($reflection->getClosureUsedVariables() as $value) {
            if (! self::isFrameworkData($value, $depth + 1)) {
                return false;
            }
        }

        return true;
    }

    private static function isFrameworkData(mixed $value, int $depth): bool
    {
        if ($value instanceof Closure) {
            return self::isFrameworkGenerated($value, $depth);
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (! self::isFrameworkData($item, $depth)) {
                    return false;
                }
            }

            return true;
        }

        return $value === null || is_scalar($value);
    }

    private static function builderFile(): string
    {
        return self::$builderFile ??= (string) (new ReflectionClass(Builder::class))->getFileName();
    }
}
