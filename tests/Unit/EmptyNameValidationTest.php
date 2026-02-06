<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use Jackardios\QueryWizard\Eloquent\Filters\ExactFilter;
use Jackardios\QueryWizard\Eloquent\Includes\RelationshipInclude;
use Jackardios\QueryWizard\Eloquent\Sorts\FieldSort;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EmptyNameValidationTest extends TestCase
{
    #[Test]
    public function filter_rejects_empty_property(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Filter property name cannot be empty.');

        ExactFilter::make('');
    }

    #[Test]
    public function filter_rejects_whitespace_property(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Filter property name cannot be empty.');

        ExactFilter::make('   ');
    }

    #[Test]
    public function sort_rejects_empty_property(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Sort property name cannot be empty.');

        FieldSort::make('');
    }

    #[Test]
    public function sort_rejects_whitespace_property(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Sort property name cannot be empty.');

        FieldSort::make('   ');
    }

    #[Test]
    public function include_rejects_empty_relation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Include relation name cannot be empty.');

        RelationshipInclude::make('');
    }

    #[Test]
    public function include_rejects_whitespace_relation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Include relation name cannot be empty.');

        RelationshipInclude::make('   ');
    }

    #[Test]
    public function filter_accepts_valid_name(): void
    {
        $filter = ExactFilter::make('name');

        $this->assertEquals('name', $filter->getProperty());
    }

    #[Test]
    public function sort_accepts_valid_name(): void
    {
        $sort = FieldSort::make('created_at');

        $this->assertEquals('created_at', $sort->getProperty());
    }

    #[Test]
    public function include_accepts_valid_name(): void
    {
        $include = RelationshipInclude::make('posts');

        $this->assertEquals('posts', $include->getRelation());
    }
}
