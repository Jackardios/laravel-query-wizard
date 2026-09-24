<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Closure;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Eloquent\EloquentQueryWizard;
use Jackardios\QueryWizard\Enums\FilterOperator;
use Jackardios\QueryWizard\QueryParametersManager;
use Jackardios\QueryWizard\Tests\App\Models\NestedRelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * A wizard built for a relation (e.g. `$user->posts()`) must apply every
 * filter within that relation.
 */
#[Group('eloquent')]
class RelationSubjectTest extends TestCase
{
    private TestModel $parent;

    private RelatedModel $alpha;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parent = TestModel::factory()->create(['name' => 'parent', 'tags' => json_encode(['php'])]);
        $other = TestModel::factory()->create(['name' => 'other']);

        $this->alpha = RelatedModel::factory()->create(['test_model_id' => $this->parent->id, 'name' => 'alpha']);
        RelatedModel::factory()->create(['test_model_id' => $this->parent->id, 'name' => 'beta']);
        RelatedModel::factory()->create(['test_model_id' => $other->id, 'name' => 'alpha']);

        NestedRelatedModel::factory()->create(['related_model_id' => $this->alpha->id, 'name' => 'deep']);
    }

    /**
     * @return array<string, array{array<string, mixed>, Closure(EloquentQueryWizard): EloquentQueryWizard}>
     */
    public static function filters(): array
    {
        return [
            'exact' => [['name' => 'alpha'], fn ($w) => $w->allowedFilters('name')],
            'partial' => [['name' => 'lph'], fn ($w) => $w->allowedFilters(EloquentFilter::partial('name'))],
            'operator' => [['name' => 'alpha'], fn ($w) => $w->allowedFilters(EloquentFilter::operator('name', FilterOperator::EQUAL))],
            'scope' => [['named' => 'alpha'], fn ($w) => $w->allowedFilters(EloquentFilter::scope('named'))],
            'relation property' => [['nestedRelatedModels.name' => 'deep'], fn ($w) => $w->allowedFilters('nestedRelatedModels.name')],
            'callback' => [['cb' => 'alpha'], fn ($w) => $w->allowedFilters(EloquentFilter::callback('cb', fn ($q, $v) => $q->where('name', $v)))],
        ];
    }

    /**
     * @param  array<string, mixed>  $filter
     * @param  Closure(EloquentQueryWizard): EloquentQueryWizard  $configure
     */
    #[Test]
    #[DataProvider('filters')]
    public function filters_apply_within_the_relation(array $filter, Closure $configure): void
    {
        $wizard = $configure($this->wizard($this->parent->relatedModels(), ['filter' => $filter]));

        $this->assertSame([$this->alpha->id], $wizard->get()->pluck('id')->all());
        $this->assertInstanceOf(Relation::class, $wizard->toQuery());
    }

    #[Test]
    public function range_filter_applies_within_the_relation(): void
    {
        $models = $this->wizard($this->parent->relatedModels(), ['filter' => ['id' => ['max' => $this->alpha->id]]])
            ->allowedFilters(EloquentFilter::range('id'))
            ->get();

        $this->assertSame([$this->alpha->id], $models->pluck('id')->all());
    }

    #[Test]
    public function null_filter_applies_within_the_relation(): void
    {
        $models = $this->wizard($this->parent->relatedModels(), ['filter' => ['name' => 'true'], 'sort' => 'name'])
            ->allowedFilters(EloquentFilter::null('name')->withInvertedLogic())
            ->allowedSorts('name')
            ->get();

        $this->assertSame(['alpha', 'beta'], $models->pluck('name')->all());
    }

    #[Test]
    public function date_range_and_json_filters_apply_within_the_relation(): void
    {
        $subject = fn () => $this->alpha->testModel();

        $byDate = $this->wizard($subject(), ['filter' => ['created_at' => ['from' => '2000-01-01']]])
            ->allowedFilters(EloquentFilter::dateRange('created_at'))
            ->get();
        $byTag = $this->wizard($subject(), ['filter' => ['tags' => 'php']])
            ->allowedFilters(EloquentFilter::jsonContains('tags'))
            ->get();

        $this->assertSame([$this->parent->id], $byDate->pluck('id')->all());
        $this->assertSame([$this->parent->id], $byTag->pluck('id')->all());
    }

    #[Test]
    public function sparse_fields_apply_within_the_relation(): void
    {
        $models = $this->wizard($this->parent->relatedModels(), ['fields' => ['relatedModel' => 'id,name'], 'sort' => 'name'])
            ->allowedFields('id', 'name')
            ->allowedSorts('name')
            ->get();

        $this->assertSame(['alpha', 'beta'], $models->pluck('name')->all());
        $this->assertSame(['id', 'name'], array_keys($models->first()->toArray()));
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function wizard(Relation $subject, array $query): EloquentQueryWizard
    {
        return new EloquentQueryWizard($subject, new QueryParametersManager(new Request($query)));
    }
}
