<?php

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;

return [
    'up' => function () {
        DB::schema()->create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('email', 100);
            $table->string('code', 255);
            $table->text('memo')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            
            $table->unique(['code']);
            $table->index(['user_id']);
            $table->index(['email']);
            $table->index(['used_at']);
        });
    },
    'down' => function () {
        DB::schema()->dropIfExists('invitations');
    }
];