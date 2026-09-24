<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Support\EagerLoads;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class EagerLoadsTest extends TestCase
{
    #[Test]
    public function it_registers_a_path_like_with_does(): void
    {
        $merged = TestModel::query();
        EagerLoads::merge($merged, 'relatedModels.nestedRelatedModels');

        $this->assertSame(
            array_keys(TestModel::query()->with('relatedModels.nestedRelatedModels')->getEagerLoads()),
            array_keys($merged->getEagerLoads())
        );
    }

    #[Test]
    public function it_keeps_existing_constraints_of_intermediate_relations(): void
    {
        $builder = TestModel::query()->with(['relatedModels' => static function (): void {}]);
        $constraint = $builder->getEagerLoads()['relatedModels'];

        EagerLoads::merge($builder, 'relatedModels.nestedRelatedModels');

        $this->assertSame($constraint, $builder->getEagerLoads()['relatedModels']);
    }

    #[Test]
    public function a_path_without_constraint_keeps_the_existing_constraint(): void
    {
        $builder = TestModel::query()->with(['relatedModels' => static function (): void {}]);
        $constraint = $builder->getEagerLoads()['relatedModels'];

        EagerLoads::merge($builder, 'relatedModels');

        $this->assertSame($constraint, $builder->getEagerLoads()['relatedModels']);
    }

    #[Test]
    public function a_new_constraint_runs_after_the_existing_one_on_the_same_query(): void
    {
        $calls = [];
        $builder = TestModel::query()->with(['relatedModels' => function ($query) use (&$calls): void {
            $calls[] = ['existing', $query];
        }]);

        EagerLoads::merge($builder, 'relatedModels', function ($query) use (&$calls): void {
            $calls[] = ['new', $query];
        });

        $query = RelatedModel::query();
        $builder->getEagerLoads()['relatedModels']($query);

        $this->assertSame([['existing', $query], ['new', $query]], $calls);
    }

    #[Test]
    public function column_selection_syntax_is_added_to_the_existing_constraint(): void
    {
        $builder = TestModel::query()->with(['relatedModels' => fn ($query) => $query->where('name', 'x')]);

        EagerLoads::merge($builder, 'relatedModels:id,name');

        $query = RelatedModel::query();
        $builder->getEagerLoads()['relatedModels']($query);

        $this->assertSame(['id', 'name'], $query->getQuery()->columns);
        $this->assertCount(1, $query->getQuery()->wheres);
    }

    #[Test]
    public function new_paths_are_appended_after_existing_ones(): void
    {
        $builder = TestModel::query()->with('otherRelatedModels');

        EagerLoads::merge($builder, 'relatedModels');

        $this->assertSame(['otherRelatedModels', 'relatedModels'], array_keys($builder->getEagerLoads()));
    }

    #[Test]
    public function it_registers_on_the_query_of_a_relation(): void
    {
        $relation = TestModel::factory()->create()->relatedModels();

        EagerLoads::merge($relation, 'nestedRelatedModels');

        $this->assertInstanceOf(Builder::class, $relation->getQuery());
        $this->assertSame(['nestedRelatedModels'], array_keys($relation->getQuery()->getEagerLoads()));
    }

    #[Test]
    public function closures_generated_by_with_are_recognized_as_framework_generated(): void
    {
        $eagerLoads = TestModel::query()->with('relatedModels.nestedRelatedModels', 'otherRelatedModels:id')->getEagerLoads();

        foreach ($eagerLoads as $name => $closure) {
            $this->assertTrue(EagerLoads::isFrameworkGenerated($closure), $name);
        }
    }

    #[Test]
    public function closures_carrying_developer_code_are_not_framework_generated(): void
    {
        $invokable = new class
        {
            public function __invoke(): void {}
        };
        $subclassClosure = (new class(TestModel::query()->getQuery()) extends Builder
        {
            public function constraint(): Closure
            {
                return static function (): void {};
            }
        })->constraint();

        $this->assertFalse(EagerLoads::isFrameworkGenerated(static function (): void {}));
        $this->assertFalse(EagerLoads::isFrameworkGenerated($subclassClosure));
        $this->assertFalse(EagerLoads::isFrameworkGenerated(
            TestModel::query()->with(['relatedModels' => static function (): void {}])->getEagerLoads()['relatedModels']
        ));
        $this->assertFalse(EagerLoads::isFrameworkGenerated(
            TestModel::query()->with(['relatedModels' => $invokable])->getEagerLoads()['relatedModels']
        ));
    }

    #[Test]
    public function preserving_keeps_a_constraint_replaced_by_a_nested_with(): void
    {
        $builder = TestModel::query()->with(['relatedModels' => fn ($query) => $query->where('name', 'x')]);

        EagerLoads::preserving($builder, fn ($subject) => $subject->with('relatedModels.nestedRelatedModels:id'));

        $query = RelatedModel::query();
        $builder->getEagerLoads()['relatedModels']($query);

        $this->assertCount(1, $query->getQuery()->wheres);
        $this->assertSame(['relatedModels', 'relatedModels.nestedRelatedModels'], array_keys($builder->getEagerLoads()));
    }

    #[Test]
    public function preserving_lets_a_developer_constraint_replace_the_existing_one(): void
    {
        $builder = TestModel::query()->with(['relatedModels' => fn ($query) => $query->where('name', 'x')]);

        EagerLoads::preserving($builder, fn ($subject) => $subject->with(['relatedModels' => fn ($query) => $query->where('id', 1)]));

        $query = RelatedModel::query();
        $builder->getEagerLoads()['relatedModels']($query);

        $this->assertSame(['id'], array_column($query->getQuery()->wheres, 'column'));
    }

    #[Test]
    public function preserving_keeps_removed_relations_removed_and_passes_other_results_through(): void
    {
        $builder = TestModel::query()->with('relatedModels');

        EagerLoads::preserving($builder, fn ($subject) => $subject->without('relatedModels'));
        $result = EagerLoads::preserving($builder, fn () => 'not a builder');

        $this->assertSame([], $builder->getEagerLoads());
        $this->assertSame('not a builder', $result);
    }
}
