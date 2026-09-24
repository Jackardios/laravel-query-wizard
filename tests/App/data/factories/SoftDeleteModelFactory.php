<?php

namespace Jackardios\QueryWizard\Tests\App\data\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Jackardios\QueryWizard\Tests\App\Models\SoftDeleteModel;

class SoftDeleteModelFactory extends Factory
{
    protected $model = SoftDeleteModel::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
        ];
    }
}
