<?php

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;

return [
    'up' => function () {
        DB::schema()->create('comments', function (Blueprint $table) {
            $table->id();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('short_id', 10)->unique();
            $table->foreignId('story_id')->constrained('stories');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('parent_comment_id')->nullable()->constrained('comments');
            $table->string('thread_id', 50)->nullable();
            $table->mediumText('comment')->charset('utf8mb4')->collation('utf8mb4_unicode_ci');
            $table->mediumText('markeddown_comment')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->boolean('is_moderated')->default(false);
            $table->integer('score')->default(1);
            $table->integer('flags')->default(0);
            $table->float('confidence', 20, 17)->default(0.0);
            $table->integer('upvotes')->default(0);
            $table->integer('downvotes')->default(0);
            
            $table->index(['story_id']);
            $table->index(['user_id']);
            $table->index(['parent_comment_id']);
            $table->index(['thread_id']);
            $table->index(['short_id']);
            $table->index(['is_deleted', 'is_moderated']);
            $table->fullText(['comment']);
        });
    },
    'down' => function () {
        DB::schema()->dropIfExists('comments');
    }
];