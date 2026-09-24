<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use Illuminate\Support\Str;
use Jackardios\QueryWizard\Support\NameConverter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class NameConverterTest extends TestCase
{
    #[Test]
    public function it_converts_camel_case_to_snake_case(): void
    {
        $this->assertEquals('first_name', NameConverter::toSnakeCase('firstName'));
        $this->assertEquals('created_at', NameConverter::toSnakeCase('createdAt'));
        $this->assertEquals('user_id', NameConverter::toSnakeCase('userId'));
    }

    #[Test]
    public function it_preserves_already_snake_case(): void
    {
        $this->assertEquals('first_name', NameConverter::toSnakeCase('first_name'));
        $this->assertEquals('created_at', NameConverter::toSnakeCase('created_at'));
    }

    #[Test]
    public function it_handles_lowercase(): void
    {
        $this->assertEquals('name', NameConverter::toSnakeCase('name'));
    }

    #[Test]
    public function it_handles_uppercase(): void
    {
        $this->assertEquals('u_r_l', NameConverter::toSnakeCase('URL'));
        $this->assertEquals('h_t_t_p', NameConverter::toSnakeCase('HTTP'));
    }

    #[Test]
    public function it_converts_path_to_snake_case(): void
    {
        $this->assertEquals('user.first_name', NameConverter::pathToSnakeCase('user.firstName'));
        $this->assertEquals('post.created_at', NameConverter::pathToSnakeCase('post.createdAt'));
        $this->assertEquals('author.profile.full_name', NameConverter::pathToSnakeCase('author.profile.fullName'));
    }

    #[Test]
    public function it_preserves_already_snake_case_paths(): void
    {
        $this->assertEquals('user.first_name', NameConverter::pathToSnakeCase('user.first_name'));
    }

    #[Test]
    public function it_converts_all_path_segments(): void
    {
        $this->assertEquals('related_models.nested_models.field_name', NameConverter::pathToSnakeCase('relatedModels.nestedModels.fieldName'));
    }

    #[Test]
    public function convert_path_accepts_custom_converter(): void
    {
        $result = NameConverter::convertPath('a.b.c', fn ($s) => strtoupper($s));

        $this->assertEquals('A.B.C', $result);
    }

    #[Test]
    public function snake_case_conversion_matches_str_snake(): void
    {
        mt_srand(38);
        $alphabet = ['a', 'b', 'Z', 'Q', '1', '9', '_', '-', '.', ' ', "\t", 'Ä', 'ä', 'É', 'ß', 'Ω', 'ω', '$'];
        $values = ['', ' ', 'HTTPRequest', 'aB', 'a  B', 'x-Y', 'ÄbcDef', 'already_snake', 'Title Case Words', '1stPlace'];

        for ($i = 0; $i < 2000; $i++) {
            $value = '';
            for ($length = mt_rand(1, 12); $length > 0; $length--) {
                $value .= $alphabet[mt_rand(0, count($alphabet) - 1)];
            }
            $values[] = $value;
        }

        foreach ($values as $value) {
            $this->assertSame(Str::snake($value), NameConverter::toSnakeCase($value), json_encode($value, JSON_THROW_ON_ERROR));
        }
    }

    #[Test]
    public function snake_case_conversion_of_malformed_utf8_does_not_fail(): void
    {
        $this->assertSame('', NameConverter::toSnakeCase("A\xff"));
    }

    #[Test]
    public function snake_case_cache_is_bounded_and_leaves_the_str_cache_alone(): void
    {
        Str::flushCache();

        for ($i = 0; $i < 1000; $i++) {
            NameConverter::toSnakeCase("requestName{$i}");
        }

        $this->assertLessThanOrEqual(256, count((new ReflectionProperty(NameConverter::class, 'snakeCache'))->getValue()));
        $this->assertSame([], (new ReflectionProperty(Str::class, 'snakeCache'))->getValue());
        $this->assertSame('request_name0', NameConverter::toSnakeCase('requestName0'));
        $this->assertSame('request_name999', NameConverter::toSnakeCase('requestName999'));
    }
}
