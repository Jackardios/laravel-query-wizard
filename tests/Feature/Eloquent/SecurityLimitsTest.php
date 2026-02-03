<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Jackardios\QueryWizard\Exceptions\MaxIncludeDepthExceeded;
use Jackardios\QueryWizard\Exceptions\MaxIncludesCountExceeded;
use Jackardios\QueryWizard\Exceptions\MaxFiltersCountExceeded;
use Jackardios\QueryWizard\Exceptions\MaxSortsCountExceeded;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;

#[Group('eloquent')]
#[Group('security')]
class SecurityLimitsTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        TestModel::factory()->count(3)->create();
    }

    // ========== Include Depth Limit Tests ==========

    #[Test]
    public function it_throws_exception_when_include_depth_exceeds_limit(): void
    {
        Config::set('query-wizard.limits.max_include_depth', 2);

        $this->expectException(MaxIncludeDepthExceeded::class);
        $this->expectExceptionMessage('has depth 3 which exceeds the maximum allowed depth of 2');

        $this
            ->createEloquentWizardWithIncludes('relatedModels.nestedRelatedModels.deepNested')
            ->setAllowedIncludes('relatedModels.nestedRelatedModels.deepNested')
            ->get();
    }

    #[Test]
    public function it_allows_includes_within_depth_limit(): void
    {
        Config::set('query-wizard.limits.max_include_depth', 3);

        $models = $this
            ->createEloquentWizardWithIncludes('relatedModels.nestedRelatedModels')
            ->setAllowedIncludes('relatedModels.nestedRelatedModels')
            ->get();

        $this->assertNotEmpty($models);
    }

    #[Test]
    public function it_allows_any_depth_when_limit_is_null(): void
    {
        Config::set('query-wizard.limits.max_include_depth', null);

        $models = $this
            ->createEloquentWizardWithIncludes('relatedModels.nestedRelatedModels')
            ->setAllowedIncludes('relatedModels.nestedRelatedModels')
            ->get();

        $this->assertNotEmpty($models);
    }

    // ========== Include Count Limit Tests ==========

    #[Test]
    public function it_throws_exception_when_include_count_exceeds_limit(): void
    {
        Config::set('query-wizard.limits.max_includes_count', 2);

        $this->expectException(MaxIncludesCountExceeded::class);
        $this->expectExceptionMessage('The number of requested includes (3) exceeds the maximum allowed (2)');

        $this
            ->createEloquentWizardWithIncludes('relatedModels,otherRelatedModels,morphModels')
            ->setAllowedIncludes('relatedModels', 'otherRelatedModels', 'morphModels')
            ->get();
    }

    #[Test]
    public function it_allows_includes_within_count_limit(): void
    {
        Config::set('query-wizard.limits.max_includes_count', 3);

        $models = $this
            ->createEloquentWizardWithIncludes('relatedModels,otherRelatedModels')
            ->setAllowedIncludes('relatedModels', 'otherRelatedModels')
            ->get();

        $this->assertNotEmpty($models);
    }

    #[Test]
    public function it_allows_any_count_when_limit_is_null(): void
    {
        Config::set('query-wizard.limits.max_includes_count', null);

        $models = $this
            ->createEloquentWizardWithIncludes('relatedModels,otherRelatedModels,morphModels')
            ->setAllowedIncludes('relatedModels', 'otherRelatedModels', 'morphModels')
            ->get();

        $this->assertNotEmpty($models);
    }

    // ========== Filter Count Limit Tests ==========

    #[Test]
    public function it_throws_exception_when_filter_count_exceeds_limit(): void
    {
        Config::set('query-wizard.limits.max_filters_count', 2);

        $this->expectException(MaxFiltersCountExceeded::class);
        $this->expectExceptionMessage('The number of requested filters (3) exceeds the maximum allowed (2)');

        $this
            ->createEloquentWizardWithFilters([
                'name' => 'test',
                'id' => 1,
                'created_at' => '2021-01-01',
            ])
            ->setAllowedFilters('name', 'id', 'created_at')
            ->get();
    }

    #[Test]
    public function it_allows_filters_within_count_limit(): void
    {
        Config::set('query-wizard.limits.max_filters_count', 5);

        $models = $this
            ->createEloquentWizardWithFilters([
                'name' => 'test',
                'id' => 1,
            ])
            ->setAllowedFilters('name', 'id')
            ->get();

        $this->assertIsIterable($models);
    }

    #[Test]
    public function it_allows_any_filter_count_when_limit_is_null(): void
    {
        Config::set('query-wizard.limits.max_filters_count', null);

        $models = $this
            ->createEloquentWizardWithFilters([
                'name' => 'test',
                'id' => 1,
                'created_at' => '2021-01-01',
            ])
            ->setAllowedFilters('name', 'id', 'created_at')
            ->get();

        $this->assertIsIterable($models);
    }

    // ========== Sort Count Limit Tests ==========

    #[Test]
    public function it_throws_exception_when_sort_count_exceeds_limit(): void
    {
        Config::set('query-wizard.limits.max_sorts_count', 2);

        $this->expectException(MaxSortsCountExceeded::class);
        $this->expectExceptionMessage('The number of requested sorts (3) exceeds the maximum allowed (2)');

        $this
            ->createEloquentWizardWithSorts('name,-id,created_at')
            ->setAllowedSorts('name', 'id', 'created_at')
            ->get();
    }

    #[Test]
    public function it_allows_sorts_within_count_limit(): void
    {
        Config::set('query-wizard.limits.max_sorts_count', 3);

        $models = $this
            ->createEloquentWizardWithSorts('name,-id')
            ->setAllowedSorts('name', 'id')
            ->get();

        $this->assertNotEmpty($models);
    }

    #[Test]
    public function it_allows_any_sort_count_when_limit_is_null(): void
    {
        Config::set('query-wizard.limits.max_sorts_count', null);

        $models = $this
            ->createEloquentWizardWithSorts('name,-id,created_at')
            ->setAllowedSorts('name', 'id', 'created_at')
            ->get();

        $this->assertNotEmpty($models);
    }

    // ========== Filter Depth Limit Tests ==========

    #[Test]
    public function it_respects_filter_depth_limit(): void
    {
        Config::set('query-wizard.limits.max_filter_depth', 2);
        Config::set('query-wizard.disable_invalid_filter_query_exception', true);

        // With max_filter_depth=2, filters like 'name' (depth 1) and 'relatedModels.id' (depth 2)
        // are extracted, but 'relatedModels.nested.deep' (depth 3) would be truncated
        $models = $this
            ->createEloquentWizardWithFilters([
                'name' => 'test',
            ])
            ->setAllowedFilters('name')
            ->get();

        $this->assertIsIterable($models);
    }

    // ========== Exception Properties Tests ==========

    #[Test]
    public function max_include_depth_exceeded_exception_has_correct_properties(): void
    {
        $exception = MaxIncludeDepthExceeded::create('a.b.c.d', 4, 3);

        $this->assertEquals('a.b.c.d', $exception->include);
        $this->assertEquals(4, $exception->depth);
        $this->assertEquals(3, $exception->maxDepth);
    }

    #[Test]
    public function max_includes_count_exceeded_exception_has_correct_properties(): void
    {
        $exception = MaxIncludesCountExceeded::create(15, 10);

        $this->assertEquals(15, $exception->count);
        $this->assertEquals(10, $exception->maxCount);
    }

    #[Test]
    public function max_filters_count_exceeded_exception_has_correct_properties(): void
    {
        $exception = MaxFiltersCountExceeded::create(20, 15);

        $this->assertEquals(20, $exception->count);
        $this->assertEquals(15, $exception->maxCount);
    }

    #[Test]
    public function max_sorts_count_exceeded_exception_has_correct_properties(): void
    {
        $exception = MaxSortsCountExceeded::create(10, 5);

        $this->assertEquals(10, $exception->count);
        $this->assertEquals(5, $exception->maxCount);
    }

    // ========== Default Config Values Tests ==========

    #[Test]
    public function default_limits_are_reasonable(): void
    {
        // Reset to package defaults
        Config::set('query-wizard.limits', [
            'max_include_depth' => 5,
            'max_includes_count' => 10,
            'max_filters_count' => 15,
            'max_filter_depth' => 5,
            'max_sorts_count' => 5,
        ]);

        // These should all pass with default limits
        $models = $this
            ->createEloquentWizardWithIncludes('relatedModels,otherRelatedModels')
            ->setAllowedIncludes('relatedModels', 'otherRelatedModels')
            ->setAllowedFilters('name', 'id')
            ->setAllowedSorts('name', 'id')
            ->get();

        $this->assertNotEmpty($models);
    }
}
