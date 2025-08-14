<?php

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;

return [
    'up' => function () {
        DB::schema()->table('users', function (Blueprint $table) {
            $table->string('homepage', 255)->nullable();
            $table->string('github_username', 50)->nullable();
            $table->string('twitter_username', 50)->nullable();
        });
    },
    'down' => function () {
        DB::schema()->table('users', function (Blueprint $table) {
            $table->dropColumn(['homepage', 'github_username', 'twitter_username']);
        });
    }
];