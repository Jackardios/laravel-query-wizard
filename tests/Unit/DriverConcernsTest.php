<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Jackardios\QueryWizard\Contracts\Definitions\FilterDefinitionInterface;
use Jackardios\QueryWizard\Contracts\Definitions\IncludeDefinitionInterface;
use Jackardios\QueryWizard\Contracts\Definitions\SortDefinitionInterface;
use Jackardios\QueryWizard\Contracts\FilterStrategyInterface;
use Jackardios\QueryWizard\Contracts\IncludeStrategyInterface;
use Jackardios\QueryWizard\Contracts\SortStrategyInterface;
use Jackardios\QueryWizard\Drivers\Concerns\HasFilterStrategies;
use Jackardios\QueryWizard\Drivers\Concerns\HasIncludeStrategies;
use Jackardios\QueryWizard\Drivers\Concerns\HasSortStrategies;
use Jackardios\QueryWizard\Tests\TestCase;

class DriverConcernsTest extends TestCase
{
    #[Test]
    public function it_can_register_filter_strategy(): void
    {
        $driver = new class {
            use HasFilterStrategies;
        };

        $driver->registerFilterStrategy('test', TestFilterStrategy::class);

        $this->assertTrue($driver->supportsFilterType('test'));
        $this->assertContains('test', $driver->getSupportedFilterTypes());
    }

    #[Test]
    public function it_can_register_sort_strategy(): void
    {
        $driver = new class {
            use HasSortStrategies;
        };

        $driver->registerSortStrategy('test', TestSortStrategy::class);

        $this->assertTrue($driver->supportsSortType('test'));
        $this->assertContains('test', $driver->getSupportedSortTypes());
    }

    #[Test]
    public function it_can_register_include_strategy(): void
    {
        $driver = new class {
            use HasIncludeStrategies;
        };

        $driver->registerIncludeStrategy('test', TestIncludeStrategy::class);

        $this->assertTrue($driver->supportsIncludeType('test'));
        $this->assertContains('test', $driver->getSupportedIncludeTypes());
    }

    #[Test]
    public function it_always_supports_custom_filter_type(): void
    {
        $driver = new class {
            use HasFilterStrategies;
        };

        $this->assertTrue($driver->supportsFilterType('custom'));
    }

    #[Test]
    public function it_always_supports_custom_sort_type(): void
    {
        $driver = new class {
            use HasSortStrategies;
        };

        $this->assertTrue($driver->supportsSortType('custom'));
    }

    #[Test]
    public function it_always_supports_custom_include_type(): void
    {
        $driver = new class {
            use HasIncludeStrategies;
        };

        $this->assertTrue($driver->supportsIncludeType('custom'));
    }

    #[Test]
    public function it_returns_false_for_unknown_filter_type(): void
    {
        $driver = new class {
            use HasFilterStrategies;
        };

        $this->assertFalse($driver->supportsFilterType('unknown'));
    }

    #[Test]
    public function it_returns_false_for_unknown_sort_type(): void
    {
        $driver = new class {
            use HasSortStrategies;
        };

        $this->assertFalse($driver->supportsSortType('unknown'));
    }

    #[Test]
    public function it_returns_false_for_unknown_include_type(): void
    {
        $driver = new class {
            use HasIncludeStrategies;
        };

        $this->assertFalse($driver->supportsIncludeType('unknown'));
    }

    #[Test]
    public function it_clears_cached_instance_on_re_register(): void
    {
        $driver = new class {
            use HasFilterStrategies;

            public function resolve(FilterDefinitionInterface $filter): FilterStrategyInterface
            {
                return $this->resolveFilterStrategy($filter);
            }
        };

        $driver->registerFilterStrategy('test', TestFilterStrategy::class);

        $filter = $this->createMock(FilterDefinitionInterface::class);
        $filter->method('getType')->willReturn('test');
        $filter->method('getStrategyClass')->willReturn(null);

        $instance1 = $driver->resolve($filter);

        // Re-register should clear the cache
        $driver->registerFilterStrategy('test', TestFilterStrategy::class);

        $instance2 = $driver->resolve($filter);

        $this->assertNotSame($instance1, $instance2);
    }

    #[Test]
    public function it_caches_strategy_instances(): void
    {
        $driver = new class {
            use HasFilterStrategies;

            public function resolve(FilterDefinitionInterface $filter): FilterStrategyInterface
            {
                return $this->resolveFilterStrategy($filter);
            }
        };

        $driver->registerFilterStrategy('test', TestFilterStrategy::class);

        $filter = $this->createMock(FilterDefinitionInterface::class);
        $filter->method('getType')->willReturn('test');
        $filter->method('getStrategyClass')->willReturn(null);

        $instance1 = $driver->resolve($filter);
        $instance2 = $driver->resolve($filter);

        $this->assertSame($instance1, $instance2);
    }

    #[Test]
    public function it_throws_exception_for_unknown_filter_strategy(): void
    {
        $driver = new class {
            use HasFilterStrategies;

            public function resolve(FilterDefinitionInterface $filter): FilterStrategyInterface
            {
                return $this->resolveFilterStrategy($filter);
            }
        };

        $filter = $this->createMock(FilterDefinitionInterface::class);
        $filter->method('getType')->willReturn('unknown');
        $filter->method('getStrategyClass')->willReturn(null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown filter type: unknown');

        $driver->resolve($filter);
    }

    #[Test]
    public function it_throws_exception_for_unknown_sort_strategy(): void
    {
        $driver = new class {
            use HasSortStrategies;

            public function resolve(SortDefinitionInterface $sort): SortStrategyInterface
            {
                return $this->resolveSortStrategy($sort);
            }
        };

        $sort = $this->createMock(SortDefinitionInterface::class);
        $sort->method('getType')->willReturn('unknown');
        $sort->method('getStrategyClass')->willReturn(null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown sort type: unknown');

        $driver->resolve($sort);
    }

    #[Test]
    public function it_throws_exception_for_unknown_include_strategy(): void
    {
        $driver = new class {
            use HasIncludeStrategies;

            public function resolve(IncludeDefinitionInterface $include): IncludeStrategyInterface
            {
                return $this->resolveIncludeStrategy($include);
            }
        };

        $include = $this->createMock(IncludeDefinitionInterface::class);
        $include->method('getType')->willReturn('unknown');
        $include->method('getStrategyClass')->willReturn(null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown include type: unknown');

        $driver->resolve($include);
    }

    #[Test]
    public function it_resolves_custom_filter_strategy_class(): void
    {
        $driver = new class {
            use HasFilterStrategies;

            public function resolve(FilterDefinitionInterface $filter): FilterStrategyInterface
            {
                return $this->resolveFilterStrategy($filter);
            }
        };

        $filter = $this->createMock(FilterDefinitionInterface::class);
        $filter->method('getType')->willReturn('custom');
        $filter->method('getStrategyClass')->willReturn(TestFilterStrategy::class);

        $strategy = $driver->resolve($filter);

        $this->assertInstanceOf(TestFilterStrategy::class, $strategy);
    }

    #[Test]
    public function custom_strategies_are_not_cached(): void
    {
        $driver = new class {
            use HasFilterStrategies;

            public function resolve(FilterDefinitionInterface $filter): FilterStrategyInterface
            {
                return $this->resolveFilterStrategy($filter);
            }
        };

        $filter = $this->createMock(FilterDefinitionInterface::class);
        $filter->method('getType')->willReturn('custom');
        $filter->method('getStrategyClass')->willReturn(TestFilterStrategy::class);

        $instance1 = $driver->resolve($filter);
        $instance2 = $driver->resolve($filter);

        $this->assertNotSame($instance1, $instance2);
    }
}

class TestFilterStrategy implements FilterStrategyInterface
{
    public function apply(mixed $subject, FilterDefinitionInterface $filter, mixed $value): mixed
    {
        return $subject;
    }
}

class TestSortStrategy implements SortStrategyInterface
{
    public function apply(mixed $subject, SortDefinitionInterface $sort, string $direction): mixed
    {
        return $subject;
    }
}

class TestIncludeStrategy implements IncludeStrategyInterface
{
    public function apply(mixed $subject, IncludeDefinitionInterface $include, array $fields = []): mixed
    {
        return $subject;
    }
}
