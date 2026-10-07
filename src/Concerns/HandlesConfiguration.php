<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\Schema\ResourceSchemaInterface;
use Jackardios\QueryWizard\Support\NameConverter;
use Jackardios\QueryWizard\Support\NamePolicy;

/**
 * Shared configuration handling methods for query wizards.
 *
 * This trait contains utility methods used by both BaseQueryWizard
 * and ModelQueryWizard for handling configuration arrays and definitions.
 *
 * @internal Wiring for the wizards: the public methods and `@api` members it gives them are supported through the
 *           wizards; using the trait in another class is not.
 */
trait HandlesConfiguration
{
    use RequiresWizardContext;

    /**
     * The resource's model, when the wizard knows it without running a query; null skips the schema model check.
     */
    protected function resourceModel(): ?Model
    {
        return null;
    }

    private int $schemaReads = 0;

    /** @var array<string, true> Public configuration getters that are running */
    private array $configurationReads = [];

    private ?bool $normalizePublicInputMemo = null;

    private ?QueryWizardConfig $configSnapshot = null;

    /** @var list<array{array<string>, bool, NamePolicy}> */
    private array $denyPolicyMemo = [];

    /**
     * Set the resource schema for configuration.
     *
     * The schema provides default filters, sorts, includes, fields, and appends.
     * Explicit calls to allowed*() methods override schema definitions.
     *
     * @param  class-string<ResourceSchemaInterface>|ResourceSchemaInterface  $schema
     *
     * @throws \InvalidArgumentException When the schema describes another model
     */
    public function schema(string|ResourceSchemaInterface $schema): static
    {
        $schema = is_string($schema) ? app($schema) : $schema;
        $this->assertSchemaDescribesResourceModel($schema);
        $this->invalidateBuild();
        $this->schema = $schema;

        return $this;
    }

    /**
     * Call a schema method, or return null without a schema.
     *
     * While it runs, reconfiguring the wizard throws: the schema is describing
     * the configuration being resolved.
     *
     * @template TResult
     *
     * @param  Closure(ResourceSchemaInterface): TResult  $read
     * @return TResult|null
     */
    protected function readSchema(Closure $read): mixed
    {
        $schema = $this->getSchema();

        if ($schema === null) {
            return null;
        }

        $this->schemaReads++;

        try {
            return $read($schema);
        } finally {
            $this->schemaReads--;
        }
    }

    /**
     * @throws \LogicException When a schema method is reconfiguring the wizard it describes
     */
    protected function assertNotReadingSchema(): void
    {
        if ($this->schemaReads > 0) {
            throw new \LogicException(
                'A schema method cannot reconfigure the wizard it receives: return the definitions instead, '
                .'or configure the wizard where it is created.'
            );
        }
    }

    /**
     * A clone made inside a schema method or a getter is not inside it.
     */
    private function forgetReadsInProgress(): void
    {
        $this->schemaReads = 0;
        $this->configurationReads = [];
    }

    /**
     * Run a public configuration getter, refusing to enter it while it is already running.
     *
     * A getter that resolves its result from the schema calls schema methods (and, for
     * filter values, the filters' own callbacks); if one of those calls the same getter,
     * directly or through another schema method, the two would call each other without
     * end. Reading any other list from a schema method is fine.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $read
     * @return TResult
     *
     * @throws \LogicException When the getter is called from a schema method or callback it runs
     */
    private function readConfiguration(string $method, Closure $read): mixed
    {
        if (isset($this->configurationReads[$method])) {
            throw new \LogicException(
                "{$method}() was called from a schema method or callback that {$method}() itself runs: "
                .'they would call each other without end.'
            );
        }

        $this->configurationReads[$method] = true;

        try {
            return $read();
        } finally {
            unset($this->configurationReads[$method]);
        }
    }

    /**
     * @throws \InvalidArgumentException When the schema describes another model
     */
    protected function assertSchemaDescribesResourceModel(ResourceSchemaInterface $schema): void
    {
        $model = $this->resourceModel();
        $schemaModel = $schema->model();

        if ($model !== null && ! $model instanceof $schemaModel) {
            throw new \InvalidArgumentException(sprintf(
                'Schema %s describes %s, but the wizard queries %s.',
                $schema::class,
                $schemaModel,
                $model::class
            ));
        }
    }

    /**
     * Flatten definitions passed variadically or in arrays, at any depth; null items are skipped.
     *
     * @template T of object
     *
     * @param  array<array-key, mixed>  $items
     * @param  class-string<T>  $type
     * @return list<T|string>
     *
     * @throws \InvalidArgumentException When an item is neither a name nor a definition
     */
    protected function flattenDefinitions(array $items, string $type): array
    {
        $result = [];

        array_walk_recursive($items, function (mixed $item) use (&$result, $type): void {
            if ($item !== null && ! is_string($item) && ! $item instanceof $type) {
                throw new \InvalidArgumentException('Expected a name or '.$type.', got '.get_debug_type($item).'.');
            }

            if ($item !== null && $item !== '') {
                $result[] = $item;
            }
        });

        return $result;
    }

    /**
     * Flatten names passed variadically or in arrays, at any depth; null items are skipped.
     *
     * @param  array<array-key, mixed>  $items
     * @return list<string>
     *
     * @throws \InvalidArgumentException When an item is not a string
     */
    protected function flattenStringArray(array $items): array
    {
        $result = [];

        array_walk_recursive($items, function (mixed $item) use (&$result): void {
            if ($item !== null && ! is_string($item)) {
                throw new \InvalidArgumentException('Expected a name, got '.get_debug_type($item).'.');
            }

            if ($item !== null && $item !== '') {
                $result[] = $item;
            }
        });

        return $result;
    }

    /**
     * Resolve the default sparse-fieldset resource key.
     *
     * The schema type wins when a schema is set, otherwise the model's short class
     * name is used. The result is always normalized so it lines up with the public
     * parameter naming convention.
     *
     * @param  object|class-string  $model
     *
     * @api
     */
    protected function resolveDefaultResourceKey(object|string $model): string
    {
        $type = $this->getSchema()?->type();

        return $this->normalizePublicName($type ?? Str::camel(class_basename($model)));
    }

    protected function normalizePublicName(string $name): string
    {
        if ($name === '' || $name === '*' || ! $this->shouldNormalizePublicInput()) {
            return $name;
        }

        return NameConverter::toSnakeCase($name);
    }

    /**
     * A requested name or dot path in the form the wizard compares names in:
     * snake case when `naming.convert_parameters_to_snake_case` is on, keeping
     * a leading `-`.
     *
     * @api
     */
    protected function normalizePublicPath(string $path): string
    {
        if ($path === '' || $path === '*' || ! $this->shouldNormalizePublicInput()) {
            return $path;
        }

        $prefix = '';
        if (str_starts_with($path, '-')) {
            $prefix = '-';
            $path = substr($path, 1);
        }

        return $prefix.NameConverter::pathToSnakeCase($path);
    }

    protected function shouldNormalizePublicInput(): bool
    {
        return $this->normalizePublicInputMemo ??= $this->getConfig()->shouldConvertParametersToSnakeCase();
    }

    /**
     * The configuration as of the current build: config() is read once, when
     * the build first needs a setting.
     */
    private function configSnapshot(QueryWizardConfig $config): QueryWizardConfig
    {
        return $this->configSnapshot ??= $config->snapshot();
    }

    /**
     * Re-read memoized configuration on the next use.
     *
     * Called when a build starts and whenever the wizard is reconfigured, so a
     * runtime config change is picked up by the next build.
     */
    private function forgetConfigurationMemo(): void
    {
        $this->configSnapshot = null;
        $this->normalizePublicInputMemo = null;
        $this->denyPolicyMemo = [];
    }

    /**
     * Deny policy for a raw deny list, memoized by the list itself.
     *
     * Keying on the list (not on an invalidation hook) keeps the memo correct
     * even when a subclass reassigns a deny list without invalidating the build.
     *
     * @param  array<string>  $disallowed
     */
    private function denyPolicyFor(array $disallowed): NamePolicy
    {
        $normalize = $this->shouldNormalizePublicInput();

        foreach ($this->denyPolicyMemo as [$list, $listNormalize, $policy]) {
            if ($listNormalize === $normalize && $list === $disallowed) {
                return $policy;
            }
        }

        $policy = NamePolicy::denying($this->normalizePublicPaths($disallowed));

        if (count($this->denyPolicyMemo) >= 8) {
            array_shift($this->denyPolicyMemo);
        }

        $this->denyPolicyMemo[] = [$disallowed, $normalize, $policy];

        return $policy;
    }

    /**
     * @param  array<string>  $paths
     * @return list<string>
     */
    protected function normalizePublicPaths(array $paths): array
    {
        return array_values(array_map(
            fn (string $path): string => $this->normalizePublicPath($path),
            $paths
        ));
    }

    /**
     * Remove disallowed strings from array.
     *
     * @param  array<string>  $items
     * @param  array<string>  $disallowed
     * @return array<string>
     */
    protected function removeDisallowedStrings(array $items, array $disallowed): array
    {
        if (empty($disallowed)) {
            return $items;
        }

        $policy = $this->denyPolicyFor($disallowed);

        return array_values(array_filter(
            $items,
            fn (string $item): bool => ! $policy->denies($this->normalizePublicPath($item))
        ));
    }

    /**
     * Check if a name is disallowed.
     *
     * Supports wildcards:
     * - '*' blocks everything
     * - 'relation.*' blocks direct children (non-recursive)
     * - 'relation' blocks relation and all descendants (prefix match)
     *
     * @param  array<string>  $disallowed
     */
    protected function isNameDisallowed(string $name, array $disallowed): bool
    {
        return $this->denyPolicyFor($disallowed)->denies($this->normalizePublicPath($name));
    }

    /**
     * Two definitions of one kind can't share a public name: the request could only reach one of them.
     *
     * @throws \InvalidArgumentException When the name is already taken
     */
    private function assertUniqueDefinitionName(string $kind, string $name, bool $taken): void
    {
        if ($taken) {
            throw new \InvalidArgumentException(
                "More than one allowed {$kind} is named `{$name}`. Give each a unique name, for example with alias()."
            );
        }
    }

    /**
     * Limits guard client input; a developer default over one is a configuration error.
     */
    private function assertDefaultWithinLimit(string $subject, int $value, ?int $limit, string $limitKey): void
    {
        if ($limit !== null && $value > $limit) {
            throw new \InvalidArgumentException(
                "{$subject} ({$value}) exceeds the `limits.{$limitKey}` limit ({$limit}) for client input. "
                .'Raise the limit or reduce the defaults.'
            );
        }
    }

    /**
     * Check if array is associative.
     *
     * @param  array<mixed>  $array
     */
    protected function isAssociativeArray(array $array): bool
    {
        return ! array_is_list($array);
    }
}
