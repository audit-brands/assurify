<?php

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;

return [
    'up' => function () {
        DB::schema()->create('stories', function (Blueprint $table) {
            $table->id();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('title', 150)->charset('utf8mb4')->collation('utf8mb4_unicode_ci');
            $table->text('description')->nullable()->charset('utf8mb4')->collation('utf8mb4_unicode_ci');
            $table->string('short_id', 6)->unique();
            $table->string('url', 500)->default('');
            $table->foreignId('user_id')->constrained('users');
            $table->integer('score')->default(1);
            $table->integer('flags')->default(0);
            $table->boolean('is_expired')->default(false);
            $table->boolean('is_moderated')->default(false);
            $table->mediumText('markeddown_description')->nullable();
            $table->mediumText('story_cache')->nullable();
            $table->integer('comments_count')->default(0);
            $table->integer('upvotes')->default(0);
            $table->integer('downvotes')->default(0);
            $table->boolean('is_unavailable')->default(false);
            $table->timestamp('unavailable_at')->nullable();
            $table->string('twitter_id', 20)->nullable();
            $table->boolean('user_is_author')->default(false);
            $table->foreignId('merged_story_id')->nullable()->constrained('stories');
            
            $table->index(['user_id']);
            $table->index(['short_id']);
            $table->index(['created_at']);
            $table->index(['is_expired', 'is_moderated']);
            $table->index(['score']);
            $table->fullText(['title', 'description']);
        });
    },
    'down' => function () {
        DB::schema()->dropIfExists('stories');
    }
];