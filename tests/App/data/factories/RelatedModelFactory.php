<?php

namespace Jackardios\QueryWizard\Tests\App\data\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;

class RelatedModelFactory extends Factory
{
    protected $model = RelatedModel::class;

    public function definition(): array
    {
        return [
            'test_model_id' => TestModel::factory(),
            'name' => $this->faker->name,
        ];
    }
}
