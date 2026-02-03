<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\Contracts\DefinitionNormalizerInterface;
use Jackardios\QueryWizard\Contracts\Definitions\FilterDefinitionInterface;
use Jackardios\QueryWizard\Contracts\Definitions\IncludeDefinitionInterface;
use Jackardios\QueryWizard\Contracts\Definitions\SortDefinitionInterface;
use Jackardios\QueryWizard\Drivers\Eloquent\Definitions\FilterDefinition;
use Jackardios\QueryWizard\Drivers\Eloquent\Definitions\IncludeDefinition;
use Jackardios\QueryWizard\Drivers\Eloquent\Definitions\SortDefinition;
use Jackardios\QueryWizard\Drivers\Eloquent\EloquentDefinitionNormalizer;
use Jackardios\QueryWizard\Tests\TestCase;

class DefinitionNormalizerTest extends TestCase
{
    private EloquentDefinitionNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new EloquentDefinitionNormalizer(app(QueryWizardConfig::class));
    }

    #[Test]
    public function it_implements_definition_normalizer_interface(): void
    {
        $this->assertInstanceOf(DefinitionNormalizerInterface::class, $this->normalizer);
    }

    #[Test]
    public function it_normalizes_string_filter_to_exact_filter(): void
    {
        $result = $this->normalizer->normalizeFilter('status');

        $this->assertInstanceOf(FilterDefinitionInterface::class, $result);
        $this->assertEquals('status', $result->getProperty());
        $this->assertEquals('status', $result->getName());
        $this->assertEquals('exact', $result->getType());
    }

    #[Test]
    public function it_returns_filter_definition_as_is(): void
    {
        $filter = FilterDefinition::partial('name');

        $result = $this->normalizer->normalizeFilter($filter);

        $this->assertSame($filter, $result);
        $this->assertEquals('partial', $result->getType());
    }

    #[Test]
    public function it_normalizes_string_sort_to_field_sort(): void
    {
        $result = $this->normalizer->normalizeSort('name');

        $this->assertInstanceOf(SortDefinitionInterface::class, $result);
        $this->assertEquals('name', $result->getProperty());
        $this->assertEquals('name', $result->getName());
        $this->assertEquals('field', $result->getType());
    }

    #[Test]
    public function it_normalizes_descending_sort_string(): void
    {
        $result = $this->normalizer->normalizeSort('-created_at');

        $this->assertInstanceOf(SortDefinitionInterface::class, $result);
        $this->assertEquals('created_at', $result->getProperty());
        $this->assertEquals('-created_at', $result->getName());
    }

    #[Test]
    public function it_returns_sort_definition_as_is(): void
    {
        $sort = SortDefinition::callback('custom', fn($q, $d, $p) => $q);

        $result = $this->normalizer->normalizeSort($sort);

        $this->assertSame($sort, $result);
        $this->assertEquals('callback', $result->getType());
    }

    #[Test]
    public function it_normalizes_string_include_to_relationship(): void
    {
        $result = $this->normalizer->normalizeInclude('posts');

        $this->assertInstanceOf(IncludeDefinitionInterface::class, $result);
        $this->assertEquals('posts', $result->getRelation());
        $this->assertEquals('posts', $result->getName());
        $this->assertEquals('relationship', $result->getType());
    }

    #[Test]
    public function it_normalizes_count_suffix_include_to_count_type(): void
    {
        $result = $this->normalizer->normalizeInclude('postsCount');

        $this->assertInstanceOf(IncludeDefinitionInterface::class, $result);
        $this->assertEquals('posts', $result->getRelation());
        $this->assertEquals('postsCount', $result->getName());
        $this->assertEquals('count', $result->getType());
    }

    #[Test]
    public function it_returns_include_definition_as_is(): void
    {
        $include = IncludeDefinition::callback('custom', fn($q, $r, $f) => $q);

        $result = $this->normalizer->normalizeInclude($include);

        $this->assertSame($include, $result);
        $this->assertEquals('callback', $result->getType());
    }

    #[Test]
    public function it_adds_count_suffix_to_count_include_without_alias(): void
    {
        $include = IncludeDefinition::count('posts');

        $result = $this->normalizer->normalizeInclude($include);

        $this->assertNotSame($include, $result);
        $this->assertEquals('posts', $result->getRelation());
        $this->assertEquals('postsCount', $result->getName());
        $this->assertEquals('count', $result->getType());
    }

    #[Test]
    public function it_preserves_count_include_with_custom_alias(): void
    {
        $include = IncludeDefinition::count('posts', 'totalPosts');

        $result = $this->normalizer->normalizeInclude($include);

        $this->assertSame($include, $result);
        $this->assertEquals('posts', $result->getRelation());
        $this->assertEquals('totalPosts', $result->getName());
    }

    #[Test]
    public function it_normalizes_nested_include_string(): void
    {
        $result = $this->normalizer->normalizeInclude('posts.comments');

        $this->assertEquals('posts.comments', $result->getRelation());
        $this->assertEquals('posts.comments', $result->getName());
        $this->assertEquals('relationship', $result->getType());
    }

    #[Test]
    public function it_normalizes_filter_with_dot_notation(): void
    {
        $result = $this->normalizer->normalizeFilter('posts.status');

        $this->assertEquals('posts.status', $result->getProperty());
        $this->assertEquals('posts.status', $result->getName());
        $this->assertEquals('exact', $result->getType());
    }
}
