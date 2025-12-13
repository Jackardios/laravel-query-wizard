<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers;

use InvalidArgumentException;
use Jackardios\QueryWizard\Contracts\DriverInterface;
use Jackardios\QueryWizard\Exceptions\InvalidSubject;
use Jackardios\QueryWizard\Drivers\Eloquent\EloquentDriver;

class DriverRegistry
{
    /** @var array<string, DriverInterface> */
    protected static array $drivers = [];

    protected static bool $initialized = false;

    /**
     * Register a driver
     */
    public static function register(DriverInterface $driver): void
    {
        static::ensureInitialized();
        static::$drivers[$driver->name()] = $driver;
    }

    /**
     * Get a driver by name
     *
     * @throws InvalidArgumentException
     */
    public static function get(string $name): DriverInterface
    {
        static::ensureInitialized();

        if (!isset(static::$drivers[$name])) {
            throw new InvalidArgumentException("Driver '{$name}' is not registered");
        }

        return static::$drivers[$name];
    }

    /**
     * Check if a driver is registered
     */
    public static function has(string $name): bool
    {
        static::ensureInitialized();

        return isset(static::$drivers[$name]);
    }

    /**
     * Auto-resolve a driver for the given subject
     *
     * @throws InvalidSubject
     */
    public static function resolve(mixed $subject): DriverInterface
    {
        static::ensureInitialized();

        foreach (static::$drivers as $driver) {
            if ($driver->supports($subject)) {
                return $driver;
            }
        }

        throw InvalidSubject::make($subject);
    }

    /**
     * Get all registered drivers
     *
     * @return array<string, DriverInterface>
     */
    public static function all(): array
    {
        static::ensureInitialized();

        return static::$drivers;
    }

    /**
     * Unregister a driver by name
     */
    public static function unregister(string $name): void
    {
        unset(static::$drivers[$name]);
    }

    /**
     * Reset the registry (useful for testing)
     */
    public static function reset(): void
    {
        static::$drivers = [];
        static::$initialized = false;
    }

    /**
     * Ensure default drivers are registered
     */
    protected static function ensureInitialized(): void
    {
        if (static::$initialized) {
            return;
        }

        static::$drivers['eloquent'] = new EloquentDriver();

        /** @var array<string, class-string<DriverInterface>> $customDrivers */
        $customDrivers = config('query-wizard.drivers', []);
        foreach ($customDrivers as $name => $driverClass) {
            if (is_string($driverClass) && class_exists($driverClass)) {
                $driver = new $driverClass();
                if ($driver instanceof DriverInterface) {
                    static::$drivers[$name] = $driver;
                }
            }
        }

        static::$initialized = true;
    }
}
