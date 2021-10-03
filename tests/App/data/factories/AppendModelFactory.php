<?php

use Faker\Generator as Faker;
use Jackardios\QueryWizard\Tests\App\Models\AppendModel;
use Illuminate\Database\Eloquent\Factory;

/** @var Factory $factory */
$factory->define(AppendModel::class, function (Faker $faker) {
    return [
        'firstname' => $faker->firstName,
        'lastname' => $faker->lastName,
    ];
});
