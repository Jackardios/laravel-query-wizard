<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use Jackardios\QueryWizard\Support\FilterValueTransformer;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class FilterValueTransformerTest extends TestCase
{
    // ========== String Passthrough Tests ==========

    #[Test]
    public function it_preserves_true_string(): void
    {
        $transformer = new FilterValueTransformer;

        $result = $transformer->transform('true');

        $this->assertSame('true', $result);
    }

    #[Test]
    public function it_preserves_false_string(): void
    {
        $transformer = new FilterValueTransformer;

        $result = $transformer->transform('false');

        $this->assertSame('false', $result);
    }

    #[Test]
    public function it_preserves_uppercase_true(): void
    {
        $transformer = new FilterValueTransformer;

        $this->assertSame('TRUE', $transformer->transform('TRUE'));
    }

    #[Test]
    public function it_preserves_uppercase_false(): void
    {
        $transformer = new FilterValueTransformer;

        $this->assertSame('FALSE', $transformer->transform('FALSE'));
    }

    #[Test]
    public function it_preserves_mixed_case_boolean_strings(): void
    {
        $transformer = new FilterValueTransformer;

        $this->assertSame('True', $transformer->transform('True'));
        $this->assertSame('False', $transformer->transform('False'));
    }

    // ========== Empty String Tests ==========

    #[Test]
    public function it_transforms_empty_string_to_null(): void
    {
        $transformer = new FilterValueTransformer;

        $result = $transformer->transform('');

        $this->assertNull($result);
    }

    // ========== Array Splitting Tests ==========

    #[Test]
    public function it_transforms_comma_separated_string_to_array(): void
    {
        $transformer = new FilterValueTransformer;

        $result = $transformer->transform('a,b,c');

        $this->assertEquals(['a', 'b', 'c'], $result);
    }

    #[Test]
    public function it_trims_whitespace_when_splitting(): void
    {
        $transformer = new FilterValueTransformer;

        $result = $transformer->transform(' a , b , c ');

        $this->assertEquals(['a', 'b', 'c'], $result);
    }

    #[Test]
    public function it_filters_empty_values_when_splitting(): void
    {
        $transformer = new FilterValueTransformer;

        $result = $transformer->transform('a,,b,,,c');

        $this->assertEquals(['a', 'b', 'c'], $result);
    }

    #[Test]
    public function it_returns_empty_array_for_only_commas(): void
    {
        $transformer = new FilterValueTransformer;

        $result = $transformer->transform(',,,');

        $this->assertEquals([], $result);
    }

    #[Test]
    public function it_returns_empty_array_for_single_comma(): void
    {
        $transformer = new FilterValueTransformer;

        $result = $transformer->transform(',');

        $this->assertEquals([], $result);
    }

    #[Test]
    public function it_uses_custom_separator(): void
    {
        $transformer = new FilterValueTransformer('|');

        $result = $transformer->transform('a|b|c');

        $this->assertEquals(['a', 'b', 'c'], $result);
    }

    #[Test]
    public function it_does_not_split_with_empty_separator(): void
    {
        $transformer = new FilterValueTransformer('');

        $result = $transformer->transform('a,b,c');

        $this->assertEquals('a,b,c', $result);
    }

    #[Test]
    public function it_does_not_split_string_without_separator(): void
    {
        $transformer = new FilterValueTransformer;

        $result = $transformer->transform('single_value');

        $this->assertEquals('single_value', $result);
    }

    // ========== Array Input Tests ==========

    #[Test]
    public function it_transforms_array_values_recursively(): void
    {
        $transformer = new FilterValueTransformer;

        $result = $transformer->transform([
            'bool' => 'true',
            'string' => 'value',
            'array' => 'a,b,c',
        ]);

        $this->assertEquals([
            'bool' => 'true',
            'string' => 'value',
            'array' => ['a', 'b', 'c'],
        ], $result);
    }

    #[Test]
    public function it_transforms_nested_arrays(): void
    {
        $transformer = new FilterValueTransformer;

        $result = $transformer->transform([
            'level1' => [
                'level2' => 'true',
                'values' => 'a,b',
            ],
        ]);

        $this->assertEquals([
            'level1' => [
                'level2' => 'true',
                'values' => ['a', 'b'],
            ],
        ], $result);
    }

    #[Test]
    public function it_preserves_array_keys(): void
    {
        $transformer = new FilterValueTransformer;

        $result = $transformer->transform([
            'custom_key' => 'value',
            123 => 'numeric_key',
        ]);

        $this->assertArrayHasKey('custom_key', $result);
        $this->assertArrayHasKey(123, $result);
    }

    // ========== Non-String Input Tests ==========

    #[Test]
    public function it_returns_integer_unchanged(): void
    {
        $transformer = new FilterValueTransformer;

        $result = $transformer->transform(123);

        $this->assertSame(123, $result);
    }

    #[Test]
    public function it_returns_float_unchanged(): void
    {
        $transformer = new FilterValueTransformer;

        $result = $transformer->transform(12.34);

        $this->assertSame(12.34, $result);
    }

    #[Test]
    public function it_returns_boolean_unchanged(): void
    {
        $transformer = new FilterValueTransformer;

        $this->assertTrue($transformer->transform(true));
        $this->assertFalse($transformer->transform(false));
    }

    #[Test]
    public function it_returns_null_unchanged(): void
    {
        $transformer = new FilterValueTransformer;

        $result = $transformer->transform(null);

        $this->assertNull($result);
    }

    #[Test]
    public function it_returns_object_unchanged(): void
    {
        $transformer = new FilterValueTransformer;
        $object = new \stdClass;
        $object->value = 'test';

        $result = $transformer->transform($object);

        $this->assertSame($object, $result);
    }

    // ========== Edge Cases ==========

    #[Test]
    public function it_handles_utf8_characters(): void
    {
        $transformer = new FilterValueTransformer;

        $result = $transformer->transform('привет,мир');

        $this->assertEquals(['привет', 'мир'], $result);
    }

    #[Test]
    public function it_handles_special_characters(): void
    {
        $transformer = new FilterValueTransformer;

        $result = $transformer->transform('a@b.com,c@d.com');

        $this->assertEquals(['a@b.com', 'c@d.com'], $result);
    }

    #[Test]
    public function it_handles_numeric_strings(): void
    {
        $transformer = new FilterValueTransformer;

        $result = $transformer->transform('1,2,3');

        $this->assertEquals(['1', '2', '3'], $result);
    }

    #[Test]
    public function it_handles_whitespace_only_values(): void
    {
        $transformer = new FilterValueTransformer;

        $result = $transformer->transform('a,   ,b');

        $this->assertEquals(['a', 'b'], $result);
    }

    #[Test]
    public function it_handles_trailing_separator(): void
    {
        $transformer = new FilterValueTransformer;

        $result = $transformer->transform('a,b,c,');

        $this->assertEquals(['a', 'b', 'c'], $result);
    }

    #[Test]
    public function it_handles_leading_separator(): void
    {
        $transformer = new FilterValueTransformer;

        $result = $transformer->transform(',a,b,c');

        $this->assertEquals(['a', 'b', 'c'], $result);
    }

    #[Test]
    #[DataProvider('complexTransformProvider')]
    public function it_handles_complex_transformations(mixed $input, mixed $expected): void
    {
        $transformer = new FilterValueTransformer;

        $result = $transformer->transform($input);

        $this->assertEquals($expected, $result);
    }

    public static function complexTransformProvider(): array
    {
        return [
            'nested with empty strings' => [
                ['a' => '', 'b' => 'value'],
                ['a' => null, 'b' => 'value'],
            ],
            'array with boolean strings' => [
                ['active' => 'true', 'deleted' => 'false'],
                ['active' => 'true', 'deleted' => 'false'],
            ],
            'mixed depth array' => [
                [
                    'simple' => 'value',
                    'nested' => ['deep' => 'true'],
                    'list' => 'a,b',
                ],
                [
                    'simple' => 'value',
                    'nested' => ['deep' => 'true'],
                    'list' => ['a', 'b'],
                ],
            ],
        ];
    }

    #[Test]
    public function it_preserves_single_value_that_looks_like_array(): void
    {
        $transformer = new FilterValueTransformer('|');

        $result = $transformer->transform('a,b,c');

        $this->assertEquals('a,b,c', $result);
    }

    #[Test]
    public function it_handles_empty_array(): void
    {
        $transformer = new FilterValueTransformer;

        $result = $transformer->transform([]);

        $this->assertEquals([], $result);
    }

    // ========== Split-to-Array String Preservation Tests ==========

    #[Test]
    public function it_preserves_boolean_strings_in_comma_separated_string(): void
    {
        $transformer = new FilterValueTransformer;

        $result = $transformer->transform('true,false');

        $this->assertSame(['true', 'false'], $result);
    }

    #[Test]
    public function it_preserves_all_strings_in_comma_separated_string(): void
    {
        $transformer = new FilterValueTransformer;

        $result = $transformer->transform('1,true,hello');

        $this->assertSame(['1', 'true', 'hello'], $result);
    }

    #[Test]
    public function it_filters_empty_parts_and_preserves_remaining(): void
    {
        $transformer = new FilterValueTransformer;

        $result = $transformer->transform(',,true,');

        $this->assertSame(['true'], $result);
    }
}
