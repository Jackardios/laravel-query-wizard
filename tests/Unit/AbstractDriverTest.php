<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use Jackardios\QueryWizard\Contracts\FilterInterface;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Contracts\SortInterface;
use Jackardios\QueryWizard\Drivers\AbstractDriver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AbstractDriverTest extends TestCase
{
    #[Test]
    public function it_returns_true_for_supported_filter_type(): void
    {
        $driver = $this->createMockDriver(filterTypes: ['exact', 'partial']);

        $this->assertTrue($driver->supportsFilterType('exact'));
        $this->assertTrue($driver->supportsFilterType('partial'));
    }

    #[Test]
    public function it_returns_false_for_unsupported_filter_type(): void
    {
        $driver = $this->createMockDriver(filterTypes: ['exact']);

        $this->assertFalse($driver->supportsFilterType('scope'));
        $this->assertFalse($driver->supportsFilterType('partial'));
    }

    #[Test]
    public function it_returns_all_supported_filter_types(): void
    {
        $types = ['exact', 'partial', 'scope'];
        $driver = $this->createMockDriver(filterTypes: $types);

        $this->assertEquals($types, $driver->getSupportedFilterTypes());
    }

    #[Test]
    public function it_returns_true_for_supported_sort_type(): void
    {
        $driver = $this->createMockDriver(sortTypes: ['field', 'callback']);

        $this->assertTrue($driver->supportsSortType('field'));
        $this->assertTrue($driver->supportsSortType('callback'));
    }

    #[Test]
    public function it_returns_false_for_unsupported_sort_type(): void
    {
        $driver = $this->createMockDriver(sortTypes: ['field']);

        $this->assertFalse($driver->supportsSortType('callback'));
        $this->assertFalse($driver->supportsSortType('custom'));
    }

    #[Test]
    public function it_returns_all_supported_sort_types(): void
    {
        $types = ['field', 'callback'];
        $driver = $this->createMockDriver(sortTypes: $types);

        $this->assertEquals($types, $driver->getSupportedSortTypes());
    }

    #[Test]
    public function it_returns_true_for_supported_include_type(): void
    {
        $driver = $this->createMockDriver(includeTypes: ['relationship', 'count']);

        $this->assertTrue($driver->supportsIncludeType('relationship'));
        $this->assertTrue($driver->supportsIncludeType('count'));
    }

    #[Test]
    public function it_returns_false_for_unsupported_include_type(): void
    {
        $driver = $this->createMockDriver(includeTypes: ['relationship']);

        $this->assertFalse($driver->supportsIncludeType('count'));
        $this->assertFalse($driver->supportsIncludeType('callback'));
    }

    #[Test]
    public function it_returns_all_supported_include_types(): void
    {
        $types = ['relationship', 'count', 'callback'];
        $driver = $this->createMockDriver(includeTypes: $types);

        $this->assertEquals($types, $driver->getSupportedIncludeTypes());
    }

    #[Test]
    public function it_returns_empty_arrays_by_default(): void
    {
        $driver = $this->createMockDriver();

        $this->assertEquals([], $driver->getSupportedFilterTypes());
        $this->assertEquals([], $driver->getSupportedSortTypes());
        $this->assertEquals([], $driver->getSupportedIncludeTypes());
    }

    /**
     * @param array<string> $filterTypes
     * @param array<string> $sortTypes
     * @param array<string> $includeTypes
     */
    private function createMockDriver(
        array $filterTypes = [],
        array $sortTypes = [],
        array $includeTypes = []
    ): AbstractDriver {
        return new class($filterTypes, $sortTypes, $includeTypes) extends AbstractDriver {
            /**
             * @param array<string> $filterTypes
             * @param array<string> $sortTypes
             * @param array<string> $includeTypes
             */
            public function __construct(array $filterTypes, array $sortTypes, array $includeTypes)
            {
                $this->supportedFilterTypes = $filterTypes;
                $this->supportedSortTypes = $sortTypes;
                $this->supportedIncludeTypes = $includeTypes;
            }

            public function name(): string
            {
                return 'mock';
            }

            public function supports(mixed $subject): bool
            {
                return true;
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
                return 'mock';
            }

            public function prepareSubject(mixed $subject): mixed
            {
                return $subject;
            }
        };
    }
}
