<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Jackardios\QueryWizard\Exceptions\InvalidAppendQuery;
use Jackardios\QueryWizard\Exceptions\InvalidFieldQuery;
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;
use Jackardios\QueryWizard\Exceptions\InvalidIncludeQuery;
use Jackardios\QueryWizard\Exceptions\InvalidQuery;
use Jackardios\QueryWizard\Exceptions\InvalidSortQuery;
use Jackardios\QueryWizard\Exceptions\InvalidSubject;
use Jackardios\QueryWizard\Exceptions\RootFieldsKeyIsNotDefined;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use stdClass;

class ExceptionsTest extends TestCase
{
    // ========== InvalidSubject Tests ==========
    #[Test]
    public function invalid_subject_formats_string_type_correctly(): void
    {
        $exception = InvalidSubject::make('some_string');

        $this->assertStringContainsString('type `string`', $exception->getMessage());
        $this->assertStringContainsString('is invalid', $exception->getMessage());
    }

    #[Test]
    public function invalid_subject_formats_integer_type_correctly(): void
    {
        $exception = InvalidSubject::make(123);

        $this->assertStringContainsString('type `integer`', $exception->getMessage());
    }

    #[Test]
    public function invalid_subject_formats_array_type_correctly(): void
    {
        $exception = InvalidSubject::make(['array']);

        $this->assertStringContainsString('type `array`', $exception->getMessage());
    }

    #[Test]
    public function invalid_subject_formats_object_class_correctly(): void
    {
        $exception = InvalidSubject::make(new stdClass());

        $this->assertStringContainsString('class `stdClass`', $exception->getMessage());
    }

    #[Test]
    public function invalid_subject_formats_custom_object_correctly(): void
    {
        $customObject = new class {
        };
        $exception = InvalidSubject::make($customObject);

        $this->assertStringContainsString('class `', $exception->getMessage());
        $this->assertStringContainsString('is invalid', $exception->getMessage());
    }

    #[Test]
    public function invalid_subject_is_invalid_argument_exception(): void
    {
        $exception = InvalidSubject::make('test');

        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
    }

    // ========== InvalidFilterValue Tests ==========
    #[Test]
    public function invalid_filter_value_has_correct_message(): void
    {
        $exception = InvalidFilterValue::make('invalid_value');

        $this->assertEquals("Filter value `invalid_value` is invalid.", $exception->getMessage());
    }

    #[Test]
    public function invalid_filter_value_handles_numeric_value(): void
    {
        $exception = InvalidFilterValue::make(123);

        $this->assertStringContainsString('123', $exception->getMessage());
    }

    #[Test]
    public function invalid_filter_value_is_exception(): void
    {
        $exception = InvalidFilterValue::make('test');

        $this->assertInstanceOf(\Exception::class, $exception);
    }

    // ========== RootFieldsKeyIsNotDefined Tests ==========
    #[Test]
    public function root_fields_key_not_defined_has_correct_message(): void
    {
        $exception = new RootFieldsKeyIsNotDefined();

        $this->assertEquals('`rootFieldsKey` is not defined in QueryWizard.', $exception->getMessage());
    }

    #[Test]
    public function root_fields_key_not_defined_is_logic_exception(): void
    {
        $exception = new RootFieldsKeyIsNotDefined();

        $this->assertInstanceOf(\LogicException::class, $exception);
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
    public function invalid_filter_query_exposes_filters(): void
    {
        $unknownFilters = collect(['unknown1', 'unknown2']);
        $allowedFilters = collect(['allowed1']);
        $exception = new InvalidFilterQuery($unknownFilters, $allowedFilters);

        $this->assertEquals($unknownFilters, $exception->unknownFilters);
        $this->assertEquals($allowedFilters, $exception->allowedFilters);
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

        $this->assertStringContainsString('no allowed includes', $exception->getMessage());
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
}
