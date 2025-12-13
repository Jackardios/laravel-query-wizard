<?php

namespace Jackardios\QueryWizard\Tests\App\data\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Jackardios\QueryWizard\Tests\App\Models\AppendModel;

class AppendModelFactory extends Factory
{
    protected $model = AppendModel::class;

    public function definition(): array
    {
        return [
            'firstname' => $this->faker->firstName,
            'lastname' => $this->faker->lastName,
        ];
    }
}
