<?php

namespace Jackardios\QueryWizard\Tests\App\data\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;

class TestModelFactory extends Factory
{
    protected $model = TestModel::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
        ];
    }
}
