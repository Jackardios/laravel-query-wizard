<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Jackardios\QueryWizard\Support\RelationResolver;
use Jackardios\QueryWizard\Tests\App\Models\NestedRelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class RelationResolverTest extends TestCase
{
    #[Test]
    public function nested_paths_reuse_the_resolved_parent_relation(): void
    {
        $calls = 0;
        TestModel::resolveRelationUsing('countedRelated', function (TestModel $model) use (&$calls): HasMany {
            $calls++;

            return $model->hasMany(RelatedModel::class, 'test_model_id');
        });
        $resolver = new RelationResolver(new TestModel);

        $this->assertInstanceOf(HasMany::class, $resolver->resolve('countedRelated.nestedRelatedModels'));
        $this->assertInstanceOf(BelongsTo::class, $resolver->resolve('countedRelated.testModel'));
        $this->assertInstanceOf(HasMany::class, $resolver->resolve('countedRelated'));
        $this->assertSame(1, $calls);
    }

    #[Test]
    public function it_resolves_the_last_segment_on_the_related_model(): void
    {
        $resolver = new RelationResolver(new TestModel);

        $this->assertInstanceOf(NestedRelatedModel::class, $resolver->resolve('relatedModels.nestedRelatedModels')?->getRelated());
        $this->assertInstanceOf(RelatedModel::class, $resolver->resolve('relatedModels.nestedRelatedModels.relatedModel')?->getRelated());
    }

    #[Test]
    public function unknown_or_empty_segments_resolve_to_null(): void
    {
        $resolver = new RelationResolver(new TestModel);

        $this->assertNull($resolver->resolve('missing'));
        $this->assertNull($resolver->resolve('missing.nestedRelatedModels'));
        $this->assertNull($resolver->resolve('relatedModels.'));
        $this->assertNull($resolver->resolve('relatedModels..testModel'));
        $this->assertNull($resolver->resolve('relatedModels.scopeNamed'));
        $this->assertNull($resolver->resolve('relatedModels.getFormattedNameAttribute'));
    }
}
