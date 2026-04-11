<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\App\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;

class FakeBelongsToThroughRelation extends Relation
{
    public const THROUGH_KEY = 'laravel_through_key';

    protected bool $joined = false;

    /**
     * @param  Builder<Model>  $query
     */
    public function __construct(Builder $query, Model $parent)
    {
        parent::__construct($query, $parent);
    }

    public function addConstraints(): void
    {
        $this->performJoin();

        if (! static::$constraints) {
            return;
        }

        $this->query->where(
            $this->getQualifiedFirstLocalKeyName(),
            '=',
            $this->parent->getAttribute($this->getFirstForeignKeyName())
        );
    }

    /**
     * @param  array<int, Model>  $models
     */
    public function addEagerConstraints(array $models): void
    {
        $this->performJoin();

        $this->query->whereIn(
            $this->getQualifiedFirstLocalKeyName(),
            $this->getKeys($models, $this->getFirstForeignKeyName())
        );
    }

    /**
     * @param  array<int, Model>  $models
     * @return array<int, Model>
     */
    public function initRelation(array $models, $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, null);
        }

        return $models;
    }

    /**
     * @param  array<int, Model>  $models
     * @param  Collection<int, Model>  $results
     * @return array<int, Model>
     */
    public function match(array $models, Collection $results, $relation): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            $dictionary[$result->getAttribute(self::THROUGH_KEY)] = $result;
            unset($result[self::THROUGH_KEY]);
        }

        foreach ($models as $model) {
            $key = $model->getAttribute($this->getFirstForeignKeyName());

            if ($key !== null && array_key_exists($key, $dictionary)) {
                $model->setRelation($relation, $dictionary[$key]);
            }
        }

        return $models;
    }

    public function getResults(): ?Model
    {
        return $this->first();
    }

    /**
     * @param  array<int, string>  $columns
     */
    public function first($columns = ['*']): ?Model
    {
        if ($columns === ['*']) {
            $columns = [$this->related->getTable().'.*'];
        }

        $columns[] = $this->getQualifiedFirstLocalKeyName().' as '.self::THROUGH_KEY;

        return $this->query->first($columns);
    }

    /**
     * @param  array<int, string>  $columns
     * @return Collection<int, Model>
     */
    public function get($columns = ['*']): Collection
    {
        $columns = $this->query->getQuery()->columns ? [] : $columns;

        if ($columns === ['*']) {
            $columns = [$this->related->getTable().'.*'];
        }

        $columns[] = $this->getQualifiedFirstLocalKeyName().' as '.self::THROUGH_KEY;

        $this->query->addSelect($columns);

        /** @var Collection<int, Model> $results */
        $results = $this->query->get();

        return $results;
    }

    public function getFirstForeignKeyName(): string
    {
        return 'related_model_id';
    }

    public function getLocalKeyName(Model $model): string
    {
        return $model->getKeyName();
    }

    public function getQualifiedFirstLocalKeyName(): string
    {
        return (new RelatedModel)->qualifyColumn('id');
    }

    protected function performJoin(): void
    {
        if ($this->joined) {
            return;
        }

        $this->query->join('related_models', 'related_models.test_model_id', '=', 'test_models.id');
        $this->joined = true;
    }
}
