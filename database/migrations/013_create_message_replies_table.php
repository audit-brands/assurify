<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class CreateMessageRepliesTable extends Migration
{
    public function up()
    {
        Schema::create('message_replies', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 10)->unique();
            $table->string('token', 32)->unique();
            
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('author_user_id');
            
            $table->text('body');
            
            $table->boolean('has_been_read')->default(false);
            $table->boolean('deleted_by_author')->default(false);
            $table->boolean('deleted_by_recipient')->default(false);
            
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('message_id')->references('id')->on('messages')->onDelete('cascade');
            $table->foreign('author_user_id')->references('id')->on('users')->onDelete('cascade');
            
            // Indexes for performance
            $table->index(['message_id', 'created_at']);
            $table->index('author_user_id');
            $table->index('short_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('message_replies');
    }
}