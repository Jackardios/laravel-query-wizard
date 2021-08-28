<?php

use Faker\Generator as Faker;
use Jackardios\QueryWizard\Tests\TestClasses\Models\AppendModel;

$factory->define(AppendModel::class, function (Faker $faker) {
    return [
        'firstname' => $faker->firstName,
        'lastname' => $faker->lastName,
    ];
});
