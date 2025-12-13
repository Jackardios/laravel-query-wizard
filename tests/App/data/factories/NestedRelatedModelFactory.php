<?php

namespace Jackardios\QueryWizard\Tests\App\data\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Jackardios\QueryWizard\Tests\App\Models\NestedRelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;

class NestedRelatedModelFactory extends Factory
{
    protected $model = NestedRelatedModel::class;

    public function definition(): array
    {
        return [
            'related_model_id' => RelatedModel::factory(),
            'name' => $this->faker->name,
        ];
    }
}
