<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use Jackardios\QueryWizard\Exceptions\InvalidAppendQuery;
use Jackardios\QueryWizard\Exceptions\InvalidFieldQuery;
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;
use Jackardios\QueryWizard\Exceptions\InvalidIncludeQuery;
use Jackardios\QueryWizard\Exceptions\InvalidQuery;
use Jackardios\QueryWizard\Exceptions\InvalidSortQuery;
use Jackardios\QueryWizard\Exceptions\MaxAppendsCountExceeded;
use Jackardios\QueryWizard\Exceptions\MaxFiltersCountExceeded;
use Jackardios\QueryWizard\Exceptions\MaxIncludeDepthExceeded;
use Jackardios\QueryWizard\Exceptions\MaxIncludesCountExceeded;
use Jackardios\QueryWizard\Exceptions\MaxSortsCountExceeded;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ExceptionsTest extends TestCase
{
    // ========== InvalidFilterValue Tests ==========
    #[Test]
    public function invalid_filter_value_has_correct_message(): void
    {
        $exception = InvalidFilterValue::make('invalid_value');

        $this->assertEquals('Filter value `invalid_value` is invalid.', $exception->getMessage());
    }

    #[Test]
    public function invalid_filter_value_handles_numeric_value(): void
    {
        $exception = InvalidFilterValue::make(123);

        $this->assertStringContainsString('123', $exception->getMessage());
    }

    #[Test]
    public function invalid_filter_value_extends_invalid_query(): void
    {
        $exception = InvalidFilterValue::make('test');

        $this->assertInstanceOf(InvalidQuery::class, $exception);
    }

    #[Test]
    public function invalid_filter_value_includes_filter_name_when_provided(): void
    {
        $exception = InvalidFilterValue::make('bad_val', 'status');

        $this->assertStringContainsString('status', $exception->getMessage());
        $this->assertStringContainsString('bad_val', $exception->getMessage());
        $this->assertEquals('status', $exception->filterName);
        $this->assertEquals('bad_val', $exception->filterValue);
    }

    #[Test]
    public function invalid_filter_value_has_empty_filter_name_by_default(): void
    {
        $exception = InvalidFilterValue::make('test');

        $this->assertEquals('', $exception->filterName);
        $this->assertEquals('test', $exception->filterValue);
    }

    // ========== InvalidQuery Base Tests ==========
    #[Test]
    public function invalid_query_is_abstract(): void
    {
        $reflection = new \ReflectionClass(InvalidQuery::class);

        $this->assertTrue($reflection->isAbstract());
    }

    #[Test]
    public function invalid_query_extends_http_exception(): void
    {
        $this->assertTrue(is_subclass_of(
            InvalidQuery::class,
            \Symfony\Component\HttpKernel\Exception\HttpException::class
        ));
    }

    // ========== InvalidFilterQuery Tests ==========
    #[Test]
    public function invalid_filter_query_extends_invalid_query(): void
    {
        $exception = new InvalidFilterQuery(collect(['unknown']), collect(['allowed']));

        $this->assertInstanceOf(InvalidQuery::class, $exception);
    }

    #[Test]
    public function invalid_filter_query_has_correct_message(): void
    {
        $exception = new InvalidFilterQuery(collect(['foo', 'bar']), collect(['name', 'email']));

        $this->assertStringContainsString('foo, bar', $exception->getMessage());
        $this->assertStringContainsString('name, email', $exception->getMessage());
    }

    #[Test]
    public function invalid_filter_query_exposes_readonly_filters(): void
    {
        $unknownFilters = collect(['unknown1', 'unknown2']);
        $allowedFilters = collect(['allowed1']);
        $exception = new InvalidFilterQuery($unknownFilters, $allowedFilters);

        $this->assertEquals($unknownFilters, $exception->unknownFilters);
        $this->assertEquals($allowedFilters, $exception->allowedFilters);

        $reflection = new \ReflectionProperty($exception, 'unknownFilters');
        $this->assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function invalid_filter_query_empty_allowed_message(): void
    {
        $exception = new InvalidFilterQuery(collect(['foo']), collect());

        $this->assertStringContainsString('No filters are allowed.', $exception->getMessage());
    }

    // ========== InvalidIncludeQuery Tests ==========
    #[Test]
    public function invalid_include_query_extends_invalid_query(): void
    {
        $exception = new InvalidIncludeQuery(collect(['unknown']), collect(['allowed']));

        $this->assertInstanceOf(InvalidQuery::class, $exception);
    }

    #[Test]
    public function invalid_include_query_has_correct_message(): void
    {
        $exception = new InvalidIncludeQuery(collect(['posts']), collect(['users']));

        $this->assertStringContainsString('posts', $exception->getMessage());
        $this->assertStringContainsString('users', $exception->getMessage());
    }

    #[Test]
    public function invalid_include_query_message_when_no_allowed(): void
    {
        $exception = new InvalidIncludeQuery(collect(['posts']), collect());

        $this->assertStringContainsString('No includes are allowed.', $exception->getMessage());
    }

    #[Test]
    public function invalid_include_query_has_readonly_properties(): void
    {
        $exception = new InvalidIncludeQuery(collect(['unknown']), collect(['allowed']));

        $reflection = new \ReflectionProperty($exception, 'unknownIncludes');
        $this->assertTrue($reflection->isReadOnly());
    }

    // ========== InvalidSortQuery Tests ==========
    #[Test]
    public function invalid_sort_query_extends_invalid_query(): void
    {
        $exception = new InvalidSortQuery(collect(['unknown']), collect(['allowed']));

        $this->assertInstanceOf(InvalidQuery::class, $exception);
    }

    #[Test]
    public function invalid_sort_query_has_correct_message(): void
    {
        $exception = new InvalidSortQuery(collect(['created_at']), collect(['name']));

        $this->assertStringContainsString('created_at', $exception->getMessage());
        $this->assertStringContainsString('name', $exception->getMessage());
    }

    #[Test]
    public function invalid_sort_query_empty_allowed_message(): void
    {
        $exception = new InvalidSortQuery(collect(['foo']), collect());

        $this->assertStringContainsString('No sorts are allowed.', $exception->getMessage());
    }

    #[Test]
    public function invalid_sort_query_has_readonly_properties(): void
    {
        $exception = new InvalidSortQuery(collect(['unknown']), collect(['allowed']));

        $reflection = new \ReflectionProperty($exception, 'unknownSorts');
        $this->assertTrue($reflection->isReadOnly());
    }

    // ========== InvalidFieldQuery Tests ==========
    #[Test]
    public function invalid_field_query_extends_invalid_query(): void
    {
        $exception = new InvalidFieldQuery(collect(['unknown']), collect(['allowed']));

        $this->assertInstanceOf(InvalidQuery::class, $exception);
    }

    #[Test]
    public function invalid_field_query_has_correct_message(): void
    {
        $exception = new InvalidFieldQuery(collect(['secret']), collect(['id', 'name']));

        $this->assertStringContainsString('secret', $exception->getMessage());
        $this->assertStringContainsString('id, name', $exception->getMessage());
    }

    #[Test]
    public function invalid_field_query_empty_allowed_message(): void
    {
        $exception = new InvalidFieldQuery(collect(['foo']), collect());

        $this->assertStringContainsString('No fields are allowed.', $exception->getMessage());
    }

    // ========== InvalidAppendQuery Tests ==========
    #[Test]
    public function invalid_append_query_extends_invalid_query(): void
    {
        $exception = new InvalidAppendQuery(collect(['unknown']), collect(['allowed']));

        $this->assertInstanceOf(InvalidQuery::class, $exception);
    }

    #[Test]
    public function invalid_append_query_has_correct_message(): void
    {
        $exception = new InvalidAppendQuery(collect(['fullName']), collect(['age']));

        $this->assertStringContainsString('fullName', $exception->getMessage());
        $this->assertStringContainsString('age', $exception->getMessage());
    }

    #[Test]
    public function invalid_append_query_empty_allowed_message(): void
    {
        $exception = new InvalidAppendQuery(collect(['foo']), collect());

        $this->assertStringContainsString('No appends are allowed.', $exception->getMessage());
    }

    // ========== Max*CountExceeded readonly Tests ==========
    #[Test]
    public function max_filters_count_exceeded_has_readonly_properties(): void
    {
        $exception = MaxFiltersCountExceeded::create(10, 5);

        $this->assertEquals(10, $exception->count);
        $this->assertEquals(5, $exception->maxCount);

        $reflection = new \ReflectionProperty($exception, 'count');
        $this->assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function max_sorts_count_exceeded_has_readonly_properties(): void
    {
        $exception = MaxSortsCountExceeded::create(10, 5);

        $reflection = new \ReflectionProperty($exception, 'count');
        $this->assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function max_includes_count_exceeded_has_readonly_properties(): void
    {
        $exception = MaxIncludesCountExceeded::create(10, 5);

        $reflection = new \ReflectionProperty($exception, 'count');
        $this->assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function max_appends_count_exceeded_has_readonly_properties(): void
    {
        $exception = MaxAppendsCountExceeded::create(10, 5);

        $reflection = new \ReflectionProperty($exception, 'count');
        $this->assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function max_include_depth_exceeded_has_readonly_properties(): void
    {
        $exception = MaxIncludeDepthExceeded::create('a.b.c', 3, 2);

        $this->assertEquals('a.b.c', $exception->include);
        $this->assertEquals(3, $exception->depth);
        $this->assertEquals(2, $exception->maxDepth);

        $reflection = new \ReflectionProperty($exception, 'include');
        $this->assertTrue($reflection->isReadOnly());
    }
}
