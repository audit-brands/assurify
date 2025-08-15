<?php

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;

return [
    'up' => function () {
        DB::schema()->create('moderations', function (Blueprint $table) {
            $table->id();
            // Legacy columns for backward compatibility
            $table->unsignedBigInteger('moderator_user_id')->nullable();
            $table->unsignedBigInteger('story_id')->nullable();
            $table->unsignedBigInteger('comment_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 100);
            $table->text('reason')->nullable();
            $table->boolean('is_from_suggestions')->default(false);
            $table->string('token', 100)->nullable();
            
            // New enhanced columns
            $table->string('subject_type', 50)->nullable(); // 'story', 'comment', 'user', 'tag', 'domain'
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_title', 200)->nullable(); // for display when subject is deleted
            $table->json('metadata')->nullable(); // store before/after values, additional context
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['moderator_user_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
            $table->index('action');
        });
    },
    'down' => function () {
        DB::schema()->dropIfExists('moderations');
    }
];