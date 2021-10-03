<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @return void
     */
    public function up(): void
    {
        Schema::create('nested_related_models', static function (Blueprint $table) {
            $table->increments('id');
            $table->integer('related_model_id');
            $table->string('name');
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('nested_related_models');
    }
};
