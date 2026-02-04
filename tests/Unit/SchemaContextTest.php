<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Jackardios\QueryWizard\Contracts\SchemaContextInterface;
use Jackardios\QueryWizard\Schema\SchemaContext;
use PHPUnit\Framework\TestCase;

class SchemaContextTest extends TestCase
{
    #[Test]
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
    #[Test]
    public function it_sets_allowed_filters(): void
    {
        $context = SchemaContext::make()->setAllowedFilters(['name', 'status']);

        $this->assertEquals(['name', 'status'], $context->getAllowedFilters());
    }
    #[Test]
    public function it_sets_empty_allowed_filters(): void
    {
        $context = SchemaContext::make()->setAllowedFilters([]);

        $this->assertEquals([], $context->getAllowedFilters());
        $this->assertNotNull($context->getAllowedFilters());
    }
    #[Test]
    public function it_sets_allowed_sorts(): void
    {
        $context = SchemaContext::make()->setAllowedSorts(['name', '-created_at']);

        $this->assertEquals(['name', '-created_at'], $context->getAllowedSorts());
    }
    #[Test]
    public function it_sets_allowed_includes(): void
    {
        $context = SchemaContext::make()->setAllowedIncludes(['posts', 'comments']);

        $this->assertEquals(['posts', 'comments'], $context->getAllowedIncludes());
    }
    #[Test]
    public function it_sets_allowed_fields(): void
    {
        $context = SchemaContext::make()->setAllowedFields(['id', 'name', 'email']);

        $this->assertEquals(['id', 'name', 'email'], $context->getAllowedFields());
    }
    #[Test]
    public function it_sets_allowed_appends(): void
    {
        $context = SchemaContext::make()->setAllowedAppends(['fullName', 'avatarUrl']);

        $this->assertEquals(['fullName', 'avatarUrl'], $context->getAllowedAppends());
    }

    // ========== Disallowed setters tests ==========
    #[Test]
    public function it_sets_disallowed_filters(): void
    {
        $context = SchemaContext::make()->setDisallowedFilters(['secret', 'password']);

        $this->assertEquals(['secret', 'password'], $context->getDisallowedFilters());
    }
    #[Test]
    public function it_sets_disallowed_sorts(): void
    {
        $context = SchemaContext::make()->setDisallowedSorts(['secret_score']);

        $this->assertEquals(['secret_score'], $context->getDisallowedSorts());
    }
    #[Test]
    public function it_sets_disallowed_includes(): void
    {
        $context = SchemaContext::make()->setDisallowedIncludes(['secrets', 'internalData']);

        $this->assertEquals(['secrets', 'internalData'], $context->getDisallowedIncludes());
    }
    #[Test]
    public function it_sets_disallowed_fields(): void
    {
        $context = SchemaContext::make()->setDisallowedFields(['password', 'remember_token']);

        $this->assertEquals(['password', 'remember_token'], $context->getDisallowedFields());
    }
    #[Test]
    public function it_sets_disallowed_appends(): void
    {
        $context = SchemaContext::make()->setDisallowedAppends(['secretData']);

        $this->assertEquals(['secretData'], $context->getDisallowedAppends());
    }

    // ========== Default setters tests ==========
    #[Test]
    public function it_sets_default_fields(): void
    {
        $context = SchemaContext::make()->setDefaultFields(['id', 'name']);

        $this->assertEquals(['id', 'name'], $context->getDefaultFields());
    }
    #[Test]
    public function it_sets_default_fields_with_wildcard(): void
    {
        $context = SchemaContext::make()->setDefaultFields(['*']);

        $this->assertEquals(['*'], $context->getDefaultFields());
    }
    #[Test]
    public function it_sets_default_includes(): void
    {
        $context = SchemaContext::make()->setDefaultIncludes(['profile']);

        $this->assertEquals(['profile'], $context->getDefaultIncludes());
    }
    #[Test]
    public function it_sets_default_sorts(): void
    {
        $context = SchemaContext::make()->setDefaultSorts(['-created_at']);

        $this->assertEquals(['-created_at'], $context->getDefaultSorts());
    }
    #[Test]
    public function it_sets_default_appends(): void
    {
        $context = SchemaContext::make()->setDefaultAppends(['fullName']);

        $this->assertEquals(['fullName'], $context->getDefaultAppends());
    }

    // ========== Fluent interface tests ==========
    #[Test]
    public function it_supports_fluent_interface(): void
    {
        $context = SchemaContext::make()
            ->setAllowedFilters(['name', 'status'])
            ->setAllowedSorts(['name'])
            ->setAllowedIncludes(['posts'])
            ->setAllowedFields(['id', 'name'])
            ->setAllowedAppends(['fullName'])
            ->setDisallowedFilters(['secret'])
            ->setDisallowedSorts(['secret_score'])
            ->setDisallowedIncludes(['secrets'])
            ->setDisallowedFields(['password'])
            ->setDisallowedAppends(['secretData'])
            ->setDefaultFields(['id', 'name'])
            ->setDefaultIncludes(['profile'])
            ->setDefaultSorts(['-created_at'])
            ->setDefaultAppends(['avatarUrl']);

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
    #[Test]
    public function it_returns_same_instance_for_fluent_calls(): void
    {
        $context = SchemaContext::make();
        $returned = $context->setAllowedFilters(['name']);

        $this->assertSame($context, $returned);
    }

    // ========== Edge cases ==========
    #[Test]
    public function it_handles_mixed_types_in_allowed_filters(): void
    {
        // When used with definitions, arrays can contain definition objects
        $context = SchemaContext::make()->setAllowedFilters([
            'name',
            'status',
            // Could be FilterDefinition objects in real usage
        ]);

        $this->assertCount(2, $context->getAllowedFilters());
    }
    #[Test]
    public function it_overwrites_previous_allowed_value(): void
    {
        $context = SchemaContext::make()
            ->setAllowedFilters(['name'])
            ->setAllowedFilters(['status', 'type']);

        $this->assertEquals(['status', 'type'], $context->getAllowedFilters());
    }
    #[Test]
    public function it_overwrites_previous_disallowed_value(): void
    {
        $context = SchemaContext::make()
            ->setDisallowedIncludes(['secrets'])
            ->setDisallowedIncludes(['other']);

        $this->assertEquals(['other'], $context->getDisallowedIncludes());
    }
    #[Test]
    public function it_overwrites_previous_default_value(): void
    {
        $context = SchemaContext::make()
            ->setDefaultSorts(['name'])
            ->setDefaultSorts(['-created_at']);

        $this->assertEquals(['-created_at'], $context->getDefaultSorts());
    }
    #[Test]
    public function null_and_empty_array_are_different(): void
    {
        $emptyContext = SchemaContext::make()->setAllowedFilters([]);
        $nullContext = SchemaContext::make();

        // Empty array = explicitly set to allow nothing
        $this->assertEquals([], $emptyContext->getAllowedFilters());
        $this->assertNotNull($emptyContext->getAllowedFilters());

        // Null = use schema defaults
        $this->assertNull($nullContext->getAllowedFilters());
    }
    #[Test]
    public function it_handles_nested_include_names(): void
    {
        $context = SchemaContext::make()->setDisallowedIncludes(['posts', 'posts.comments']);

        $this->assertEquals(['posts', 'posts.comments'], $context->getDisallowedIncludes());
    }
}
