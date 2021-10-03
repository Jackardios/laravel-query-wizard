<?php

namespace Jackardios\QueryWizard\Tests\Concerns;

use Illuminate\Support\Collection;
use Jackardios\QueryWizard\Tests\TestCase;

/**
 * @mixin TestCase
 */
trait AssertsCollectionSorting
{
    /**
     * @param Collection $collection
     * @param callable|array|string $key
     * @return void
     */
    protected function assertSortedAscending(Collection $collection, $key): void
    {
        $this->assertSorted($collection, $key);
    }

    /**
     * @param Collection $collection
     * @param callable|array|string $key
     * @return void
     */
    protected function assertSortedDescending(Collection $collection, $key): void
    {
        $this->assertSorted($collection, $key, true);
    }

    /**
     * @param Collection $collection
     * @param callable|array|string $key
     * @param bool  $descending
     * @return void
     */
    protected function assertSorted(Collection $collection, $key, bool $descending = false): void
    {
        $sortedCollection = $collection->sortBy($key, SORT_REGULAR, $descending);

        $this->assertEquals($sortedCollection->pluck('id'), $collection->pluck('id'));
    }

    /**
     * @param Collection $collection
     * @param callable|array|string $key
     * @param bool  $descending
     * @return void
     */
    protected function assertNotSorted(Collection $collection, $key, bool $descending = false): void
    {
        $sortedCollection = $collection->sortBy($key, SORT_REGULAR, $descending);

        $this->assertNotEquals($sortedCollection->pluck('id'), $collection->pluck('id'));
    }
}
