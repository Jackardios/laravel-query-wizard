<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use Closure;
use PHPUnit\Framework\Attributes\Test;
use Jackardios\QueryWizard\Contracts\Definitions\FilterDefinitionInterface;
use Jackardios\QueryWizard\Contracts\Definitions\IncludeDefinitionInterface;
use Jackardios\QueryWizard\Contracts\Definitions\SortDefinitionInterface;
use Jackardios\QueryWizard\Strategies\CallbackFilterStrategy;
use Jackardios\QueryWizard\Strategies\CallbackIncludeStrategy;
use Jackardios\QueryWizard\Strategies\CallbackSortStrategy;
use Jackardios\QueryWizard\Tests\TestCase;
use stdClass;

class GenericStrategiesTest extends TestCase
{
    #[Test]
    public function callback_filter_strategy_applies_callback(): void
    {
        $strategy = new CallbackFilterStrategy();

        $callbackCalled = false;
        $receivedSubject = null;
        $receivedValue = null;
        $receivedProperty = null;

        $callback = function ($subject, $value, $property) use (&$callbackCalled, &$receivedSubject, &$receivedValue, &$receivedProperty) {
            $callbackCalled = true;
            $receivedSubject = $subject;
            $receivedValue = $value;
            $receivedProperty = $property;
        };

        $filter = $this->createMock(FilterDefinitionInterface::class);
        $filter->method('getCallback')->willReturn(Closure::fromCallable($callback));
        $filter->method('getProperty')->willReturn('test_property');

        $subject = new stdClass();
        $result = $strategy->apply($subject, $filter, 'test_value');

        $this->assertTrue($callbackCalled);
        $this->assertSame($subject, $receivedSubject);
        $this->assertEquals('test_value', $receivedValue);
        $this->assertEquals('test_property', $receivedProperty);
        $this->assertSame($subject, $result);
    }

    #[Test]
    public function callback_filter_strategy_returns_subject_when_no_callback(): void
    {
        $strategy = new CallbackFilterStrategy();

        $filter = $this->createMock(FilterDefinitionInterface::class);
        $filter->method('getCallback')->willReturn(null);
        $filter->method('getProperty')->willReturn('test_property');

        $subject = new stdClass();
        $result = $strategy->apply($subject, $filter, 'test_value');

        $this->assertSame($subject, $result);
    }

    #[Test]
    public function callback_sort_strategy_applies_callback(): void
    {
        $strategy = new CallbackSortStrategy();

        $callbackCalled = false;
        $receivedSubject = null;
        $receivedDirection = null;
        $receivedProperty = null;

        $callback = function ($subject, $direction, $property) use (&$callbackCalled, &$receivedSubject, &$receivedDirection, &$receivedProperty) {
            $callbackCalled = true;
            $receivedSubject = $subject;
            $receivedDirection = $direction;
            $receivedProperty = $property;
        };

        $sort = $this->createMock(SortDefinitionInterface::class);
        $sort->method('getCallback')->willReturn(Closure::fromCallable($callback));
        $sort->method('getProperty')->willReturn('test_property');

        $subject = new stdClass();
        $result = $strategy->apply($subject, $sort, 'desc');

        $this->assertTrue($callbackCalled);
        $this->assertSame($subject, $receivedSubject);
        $this->assertEquals('desc', $receivedDirection);
        $this->assertEquals('test_property', $receivedProperty);
        $this->assertSame($subject, $result);
    }

    #[Test]
    public function callback_sort_strategy_returns_subject_when_no_callback(): void
    {
        $strategy = new CallbackSortStrategy();

        $sort = $this->createMock(SortDefinitionInterface::class);
        $sort->method('getCallback')->willReturn(null);
        $sort->method('getProperty')->willReturn('test_property');

        $subject = new stdClass();
        $result = $strategy->apply($subject, $sort, 'asc');

        $this->assertSame($subject, $result);
    }

    #[Test]
    public function callback_include_strategy_applies_callback(): void
    {
        $strategy = new CallbackIncludeStrategy();

        $callbackCalled = false;
        $receivedSubject = null;
        $receivedRelation = null;
        $receivedFields = null;

        $callback = function ($subject, $relation, $fields) use (&$callbackCalled, &$receivedSubject, &$receivedRelation, &$receivedFields) {
            $callbackCalled = true;
            $receivedSubject = $subject;
            $receivedRelation = $relation;
            $receivedFields = $fields;
        };

        $include = $this->createMock(IncludeDefinitionInterface::class);
        $include->method('getCallback')->willReturn(Closure::fromCallable($callback));
        $include->method('getRelation')->willReturn('test_relation');

        $subject = new stdClass();
        $fields = ['field1', 'field2'];
        $result = $strategy->apply($subject, $include, $fields);

        $this->assertTrue($callbackCalled);
        $this->assertSame($subject, $receivedSubject);
        $this->assertEquals('test_relation', $receivedRelation);
        $this->assertEquals($fields, $receivedFields);
        $this->assertSame($subject, $result);
    }

    #[Test]
    public function callback_include_strategy_returns_subject_when_no_callback(): void
    {
        $strategy = new CallbackIncludeStrategy();

        $include = $this->createMock(IncludeDefinitionInterface::class);
        $include->method('getCallback')->willReturn(null);
        $include->method('getRelation')->willReturn('test_relation');

        $subject = new stdClass();
        $result = $strategy->apply($subject, $include, []);

        $this->assertSame($subject, $result);
    }

    #[Test]
    public function callback_filter_strategy_works_with_array_subject(): void
    {
        $strategy = new CallbackFilterStrategy();

        $modified = false;
        $callback = function (&$subject) use (&$modified) {
            $subject['modified'] = true;
            $modified = true;
        };

        $filter = $this->createMock(FilterDefinitionInterface::class);
        $filter->method('getCallback')->willReturn(Closure::fromCallable($callback));
        $filter->method('getProperty')->willReturn('prop');

        $subject = ['data' => 'value'];
        $strategy->apply($subject, $filter, null);

        $this->assertTrue($modified);
    }

    #[Test]
    public function callback_sort_strategy_works_with_custom_object(): void
    {
        $strategy = new CallbackSortStrategy();

        $customObj = new class {
            public string $sortField = '';
            public string $sortDirection = '';
        };

        $callback = function ($obj, $direction, $property) {
            $obj->sortField = $property;
            $obj->sortDirection = $direction;
        };

        $sort = $this->createMock(SortDefinitionInterface::class);
        $sort->method('getCallback')->willReturn(Closure::fromCallable($callback));
        $sort->method('getProperty')->willReturn('created_at');

        $strategy->apply($customObj, $sort, 'desc');

        $this->assertEquals('created_at', $customObj->sortField);
        $this->assertEquals('desc', $customObj->sortDirection);
    }

    #[Test]
    public function callback_include_strategy_works_with_empty_fields(): void
    {
        $strategy = new CallbackIncludeStrategy();

        $receivedFields = null;
        $callback = function ($subject, $relation, $fields) use (&$receivedFields) {
            $receivedFields = $fields;
        };

        $include = $this->createMock(IncludeDefinitionInterface::class);
        $include->method('getCallback')->willReturn(Closure::fromCallable($callback));
        $include->method('getRelation')->willReturn('relation');

        $strategy->apply(new stdClass(), $include, []);

        $this->assertEquals([], $receivedFields);
    }
}
