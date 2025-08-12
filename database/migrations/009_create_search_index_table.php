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
        Schema::create('search_index', function (Blueprint $table) {
            $table->id();
            $table->enum('entity_type', ['story', 'comment', 'user']);
            $table->unsignedBigInteger('entity_id');
            $table->longText('content');
            $table->longText('content_normalized');
            $table->json('metadata');
            $table->timestamps();
            
            // Unique constraint to prevent duplicates
            $table->unique(['entity_type', 'entity_id']);
            
            // Indexes for search performance
            $table->index(['entity_type', 'updated_at']);
            $table->fullText(['content', 'content_normalized']);
            
            // Add foreign key constraints
            $table->index('entity_id'); // For foreign key performance
        });
        
        // Add full-text indexes if supported
        if (config('database.default') === 'mysql') {
            DB::statement('ALTER TABLE search_index ADD FULLTEXT(content, content_normalized)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_index');
    }
};