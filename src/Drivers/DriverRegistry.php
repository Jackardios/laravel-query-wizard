<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers;

use InvalidArgumentException;
use Jackardios\QueryWizard\Contracts\DriverInterface;
use Jackardios\QueryWizard\Exceptions\InvalidSubject;
use Jackardios\QueryWizard\Drivers\Eloquent\EloquentDriver;

/**
 * Registry for query wizard drivers.
 *
 * This class is designed to be used as a singleton registered in the Laravel container,
 * making it fully compatible with Laravel Octane and other long-running processes.
 */
class DriverRegistry
{
    /** @var array<string, DriverInterface> */
    protected array $drivers = [];

    protected bool $initialized = false;

    public function __construct()
    {
        $this->initialize();
    }

    /**
     * Register a driver
     */
    public function register(DriverInterface $driver): void
    {
        $this->drivers[$driver->name()] = $driver;
    }

    /**
     * Get a driver by name
     *
     * @throws InvalidArgumentException
     */
    public function get(string $name): DriverInterface
    {
        if (!isset($this->drivers[$name])) {
            throw new InvalidArgumentException("Driver '{$name}' is not registered");
        }

        return $this->drivers[$name];
    }

    /**
     * Check if a driver is registered
     */
    public function has(string $name): bool
    {
        return isset($this->drivers[$name]);
    }

    /**
     * Auto-resolve a driver for the given subject
     *
     * @throws InvalidSubject
     */
    public function resolve(mixed $subject): DriverInterface
    {
        foreach ($this->drivers as $driver) {
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
    public function all(): array
    {
        return $this->drivers;
    }

    /**
     * Unregister a driver by name
     */
    public function unregister(string $name): void
    {
        unset($this->drivers[$name]);
    }

    /**
     * Initialize default drivers
     */
    protected function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->drivers['eloquent'] = new EloquentDriver();

        /** @var array<string, class-string<DriverInterface>> $customDrivers */
        $customDrivers = config('query-wizard.drivers', []);
        foreach ($customDrivers as $name => $driverClass) {
            if (is_string($driverClass) && class_exists($driverClass)) {
                $driver = new $driverClass();
                if ($driver instanceof DriverInterface) {
                    $this->drivers[$name] = $driver;
                }
            }
        }

        $this->initialized = true;
    }
}
