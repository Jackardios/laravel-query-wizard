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
     * @param  callable|array|string  $key
     */
    protected function assertSortedAscending(Collection $collection, $key): void
    {
        $this->assertSorted($collection, $key);
    }

    /**
     * @param  callable|array|string  $key
     */
    protected function assertSortedDescending(Collection $collection, $key): void
    {
        $this->assertSorted($collection, $key, true);
    }

    /**
     * @param  callable|array|string  $key
     */
    protected function assertSorted(Collection $collection, $key, bool $descending = false): void
    {
        $sortedCollection = $collection->sortBy($key, SORT_REGULAR, $descending);

        $this->assertEquals($sortedCollection->pluck('id'), $collection->pluck('id'));
    }

    /**
     * @param  callable|array|string  $key
     */
    protected function assertNotSorted(Collection $collection, $key, bool $descending = false): void
    {
        $sortedCollection = $collection->sortBy($key, SORT_REGULAR, $descending);

        $this->assertNotEquals($sortedCollection->pluck('id'), $collection->pluck('id'));
    }
}
