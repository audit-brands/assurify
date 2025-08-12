<?php

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;

return [
    'up' => function () {
        // Security Events Table
        DB::schema()->create('security_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('event_type', 50);
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->string('source_ip', 45)->nullable();
            $table->integer('user_id')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('request_data')->nullable();
            $table->json('threat_indicators')->nullable();
            $table->string('response_action', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->index(['event_type', 'severity'], 'idx_type_severity');
            $table->index(['source_ip', 'created_at'], 'idx_ip_time');
            $table->index(['user_id', 'created_at'], 'idx_user_time');
            $table->index(['severity', 'created_at'], 'idx_severity_time');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });

        // Rate Limiting Events Table
        DB::schema()->create('rate_limit_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('identifier', 255);
            $table->string('limit_type', 100);
            $table->string('limit_key', 255);
            $table->integer('requests_made');
            $table->boolean('limit_exceeded')->default(false);
            $table->string('source_ip', 45)->nullable();
            $table->integer('user_id')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('context_data')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->index(['identifier', 'limit_type'], 'idx_identifier_type');
            $table->index(['limit_key', 'created_at'], 'idx_limit_key_time');
            $table->index(['limit_exceeded', 'created_at'], 'idx_exceeded_time');
            $table->index(['source_ip', 'created_at'], 'idx_ip_time');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });

        // IP Reputation Table
        DB::schema()->create('ip_reputation', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('ip_address', 45)->unique();
            $table->decimal('reputation_score', 5, 2)->default(50.00);
            $table->enum('threat_level', ['none', 'low', 'medium', 'high', 'critical'])->default('none');
            $table->timestamp('first_seen')->useCurrent();
            $table->timestamp('last_seen')->useCurrent();
            $table->integer('total_requests')->default(0);
            $table->integer('blocked_requests')->default(0);
            $table->integer('security_events')->default(0);
            $table->string('geolocation_country', 2)->nullable();
            $table->string('geolocation_region', 100)->nullable();
            $table->string('geolocation_city', 100)->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->text('block_reason')->nullable();
            $table->timestamp('blocked_until')->nullable();
            $table->json('threat_indicators')->nullable();
            $table->timestamps();
            
            $table->index('reputation_score');
            $table->index('threat_level');
            $table->index('is_blocked');
            $table->index('geolocation_country');
            $table->index('last_seen');
        });

        // Content Moderation Queue Table
        DB::schema()->create('content_moderation_queue', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->enum('content_type', ['story', 'comment']);
            $table->integer('content_id');
            $table->integer('user_id');
            $table->enum('moderation_status', ['pending', 'approved', 'rejected', 'flagged'])->default('pending');
            $table->json('ai_analysis')->nullable();
            $table->decimal('spam_score', 5, 4)->default(0.0000);
            $table->decimal('toxicity_score', 5, 4)->default(0.0000);
            $table->string('sentiment', 20)->nullable();
            $table->string('detected_language', 5)->nullable();
            $table->json('content_topics')->nullable();
            $table->json('moderation_flags')->nullable();
            $table->integer('human_reviewer_id')->nullable();
            $table->text('review_notes')->nullable();
            $table->boolean('auto_decision')->default(false);
            $table->decimal('confidence_score', 5, 4)->default(0.0000);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('reviewed_at')->nullable();
            
            $table->index(['moderation_status', 'created_at'], 'idx_status_created');
            $table->index(['content_type', 'content_id'], 'idx_content_type_id');
            $table->index(['user_id', 'moderation_status'], 'idx_user_status');
            $table->index('spam_score');
            $table->index('toxicity_score');
            $table->index('human_reviewer_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('human_reviewer_id')->references('id')->on('users')->onDelete('set null');
        });

        // Security Configuration Table
        DB::schema()->create('security_configuration', function (Blueprint $table) {
            $table->increments('id');
            $table->string('config_key', 100)->unique();
            $table->json('config_value');
            $table->enum('config_type', ['rate_limit', 'security_rule', 'threat_pattern', 'whitelist', 'blacklist']);
            $table->boolean('is_active')->default(true);
            $table->integer('created_by')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->index(['config_key', 'config_type'], 'idx_key_type');
            $table->index(['config_type', 'is_active'], 'idx_type_active');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });

        echo "Security and rate limiting tables created successfully.\n";
    },
    'down' => function () {
        DB::schema()->dropIfExists('security_configuration');
        DB::schema()->dropIfExists('content_moderation_queue');
        DB::schema()->dropIfExists('ip_reputation');
        DB::schema()->dropIfExists('rate_limit_events');
        DB::schema()->dropIfExists('security_events');
    }
];