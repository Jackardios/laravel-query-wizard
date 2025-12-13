<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use Jackardios\QueryWizard\Contracts\ResourceSchemaInterface;
use Jackardios\QueryWizard\Contracts\SchemaContextInterface;
use Jackardios\QueryWizard\Drivers\Eloquent\Definitions\FilterDefinition;
use Jackardios\QueryWizard\Drivers\Eloquent\Definitions\IncludeDefinition;
use Jackardios\QueryWizard\Drivers\Eloquent\Definitions\SortDefinition;
use Jackardios\QueryWizard\Schema\ResourceSchema;
use Jackardios\QueryWizard\Schema\SchemaContext;
use PHPUnit\Framework\TestCase;

class ResourceSchemaTest extends TestCase
{
    // ========== Abstract Method Tests ==========

    /** @test */
    public function it_requires_model_method(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return 'App\\Models\\User';
            }
        };

        $this->assertEquals('App\\Models\\User', $schema->model());
    }

    // ========== Type Method Tests ==========

    /** @test */
    public function it_generates_type_from_model_name(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return 'App\\Models\\UserProfile';
            }
        };

        $this->assertEquals('userProfile', $schema->type());
    }

    /** @test */
    public function it_handles_simple_model_name(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return 'App\\Models\\User';
            }
        };

        $this->assertEquals('user', $schema->type());
    }

    /** @test */
    public function it_can_override_type(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return 'App\\Models\\User';
            }

            public function type(): string
            {
                return 'users';
            }
        };

        $this->assertEquals('users', $schema->type());
    }

    // ========== Driver Method Tests ==========

    /** @test */
    public function it_defaults_to_eloquent_driver(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return 'App\\Models\\User';
            }
        };

        $this->assertEquals('eloquent', $schema->driver());
    }

    /** @test */
    public function it_can_override_driver(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return 'App\\Models\\User';
            }

            public function driver(): string
            {
                return 'custom';
            }
        };

        $this->assertEquals('custom', $schema->driver());
    }

    // ========== Filters Method Tests ==========

    /** @test */
    public function it_returns_empty_filters_by_default(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return 'App\\Models\\User';
            }
        };

        $this->assertEquals([], $schema->filters());
    }

    /** @test */
    public function it_can_return_string_filters(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return 'App\\Models\\User';
            }

            public function filters(): array
            {
                return ['name', 'email', 'status'];
            }
        };

        $this->assertEquals(['name', 'email', 'status'], $schema->filters());
    }

    /** @test */
    public function it_can_return_filter_definitions(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return 'App\\Models\\User';
            }

            public function filters(): array
            {
                return [
                    FilterDefinition::exact('name'),
                    FilterDefinition::partial('email'),
                ];
            }
        };

        $filters = $schema->filters();
        $this->assertCount(2, $filters);
        $this->assertInstanceOf(FilterDefinition::class, $filters[0]);
    }

    /** @test */
    public function it_can_mix_strings_and_filter_definitions(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return 'App\\Models\\User';
            }

            public function filters(): array
            {
                return [
                    'status',
                    FilterDefinition::partial('name'),
                ];
            }
        };

        $filters = $schema->filters();
        $this->assertEquals('status', $filters[0]);
        $this->assertInstanceOf(FilterDefinition::class, $filters[1]);
    }

    // ========== Includes Method Tests ==========

    /** @test */
    public function it_returns_empty_includes_by_default(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return 'App\\Models\\User';
            }
        };

        $this->assertEquals([], $schema->includes());
    }

    /** @test */
    public function it_can_return_string_includes(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return 'App\\Models\\User';
            }

            public function includes(): array
            {
                return ['posts', 'comments', 'profile'];
            }
        };

        $this->assertEquals(['posts', 'comments', 'profile'], $schema->includes());
    }

    /** @test */
    public function it_can_return_include_definitions(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return 'App\\Models\\User';
            }

            public function includes(): array
            {
                return [
                    IncludeDefinition::relationship('posts'),
                    IncludeDefinition::count('comments'),
                ];
            }
        };

        $includes = $schema->includes();
        $this->assertCount(2, $includes);
        $this->assertInstanceOf(IncludeDefinition::class, $includes[0]);
    }

    // ========== Sorts Method Tests ==========

    /** @test */
    public function it_returns_empty_sorts_by_default(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return 'App\\Models\\User';
            }
        };

        $this->assertEquals([], $schema->sorts());
    }

    /** @test */
    public function it_can_return_string_sorts(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return 'App\\Models\\User';
            }

            public function sorts(): array
            {
                return ['name', 'created_at'];
            }
        };

        $this->assertEquals(['name', 'created_at'], $schema->sorts());
    }

    /** @test */
    public function it_can_return_sort_definitions(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return 'App\\Models\\User';
            }

            public function sorts(): array
            {
                return [
                    SortDefinition::field('name'),
                    SortDefinition::callback('custom', fn($q, $d) => $q),
                ];
            }
        };

        $sorts = $schema->sorts();
        $this->assertCount(2, $sorts);
        $this->assertInstanceOf(SortDefinition::class, $sorts[0]);
    }

    // ========== Fields Method Tests ==========

    /** @test */
    public function it_returns_empty_fields_by_default(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return 'App\\Models\\User';
            }
        };

        $this->assertEquals([], $schema->fields());
    }

    /** @test */
    public function it_can_return_fields(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return 'App\\Models\\User';
            }

            public function fields(): array
            {
                return ['id', 'name', 'email', 'created_at'];
            }
        };

        $this->assertEquals(['id', 'name', 'email', 'created_at'], $schema->fields());
    }

    // ========== Appends Method Tests ==========

    /** @test */
    public function it_returns_empty_appends_by_default(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return 'App\\Models\\User';
            }
        };

        $this->assertEquals([], $schema->appends());
    }

    /** @test */
    public function it_can_return_appends(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return 'App\\Models\\User';
            }

            public function appends(): array
            {
                return ['fullName', 'avatarUrl'];
            }
        };

        $this->assertEquals(['fullName', 'avatarUrl'], $schema->appends());
    }

    // ========== Default Fields Tests ==========

    /** @test */
    public function it_returns_wildcard_as_default_fields(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return 'App\\Models\\User';
            }
        };

        $this->assertEquals(['*'], $schema->defaultFields());
    }

    /** @test */
    public function it_can_override_default_fields(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return 'App\\Models\\User';
            }

            public function defaultFields(): array
            {
                return ['id', 'name'];
            }
        };

        $this->assertEquals(['id', 'name'], $schema->defaultFields());
    }

    // ========== Default Includes Tests ==========

    /** @test */
    public function it_returns_empty_default_includes(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return 'App\\Models\\User';
            }
        };

        $this->assertEquals([], $schema->defaultIncludes());
    }

    /** @test */
    public function it_can_set_default_includes(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return 'App\\Models\\User';
            }

            public function defaultIncludes(): array
            {
                return ['profile'];
            }
        };

        $this->assertEquals(['profile'], $schema->defaultIncludes());
    }

    // ========== Default Sorts Tests ==========

    /** @test */
    public function it_returns_empty_default_sorts(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return 'App\\Models\\User';
            }
        };

        $this->assertEquals([], $schema->defaultSorts());
    }

    /** @test */
    public function it_can_set_default_sorts(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return 'App\\Models\\User';
            }

            public function defaultSorts(): array
            {
                return ['-created_at'];
            }
        };

        $this->assertEquals(['-created_at'], $schema->defaultSorts());
    }

    // ========== Default Appends Tests ==========

    /** @test */
    public function it_returns_empty_default_appends(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return 'App\\Models\\User';
            }
        };

        $this->assertEquals([], $schema->defaultAppends());
    }

    /** @test */
    public function it_can_set_default_appends(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return 'App\\Models\\User';
            }

            public function defaultAppends(): array
            {
                return ['fullName'];
            }
        };

        $this->assertEquals(['fullName'], $schema->defaultAppends());
    }

    // ========== Context Methods Tests ==========

    /** @test */
    public function it_returns_null_forList_by_default(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return 'App\\Models\\User';
            }
        };

        $this->assertNull($schema->forList());
    }

    /** @test */
    public function it_returns_null_forItem_by_default(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return 'App\\Models\\User';
            }
        };

        $this->assertNull($schema->forItem());
    }

    /** @test */
    public function it_can_return_forList_context(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return 'App\\Models\\User';
            }

            public function forList(): ?SchemaContextInterface
            {
                return SchemaContext::make()
                    ->disallowIncludes(['secrets']);
            }
        };

        $context = $schema->forList();
        $this->assertInstanceOf(SchemaContextInterface::class, $context);
        $this->assertEquals(['secrets'], $context->getDisallowedIncludes());
    }

    /** @test */
    public function it_can_return_forItem_context(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return 'App\\Models\\User';
            }

            public function forItem(): ?SchemaContextInterface
            {
                return SchemaContext::make()
                    ->defaultIncludes(['profile', 'posts']);
            }
        };

        $context = $schema->forItem();
        $this->assertInstanceOf(SchemaContextInterface::class, $context);
        $this->assertEquals(['profile', 'posts'], $context->getDefaultIncludes());
    }

    // ========== Interface Implementation Tests ==========

    /** @test */
    public function it_implements_resource_schema_interface(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return 'App\\Models\\User';
            }
        };

        $this->assertInstanceOf(ResourceSchemaInterface::class, $schema);
    }

    // ========== Complex Schema Tests ==========

    /** @test */
    public function it_can_create_complex_schema(): void
    {
        $schema = new class extends ResourceSchema {
            public function model(): string
            {
                return 'App\\Models\\User';
            }

            public function type(): string
            {
                return 'users';
            }

            public function filters(): array
            {
                return [
                    'status',
                    FilterDefinition::partial('name'),
                    FilterDefinition::exact('email'),
                    FilterDefinition::scope('active'),
                ];
            }

            public function includes(): array
            {
                return [
                    'profile',
                    IncludeDefinition::relationship('posts'),
                    IncludeDefinition::count('comments'),
                ];
            }

            public function sorts(): array
            {
                return [
                    'name',
                    '-created_at',
                    SortDefinition::callback('popularity', fn($q, $d) => $q),
                ];
            }

            public function fields(): array
            {
                return ['id', 'name', 'email', 'status', 'created_at'];
            }

            public function appends(): array
            {
                return ['fullName', 'avatarUrl'];
            }

            public function defaultFields(): array
            {
                return ['id', 'name', 'email'];
            }

            public function defaultIncludes(): array
            {
                return ['profile'];
            }

            public function defaultSorts(): array
            {
                return ['-created_at'];
            }

            public function forList(): ?SchemaContextInterface
            {
                return SchemaContext::make()
                    ->disallowIncludes(['posts.comments'])
                    ->defaultFields(['id', 'name']);
            }

            public function forItem(): ?SchemaContextInterface
            {
                return SchemaContext::make()
                    ->defaultIncludes(['profile', 'posts']);
            }
        };

        // Verify all methods work
        $this->assertEquals('App\\Models\\User', $schema->model());
        $this->assertEquals('users', $schema->type());
        $this->assertEquals('eloquent', $schema->driver());
        $this->assertCount(4, $schema->filters());
        $this->assertCount(3, $schema->includes());
        $this->assertCount(3, $schema->sorts());
        $this->assertCount(5, $schema->fields());
        $this->assertCount(2, $schema->appends());
        $this->assertEquals(['id', 'name', 'email'], $schema->defaultFields());
        $this->assertEquals(['profile'], $schema->defaultIncludes());
        $this->assertEquals(['-created_at'], $schema->defaultSorts());
        $this->assertNotNull($schema->forList());
        $this->assertNotNull($schema->forItem());
    }
}
