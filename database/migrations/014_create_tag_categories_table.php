<?php

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;

return [
    'up' => function () {
        DB::schema()->create('tag_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('description', 200)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['sort_order']);
            $table->index(['is_active']);
        });
    },
    'down' => function () {
        DB::schema()->dropIfExists('tag_categories');
    }
];