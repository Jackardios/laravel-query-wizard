<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Support\Facades\DB;
use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('eloquent')]
#[Group('filter')]
class JsonContainsFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::enableQueryLog();

        TestModel::factory()->count(3)->create();
    }

    #[Test]
    public function single_value_generates_json_contains_sql(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['tags' => 'php'])
            ->allowedFilters(EloquentFilter::jsonContains('tags'))
            ->toQuery()
            ->toSql();

        $sqlLower = strtolower($sql);
        $this->assertTrue(
            str_contains($sqlLower, 'json_contains') || str_contains($sqlLower, 'json_each'),
            "Expected JSON filtering SQL, got: {$sql}"
        );
    }

    #[Test]
    public function array_with_match_all_generates_and_logic(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['tags' => ['php', 'laravel']])
            ->allowedFilters(EloquentFilter::jsonContains('tags')->matchAll())
            ->toQuery()
            ->toSql();

        $sqlLower = strtolower($sql);
        // matchAll = AND logic: two separate json_contains/json_each clauses
        $jsonCount = substr_count($sqlLower, 'json_contains') + substr_count($sqlLower, 'json_each');
        $this->assertGreaterThanOrEqual(2, $jsonCount, "Expected at least 2 JSON checks for AND logic, got: {$sql}");
    }

    #[Test]
    public function array_with_match_any_generates_or_logic(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['tags' => ['php', 'laravel']])
            ->allowedFilters(EloquentFilter::jsonContains('tags')->matchAny())
            ->toQuery()
            ->toSql();

        $sqlLower = strtolower($sql);
        $this->assertTrue(
            str_contains($sqlLower, ' or '),
            "Expected OR logic in SQL, got: {$sql}"
        );
    }

    #[Test]
    public function dot_notation_generates_json_path_syntax(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['meta.roles' => 'admin'])
            ->allowedFilters(EloquentFilter::jsonContains('meta.roles'))
            ->toQuery()
            ->toSql();

        // MySQL uses -> arrow syntax, SQLite uses json_each with $."path" syntax
        $this->assertTrue(
            str_contains($sql, '->') || str_contains($sql, '"roles"'),
            "Expected JSON path notation in SQL, got: {$sql}"
        );
    }

    #[Test]
    public function alias_uses_original_column(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['labels' => 'php'])
            ->allowedFilters(EloquentFilter::jsonContains('tags')->alias('labels'))
            ->toQuery()
            ->toSql();

        $sqlLower = strtolower($sql);
        $this->assertTrue(
            str_contains($sqlLower, 'json_contains') || str_contains($sqlLower, 'json_each'),
            "Expected JSON filtering SQL, got: {$sql}"
        );
        // The column should reference 'tags', not 'labels'
        $this->assertStringContainsString('tags', $sqlLower);
    }
}
