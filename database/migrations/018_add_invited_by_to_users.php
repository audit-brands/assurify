<?php

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;

return [
    'up' => function () {
        DB::schema()->table('users', function (Blueprint $table) {
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->onDelete('set null');
        });
    },
    'down' => function () {
        DB::schema()->table('users', function (Blueprint $table) {
            $table->dropForeign(['invited_by_user_id']);
            $table->dropColumn('invited_by_user_id');
        });
    }
];