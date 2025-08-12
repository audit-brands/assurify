<?php

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;

return [
    'up' => function () {
        DB::schema()->create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('tag', 25)->unique();
            $table->string('description', 100)->nullable();
            $table->boolean('privileged')->default(false);
            $table->boolean('is_media')->default(false);
            $table->boolean('inactive')->default(false);
            $table->float('hotness_mod', 4, 2)->default(0.0);
            $table->timestamps();
            
            $table->index(['tag']);
            $table->index(['inactive']);
        });
    },
    'down' => function () {
        DB::schema()->dropIfExists('tags');
    }
];