<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Http\Request;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Eloquent\EloquentInclude;
use Jackardios\QueryWizard\Exceptions\InvalidIncludeQuery;
use Jackardios\QueryWizard\ModelQueryWizard;
use Jackardios\QueryWizard\QueryParametersManager;
use Jackardios\QueryWizard\Tests\App\Models\NestedRelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * disallowedIncludes() blocks loading a relation under any alias, not only by its public name.
 */
#[Group('eloquent')]
#[Group('include')]
class DisallowedIncludePathTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $model = TestModel::factory()->create();
        $related = RelatedModel::factory()->create(['test_model_id' => $model->id]);
        NestedRelatedModel::factory()->create(['related_model_id' => $related->id]);
    }

    /**
     * @return array<string, array{IncludeInterface, string}>
     */
    public static function aliasedRelationships(): array
    {
        return [
            'alias of the relation' => [EloquentInclude::relationship('relatedModels')->alias('related'), 'relatedModels'],
            'alias of a nested relation under a denied parent' => [
                EloquentInclude::relationship('relatedModels.nestedRelatedModels')->alias('nested'),
                'relatedModels',
            ],
            'alias of a nested relation under denied children' => [
                EloquentInclude::relationship('relatedModels.nestedRelatedModels')->alias('nested'),
                'relatedModels.*',
            ],
            'alias of the denied nested relation' => [
                EloquentInclude::relationship('relatedModels.nestedRelatedModels')->alias('nested'),
                'relatedModels.nestedRelatedModels',
            ],
        ];
    }

    #[Test]
    #[DataProvider('aliasedRelationships')]
    public function aliases_do_not_bypass_disallowed_relations(IncludeInterface $include, string $disallowed): void
    {
        try {
            $this->createEloquentWizardFromQuery(['include' => $include->getName()])
                ->allowedIncludes($include)
                ->disallowedIncludes($disallowed)
                ->get();
            $this->fail('Expected InvalidIncludeQuery');
        } catch (InvalidIncludeQuery $exception) {
            $this->assertSame([$include->getName()], $exception->unknownIncludes->all());
        }
    }

    #[Test]
    public function aliased_default_includes_of_disallowed_relations_are_not_loaded(): void
    {
        $model = $this->createEloquentWizardFromQuery([])
            ->allowedIncludes(EloquentInclude::relationship('relatedModels.nestedRelatedModels')->alias('nested'))
            ->disallowedIncludes('relatedModels')
            ->defaultIncludes('nested')
            ->get()
            ->first();

        $this->assertFalse($model->relationLoaded('relatedModels'));
    }

    #[Test]
    public function count_and_exists_includes_are_not_blocked_by_the_relation(): void
    {
        $model = $this->createEloquentWizardFromQuery(['include' => 'relatedModelsCount,relatedModelsExists'])
            ->allowedIncludes(EloquentInclude::count('relatedModels'), EloquentInclude::exists('relatedModels'))
            ->disallowedIncludes('relatedModels')
            ->get()
            ->first();

        $this->assertSame(1, (int) $model->related_models_count);
        $this->assertTrue((bool) $model->related_models_exists);
    }

    #[Test]
    public function sibling_relations_stay_allowed(): void
    {
        $model = $this->createEloquentWizardFromQuery(['include' => 'other'])
            ->allowedIncludes(EloquentInclude::relationship('otherRelatedModels')->alias('other'))
            ->disallowedIncludes('relatedModels')
            ->get()
            ->first();

        $this->assertTrue($model->relationLoaded('otherRelatedModels'));
    }

    #[Test]
    public function model_wizard_does_not_load_disallowed_relations_through_an_alias(): void
    {
        $this->expectException(InvalidIncludeQuery::class);

        (new ModelQueryWizard(TestModel::query()->firstOrFail(), new QueryParametersManager(new Request(['include' => 'nested']))))
            ->allowedIncludes(EloquentInclude::relationship('relatedModels.nestedRelatedModels')->alias('nested'))
            ->disallowedIncludes('relatedModels')
            ->process();
    }
}
