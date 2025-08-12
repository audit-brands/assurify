<?php

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Create analytics and performance monitoring tables
 */

// Analytics Events Table
DB::statement("
    CREATE TABLE IF NOT EXISTS analytics_events (
        id BIGINT PRIMARY KEY AUTO_INCREMENT,
        event_type VARCHAR(50) NOT NULL,
        user_id INT NULL,
        session_id VARCHAR(100),
        event_data JSON,
        user_agent TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_event_type_time (event_type, created_at),
        INDEX idx_user_time (user_id, created_at),
        INDEX idx_session_time (session_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Performance Metrics Table
DB::statement("
    CREATE TABLE IF NOT EXISTS performance_metrics (
        id BIGINT PRIMARY KEY AUTO_INCREMENT,
        metric_name VARCHAR(100) NOT NULL,
        metric_value DECIMAL(15,6),
        metric_labels JSON,
        recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_metric_time (metric_name, recorded_at),
        INDEX idx_recorded_at (recorded_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// User Sessions Table
DB::statement("
    CREATE TABLE IF NOT EXISTS user_sessions (
        id BIGINT PRIMARY KEY AUTO_INCREMENT,
        session_id VARCHAR(100) UNIQUE NOT NULL,
        user_id INT NULL,
        start_time TIMESTAMP,
        end_time TIMESTAMP NULL,
        page_views INT DEFAULT 0,
        actions_count INT DEFAULT 0,
        device_type VARCHAR(50),
        browser VARCHAR(100),
        referrer TEXT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_session (user_id, start_time),
        INDEX idx_session_time (start_time),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Daily Analytics Summary Table
DB::statement("
    CREATE TABLE IF NOT EXISTS daily_analytics (
        date DATE PRIMARY KEY,
        total_users INT DEFAULT 0,
        active_users INT DEFAULT 0,
        new_users INT DEFAULT 0,
        total_sessions INT DEFAULT 0,
        page_views INT DEFAULT 0,
        unique_page_views INT DEFAULT 0,
        avg_session_duration INT DEFAULT 0,
        bounce_rate DECIMAL(5,2) DEFAULT 0,
        top_content JSON,
        device_breakdown JSON,
        traffic_sources JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Content Performance Table
DB::statement("
    CREATE TABLE IF NOT EXISTS content_performance (
        content_id INT,
        content_type VARCHAR(50),
        date DATE,
        views INT DEFAULT 0,
        unique_views INT DEFAULT 0,
        shares INT DEFAULT 0,
        comments INT DEFAULT 0,
        likes INT DEFAULT 0,
        engagement_rate DECIMAL(8,6) DEFAULT 0,
        time_on_page INT DEFAULT 0,
        bounce_rate DECIMAL(5,2) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (content_id, content_type, date),
        INDEX idx_performance_date (date, engagement_rate DESC),
        INDEX idx_content_type_date (content_type, date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Performance Alerts Table
DB::statement("
    CREATE TABLE IF NOT EXISTS performance_alerts (
        id BIGINT PRIMARY KEY AUTO_INCREMENT,
        alert_type VARCHAR(100) NOT NULL,
        severity ENUM('info', 'warning', 'critical') DEFAULT 'warning',
        message TEXT NOT NULL,
        context JSON,
        status ENUM('active', 'acknowledged', 'resolved') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        acknowledged_at TIMESTAMP NULL,
        resolved_at TIMESTAMP NULL,
        INDEX idx_status_created (status, created_at),
        INDEX idx_severity_created (severity, created_at),
        INDEX idx_alert_type (alert_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// System Health Checks Table
DB::statement("
    CREATE TABLE IF NOT EXISTS system_health_checks (
        id BIGINT PRIMARY KEY AUTO_INCREMENT,
        check_name VARCHAR(100) NOT NULL,
        status ENUM('healthy', 'warning', 'critical') DEFAULT 'healthy',
        response_time INT,
        error_message TEXT NULL,
        check_data JSON,
        checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_check_time (check_name, checked_at),
        INDEX idx_status_time (status, checked_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// User Behavior Patterns Table
DB::statement("
    CREATE TABLE IF NOT EXISTS user_behavior_patterns (
        id BIGINT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        pattern_type VARCHAR(50) NOT NULL,
        pattern_data JSON,
        confidence_score DECIMAL(5,4),
        first_observed TIMESTAMP,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_pattern (user_id, pattern_type),
        INDEX idx_pattern_confidence (pattern_type, confidence_score DESC),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// API Usage Statistics Table
DB::statement("
    CREATE TABLE IF NOT EXISTS api_usage_stats (
        id BIGINT PRIMARY KEY AUTO_INCREMENT,
        endpoint VARCHAR(255) NOT NULL,
        method VARCHAR(10) NOT NULL,
        user_id INT NULL,
        api_key_id INT NULL,
        response_time INT,
        status_code INT,
        request_size INT,
        response_size INT,
        user_agent TEXT,
        ip_address VARCHAR(45),
        requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_endpoint_time (endpoint, requested_at),
        INDEX idx_user_time (user_id, requested_at),
        INDEX idx_status_time (status_code, requested_at),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Cache Performance Statistics Table
DB::statement("
    CREATE TABLE IF NOT EXISTS cache_performance_stats (
        id BIGINT PRIMARY KEY AUTO_INCREMENT,
        cache_key VARCHAR(255) NOT NULL,
        operation ENUM('get', 'set', 'delete', 'flush') NOT NULL,
        hit BOOLEAN DEFAULT FALSE,
        execution_time INT,
        data_size INT,
        ttl INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_key_time (cache_key, created_at),
        INDEX idx_operation_time (operation, created_at),
        INDEX idx_hit_time (hit, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

echo "Analytics and performance monitoring tables created successfully.\n";