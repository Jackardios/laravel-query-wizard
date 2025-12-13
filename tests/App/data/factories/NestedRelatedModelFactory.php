<?php

use Faker\Generator as Faker;
use Jackardios\QueryWizard\Tests\App\Models\NestedRelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Illuminate\Database\Eloquent\Factory;

/** @var Factory $factory */
$factory->define(NestedRelatedModel::class, function (Faker $faker) {
    return [
        'related_model_id' => factory(RelatedModel::class),
        'name' => $faker->name,
    ];
});
