<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Jackardios\QueryWizard\Eloquent\Filters\Concerns\HandlesRelationFiltering;
use Jackardios\QueryWizard\Filters\AbstractFilter;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('eloquent')]
#[Group('filter')]
class CustomFilterTest extends EloquentFilterTestCase
{
    #[Test]
    public function a_filter_built_on_the_relation_filtering_trait_overrides_the_relation_constraint(): void
    {
        $first = $this->models[0];
        $second = $this->models[1];
        RelatedModel::query()->create(['test_model_id' => $first->id, 'name' => 'kept']);
        RelatedModel::query()->create(['test_model_id' => $second->id, 'name' => 'other']);

        $ids = $this
            ->createEloquentWizardWithFilters(['relatedModels.name' => 'kept'])
            ->allowedFilters(NullableExactFilter::make('relatedModels.name'))
            ->get()
            ->pluck('id')
            ->all();

        $this->assertSame([$first->id, ...$this->models->slice(2)->pluck('id')->all()], $ids);
    }

    #[Test]
    public function a_filter_built_on_the_relation_filtering_trait_filters_its_own_column(): void
    {
        $query = $this
            ->createEloquentWizardWithFilters(['name' => 'a,b'])
            ->allowedFilters(NullableExactFilter::make('name'))
            ->toQuery();

        $this->assertStringContainsString('"test_models"."name" is null or "test_models"."name" in (?, ?)', $query->toSql());
    }

    #[Test]
    public function the_readme_example_filter_works(): void
    {
        $name = $this->models[0]->name;

        $models = $this
            ->createEloquentWizardWithFilters(['name' => $name])
            ->allowedFilters(NullOrEqualFilter::make('name'))
            ->get();

        $this->assertSame([$this->models[0]->id], $models->pluck('id')->all());
    }
}

final class NullOrEqualFilter extends AbstractFilter
{
    /** @use HandlesRelationFiltering<mixed> */
    use HandlesRelationFiltering;

    public static function make(string $property, ?string $alias = null): static
    {
        return new self($property, $alias);
    }

    public function validateValueShape(mixed $value): ?string
    {
        return $this->validateScalarOrFlatListValueShape($value);
    }

    public function apply(mixed $subject, mixed $value): mixed
    {
        return $this->applyToSubject($subject, $value);
    }

    protected function applyOnQuery(Builder $builder, mixed $value, string $column): Builder
    {
        $column = $builder->qualifyColumn($column);

        return $builder->where(fn (Builder $query) => $query->whereNull($column)->orWhereIn($column, (array) $value));
    }
}

/**
 * Matches the value or null, and for a relation property also rows without related records.
 */
final class NullableExactFilter extends AbstractFilter
{
    /** @use HandlesRelationFiltering<mixed> */
    use HandlesRelationFiltering;

    public static function make(string $property, ?string $alias = null): static
    {
        return new self($property, $alias);
    }

    public function validateValueShape(mixed $value): ?string
    {
        return $this->validateScalarOrFlatListValueShape($value);
    }

    /**
     * @param  Builder<Model>|Relation<Model, Model, mixed>  $subject
     * @return Builder<Model>|Relation<Model, Model, mixed>
     */
    public function apply(mixed $subject, mixed $value): mixed
    {
        return $this->applyToSubject($subject, $value);
    }

    /**
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    protected function applyOnQuery(Builder $builder, mixed $value, string $column): Builder
    {
        $column = $builder->qualifyColumn($column);

        return $builder->where(fn (Builder $query) => $query
            ->whereNull($column)
            ->when(is_array($value), fn (Builder $query) => $query->orWhereIn($column, $value))
            ->when(! is_array($value), fn (Builder $query) => $query->orWhere($column, $value)));
    }

    /**
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    protected function applyRelationFilter(Builder $builder, string $property, mixed $value): Builder
    {
        $relation = Str::beforeLast($property, '.');
        $column = Str::afterLast($property, '.');

        return $builder->where(fn (Builder $query) => $query
            ->doesntHave($relation)
            ->orWhereHas($relation, fn (Builder $query) => $this->applyOnQuery($query, $value, $column)));
    }
}
