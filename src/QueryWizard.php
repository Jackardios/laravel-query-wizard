<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard;

use Illuminate\Database\Eloquent\Model;
use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\Contracts\ResourceSchemaInterface;
use Jackardios\QueryWizard\Drivers\DriverRegistry;
use Jackardios\QueryWizard\Wizards\ItemQueryWizard;
use Jackardios\QueryWizard\Wizards\ListQueryWizard;

class QueryWizard
{
    /**
     * Create a list query wizard from a schema
     *
     * @param string|ResourceSchemaInterface $schema Schema instance or class name
     */
    public static function forList(
        string|ResourceSchemaInterface $schema,
        ?QueryParametersManager $parameters = null
    ): ListQueryWizard {
        $schema = static::resolveSchema($schema);
        $parameters ??= app(QueryParametersManager::class);
        $config = app(QueryWizardConfig::class);

        $driverName = $schema->driver();
        $driver = app(DriverRegistry::class)->get($driverName);

        $modelClass = $schema->model();

        return new ListQueryWizard($modelClass, $driver, $parameters, $config, $schema);
    }

    /**
     * Create an item query wizard from a schema
     *
     * @param string|ResourceSchemaInterface $schema Schema instance or class name
     * @param int|string|Model $key Model ID or already loaded model
     */
    public static function forItem(
        string|ResourceSchemaInterface $schema,
        int|string|Model $key,
        ?QueryParametersManager $parameters = null
    ): ItemQueryWizard {
        $schema = static::resolveSchema($schema);
        $parameters ??= app(QueryParametersManager::class);
        $config = app(QueryWizardConfig::class);

        $driverName = $schema->driver();
        $driver = app(DriverRegistry::class)->get($driverName);

        return new ItemQueryWizard($schema, $key, $driver, $parameters, $config);
    }

    /**
     * @param class-string<Model>|\Illuminate\Database\Eloquent\Builder<Model>|\Illuminate\Database\Eloquent\Relations\Relation<Model, Model, mixed>|Model $subject
     */
    public static function for(mixed $subject, ?QueryParametersManager $parameters = null): ListQueryWizard
    {
        $parameters ??= app(QueryParametersManager::class);
        $config = app(QueryWizardConfig::class);
        $driver = app(DriverRegistry::class)->resolve($subject);

        return new ListQueryWizard($subject, $driver, $parameters, $config);
    }

    /**
     * @param class-string<Model>|\Illuminate\Database\Eloquent\Builder<Model>|\Illuminate\Database\Eloquent\Relations\Relation<Model, Model, mixed>|Model $subject
     */
    public static function using(string $driverName, mixed $subject, ?QueryParametersManager $parameters = null): ListQueryWizard
    {
        $parameters ??= app(QueryParametersManager::class);
        $config = app(QueryWizardConfig::class);
        $driver = app(DriverRegistry::class)->get($driverName);

        return new ListQueryWizard($subject, $driver, $parameters, $config);
    }

    /**
     * @param class-string<ResourceSchemaInterface>|ResourceSchemaInterface $schema
     */
    protected static function resolveSchema(string|ResourceSchemaInterface $schema): ResourceSchemaInterface
    {
        if (is_string($schema)) {
            /** @var ResourceSchemaInterface $schema */
            $schema = app($schema);
        }

        return $schema;
    }
}
