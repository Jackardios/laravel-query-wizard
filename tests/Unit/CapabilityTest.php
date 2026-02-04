<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Jackardios\QueryWizard\Enums\Capability;
use PHPUnit\Framework\TestCase;

class CapabilityTest extends TestCase
{
    #[Test]
    public function it_has_filters_capability(): void
    {
        $this->assertEquals('filters', Capability::FILTERS->value);
    }

    #[Test]
    public function it_has_sorts_capability(): void
    {
        $this->assertEquals('sorts', Capability::SORTS->value);
    }

    #[Test]
    public function it_has_includes_capability(): void
    {
        $this->assertEquals('includes', Capability::INCLUDES->value);
    }

    #[Test]
    public function it_has_fields_capability(): void
    {
        $this->assertEquals('fields', Capability::FIELDS->value);
    }

    #[Test]
    public function it_has_appends_capability(): void
    {
        $this->assertEquals('appends', Capability::APPENDS->value);
    }

    #[Test]
    public function it_returns_all_values(): void
    {
        $values = Capability::values();

        $this->assertIsArray($values);
        $this->assertCount(5, $values);
        $this->assertContains('filters', $values);
        $this->assertContains('sorts', $values);
        $this->assertContains('includes', $values);
        $this->assertContains('fields', $values);
        $this->assertContains('appends', $values);
    }

    #[Test]
    public function it_returns_all_cases(): void
    {
        $cases = Capability::cases();

        $this->assertCount(5, $cases);
        $this->assertContains(Capability::FILTERS, $cases);
        $this->assertContains(Capability::SORTS, $cases);
        $this->assertContains(Capability::INCLUDES, $cases);
        $this->assertContains(Capability::FIELDS, $cases);
        $this->assertContains(Capability::APPENDS, $cases);
    }

    #[Test]
    public function it_can_be_created_from_string(): void
    {
        $capability = Capability::from('filters');

        $this->assertEquals(Capability::FILTERS, $capability);
    }

    #[Test]
    public function it_throws_for_invalid_string(): void
    {
        $this->expectException(\ValueError::class);

        Capability::from('invalid');
    }

    #[Test]
    public function try_from_returns_null_for_invalid_string(): void
    {
        $capability = Capability::tryFrom('invalid');

        $this->assertNull($capability);
    }

    #[Test]
    public function try_from_returns_capability_for_valid_string(): void
    {
        $capability = Capability::tryFrom('sorts');

        $this->assertEquals(Capability::SORTS, $capability);
    }
}
