<?php

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;

return [
    'up' => function () {
        DB::schema()->create('votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('story_id')->nullable()->constrained('stories')->onDelete('cascade');
            $table->foreignId('comment_id')->nullable()->constrained('comments')->onDelete('cascade');
            $table->tinyInteger('vote'); // 1 for upvote, -1 for downvote
            $table->string('reason', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            
            $table->unique(['user_id', 'story_id']);
            $table->unique(['user_id', 'comment_id']);
            $table->index(['user_id']);
            $table->index(['story_id']);
            $table->index(['comment_id']);
            $table->index(['vote']);
        });
    },
    'down' => function () {
        DB::schema()->dropIfExists('votes');
    }
];