<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pivot_models', static function (Blueprint $table) {
            $table->increments('id');
            $table->integer('test_model_id');
            $table->integer('related_through_pivot_model_id');
            $table->string('location')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pivot_models');
    }
};
