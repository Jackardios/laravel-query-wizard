<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use Jackardios\QueryWizard\Support\NameConverter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
}
