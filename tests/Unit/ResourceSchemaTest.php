<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use Jackardios\QueryWizard\Contracts\FilterInterface;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Contracts\QueryWizardInterface;
use Jackardios\QueryWizard\Contracts\SortInterface;
use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Eloquent\EloquentInclude;
use Jackardios\QueryWizard\Eloquent\EloquentSort;
use Jackardios\QueryWizard\Schema\ResourceSchema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ResourceSchemaTest extends TestCase
{
    /**
     * Create a mock wizard for testing.
     */
    protected function mockWizard(): QueryWizardInterface
    {
        return $this->createMock(QueryWizardInterface::class);
    }

    // ========== Abstract Method Tests ==========
    #[Test]
    public function it_requires_model_method(): void
    {
        $schema = new class extends ResourceSchema
        {
            public function model(): string
            {
                return 'App\\Models\\User';
            }
        };

        $this->assertEquals('App\\Models\\User', $schema->model());
    }

    // ========== Type Method Tests ==========
    #[Test]
    public function it_generates_type_from_model_name(): void
    {
        $schema = new class extends ResourceSchema
        {
            public function model(): string
            {
                return 'App\\Models\\UserProfile';
            }
        };

        $this->assertEquals('userProfile', $schema->type());
    }

    #[Test]
    public function it_handles_simple_model_name(): void
    {
        $schema = new class extends ResourceSchema
        {
            public function model(): string
            {
                return 'App\\Models\\User';
            }
        };

        $this->assertEquals('user', $schema->type());
    }

    #[Test]
    public function it_can_override_type(): void
    {
        $schema = new class extends ResourceSchema
        {
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

    // ========== Filters Method Tests ==========
    #[Test]
    public function it_returns_empty_filters_by_default(): void
    {
        $schema = new class extends ResourceSchema
        {
            public function model(): string
            {
                return 'App\\Models\\User';
            }
        };

        $this->assertEquals([], $schema->filters($this->mockWizard()));
    }

    #[Test]
    public function it_can_return_string_filters(): void
    {
        $schema = new class extends ResourceSchema
        {
            public function model(): string
            {
                return 'App\\Models\\User';
            }

            public function filters(QueryWizardInterface $wizard): array
            {
                return ['name', 'email', 'status'];
            }
        };

        $this->assertEquals(['name', 'email', 'status'], $schema->filters($this->mockWizard()));
    }

    #[Test]
    public function it_can_return_filter_instances(): void
    {
        $schema = new class extends ResourceSchema
        {
            public function model(): string
            {
                return 'App\\Models\\User';
            }

            public function filters(QueryWizardInterface $wizard): array
            {
                return [
                    EloquentFilter::exact('name'),
                    EloquentFilter::partial('email'),
                ];
            }
        };

        $filters = $schema->filters($this->mockWizard());
        $this->assertCount(2, $filters);
        $this->assertInstanceOf(FilterInterface::class, $filters[0]);
    }

    #[Test]
    public function it_can_mix_strings_and_filter_instances(): void
    {
        $schema = new class extends ResourceSchema
        {
            public function model(): string
            {
                return 'App\\Models\\User';
            }

            public function filters(QueryWizardInterface $wizard): array
            {
                return [
                    'status',
                    EloquentFilter::partial('name'),
                ];
            }
        };

        $filters = $schema->filters($this->mockWizard());
        $this->assertEquals('status', $filters[0]);
        $this->assertInstanceOf(FilterInterface::class, $filters[1]);
    }

    // ========== Includes Method Tests ==========
    #[Test]
    public function it_returns_empty_includes_by_default(): void
    {
        $schema = new class extends ResourceSchema
        {
            public function model(): string
            {
                return 'App\\Models\\User';
            }
        };

        $this->assertEquals([], $schema->includes($this->mockWizard()));
    }

    #[Test]
    public function it_can_return_string_includes(): void
    {
        $schema = new class extends ResourceSchema
        {
            public function model(): string
            {
                return 'App\\Models\\User';
            }

            public function includes(QueryWizardInterface $wizard): array
            {
                return ['posts', 'comments', 'profile'];
            }
        };

        $this->assertEquals(['posts', 'comments', 'profile'], $schema->includes($this->mockWizard()));
    }

    #[Test]
    public function it_can_return_include_instances(): void
    {
        $schema = new class extends ResourceSchema
        {
            public function model(): string
            {
                return 'App\\Models\\User';
            }

            public function includes(QueryWizardInterface $wizard): array
            {
                return [
                    EloquentInclude::relationship('posts'),
                    EloquentInclude::count('comments'),
                ];
            }
        };

        $includes = $schema->includes($this->mockWizard());
        $this->assertCount(2, $includes);
        $this->assertInstanceOf(IncludeInterface::class, $includes[0]);
    }

    // ========== Sorts Method Tests ==========
    #[Test]
    public function it_returns_empty_sorts_by_default(): void
    {
        $schema = new class extends ResourceSchema
        {
            public function model(): string
            {
                return 'App\\Models\\User';
            }
        };

        $this->assertEquals([], $schema->sorts($this->mockWizard()));
    }

    #[Test]
    public function it_can_return_string_sorts(): void
    {
        $schema = new class extends ResourceSchema
        {
            public function model(): string
            {
                return 'App\\Models\\User';
            }

            public function sorts(QueryWizardInterface $wizard): array
            {
                return ['name', 'created_at'];
            }
        };

        $this->assertEquals(['name', 'created_at'], $schema->sorts($this->mockWizard()));
    }

    #[Test]
    public function it_can_return_sort_instances(): void
    {
        $schema = new class extends ResourceSchema
        {
            public function model(): string
            {
                return 'App\\Models\\User';
            }

            public function sorts(QueryWizardInterface $wizard): array
            {
                return [
                    EloquentSort::field('name'),
                    EloquentSort::callback('custom', fn ($q, $d) => $q),
                ];
            }
        };

        $sorts = $schema->sorts($this->mockWizard());
        $this->assertCount(2, $sorts);
        $this->assertInstanceOf(SortInterface::class, $sorts[0]);
    }

    // ========== Fields Method Tests ==========
    #[Test]
    public function it_returns_empty_fields_by_default(): void
    {
        // Default is empty = forbid all fields unless explicitly allowed
        $schema = new class extends ResourceSchema
        {
            public function model(): string
            {
                return 'App\\Models\\User';
            }
        };

        $this->assertEquals([], $schema->fields($this->mockWizard()));
    }

    #[Test]
    public function it_can_return_fields(): void
    {
        $schema = new class extends ResourceSchema
        {
            public function model(): string
            {
                return 'App\\Models\\User';
            }

            public function fields(QueryWizardInterface $wizard): array
            {
                return ['id', 'name', 'email', 'created_at'];
            }
        };

        $this->assertEquals(['id', 'name', 'email', 'created_at'], $schema->fields($this->mockWizard()));
    }

    // ========== Appends Method Tests ==========
    #[Test]
    public function it_returns_empty_appends_by_default(): void
    {
        $schema = new class extends ResourceSchema
        {
            public function model(): string
            {
                return 'App\\Models\\User';
            }
        };

        $this->assertEquals([], $schema->appends($this->mockWizard()));
    }

    #[Test]
    public function it_can_return_appends(): void
    {
        $schema = new class extends ResourceSchema
        {
            public function model(): string
            {
                return 'App\\Models\\User';
            }

            public function appends(QueryWizardInterface $wizard): array
            {
                return ['fullName', 'avatarUrl'];
            }
        };

        $this->assertEquals(['fullName', 'avatarUrl'], $schema->appends($this->mockWizard()));
    }

    // ========== Default Includes Tests ==========
    #[Test]
    public function it_returns_empty_default_includes(): void
    {
        $schema = new class extends ResourceSchema
        {
            public function model(): string
            {
                return 'App\\Models\\User';
            }
        };

        $this->assertEquals([], $schema->defaultIncludes($this->mockWizard()));
    }

    #[Test]
    public function it_can_set_default_includes(): void
    {
        $schema = new class extends ResourceSchema
        {
            public function model(): string
            {
                return 'App\\Models\\User';
            }

            public function defaultIncludes(QueryWizardInterface $wizard): array
            {
                return ['profile'];
            }
        };

        $this->assertEquals(['profile'], $schema->defaultIncludes($this->mockWizard()));
    }

    // ========== Default Sorts Tests ==========
    #[Test]
    public function it_returns_empty_default_sorts(): void
    {
        $schema = new class extends ResourceSchema
        {
            public function model(): string
            {
                return 'App\\Models\\User';
            }
        };

        $this->assertEquals([], $schema->defaultSorts($this->mockWizard()));
    }

    #[Test]
    public function it_can_set_default_sorts(): void
    {
        $schema = new class extends ResourceSchema
        {
            public function model(): string
            {
                return 'App\\Models\\User';
            }

            public function defaultSorts(QueryWizardInterface $wizard): array
            {
                return ['-created_at'];
            }
        };

        $this->assertEquals(['-created_at'], $schema->defaultSorts($this->mockWizard()));
    }

    // ========== Default Appends Tests ==========
    #[Test]
    public function it_returns_empty_default_appends(): void
    {
        $schema = new class extends ResourceSchema
        {
            public function model(): string
            {
                return 'App\\Models\\User';
            }
        };

        $this->assertEquals([], $schema->defaultAppends($this->mockWizard()));
    }

    #[Test]
    public function it_can_set_default_appends(): void
    {
        $schema = new class extends ResourceSchema
        {
            public function model(): string
            {
                return 'App\\Models\\User';
            }

            public function defaultAppends(QueryWizardInterface $wizard): array
            {
                return ['fullName'];
            }
        };

        $this->assertEquals(['fullName'], $schema->defaultAppends($this->mockWizard()));
    }

    // ========== Complex Schema Tests ==========
    #[Test]
    public function it_can_create_complex_schema(): void
    {
        $schema = new class extends ResourceSchema
        {
            public function model(): string
            {
                return 'App\\Models\\User';
            }

            public function type(): string
            {
                return 'users';
            }

            public function filters(QueryWizardInterface $wizard): array
            {
                return [
                    'status',
                    EloquentFilter::partial('name'),
                    EloquentFilter::exact('email'),
                    EloquentFilter::scope('active'),
                ];
            }

            public function includes(QueryWizardInterface $wizard): array
            {
                return [
                    'profile',
                    EloquentInclude::relationship('posts'),
                    EloquentInclude::count('comments'),
                ];
            }

            public function sorts(QueryWizardInterface $wizard): array
            {
                return [
                    'name',
                    '-created_at',
                    EloquentSort::callback('popularity', fn ($q, $d) => $q),
                ];
            }

            public function fields(QueryWizardInterface $wizard): array
            {
                return ['id', 'name', 'email', 'status', 'created_at'];
            }

            public function appends(QueryWizardInterface $wizard): array
            {
                return ['fullName', 'avatarUrl'];
            }

            public function defaultIncludes(QueryWizardInterface $wizard): array
            {
                return ['profile'];
            }

            public function defaultSorts(QueryWizardInterface $wizard): array
            {
                return ['-created_at'];
            }

            public function defaultAppends(QueryWizardInterface $wizard): array
            {
                return ['fullName'];
            }
        };

        $wizard = $this->mockWizard();

        // Verify all methods work
        $this->assertEquals('App\\Models\\User', $schema->model());
        $this->assertEquals('users', $schema->type());
        $this->assertCount(4, $schema->filters($wizard));
        $this->assertCount(3, $schema->includes($wizard));
        $this->assertCount(3, $schema->sorts($wizard));
        $this->assertCount(5, $schema->fields($wizard));
        $this->assertCount(2, $schema->appends($wizard));
        $this->assertEquals(['profile'], $schema->defaultIncludes($wizard));
        $this->assertEquals(['-created_at'], $schema->defaultSorts($wizard));
        $this->assertEquals(['fullName'], $schema->defaultAppends($wizard));
    }
}
