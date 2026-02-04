<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Drivers\Eloquent\Definitions\IncludeDefinition;
use Jackardios\QueryWizard\Drivers\Eloquent\Includes\RelationshipInclude;
use Jackardios\QueryWizard\Drivers\Eloquent\Includes\CountInclude;
use Jackardios\QueryWizard\Includes\CallbackInclude;
use PHPUnit\Framework\TestCase;

class IncludeDefinitionTest extends TestCase
{
    #[Test]
    public function it_creates_relationship_include(): void
    {
        $include = IncludeDefinition::relationship('posts');

        $this->assertInstanceOf(IncludeInterface::class, $include);
        $this->assertInstanceOf(RelationshipInclude::class, $include);
        $this->assertEquals('posts', $include->getRelation());
        $this->assertEquals('posts', $include->getName());
        $this->assertEquals('relationship', $include->getType());
        $this->assertNull($include->getAlias());
    }

    #[Test]
    public function it_creates_relationship_include_with_alias(): void
    {
        $include = IncludeDefinition::relationship('publishedPosts', 'posts');

        $this->assertEquals('publishedPosts', $include->getRelation());
        $this->assertEquals('posts', $include->getName());
        $this->assertEquals('posts', $include->getAlias());
    }

    #[Test]
    public function it_creates_nested_relationship_include(): void
    {
        $include = IncludeDefinition::relationship('posts.comments');

        $this->assertEquals('posts.comments', $include->getRelation());
        $this->assertEquals('posts.comments', $include->getName());
    }

    #[Test]
    public function it_creates_deeply_nested_relationship_include(): void
    {
        $include = IncludeDefinition::relationship('posts.comments.author');

        $this->assertEquals('posts.comments.author', $include->getRelation());
    }

    #[Test]
    public function it_creates_count_include(): void
    {
        $include = IncludeDefinition::count('posts');

        $this->assertInstanceOf(CountInclude::class, $include);
        $this->assertEquals('posts', $include->getRelation());
        // Without alias, getName() returns the relation name
        // The count suffix is applied during normalization in the driver
        $this->assertEquals('posts', $include->getName());
        $this->assertEquals('count', $include->getType());
        $this->assertNull($include->getAlias());
    }

    #[Test]
    public function it_creates_count_include_with_alias(): void
    {
        $include = IncludeDefinition::count('posts', 'postsCount');

        $this->assertEquals('posts', $include->getRelation());
        $this->assertEquals('postsCount', $include->getName());
    }

    #[Test]
    public function it_creates_callback_include(): void
    {
        $callback = fn($query, $relation, $fields) => $query->with($relation);
        $include = IncludeDefinition::callback('custom', $callback);

        $this->assertInstanceOf(CallbackInclude::class, $include);
        $this->assertEquals('custom', $include->getRelation());
        $this->assertEquals('callback', $include->getType());
    }

    #[Test]
    public function it_creates_callback_include_with_alias(): void
    {
        $callback = fn($query, $relation, $fields) => $query->with($relation);
        $include = IncludeDefinition::callback('customRelation', $callback, 'custom');

        $this->assertEquals('customRelation', $include->getRelation());
        $this->assertEquals('custom', $include->getName());
    }

    #[Test]
    public function it_sets_alias_via_method(): void
    {
        $include = IncludeDefinition::relationship('posts')->alias('alias');

        $this->assertEquals('alias', $include->getName());
        $this->assertEquals('alias', $include->getAlias());
    }

    #[Test]
    public function it_sets_alias_immutably(): void
    {
        $original = IncludeDefinition::relationship('posts');
        $withAlias = $original->alias('alias');

        $this->assertNull($original->getAlias());
        $this->assertEquals('alias', $withAlias->getAlias());
        $this->assertNotSame($original, $withAlias);
    }

    #[Test]
    public function it_handles_empty_relation_name(): void
    {
        $include = IncludeDefinition::relationship('');

        $this->assertEquals('', $include->getRelation());
        $this->assertEquals('', $include->getName());
    }

    #[Test]
    public function it_handles_relation_with_special_characters(): void
    {
        $include = IncludeDefinition::relationship('user_posts');

        $this->assertEquals('user_posts', $include->getRelation());
    }

    #[Test]
    public function it_handles_camelCase_relation(): void
    {
        $include = IncludeDefinition::relationship('relatedModels');

        $this->assertEquals('relatedModels', $include->getRelation());
    }

    #[Test]
    public function it_handles_nested_count(): void
    {
        $include = IncludeDefinition::count('posts.comments');

        $this->assertEquals('posts.comments', $include->getRelation());
        $this->assertEquals('count', $include->getType());
    }
}
