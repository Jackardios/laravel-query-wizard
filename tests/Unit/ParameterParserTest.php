<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use Jackardios\QueryWizard\Support\ParameterParser;
use Jackardios\QueryWizard\Tests\TestCase;
use Jackardios\QueryWizard\Values\Sort;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ParameterParserTest extends TestCase
{
    // ========== parseList Tests ==========

    #[Test]
    public function it_parses_comma_separated_string_to_collection(): void
    {
        $parser = new ParameterParser;

        $result = $parser->parseList('a,b,c');

        $this->assertEquals(['a', 'b', 'c'], $result->toArray());
    }

    #[Test]
    public function it_parses_array_to_collection(): void
    {
        $parser = new ParameterParser;

        $result = $parser->parseList(['a', 'b', 'c']);

        $this->assertEquals(['a', 'b', 'c'], $result->toArray());
    }

    #[Test]
    public function it_trims_whitespace_from_list_items(): void
    {
        $parser = new ParameterParser;

        $result = $parser->parseList(' a , b , c ');

        $this->assertEquals(['a', 'b', 'c'], $result->toArray());
    }

    #[Test]
    public function it_filters_empty_values_from_list(): void
    {
        $parser = new ParameterParser;

        $result = $parser->parseList('a,,b,,,c');

        $this->assertEquals(['a', 'b', 'c'], $result->toArray());
    }

    #[Test]
    public function it_removes_duplicate_values_from_list(): void
    {
        $parser = new ParameterParser;

        $result = $parser->parseList('a,b,a,c,b');

        $this->assertEquals(['a', 'b', 'c'], $result->toArray());
    }

    #[Test]
    public function it_returns_empty_collection_for_empty_string(): void
    {
        $parser = new ParameterParser;

        $result = $parser->parseList('');

        $this->assertTrue($result->isEmpty());
    }

    #[Test]
    public function it_returns_empty_collection_for_empty_array(): void
    {
        $parser = new ParameterParser;

        $result = $parser->parseList([]);

        $this->assertTrue($result->isEmpty());
    }

    #[Test]
    public function it_handles_single_value_string(): void
    {
        $parser = new ParameterParser;

        $result = $parser->parseList('single');

        $this->assertEquals(['single'], $result->toArray());
    }

    #[Test]
    public function it_uses_custom_separator(): void
    {
        $parser = new ParameterParser('|');

        $result = $parser->parseList('a|b|c');

        $this->assertEquals(['a', 'b', 'c'], $result->toArray());
    }

    #[Test]
    public function it_treats_string_as_single_value_with_empty_separator(): void
    {
        $parser = new ParameterParser('');

        $result = $parser->parseList('a,b,c');

        $this->assertEquals(['a,b,c'], $result->toArray());
    }

    #[Test]
    public function it_handles_non_string_values_in_array(): void
    {
        $parser = new ParameterParser;

        $result = $parser->parseList([1, 2, 3]);

        $this->assertEquals([1, 2, 3], $result->toArray());
    }

    // ========== parseSorts Tests ==========

    #[Test]
    public function it_parses_sorts_from_string(): void
    {
        $parser = new ParameterParser;

        $result = $parser->parseSorts('name,-created_at');

        $this->assertCount(2, $result);
        $this->assertInstanceOf(Sort::class, $result[0]);
        $this->assertEquals('name', $result[0]->getField());
        $this->assertEquals('asc', $result[0]->getDirection());
        $this->assertEquals('created_at', $result[1]->getField());
        $this->assertEquals('desc', $result[1]->getDirection());
    }

    #[Test]
    public function it_parses_sorts_from_array(): void
    {
        $parser = new ParameterParser;

        $result = $parser->parseSorts(['name', '-created_at']);

        $this->assertCount(2, $result);
        $this->assertEquals('name', $result[0]->getField());
        $this->assertEquals('created_at', $result[1]->getField());
    }

    #[Test]
    public function it_trims_whitespace_from_sorts(): void
    {
        $parser = new ParameterParser;

        $result = $parser->parseSorts(' name , -created_at ');

        $this->assertCount(2, $result);
        $this->assertEquals('name', $result[0]->getField());
        $this->assertEquals('created_at', $result[1]->getField());
    }

    #[Test]
    public function it_removes_duplicate_sorts_by_field(): void
    {
        $parser = new ParameterParser;

        $result = $parser->parseSorts('name,-name,name');

        $this->assertCount(1, $result);
        $this->assertEquals('name', $result[0]->getField());
    }

    #[Test]
    public function it_filters_empty_sort_values(): void
    {
        $parser = new ParameterParser;

        $result = $parser->parseSorts('name,,created_at');

        $this->assertCount(2, $result);
    }

    #[Test]
    public function it_returns_empty_collection_for_empty_sorts(): void
    {
        $parser = new ParameterParser;

        $result = $parser->parseSorts('');

        $this->assertTrue($result->isEmpty());
    }

    // ========== parseFields Tests ==========

    #[Test]
    public function it_parses_fields_from_array_format(): void
    {
        $parser = new ParameterParser;

        $result = $parser->parseFields([
            'user' => ['id', 'name'],
            'post' => ['title', 'body'],
        ]);

        $this->assertEquals(['id', 'name'], $result->get('user'));
        $this->assertEquals(['title', 'body'], $result->get('post'));
    }

    #[Test]
    public function it_parses_fields_from_string_format(): void
    {
        $parser = new ParameterParser;

        $result = $parser->parseFields('user.id,user.name,post.title');

        $this->assertEquals(['id', 'name'], $result->get('user'));
        $this->assertEquals(['title'], $result->get('post'));
    }

    #[Test]
    public function it_parses_simple_fields_without_resource(): void
    {
        $parser = new ParameterParser;

        $result = $parser->parseFields('id,name,email');

        $this->assertEquals(['id', 'name', 'email'], $result->get(''));
    }

    #[Test]
    public function it_parses_mixed_fields_with_and_without_resource(): void
    {
        $parser = new ParameterParser;

        $result = $parser->parseFields('user.id,name,post.title');

        $this->assertEquals(['id'], $result->get('user'));
        $this->assertEquals(['name'], $result->get(''));
        $this->assertEquals(['title'], $result->get('post'));
    }

    #[Test]
    public function it_handles_nested_resource_in_fields(): void
    {
        $parser = new ParameterParser;

        $result = $parser->parseFields('user.profile.avatar,user.name');

        $this->assertEquals(['avatar'], $result->get('user.profile'));
        $this->assertEquals(['name'], $result->get('user'));
    }

    #[Test]
    public function it_parses_comma_separated_fields_in_array_format(): void
    {
        $parser = new ParameterParser;

        $result = $parser->parseFields([
            'user' => 'id,name,email',
        ]);

        $this->assertEquals(['id', 'name', 'email'], $result->get('user'));
    }

    #[Test]
    public function it_filters_empty_field_groups(): void
    {
        $parser = new ParameterParser;

        $result = $parser->parseFields([
            'user' => ['id', 'name'],
            'post' => [],
        ]);

        $this->assertTrue($result->has('user'));
        $this->assertFalse($result->has('post'));
    }

    #[Test]
    public function it_trims_whitespace_from_fields(): void
    {
        $parser = new ParameterParser;

        $result = $parser->parseFields(' user.id , user.name ');

        $this->assertEquals(['id', 'name'], $result->get('user'));
    }

    #[Test]
    public function it_removes_duplicate_fields(): void
    {
        $parser = new ParameterParser;

        $result = $parser->parseFields('user.id,user.name,user.id');

        $this->assertEquals(['id', 'name'], $result->get('user'));
    }

    #[Test]
    public function it_returns_empty_collection_for_empty_fields(): void
    {
        $parser = new ParameterParser;

        $result = $parser->parseFields('');

        $this->assertTrue($result->isEmpty());
    }

    // ========== Edge Cases ==========

    #[Test]
    public function it_handles_utf8_characters_in_list(): void
    {
        $parser = new ParameterParser;

        $result = $parser->parseList('имя,фамилия,город');

        $this->assertEquals(['имя', 'фамилия', 'город'], $result->toArray());
    }

    #[Test]
    public function it_handles_utf8_characters_in_fields(): void
    {
        $parser = new ParameterParser;

        $result = $parser->parseFields('пользователь.имя,пользователь.фамилия');

        $this->assertEquals(['имя', 'фамилия'], $result->get('пользователь'));
    }

    #[Test]
    public function it_handles_special_characters_in_list(): void
    {
        $parser = new ParameterParser;

        $result = $parser->parseList('field-name,field_name,field.name');

        $this->assertEquals(['field-name', 'field_name', 'field.name'], $result->toArray());
    }

    #[Test]
    public function it_handles_numeric_strings_in_list(): void
    {
        $parser = new ParameterParser;

        $result = $parser->parseList('1,2,3');

        $this->assertEquals(['1', '2', '3'], $result->toArray());
    }

    #[Test]
    #[DataProvider('nullAndMixedValuesProvider')]
    public function it_handles_null_and_mixed_values_in_array(array $input, array $expected): void
    {
        $parser = new ParameterParser;

        $result = $parser->parseList($input);

        $this->assertEquals($expected, $result->toArray());
    }

    public static function nullAndMixedValuesProvider(): array
    {
        return [
            'with null' => [['a', null, 'b'], ['a', 'b']],
            'with false' => [['a', false, 'b'], ['a', 'b']],
            'with zero' => [['a', 0, 'b'], ['a', 'b']], // 0 is falsy, filtered out
            'with empty string' => [['a', '', 'b'], ['a', 'b']],
        ];
    }

    #[Test]
    public function it_handles_only_commas_string(): void
    {
        $parser = new ParameterParser;

        $result = $parser->parseList(',,,');

        $this->assertTrue($result->isEmpty());
    }

    #[Test]
    public function it_handles_trailing_separator(): void
    {
        $parser = new ParameterParser;

        $result = $parser->parseList('a,b,c,');

        $this->assertEquals(['a', 'b', 'c'], $result->toArray());
    }

    #[Test]
    public function it_handles_leading_separator(): void
    {
        $parser = new ParameterParser;

        $result = $parser->parseList(',a,b,c');

        $this->assertEquals(['a', 'b', 'c'], $result->toArray());
    }
}
