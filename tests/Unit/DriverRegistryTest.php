<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Jackardios\QueryWizard\Contracts\DriverInterface;
use Jackardios\QueryWizard\Contracts\FilterInterface;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Contracts\SortInterface;
use Jackardios\QueryWizard\Drivers\AbstractDriver;
use Jackardios\QueryWizard\Drivers\DriverRegistry;
use Jackardios\QueryWizard\Drivers\Eloquent\EloquentDriver;
use Jackardios\QueryWizard\Exceptions\InvalidSubject;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;

class DriverRegistryTest extends TestCase
{
    private DriverRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new DriverRegistry();
    }

    #[Test]
    public function it_initializes_with_eloquent_driver(): void
    {
        $this->assertTrue($this->registry->has('eloquent'));
        $this->assertInstanceOf(EloquentDriver::class, $this->registry->get('eloquent'));
    }

    #[Test]
    public function it_can_get_driver_by_name(): void
    {
        $driver = $this->registry->get('eloquent');

        $this->assertInstanceOf(DriverInterface::class, $driver);
        $this->assertEquals('eloquent', $driver->name());
    }

    #[Test]
    public function it_throws_exception_for_non_existent_driver(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Driver 'non_existent' is not registered");

        $this->registry->get('non_existent');
    }

    #[Test]
    public function it_can_check_if_driver_exists(): void
    {
        $this->assertTrue($this->registry->has('eloquent'));
        $this->assertFalse($this->registry->has('non_existent'));
    }

    #[Test]
    public function it_can_register_custom_driver(): void
    {
        $customDriver = $this->createCustomDriver('custom');

        $this->registry->register($customDriver);

        $this->assertTrue($this->registry->has('custom'));
        $this->assertSame($customDriver, $this->registry->get('custom'));
    }

    #[Test]
    public function it_can_unregister_driver(): void
    {
        $this->assertTrue($this->registry->has('eloquent'));

        $this->registry->unregister('eloquent');

        $this->assertFalse($this->registry->has('eloquent'));
    }

    #[Test]
    public function it_can_get_all_drivers(): void
    {
        $drivers = $this->registry->all();

        $this->assertIsArray($drivers);
        $this->assertArrayHasKey('eloquent', $drivers);
        $this->assertInstanceOf(DriverInterface::class, $drivers['eloquent']);
    }

    #[Test]
    public function it_resolves_eloquent_driver_for_model_class(): void
    {
        $driver = $this->registry->resolve(TestModel::class);

        $this->assertInstanceOf(EloquentDriver::class, $driver);
    }

    #[Test]
    public function it_resolves_eloquent_driver_for_query_builder(): void
    {
        $driver = $this->registry->resolve(TestModel::query());

        $this->assertInstanceOf(EloquentDriver::class, $driver);
    }

    #[Test]
    public function it_resolves_eloquent_driver_for_model_instance(): void
    {
        $model = new TestModel();
        $driver = $this->registry->resolve($model);

        $this->assertInstanceOf(EloquentDriver::class, $driver);
    }

    #[Test]
    public function it_throws_invalid_subject_for_unsupported_type(): void
    {
        $this->expectException(InvalidSubject::class);

        $this->registry->resolve('invalid_string');
    }

    #[Test]
    public function it_throws_invalid_subject_for_array(): void
    {
        $this->expectException(InvalidSubject::class);

        $this->registry->resolve(['array', 'data']);
    }

    #[Test]
    public function it_throws_invalid_subject_for_stdclass(): void
    {
        $this->expectException(InvalidSubject::class);

        $this->registry->resolve(new \stdClass());
    }

    #[Test]
    public function registered_driver_overrides_existing(): void
    {
        $originalDriver = $this->registry->get('eloquent');
        $newDriver = $this->createCustomDriver('eloquent');

        $this->registry->register($newDriver);

        $this->assertNotSame($originalDriver, $this->registry->get('eloquent'));
        $this->assertSame($newDriver, $this->registry->get('eloquent'));
    }

    #[Test]
    public function it_resolves_first_supporting_driver(): void
    {
        $customDriver = $this->createCustomDriver('custom', supportsAll: true);
        $this->registry->register($customDriver);

        // Eloquent was registered first, so it should be resolved
        $driver = $this->registry->resolve(TestModel::class);

        $this->assertInstanceOf(EloquentDriver::class, $driver);
    }

    private function createCustomDriver(string $name, bool $supportsAll = false): DriverInterface
    {
        return new class($name, $supportsAll) extends AbstractDriver {
            public function __construct(
                private string $driverName,
                private bool $supportsAll
            ) {
            }

            public function name(): string
            {
                return $this->driverName;
            }

            public function supports(mixed $subject): bool
            {
                return $this->supportsAll;
            }

            public function capabilities(): array
            {
                return [];
            }

            public function normalizeFilter(FilterInterface|string $filter): FilterInterface
            {
                throw new \RuntimeException('Not implemented');
            }

            public function normalizeInclude(IncludeInterface|string $include): IncludeInterface
            {
                throw new \RuntimeException('Not implemented');
            }

            public function normalizeSort(SortInterface|string $sort): SortInterface
            {
                throw new \RuntimeException('Not implemented');
            }

            public function applyFilter(mixed $subject, FilterInterface $filter, mixed $value): mixed
            {
                return $subject;
            }

            public function applyInclude(mixed $subject, IncludeInterface $include, array $fields = []): mixed
            {
                return $subject;
            }

            public function applySort(mixed $subject, SortInterface $sort, string $direction): mixed
            {
                return $subject;
            }

            public function applyFields(mixed $subject, array $fields): mixed
            {
                return $subject;
            }

            public function applyAppends(mixed $result, array $appends): mixed
            {
                return $result;
            }

            public function getResourceKey(mixed $subject): string
            {
                return 'custom';
            }

            public function prepareSubject(mixed $subject): mixed
            {
                return $subject;
            }
        };
    }
}
