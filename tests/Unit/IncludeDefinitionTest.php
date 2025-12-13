<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use Closure;
use Jackardios\QueryWizard\Contracts\Definitions\IncludeDefinitionInterface;
use Jackardios\QueryWizard\Drivers\Eloquent\Definitions\IncludeDefinition;
use Jackardios\QueryWizard\Tests\TestCase;

class IncludeDefinitionTest extends TestCase
{
    /** @test */
    public function it_creates_relationship_include(): void
    {
        $include = IncludeDefinition::relationship('posts');

        $this->assertInstanceOf(IncludeDefinitionInterface::class, $include);
        $this->assertEquals('posts', $include->getRelation());
        $this->assertEquals('posts', $include->getName());
        $this->assertEquals('relationship', $include->getType());
        $this->assertNull($include->getAlias());
        $this->assertNull($include->getCallback());
        $this->assertNull($include->getStrategyClass());
        $this->assertEquals([], $include->getOptions());
    }

    /** @test */
    public function it_creates_relationship_include_with_alias(): void
    {
        $include = IncludeDefinition::relationship('publishedPosts', 'posts');

        $this->assertEquals('publishedPosts', $include->getRelation());
        $this->assertEquals('posts', $include->getName());
        $this->assertEquals('posts', $include->getAlias());
    }

    /** @test */
    public function it_creates_nested_relationship_include(): void
    {
        $include = IncludeDefinition::relationship('posts.comments');

        $this->assertEquals('posts.comments', $include->getRelation());
        $this->assertEquals('posts.comments', $include->getName());
    }

    /** @test */
    public function it_creates_deeply_nested_relationship_include(): void
    {
        $include = IncludeDefinition::relationship('posts.comments.author');

        $this->assertEquals('posts.comments.author', $include->getRelation());
    }

    /** @test */
    public function it_creates_count_include(): void
    {
        $include = IncludeDefinition::count('posts');

        $this->assertEquals('posts', $include->getRelation());
        // getName() auto-generates count suffix for count includes
        $this->assertEquals('postsCount', $include->getName());
        $this->assertEquals('count', $include->getType());
    }

    /** @test */
    public function it_creates_count_include_with_alias(): void
    {
        $include = IncludeDefinition::count('posts', 'postsCount');

        $this->assertEquals('posts', $include->getRelation());
        $this->assertEquals('postsCount', $include->getName());
    }

    /** @test */
    public function it_creates_callback_include(): void
    {
        $callback = fn($query, $relation, $fields) => $query->with($relation);
        $include = IncludeDefinition::callback('custom', $callback);

        $this->assertEquals('custom', $include->getRelation());
        $this->assertEquals('callback', $include->getType());
        $this->assertInstanceOf(Closure::class, $include->getCallback());
    }

    /** @test */
    public function it_creates_callback_include_with_alias(): void
    {
        $callback = fn($query, $relation, $fields) => $query->with($relation);
        $include = IncludeDefinition::callback('customRelation', $callback, 'custom');

        $this->assertEquals('customRelation', $include->getRelation());
        $this->assertEquals('custom', $include->getName());
    }

    /** @test */
    public function it_creates_custom_include(): void
    {
        $include = IncludeDefinition::custom('posts', 'App\\Includes\\CustomInclude');

        $this->assertEquals('posts', $include->getRelation());
        $this->assertEquals('custom', $include->getType());
        $this->assertEquals('App\\Includes\\CustomInclude', $include->getStrategyClass());
    }

    /** @test */
    public function it_creates_custom_include_with_alias(): void
    {
        $include = IncludeDefinition::custom('publishedPosts', 'App\\Includes\\CustomInclude', 'posts');

        $this->assertEquals('publishedPosts', $include->getRelation());
        $this->assertEquals('posts', $include->getName());
    }

    /** @test */
    public function it_sets_options(): void
    {
        $include = IncludeDefinition::relationship('posts')
            ->withOptions(['eager' => true]);

        $this->assertEquals(['eager' => true], $include->getOptions());
        $this->assertTrue($include->getOption('eager'));
    }

    /** @test */
    public function it_returns_default_for_missing_option(): void
    {
        $include = IncludeDefinition::relationship('posts');

        $this->assertNull($include->getOption('missing'));
        $this->assertEquals('default', $include->getOption('missing', 'default'));
    }

    /** @test */
    public function it_merges_options(): void
    {
        $include = IncludeDefinition::relationship('posts')
            ->withOptions(['key1' => 'value1'])
            ->withOptions(['key2' => 'value2']);

        $this->assertEquals(['key1' => 'value1', 'key2' => 'value2'], $include->getOptions());
    }

    /** @test */
    public function it_creates_options_immutably(): void
    {
        $original = IncludeDefinition::relationship('posts');
        $withOptions = $original->withOptions(['key' => 'value']);

        $this->assertEquals([], $original->getOptions());
        $this->assertEquals(['key' => 'value'], $withOptions->getOptions());
        $this->assertNotSame($original, $withOptions);
    }

    /** @test */
    public function it_handles_empty_relation_name(): void
    {
        $include = IncludeDefinition::relationship('');

        $this->assertEquals('', $include->getRelation());
        $this->assertEquals('', $include->getName());
    }

    /** @test */
    public function it_handles_relation_with_special_characters(): void
    {
        $include = IncludeDefinition::relationship('user_posts');

        $this->assertEquals('user_posts', $include->getRelation());
    }

    /** @test */
    public function it_handles_camelCase_relation(): void
    {
        $include = IncludeDefinition::relationship('relatedModels');

        $this->assertEquals('relatedModels', $include->getRelation());
    }
}
