<?php

use Faker\Generator as Faker;
use Jackardios\QueryWizard\Tests\App\Models\SoftDeleteModel;
use Illuminate\Database\Eloquent\Factory;

/** @var Factory $factory */
$factory->define(SoftDeleteModel::class, function (Faker $faker) {
    return [
        'name' => $faker->name,
    ];
});
