<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('search_analytics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('query', 500);
            $table->string('query_normalized', 500)->index();
            $table->enum('type', ['all', 'stories', 'comments', 'users'])->default('all');
            $table->json('filters')->nullable();
            $table->integer('results_count')->default(0);
            $table->unsignedBigInteger('clicked_result_id')->nullable();
            $table->string('clicked_result_type', 50)->nullable();
            $table->decimal('search_time_ms', 8, 2)->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at');
            
            // Indexes for analytics queries
            $table->index(['created_at', 'query_normalized']);
            $table->index(['user_id', 'created_at']);
            $table->index(['results_count', 'created_at']);
            $table->index(['clicked_result_id', 'clicked_result_type']);
            
            // Foreign key constraint
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_analytics');
    }
};