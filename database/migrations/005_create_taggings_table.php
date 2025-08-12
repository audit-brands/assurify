<?php

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;

return [
    'up' => function () {
        DB::schema()->create('taggings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained('stories')->onDelete('cascade');
            $table->foreignId('tag_id')->constrained('tags')->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['story_id', 'tag_id']);
            $table->index(['story_id']);
            $table->index(['tag_id']);
        });
    },
    'down' => function () {
        DB::schema()->dropIfExists('taggings');
    }
];