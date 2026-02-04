<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use Jackardios\QueryWizard\Exceptions\UnsupportedCapability;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UnsupportedCapabilityTest extends TestCase
{
    #[Test]
    public function it_creates_exception_with_correct_message(): void
    {
        $exception = new UnsupportedCapability('filters', 'scout');

        $this->assertEquals(
            "Driver 'scout' does not support 'filters' capability",
            $exception->getMessage()
        );
    }

    #[Test]
    public function it_exposes_capability_property(): void
    {
        $exception = new UnsupportedCapability('includes', 'custom');

        $this->assertEquals('includes', $exception->capability);
    }

    #[Test]
    public function it_exposes_driver_name_property(): void
    {
        $exception = new UnsupportedCapability('sorts', 'scout');

        $this->assertEquals('scout', $exception->driverName);
    }

    #[Test]
    public function it_can_be_created_via_static_factory(): void
    {
        $exception = UnsupportedCapability::make('fields', 'custom-driver');

        $this->assertInstanceOf(UnsupportedCapability::class, $exception);
        $this->assertEquals('fields', $exception->capability);
        $this->assertEquals('custom-driver', $exception->driverName);
        $this->assertEquals(
            "Driver 'custom-driver' does not support 'fields' capability",
            $exception->getMessage()
        );
    }
}
