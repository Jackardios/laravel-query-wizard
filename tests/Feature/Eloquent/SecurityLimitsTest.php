<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Support\Facades\Config;
use Jackardios\QueryWizard\Exceptions\MaxAppendDepthExceeded;
use Jackardios\QueryWizard\Exceptions\MaxAppendsCountExceeded;
use Jackardios\QueryWizard\Exceptions\MaxFiltersCountExceeded;
use Jackardios\QueryWizard\Exceptions\MaxIncludeDepthExceeded;
use Jackardios\QueryWizard\Exceptions\MaxIncludesCountExceeded;
use Jackardios\QueryWizard\Exceptions\MaxSortsCountExceeded;
use Jackardios\QueryWizard\Tests\App\Models\AppendModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('eloquent')]
#[Group('security')]
class SecurityLimitsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TestModel::factory()->count(3)->create();
        AppendModel::factory()->count(3)->create();
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
            ->allowedIncludes('relatedModels.nestedRelatedModels.deepNested')
            ->get();
    }

    #[Test]
    public function it_allows_includes_within_depth_limit(): void
    {
        Config::set('query-wizard.limits.max_include_depth', 3);

        $models = $this
            ->createEloquentWizardWithIncludes('relatedModels.nestedRelatedModels')
            ->allowedIncludes('relatedModels.nestedRelatedModels')
            ->get();

        $this->assertNotEmpty($models);
    }

    #[Test]
    public function it_allows_any_depth_when_limit_is_null(): void
    {
        Config::set('query-wizard.limits.max_include_depth', null);

        $models = $this
            ->createEloquentWizardWithIncludes('relatedModels.nestedRelatedModels')
            ->allowedIncludes('relatedModels.nestedRelatedModels')
            ->get();

        $this->assertNotEmpty($models);
    }

    #[Test]
    public function it_validates_depth_by_relation_not_alias(): void
    {
        Config::set('query-wizard.limits.max_include_depth', 2);

        // Using alias 'simpleAlias' for deeply nested relation should still throw
        // because depth is validated by relation 'relatedModels.nestedRelatedModels.deepNested' (depth 3)
        $this->expectException(MaxIncludeDepthExceeded::class);
        $this->expectExceptionMessage('has depth 3 which exceeds the maximum allowed depth of 2');

        $this
            ->createEloquentWizardWithIncludes('simpleAlias')
            ->allowedIncludes(
                \Jackardios\QueryWizard\Eloquent\EloquentInclude::relationship('relatedModels.nestedRelatedModels.deepNested', 'simpleAlias')
            )
            ->get();
    }

    #[Test]
    public function it_allows_aliased_include_when_relation_depth_within_limit(): void
    {
        Config::set('query-wizard.limits.max_include_depth', 3);

        // Alias 'simpleAlias' for 'relatedModels.nestedRelatedModels' (depth 2) should pass with limit 3
        $models = $this
            ->createEloquentWizardWithIncludes('simpleAlias')
            ->allowedIncludes(
                \Jackardios\QueryWizard\Eloquent\EloquentInclude::relationship('relatedModels.nestedRelatedModels', 'simpleAlias')
            )
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
            ->allowedIncludes('relatedModels', 'otherRelatedModels', 'morphModels')
            ->get();
    }

    #[Test]
    public function it_allows_includes_within_count_limit(): void
    {
        Config::set('query-wizard.limits.max_includes_count', 3);

        $models = $this
            ->createEloquentWizardWithIncludes('relatedModels,otherRelatedModels')
            ->allowedIncludes('relatedModels', 'otherRelatedModels')
            ->get();

        $this->assertNotEmpty($models);
    }

    #[Test]
    public function it_allows_any_count_when_limit_is_null(): void
    {
        Config::set('query-wizard.limits.max_includes_count', null);

        $models = $this
            ->createEloquentWizardWithIncludes('relatedModels,otherRelatedModels,morphModels')
            ->allowedIncludes('relatedModels', 'otherRelatedModels', 'morphModels')
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
            ->allowedFilters('name', 'id', 'created_at')
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
            ->allowedFilters('name', 'id')
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
            ->allowedFilters('name', 'id', 'created_at')
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
            ->allowedSorts('name', 'id', 'created_at')
            ->get();
    }

    #[Test]
    public function it_allows_sorts_within_count_limit(): void
    {
        Config::set('query-wizard.limits.max_sorts_count', 3);

        $models = $this
            ->createEloquentWizardWithSorts('name,-id')
            ->allowedSorts('name', 'id')
            ->get();

        $this->assertNotEmpty($models);
    }

    #[Test]
    public function it_allows_any_sort_count_when_limit_is_null(): void
    {
        Config::set('query-wizard.limits.max_sorts_count', null);

        $models = $this
            ->createEloquentWizardWithSorts('name,-id,created_at')
            ->allowedSorts('name', 'id', 'created_at')
            ->get();

        $this->assertNotEmpty($models);
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

    // ========== Append Count Limit Tests ==========

    #[Test]
    public function it_throws_exception_when_append_count_exceeds_limit(): void
    {
        Config::set('query-wizard.limits.max_appends_count', 1);

        $this->expectException(MaxAppendsCountExceeded::class);
        $this->expectExceptionMessage('The number of requested appends (2) exceeds the maximum allowed (1)');

        $this
            ->createEloquentWizardWithAppends('fullname,reversename', AppendModel::class)
            ->allowedAppends('fullname', 'reversename')
            ->get();
    }

    #[Test]
    public function it_allows_appends_within_count_limit(): void
    {
        Config::set('query-wizard.limits.max_appends_count', 5);

        $models = $this
            ->createEloquentWizardWithAppends('fullname', AppendModel::class)
            ->allowedAppends('fullname')
            ->get();

        $this->assertNotEmpty($models);
    }

    #[Test]
    public function it_allows_any_append_count_when_limit_is_null(): void
    {
        Config::set('query-wizard.limits.max_appends_count', null);

        $models = $this
            ->createEloquentWizardWithAppends('fullname,reversename', AppendModel::class)
            ->allowedAppends('fullname', 'reversename')
            ->get();

        $this->assertNotEmpty($models);
    }

    // ========== Append Depth Limit Tests ==========

    #[Test]
    public function it_throws_exception_when_append_depth_exceeds_limit(): void
    {
        Config::set('query-wizard.limits.max_append_depth', 1);

        $this->expectException(MaxAppendDepthExceeded::class);
        $this->expectExceptionMessage('has depth 2 which exceeds the maximum allowed depth of 1');

        $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'append' => 'relatedModels.formattedName',
            ], TestModel::class)
            ->allowedIncludes('relatedModels')
            ->allowedAppends('relatedModels.formattedName')
            ->get();
    }

    #[Test]
    public function it_allows_appends_within_depth_limit(): void
    {
        Config::set('query-wizard.limits.max_append_depth', 3);

        $models = $this
            ->createEloquentWizardWithAppends('fullname', AppendModel::class)
            ->allowedAppends('fullname')
            ->get();

        $this->assertNotEmpty($models);
    }

    #[Test]
    public function it_allows_any_append_depth_when_limit_is_null(): void
    {
        Config::set('query-wizard.limits.max_append_depth', null);

        $models = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
                'append' => 'relatedModels.formattedName',
            ], TestModel::class)
            ->allowedIncludes('relatedModels')
            ->allowedAppends('relatedModels.formattedName')
            ->get();

        $this->assertNotEmpty($models);
    }

    // ========== Append Depth Exception Properties Tests ==========

    #[Test]
    public function max_append_depth_exceeded_exception_has_correct_properties(): void
    {
        $exception = MaxAppendDepthExceeded::create('posts.comments.body', 3, 2);

        $this->assertEquals('posts.comments.body', $exception->append);
        $this->assertEquals(3, $exception->depth);
        $this->assertEquals(2, $exception->maxDepth);
    }

    // ========== Append Count Exception Properties Tests ==========

    #[Test]
    public function max_appends_count_exceeded_exception_has_correct_properties(): void
    {
        $exception = MaxAppendsCountExceeded::create(12, 10);

        $this->assertEquals(12, $exception->count);
        $this->assertEquals(10, $exception->maxCount);
    }

    // ========== Default Sorts Validation Tests ==========

    #[Test]
    public function it_silently_skips_default_sorts_not_in_allowed_list(): void
    {
        $models = TestModel::factory()->count(3)->create();

        $result = $this
            ->createEloquentWizardFromQuery([], TestModel::class)
            ->allowedSorts('name')
            ->defaultSorts('-id')
            ->get();

        $this->assertNotEmpty($result);
        // -id is not in allowed list, so it should be skipped — models NOT sorted by id desc
        $ids = $result->pluck('id')->toArray();
        // The result should be in default DB order, not sorted by -id
        $this->assertNotEquals(
            $models->sortByDesc('id')->pluck('id')->values()->toArray(),
            $ids,
            'Default sort -id should have been skipped since it is not in allowed list'
        );
    }

    #[Test]
    public function it_applies_default_sorts_that_are_in_allowed_list(): void
    {
        $result = $this
            ->createEloquentWizardFromQuery([], TestModel::class)
            ->allowedSorts('name', 'id')
            ->defaultSorts('-id')
            ->get();

        $this->assertNotEmpty($result);
        // -id IS in allowed list, so models should be sorted by id desc
        $ids = $result->pluck('id')->toArray();
        $sorted = $ids;
        rsort($sorted);
        $this->assertEquals($sorted, $ids);
    }

    // ========== Default Appends Validation Tests ==========

    #[Test]
    public function it_silently_skips_default_appends_not_in_allowed_list(): void
    {
        $result = $this
            ->createEloquentWizardFromQuery([], AppendModel::class)
            ->allowedAppends('fullname')
            ->defaultAppends('fullname', 'nonexistent')
            ->get();

        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('fullname', $result->first()->toArray());
    }

    #[Test]
    public function it_throws_when_default_appends_exceed_depth(): void
    {
        Config::set('query-wizard.limits.max_append_depth', 1);

        $this->expectException(MaxAppendDepthExceeded::class);

        // Relation must be included for its appends to be validated
        // Defaults only apply when request has no append param
        $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
            ], TestModel::class)
            ->allowedIncludes('relatedModels')
            ->allowedAppends('fullname', 'relatedModels.formattedName')
            ->defaultAppends('relatedModels.formattedName')
            ->get();
    }

    #[Test]
    public function it_validates_total_appends_count_when_using_defaults(): void
    {
        // New behavior: defaults only apply when request is empty
        // So this test validates count when defaults ARE applied (no append param in request)
        Config::set('query-wizard.limits.max_appends_count', 1);

        $this->expectException(MaxAppendsCountExceeded::class);

        $this
            ->createEloquentWizardFromQuery([], AppendModel::class) // No append param
            ->allowedAppends('fullname', 'reversename')
            ->defaultAppends('fullname', 'reversename') // 2 appends, exceeds limit of 1
            ->get();
    }

    // ========== Default Includes Validation Tests ==========

    #[Test]
    public function it_silently_skips_default_includes_not_in_allowed_list(): void
    {
        $result = $this
            ->createEloquentWizardFromQuery([], TestModel::class)
            ->allowedIncludes('relatedModels')
            ->defaultIncludes('relatedModels', 'nonExistent')
            ->get();

        $this->assertNotEmpty($result);
        $this->assertTrue($result->first()->relationLoaded('relatedModels'));
    }

    #[Test]
    public function it_silently_skips_default_includes_with_empty_allowed_list(): void
    {
        $result = $this
            ->createEloquentWizardFromQuery([], TestModel::class)
            ->allowedIncludes([])
            ->defaultIncludes('relatedModels')
            ->get();

        $this->assertNotEmpty($result);
        $this->assertFalse($result->first()->relationLoaded('relatedModels'));
    }

    // ========== ModelQueryWizard Includes Count Validation ==========

    #[Test]
    public function it_validates_model_wizard_includes_count_before_filtering(): void
    {
        Config::set('query-wizard.limits.max_includes_count', 2);

        $this->expectException(MaxIncludesCountExceeded::class);

        $this
            ->createEloquentWizardWithIncludes('relatedModels,otherRelatedModels,morphModels,invalid1,invalid2')
            ->allowedIncludes('relatedModels', 'otherRelatedModels', 'morphModels')
            ->get();
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
            'max_sorts_count' => 5,
        ]);

        // These should all pass with default limits
        $models = $this
            ->createEloquentWizardWithIncludes('relatedModels,otherRelatedModels')
            ->allowedIncludes('relatedModels', 'otherRelatedModels')
            ->allowedFilters('name', 'id')
            ->allowedSorts('name', 'id')
            ->get();

        $this->assertNotEmpty($models);
    }
}
