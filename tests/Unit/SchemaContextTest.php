<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use Jackardios\QueryWizard\Contracts\SchemaContextInterface;
use Jackardios\QueryWizard\Schema\SchemaContext;
use PHPUnit\Framework\TestCase;

class SchemaContextTest extends TestCase
{
    /** @test */
    public function it_creates_empty_context(): void
    {
        $context = SchemaContext::make();

        $this->assertInstanceOf(SchemaContextInterface::class, $context);
        $this->assertNull($context->getAllowedFilters());
        $this->assertNull($context->getAllowedSorts());
        $this->assertNull($context->getAllowedIncludes());
        $this->assertNull($context->getAllowedFields());
        $this->assertNull($context->getAllowedAppends());
        $this->assertEquals([], $context->getDisallowedFilters());
        $this->assertEquals([], $context->getDisallowedSorts());
        $this->assertEquals([], $context->getDisallowedIncludes());
        $this->assertEquals([], $context->getDisallowedFields());
        $this->assertEquals([], $context->getDisallowedAppends());
        $this->assertNull($context->getDefaultFields());
        $this->assertNull($context->getDefaultIncludes());
        $this->assertNull($context->getDefaultSorts());
        $this->assertNull($context->getDefaultAppends());
    }

    // ========== Allowed setters tests ==========

    /** @test */
    public function it_sets_allowed_filters(): void
    {
        $context = SchemaContext::make()->allowFilters(['name', 'status']);

        $this->assertEquals(['name', 'status'], $context->getAllowedFilters());
    }

    /** @test */
    public function it_sets_empty_allowed_filters(): void
    {
        $context = SchemaContext::make()->allowFilters([]);

        $this->assertEquals([], $context->getAllowedFilters());
        $this->assertNotNull($context->getAllowedFilters());
    }

    /** @test */
    public function it_sets_allowed_sorts(): void
    {
        $context = SchemaContext::make()->allowSorts(['name', '-created_at']);

        $this->assertEquals(['name', '-created_at'], $context->getAllowedSorts());
    }

    /** @test */
    public function it_sets_allowed_includes(): void
    {
        $context = SchemaContext::make()->allowIncludes(['posts', 'comments']);

        $this->assertEquals(['posts', 'comments'], $context->getAllowedIncludes());
    }

    /** @test */
    public function it_sets_allowed_fields(): void
    {
        $context = SchemaContext::make()->allowFields(['id', 'name', 'email']);

        $this->assertEquals(['id', 'name', 'email'], $context->getAllowedFields());
    }

    /** @test */
    public function it_sets_allowed_appends(): void
    {
        $context = SchemaContext::make()->allowAppends(['fullName', 'avatarUrl']);

        $this->assertEquals(['fullName', 'avatarUrl'], $context->getAllowedAppends());
    }

    // ========== Disallowed setters tests ==========

    /** @test */
    public function it_sets_disallowed_filters(): void
    {
        $context = SchemaContext::make()->disallowFilters(['secret', 'password']);

        $this->assertEquals(['secret', 'password'], $context->getDisallowedFilters());
    }

    /** @test */
    public function it_sets_disallowed_sorts(): void
    {
        $context = SchemaContext::make()->disallowSorts(['secret_score']);

        $this->assertEquals(['secret_score'], $context->getDisallowedSorts());
    }

    /** @test */
    public function it_sets_disallowed_includes(): void
    {
        $context = SchemaContext::make()->disallowIncludes(['secrets', 'internalData']);

        $this->assertEquals(['secrets', 'internalData'], $context->getDisallowedIncludes());
    }

    /** @test */
    public function it_sets_disallowed_fields(): void
    {
        $context = SchemaContext::make()->disallowFields(['password', 'remember_token']);

        $this->assertEquals(['password', 'remember_token'], $context->getDisallowedFields());
    }

    /** @test */
    public function it_sets_disallowed_appends(): void
    {
        $context = SchemaContext::make()->disallowAppends(['secretData']);

        $this->assertEquals(['secretData'], $context->getDisallowedAppends());
    }

    // ========== Default setters tests ==========

    /** @test */
    public function it_sets_default_fields(): void
    {
        $context = SchemaContext::make()->defaultFields(['id', 'name']);

        $this->assertEquals(['id', 'name'], $context->getDefaultFields());
    }

    /** @test */
    public function it_sets_default_fields_with_wildcard(): void
    {
        $context = SchemaContext::make()->defaultFields(['*']);

        $this->assertEquals(['*'], $context->getDefaultFields());
    }

    /** @test */
    public function it_sets_default_includes(): void
    {
        $context = SchemaContext::make()->defaultIncludes(['profile']);

        $this->assertEquals(['profile'], $context->getDefaultIncludes());
    }

    /** @test */
    public function it_sets_default_sorts(): void
    {
        $context = SchemaContext::make()->defaultSorts(['-created_at']);

        $this->assertEquals(['-created_at'], $context->getDefaultSorts());
    }

    /** @test */
    public function it_sets_default_appends(): void
    {
        $context = SchemaContext::make()->defaultAppends(['fullName']);

        $this->assertEquals(['fullName'], $context->getDefaultAppends());
    }

    // ========== Fluent interface tests ==========

    /** @test */
    public function it_supports_fluent_interface(): void
    {
        $context = SchemaContext::make()
            ->allowFilters(['name', 'status'])
            ->allowSorts(['name'])
            ->allowIncludes(['posts'])
            ->allowFields(['id', 'name'])
            ->allowAppends(['fullName'])
            ->disallowFilters(['secret'])
            ->disallowSorts(['secret_score'])
            ->disallowIncludes(['secrets'])
            ->disallowFields(['password'])
            ->disallowAppends(['secretData'])
            ->defaultFields(['id', 'name'])
            ->defaultIncludes(['profile'])
            ->defaultSorts(['-created_at'])
            ->defaultAppends(['avatarUrl']);

        $this->assertEquals(['name', 'status'], $context->getAllowedFilters());
        $this->assertEquals(['name'], $context->getAllowedSorts());
        $this->assertEquals(['posts'], $context->getAllowedIncludes());
        $this->assertEquals(['id', 'name'], $context->getAllowedFields());
        $this->assertEquals(['fullName'], $context->getAllowedAppends());
        $this->assertEquals(['secret'], $context->getDisallowedFilters());
        $this->assertEquals(['secret_score'], $context->getDisallowedSorts());
        $this->assertEquals(['secrets'], $context->getDisallowedIncludes());
        $this->assertEquals(['password'], $context->getDisallowedFields());
        $this->assertEquals(['secretData'], $context->getDisallowedAppends());
        $this->assertEquals(['id', 'name'], $context->getDefaultFields());
        $this->assertEquals(['profile'], $context->getDefaultIncludes());
        $this->assertEquals(['-created_at'], $context->getDefaultSorts());
        $this->assertEquals(['avatarUrl'], $context->getDefaultAppends());
    }

    /** @test */
    public function it_returns_same_instance_for_fluent_calls(): void
    {
        $context = SchemaContext::make();
        $returned = $context->allowFilters(['name']);

        $this->assertSame($context, $returned);
    }

    // ========== Edge cases ==========

    /** @test */
    public function it_handles_mixed_types_in_allowed_filters(): void
    {
        // When used with definitions, arrays can contain definition objects
        $context = SchemaContext::make()->allowFilters([
            'name',
            'status',
            // Could be FilterDefinition objects in real usage
        ]);

        $this->assertCount(2, $context->getAllowedFilters());
    }

    /** @test */
    public function it_overwrites_previous_allowed_value(): void
    {
        $context = SchemaContext::make()
            ->allowFilters(['name'])
            ->allowFilters(['status', 'type']);

        $this->assertEquals(['status', 'type'], $context->getAllowedFilters());
    }

    /** @test */
    public function it_overwrites_previous_disallowed_value(): void
    {
        $context = SchemaContext::make()
            ->disallowIncludes(['secrets'])
            ->disallowIncludes(['other']);

        $this->assertEquals(['other'], $context->getDisallowedIncludes());
    }

    /** @test */
    public function it_overwrites_previous_default_value(): void
    {
        $context = SchemaContext::make()
            ->defaultSorts(['name'])
            ->defaultSorts(['-created_at']);

        $this->assertEquals(['-created_at'], $context->getDefaultSorts());
    }

    /** @test */
    public function null_and_empty_array_are_different(): void
    {
        $emptyContext = SchemaContext::make()->allowFilters([]);
        $nullContext = SchemaContext::make();

        // Empty array = explicitly set to allow nothing
        $this->assertEquals([], $emptyContext->getAllowedFilters());
        $this->assertNotNull($emptyContext->getAllowedFilters());

        // Null = use schema defaults
        $this->assertNull($nullContext->getAllowedFilters());
    }

    /** @test */
    public function it_handles_nested_include_names(): void
    {
        $context = SchemaContext::make()->disallowIncludes(['posts', 'posts.comments']);

        $this->assertEquals(['posts', 'posts.comments'], $context->getDisallowedIncludes());
    }
}
