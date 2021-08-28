<?php

use Faker\Generator as Faker;
use Jackardios\QueryWizard\Tests\TestClasses\Models\SoftDeleteModel;

$factory->define(SoftDeleteModel::class, function (Faker $faker) {
    return [
        'name' => $faker->name,
    ];
});
