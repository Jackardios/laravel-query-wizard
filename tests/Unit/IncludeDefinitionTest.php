<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Eloquent\EloquentInclude;
use Jackardios\QueryWizard\Eloquent\Includes\CountInclude;
use Jackardios\QueryWizard\Eloquent\Includes\RelationshipInclude;
use Jackardios\QueryWizard\Includes\CallbackInclude;
use PHPUnit\Framework\TestCase;

class IncludeDefinitionTest extends TestCase
{
    // ========== RelationshipInclude Base Tests ==========

    #[Test]
    public function it_creates_relationship_include_with_make(): void
    {
        $include = RelationshipInclude::make('posts');

        $this->assertEquals('relationship', $include->getType());
        $this->assertEquals('posts', $include->getRelation());
        $this->assertEquals('posts', $include->getName()); // name = alias ?? relation
        $this->assertNull($include->getAlias());
    }

    #[Test]
    public function it_sets_alias(): void
    {
        $include = RelationshipInclude::make('publishedPosts')->alias('posts');

        $this->assertEquals('publishedPosts', $include->getRelation());
        $this->assertEquals('posts', $include->getName());
        $this->assertEquals('posts', $include->getAlias());
    }

    #[Test]
    public function it_sets_alias_fluently(): void
    {
        $include = RelationshipInclude::make('posts');
        $result = $include->alias('alias');

        $this->assertSame($include, $result);
        $this->assertEquals('alias', $include->getAlias());
    }

    #[Test]
    public function it_creates_callback_include_with_make(): void
    {
        $cb = fn($query, $relation) => $query->with($relation);
        $include = CallbackInclude::make('custom', $cb);

        $this->assertEquals('custom', $include->getRelation());
        $this->assertEquals('callback', $include->getType());
    }

    // ========== Factory Method Tests (EloquentInclude) ==========

    #[Test]
    public function it_creates_relationship_include(): void
    {
        $include = EloquentInclude::relationship('posts');

        $this->assertInstanceOf(RelationshipInclude::class, $include);
        $this->assertInstanceOf(IncludeInterface::class, $include);
        $this->assertEquals('posts', $include->getRelation());
        $this->assertEquals('posts', $include->getName());
        $this->assertEquals('relationship', $include->getType());
        $this->assertNull($include->getAlias());
    }

    #[Test]
    public function it_creates_relationship_include_with_alias(): void
    {
        $include = EloquentInclude::relationship('publishedPosts', 'posts');

        $this->assertEquals('publishedPosts', $include->getRelation());
        $this->assertEquals('posts', $include->getName());
        $this->assertEquals('posts', $include->getAlias());
    }

    #[Test]
    public function it_creates_nested_relationship_include(): void
    {
        $include = EloquentInclude::relationship('posts.comments');

        $this->assertEquals('posts.comments', $include->getRelation());
        $this->assertEquals('posts.comments', $include->getName());
    }

    #[Test]
    public function it_creates_deeply_nested_relationship_include(): void
    {
        $include = EloquentInclude::relationship('posts.comments.author');

        $this->assertEquals('posts.comments.author', $include->getRelation());
    }

    #[Test]
    public function it_creates_count_include(): void
    {
        $include = EloquentInclude::count('posts');

        $this->assertInstanceOf(CountInclude::class, $include);
        $this->assertInstanceOf(IncludeInterface::class, $include);
        $this->assertEquals('posts', $include->getRelation());
        // Without alias, getName() returns the relation name
        $this->assertEquals('posts', $include->getName());
        $this->assertEquals('count', $include->getType());
        $this->assertNull($include->getAlias());
    }

    #[Test]
    public function it_creates_count_include_with_alias(): void
    {
        $include = EloquentInclude::count('posts', 'postsCount');

        $this->assertEquals('posts', $include->getRelation());
        $this->assertEquals('postsCount', $include->getName());
    }

    #[Test]
    public function it_creates_callback_include(): void
    {
        $callback = fn($query, $relation) => $query->with($relation);
        $include = EloquentInclude::callback('custom', $callback);

        $this->assertInstanceOf(CallbackInclude::class, $include);
        $this->assertInstanceOf(IncludeInterface::class, $include);
        $this->assertEquals('custom', $include->getRelation());
        $this->assertEquals('callback', $include->getType());
    }

    #[Test]
    public function it_creates_callback_include_with_alias(): void
    {
        $callback = fn($query, $relation) => $query->with($relation);
        $include = EloquentInclude::callback('customRelation', $callback, 'custom');

        $this->assertEquals('customRelation', $include->getRelation());
        $this->assertEquals('custom', $include->getName());
    }

    #[Test]
    public function it_sets_alias_via_method(): void
    {
        $include = EloquentInclude::relationship('posts')->alias('alias');

        $this->assertEquals('alias', $include->getName());
        $this->assertEquals('alias', $include->getAlias());
    }

    #[Test]
    public function factory_sets_alias_fluently(): void
    {
        $include = EloquentInclude::relationship('posts');
        $result = $include->alias('alias');

        $this->assertSame($include, $result);
        $this->assertEquals('alias', $include->getAlias());
    }

    #[Test]
    public function it_handles_empty_relation_name(): void
    {
        $include = RelationshipInclude::make('');

        $this->assertEquals('', $include->getRelation());
        $this->assertEquals('', $include->getName());
    }

    #[Test]
    public function it_handles_relation_with_special_characters(): void
    {
        $include = EloquentInclude::relationship('user_posts');

        $this->assertEquals('user_posts', $include->getRelation());
    }

    #[Test]
    public function it_handles_camelCase_relation(): void
    {
        $include = EloquentInclude::relationship('relatedModels');

        $this->assertEquals('relatedModels', $include->getRelation());
    }

    #[Test]
    public function it_handles_nested_count(): void
    {
        $include = EloquentInclude::count('posts.comments');

        $this->assertEquals('posts.comments', $include->getRelation());
        $this->assertEquals('count', $include->getType());
    }
}
