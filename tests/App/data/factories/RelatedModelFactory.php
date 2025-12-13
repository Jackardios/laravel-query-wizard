<?php

use Faker\Generator as Faker;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Illuminate\Database\Eloquent\Factory;

/** @var Factory $factory */
$factory->define(RelatedModel::class, function (Faker $faker) {
    return [
        'test_model_id' => factory(TestModel::class),
        'name' => $faker->name,
    ];
});
