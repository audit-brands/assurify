<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class CreateMessagesTable extends Migration
{
    public function up()
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 10)->unique();
            $table->string('token', 32)->unique();
            
            $table->unsignedBigInteger('author_user_id');
            $table->unsignedBigInteger('recipient_user_id');
            
            $table->string('subject', 100);
            $table->text('body');
            
            $table->boolean('has_been_read')->default(false);
            $table->boolean('deleted_by_author')->default(false);
            $table->boolean('deleted_by_recipient')->default(false);
            
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('author_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('recipient_user_id')->references('id')->on('users')->onDelete('cascade');
            
            // Indexes for performance
            $table->index(['author_user_id', 'created_at']);
            $table->index(['recipient_user_id', 'created_at']);
            $table->index(['recipient_user_id', 'has_been_read']);
            $table->index('short_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('messages');
    }
}