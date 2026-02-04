<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Jackardios\QueryWizard\Filters\CallbackFilter;
use Jackardios\QueryWizard\Filters\PassthroughFilter;
use Jackardios\QueryWizard\Includes\CallbackInclude;
use Jackardios\QueryWizard\Sorts\CallbackSort;
use Jackardios\QueryWizard\Tests\TestCase;
use stdClass;

class GenericStrategiesTest extends TestCase
{
    #[Test]
    public function callback_filter_applies_callback(): void
    {
        $callbackCalled = false;
        $receivedSubject = null;
        $receivedValue = null;
        $receivedProperty = null;

        $filter = CallbackFilter::make('test_property', function ($subject, $value, $property) use (&$callbackCalled, &$receivedSubject, &$receivedValue, &$receivedProperty) {
            $callbackCalled = true;
            $receivedSubject = $subject;
            $receivedValue = $value;
            $receivedProperty = $property;
        });

        $subject = new stdClass();
        $result = $filter->apply($subject, 'test_value');

        $this->assertTrue($callbackCalled);
        $this->assertSame($subject, $receivedSubject);
        $this->assertEquals('test_value', $receivedValue);
        $this->assertEquals('test_property', $receivedProperty);
        $this->assertSame($subject, $result);
    }

    #[Test]
    public function callback_filter_has_correct_type(): void
    {
        $filter = CallbackFilter::make('test_property', fn() => null);

        $this->assertEquals('callback', $filter->getType());
    }

    #[Test]
    public function callback_filter_has_correct_name_and_property(): void
    {
        $filter = CallbackFilter::make('test_property', fn() => null, 'test_alias');

        $this->assertEquals('test_alias', $filter->getName());
        $this->assertEquals('test_property', $filter->getProperty());
        $this->assertEquals('test_alias', $filter->getAlias());
    }

    #[Test]
    public function passthrough_filter_returns_subject_unchanged(): void
    {
        $filter = PassthroughFilter::make('test_name');

        $subject = new stdClass();
        $subject->data = 'value';

        $result = $filter->apply($subject, 'some_value');

        $this->assertSame($subject, $result);
        $this->assertEquals('passthrough', $filter->getType());
    }

    #[Test]
    public function callback_sort_applies_callback(): void
    {
        $callbackCalled = false;
        $receivedSubject = null;
        $receivedDirection = null;
        $receivedProperty = null;

        $sort = CallbackSort::make('test_property', function ($subject, $direction, $property) use (&$callbackCalled, &$receivedSubject, &$receivedDirection, &$receivedProperty) {
            $callbackCalled = true;
            $receivedSubject = $subject;
            $receivedDirection = $direction;
            $receivedProperty = $property;
        });

        $subject = new stdClass();
        $result = $sort->apply($subject, 'desc');

        $this->assertTrue($callbackCalled);
        $this->assertSame($subject, $receivedSubject);
        $this->assertEquals('desc', $receivedDirection);
        $this->assertEquals('test_property', $receivedProperty);
        $this->assertSame($subject, $result);
    }

    #[Test]
    public function callback_sort_has_correct_type(): void
    {
        $sort = CallbackSort::make('test_property', fn() => null);

        $this->assertEquals('callback', $sort->getType());
    }

    #[Test]
    public function callback_sort_has_correct_name_and_property(): void
    {
        $sort = CallbackSort::make('test_property', fn() => null, 'test_alias');

        $this->assertEquals('test_alias', $sort->getName());
        $this->assertEquals('test_property', $sort->getProperty());
        $this->assertEquals('test_alias', $sort->getAlias());
    }

    #[Test]
    public function callback_include_applies_callback(): void
    {
        $callbackCalled = false;
        $receivedSubject = null;
        $receivedRelation = null;
        $receivedFields = null;

        $include = CallbackInclude::make('test_relation', function ($subject, $relation, $fields) use (&$callbackCalled, &$receivedSubject, &$receivedRelation, &$receivedFields) {
            $callbackCalled = true;
            $receivedSubject = $subject;
            $receivedRelation = $relation;
            $receivedFields = $fields;
        });

        $subject = new stdClass();
        $fields = ['field1', 'field2'];
        $result = $include->apply($subject, $fields);

        $this->assertTrue($callbackCalled);
        $this->assertSame($subject, $receivedSubject);
        $this->assertEquals('test_relation', $receivedRelation);
        $this->assertEquals($fields, $receivedFields);
        $this->assertSame($subject, $result);
    }

    #[Test]
    public function callback_include_has_correct_type(): void
    {
        $include = CallbackInclude::make('test_relation', fn() => null);

        $this->assertEquals('callback', $include->getType());
    }

    #[Test]
    public function callback_include_has_correct_name_and_relation(): void
    {
        $include = CallbackInclude::make('test_relation', fn() => null, 'test_alias');

        $this->assertEquals('test_alias', $include->getName());
        $this->assertEquals('test_relation', $include->getRelation());
        $this->assertEquals('test_alias', $include->getAlias());
    }

    #[Test]
    public function callback_filter_works_with_array_subject(): void
    {
        $modified = false;
        $filter = CallbackFilter::make('prop', function (&$subject) use (&$modified) {
            $subject['modified'] = true;
            $modified = true;
        });

        $subject = ['data' => 'value'];
        $filter->apply($subject, null);

        $this->assertTrue($modified);
    }

    #[Test]
    public function callback_sort_works_with_custom_object(): void
    {
        $customObj = new class {
            public string $sortField = '';
            public string $sortDirection = '';
        };

        $sort = CallbackSort::make('created_at', function ($obj, $direction, $property) {
            $obj->sortField = $property;
            $obj->sortDirection = $direction;
        });

        $sort->apply($customObj, 'desc');

        $this->assertEquals('created_at', $customObj->sortField);
        $this->assertEquals('desc', $customObj->sortDirection);
    }

    #[Test]
    public function callback_include_works_with_empty_fields(): void
    {
        $receivedFields = null;
        $include = CallbackInclude::make('relation', function ($subject, $relation, $fields) use (&$receivedFields) {
            $receivedFields = $fields;
        });

        $include->apply(new stdClass(), []);

        $this->assertEquals([], $receivedFields);
    }

    #[Test]
    public function callback_filter_supports_default_value(): void
    {
        $filter = CallbackFilter::make('test', fn() => null)->default('default_value');

        $this->assertEquals('default_value', $filter->getDefault());
    }

    #[Test]
    public function callback_filter_supports_prepare_value(): void
    {
        $filter = CallbackFilter::make('test', fn() => null)
            ->prepareValueWith(fn($value) => strtoupper($value));

        $this->assertEquals('HELLO', $filter->prepareValue('hello'));
    }

    #[Test]
    public function passthrough_filter_supports_default_value(): void
    {
        $filter = PassthroughFilter::make('test')->default('default_value');

        $this->assertEquals('default_value', $filter->getDefault());
    }

    #[Test]
    public function filters_are_immutable(): void
    {
        $original = CallbackFilter::make('test', fn() => null);
        $withDefault = $original->default('value');
        $withAlias = $original->alias('alias');

        $this->assertNull($original->getDefault());
        $this->assertEquals('value', $withDefault->getDefault());
        $this->assertNull($original->getAlias());
        $this->assertEquals('alias', $withAlias->getAlias());
        $this->assertNotSame($original, $withDefault);
        $this->assertNotSame($original, $withAlias);
    }

    #[Test]
    public function sorts_are_immutable(): void
    {
        $original = CallbackSort::make('test', fn() => null);
        $withAlias = $original->alias('alias');

        $this->assertNull($original->getAlias());
        $this->assertEquals('alias', $withAlias->getAlias());
        $this->assertNotSame($original, $withAlias);
    }

    #[Test]
    public function includes_are_immutable(): void
    {
        $original = CallbackInclude::make('test', fn() => null);
        $withAlias = $original->alias('alias');

        $this->assertNull($original->getAlias());
        $this->assertEquals('alias', $withAlias->getAlias());
        $this->assertNotSame($original, $withAlias);
    }
}
