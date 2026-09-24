<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('eloquent')]
#[Group('filter')]
#[Group('partial-filter')]
class PartialFilterTest extends EloquentFilterTestCase
{
    #[Test]
    public function it_can_filter_by_partial_property(): void
    {
        $uniqueModel = TestModel::factory()->create(['name' => 'UniquePartialTestName']);

        $models = $this
            ->createEloquentWizardWithFilters(['name' => 'UniquePart'])
            ->allowedFilters(EloquentFilter::partial('name'))
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals($uniqueModel->id, $models->first()->id);
    }

    #[Test]
    public function partial_filter_is_case_insensitive(): void
    {
        $uniqueModel = TestModel::factory()->create(['name' => 'CaseInsensitiveTest']);

        $models = $this
            ->createEloquentWizardWithFilters(['name' => 'CASEINSENSITIVE'])
            ->allowedFilters(EloquentFilter::partial('name'))
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals($uniqueModel->id, $models->first()->id);
    }

    #[Test]
    public function partial_filter_works_with_array_of_values(): void
    {
        $model1 = TestModel::factory()->create(['name' => 'ArrayPartialAlpha']);
        $model2 = TestModel::factory()->create(['name' => 'ArrayPartialBeta']);

        $models = $this
            ->createEloquentWizardWithFilters(['name' => ['ArrayPartialAlpha', 'ArrayPartialBeta']])
            ->allowedFilters(EloquentFilter::partial('name'))
            ->get();

        $this->assertCount(2, $models);
        $this->assertTrue($models->contains('id', $model1->id));
        $this->assertTrue($models->contains('id', $model2->id));
    }

    #[Test]
    public function partial_filter_ignores_empty_values_in_array(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['name' => ['', null, '']])
            ->allowedFilters(EloquentFilter::partial('name'))
            ->get();

        $this->assertEquals(TestModel::count(), $models->count());
    }

    #[Test]
    public function partial_filter_ignores_whitespace_items_in_a_list(): void
    {
        TestModel::factory()->create(['name' => 'with space']);
        $target = TestModel::factory()->create(['name' => 'target']);

        $models = $this
            ->createEloquentWizardWithFilters(['name' => ['targ', ' ']])
            ->allowedFilters(EloquentFilter::partial('name'))
            ->get();

        $this->assertSame([$target->id], $models->modelKeys());
    }

    #[Test]
    public function default_filter_works_with_partial_filter(): void
    {
        TestModel::factory()->create(['name' => 'default_partial_test']);

        $models = $this
            ->createEloquentWizardFromQuery()
            ->allowedFilters(EloquentFilter::partial('name')->default('partial'))
            ->get();

        $this->assertTrue($models->contains('name', 'default_partial_test'));
    }

    #[Test]
    public function partial_filter_escapes_percent_metacharacter(): void
    {
        $target = TestModel::factory()->create(['name' => '100% complete']);
        TestModel::factory()->create(['name' => '100 units complete']);

        $models = $this
            ->createEloquentWizardWithFilters(['name' => '100%'])
            ->allowedFilters(EloquentFilter::partial('name'))
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals($target->id, $models->first()->id);
    }

    #[Test]
    public function partial_filter_escapes_underscore_metacharacter(): void
    {
        $target = TestModel::factory()->create(['name' => 'test_value']);
        TestModel::factory()->create(['name' => 'testXvalue']);

        $models = $this
            ->createEloquentWizardWithFilters(['name' => 'test_val'])
            ->allowedFilters(EloquentFilter::partial('name'))
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals($target->id, $models->first()->id);
    }

    #[Test]
    public function partial_filter_escapes_backslash_metacharacter(): void
    {
        $target = TestModel::factory()->create(['name' => 'back\\slash']);
        TestModel::factory()->create(['name' => 'backAslash']);

        $models = $this
            ->createEloquentWizardWithFilters(['name' => 'back\\'])
            ->allowedFilters(EloquentFilter::partial('name'))
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals($target->id, $models->first()->id);
    }

    #[Test]
    public function partial_filter_escapes_metacharacters_in_array_values(): void
    {
        $target1 = TestModel::factory()->create(['name' => '100% done']);
        $target2 = TestModel::factory()->create(['name' => 'test_item']);
        TestModel::factory()->create(['name' => '100 done']);

        $models = $this
            ->createEloquentWizardWithFilters(['name' => ['100%', 'test_item']])
            ->allowedFilters(EloquentFilter::partial('name'))
            ->get();

        $this->assertCount(2, $models);
        $this->assertTrue($models->contains('id', $target1->id));
        $this->assertTrue($models->contains('id', $target2->id));
    }

    #[Test]
    public function partial_filter_with_alias(): void
    {
        $uniqueModel = TestModel::factory()->create(['name' => 'AliasPartialTest']);

        $models = $this
            ->createEloquentWizardWithFilters(['search' => 'AliasPartial'])
            ->allowedFilters(EloquentFilter::partial('name')->alias('search'))
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals($uniqueModel->id, $models->first()->id);
    }

    #[Test]
    public function partial_filter_matches_phrase_containing_separator_as_a_whole(): void
    {
        // ASCII on purpose: SQLite's LOWER() folds only ASCII letters.
        $target = TestModel::factory()->create(['name' => 'Screens in Tower (West, Moscow)']);
        TestModel::factory()->create(['name' => 'Screens in Tower (West']);
        TestModel::factory()->create(['name' => 'Moscow) only']);

        $models = $this
            ->createEloquentWizardWithFilters(['name' => 'tower (west, moscow)'])
            ->allowedFilters(EloquentFilter::partial('name'))
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals($target->id, $models->first()->id);
    }

    #[Test]
    public function partial_filter_splits_by_separator_when_value_splitting_is_enabled(): void
    {
        $alpha = TestModel::factory()->create(['name' => 'SplitAlphaName']);
        $beta = TestModel::factory()->create(['name' => 'SplitBetaName']);

        $models = $this
            ->createEloquentWizardWithFilters(['name' => 'SplitAlpha,SplitBeta'])
            ->allowedFilters(EloquentFilter::partial('name')->withValueSplitting())
            ->get();

        $this->assertCount(2, $models);
        $this->assertTrue($models->contains('id', $alpha->id));
        $this->assertTrue($models->contains('id', $beta->id));
    }

    #[Test]
    public function partial_filter_escapes_escape_character(): void
    {
        $target = TestModel::factory()->create(['name' => 'wow!% sale']);
        TestModel::factory()->create(['name' => 'wow% sale']);
        TestModel::factory()->create(['name' => 'wow!x sale']);

        $models = $this
            ->createEloquentWizardWithFilters(['name' => 'wow!%'])
            ->allowedFilters(EloquentFilter::partial('name'))
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals($target->id, $models->first()->id);
    }

    #[Test]
    public function partial_filter_sql_keeps_every_placeholder_visible_to_pdo_parsers(): void
    {
        // pdo_pgsql rewrites `?` itself and reads `'\'` as an unterminated literal,
        // hiding the placeholders after it (SQLSTATE[HY093]). SQLite never rewrites
        // placeholders, so guard the generated SQL instead of relying on the driver.
        $query = $this
            ->createEloquentWizardWithFilters(['name' => ['first', 'second', 'third']])
            ->allowedFilters(EloquentFilter::partial('name'))
            ->toQuery();

        $sql = $query->toSql();

        $this->assertStringNotContainsString('\\', $sql);
        $this->assertSame(count($query->getBindings()), substr_count($sql, '?'));
    }

    #[Test]
    public function partial_filter_matches_non_text_columns(): void
    {
        $expected = $this->models->modelKeys();
        $expected = array_values(array_filter($expected, fn (int $id) => str_contains((string) $id, '1')));

        $models = $this
            ->createEloquentWizardWithFilters(['id' => '1'])
            ->allowedFilters(EloquentFilter::partial('id'))
            ->get();

        $this->assertNotEmpty($expected);
        $this->assertEqualsCanonicalizing($expected, $models->modelKeys());
    }

    #[Test]
    #[DataProvider('nonTextValues')]
    public function partial_filter_rejects_values_that_are_not_text(mixed $value): void
    {
        $this->expectException(InvalidFilterValue::class);
        $this->expectExceptionMessage('Expected text.');

        $this
            ->createEloquentWizardWithFilters([])
            ->allowedFilters(EloquentFilter::partial('name')->default($value))
            ->toQuery();
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function nonTextValues(): array
    {
        return [
            'true' => [true],
            'false' => [false],
            'boolean in a list' => [['a', true]],
        ];
    }

    #[Test]
    public function partial_filter_searches_numbers_as_text(): void
    {
        $query = $this
            ->createEloquentWizardWithFilters([])
            ->allowedFilters(EloquentFilter::partial('name')->default(12))
            ->toQuery();

        $this->assertSame(['%12%'], $query->getBindings());
    }
}
