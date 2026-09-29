<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use Jackardios\QueryWizard\Support\ListLimitExceeded;
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
    public function it_keeps_the_first_sort_of_each_field_in_request_order(): void
    {
        $parser = new ParameterParser;

        $result = $parser->parseSorts(['-name', 'created_at', 'name', 7, '-created_at', ' id ', '-7']);

        $this->assertSame(['-name', 'created_at', '7', 'id'], $result->map(fn ($sort) => $sort->getDirection() === 'desc' ? '-'.$sort->getField() : $sort->getField())->all());
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
        $this->assertSame([], $result->get('post'));
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

        $this->assertSame(['' => []], $result->all());
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
            'with zero' => [['a', 0, 'b'], ['a', '0', 'b']],
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

    // ========== Item Limit Tests ==========

    #[Test]
    public function a_list_within_the_limit_counts_distinct_non_blank_items(): void
    {
        $parser = new ParameterParser;

        $this->assertSame(['a', 'b'], $parser->parseList('a, a,,  ,b', 2)->all());
        $this->assertSame(['a', 'b'], $parser->parseList(['a', 'a', null, true, 'b'], 2)->all());
    }

    #[Test]
    public function a_list_over_the_limit_stops_one_item_past_it(): void
    {
        $this->assertListLimitExceeded(3, fn () => (new ParameterParser)->parseList('a,b,c,d,e', 2));
    }

    #[Test]
    public function sorts_count_each_field_once_whatever_its_direction(): void
    {
        $parser = new ParameterParser;

        $this->assertCount(2, $parser->parseSorts('name,-name,id,-', 2));
        $this->assertListLimitExceeded(3, fn () => $parser->parseSorts('name,-id,created_at', 2));
    }

    #[Test]
    public function fields_count_across_every_fieldset(): void
    {
        $parser = new ParameterParser;

        $this->assertSame(
            ['user' => ['id'], '' => ['id']],
            $parser->parseFields('user.id,user.id,id,user.', 2)->all()
        );
        $this->assertListLimitExceeded(3, fn () => $parser->parseFields(['user' => 'id,name', 'post' => ['title']], 2));
        $this->assertListLimitExceeded(3, fn () => $parser->parseFields('user.id,post.id,id', 2));
        $this->assertListLimitExceeded(3, fn () => $parser->parseFields(['user.id', 'post.id', 'id'], 2));
    }

    #[Test]
    public function a_long_list_is_not_split_past_the_limit(): void
    {
        $parser = new ParameterParser;
        $value = implode(',', range(1, 200_000));

        $this->assertListLimitExceeded(4, fn () => $parser->parseList($value, 3));
        $this->assertCount(200_000, $parser->parseList($value));
    }

    #[Test]
    public function a_multi_character_separator_splits_lazily(): void
    {
        $parser = new ParameterParser('::');

        $this->assertSame(['a', 'b:c', 'd'], $parser->parseList('a::b:c::::d::', 3)->all());
    }

    #[Test]
    public function with_snake_case_names_items_that_convert_to_one_name_count_once(): void
    {
        $parser = new ParameterParser(',', true);

        $this->assertSame(['relatedModels'], $parser->parseList('relatedModels,related_models', 1)->all());
        $this->assertCount(1, $parser->parseSorts('createdAt,-created_at', 1));
        $this->assertSame(
            ['relatedModels' => ['firstName'], 'related_models' => []],
            $parser->parseFields(['relatedModels' => 'firstName', 'related_models' => 'first_name'], 1)->all()
        );
        $this->assertListLimitExceeded(2, fn () => (new ParameterParser)->parseList('relatedModels,related_models', 1));
    }

    private function assertListLimitExceeded(int $count, \Closure $parse): void
    {
        try {
            $parse();
            $this->fail('Expected ListLimitExceeded');
        } catch (ListLimitExceeded $exception) {
            $this->assertSame($count, $exception->count);
        }
    }
}
