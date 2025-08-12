<?php

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;

return [
    'up' => function () {
        DB::schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->unique();
            $table->string('email', 100)->unique();
            $table->string('password_hash', 255);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_moderator')->default(false);
            $table->integer('karma')->default(1);
            $table->text('about')->nullable();
            $table->boolean('email_notifications')->default(true);
            $table->boolean('pushover_notifications')->default(false);
            $table->string('pushover_user_key', 30)->nullable();
            $table->string('pushover_sound', 30)->nullable();
            $table->integer('mailing_list_mode')->default(0);
            $table->boolean('show_avatars')->default(true);
            $table->boolean('show_story_previews')->default(false);
            $table->boolean('show_submit_tagging_hints')->default(true);
            $table->boolean('show_read_ribbons')->default(true);
            $table->boolean('hide_dragons')->default(false);
            $table->boolean('post_reply_notifications')->default(false);
            $table->string('theme', 20)->default('default');
            $table->string('session_token', 75)->nullable();
            $table->string('password_reset_token', 75)->nullable();
            $table->timestamp('banned_at')->nullable();
            $table->text('banned_reason')->nullable();
            $table->boolean('deleted')->default(false);
            
            $table->index(['username']);
            $table->index(['email']);
            $table->index(['session_token']);
            $table->index(['password_reset_token']);
        });
    },
    'down' => function () {
        DB::schema()->dropIfExists('users');
    }
];