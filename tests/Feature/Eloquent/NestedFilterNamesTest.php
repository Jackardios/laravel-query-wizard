<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;
use Jackardios\QueryWizard\Filters\CallbackFilter;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * A request key belongs to the deepest allowed filter name it falls under.
 */
#[Group('eloquent')]
#[Group('filter')]
class NestedFilterNamesTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $received = [];

    #[Test]
    public function a_nested_key_goes_to_the_nested_filter_only(): void
    {
        $this->applyFilters(['name' => ['first' => 'Ann']]);

        $this->assertSame(['name.first' => 'Ann'], $this->received);
    }

    #[Test]
    public function the_outer_filter_keeps_the_keys_no_nested_filter_takes(): void
    {
        $this->applyFilters(['name' => ['first' => 'Ann', 'last' => 'Lee']]);

        $this->assertSame(['name' => ['last' => 'Lee'], 'name.first' => 'Ann'], $this->received);
    }

    #[Test]
    public function a_plain_value_goes_to_the_outer_filter(): void
    {
        $this->applyFilters(['name' => 'Ann']);

        $this->assertSame(['name' => 'Ann'], $this->received);
    }

    #[Test]
    public function the_outer_default_applies_when_only_the_nested_filter_is_requested(): void
    {
        $this->applyFilters(['name' => ['first' => 'Ann']], $this->recordingFilter('name')->default('anyone'));

        $this->assertSame(['name' => 'anyone', 'name.first' => 'Ann'], $this->received);
    }

    #[Test]
    public function keys_under_an_unrelated_name_are_still_rejected(): void
    {
        $this->expectException(InvalidFilterQuery::class);

        $this->applyFilters(['other' => ['first' => 'Ann']]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(array $filters, ?CallbackFilter $outer = null): void
    {
        $this
            ->createEloquentWizardWithFilters($filters)
            ->allowedFilters($outer ?? $this->recordingFilter('name'), $this->recordingFilter('name.first'))
            ->toQuery();

        ksort($this->received);
    }

    private function recordingFilter(string $name): CallbackFilter
    {
        return EloquentFilter::callback($name, function ($query, mixed $value) use ($name): void {
            $this->received[$name] = $value;
        });
    }
}
