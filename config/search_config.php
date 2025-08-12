<?php

declare(strict_types=1);

/**
 * Advanced Search System Configuration
 * 
 * This file contains all configuration options for the search system
 * including indexing settings, caching, and performance tuning.
 */

return [
    
    // Basic search settings
    'pagination' => [
        'per_page' => 20,
        'max_results' => 400,
        'max_per_page' => 100,
    ],
    
    // Cache settings
    'cache' => [
        'search_results_ttl' => 600,     // 10 minutes
        'suggestions_ttl' => 3600,       // 1 hour
        'popular_searches_ttl' => 3600,  // 1 hour
        'analytics_ttl' => 1800,         // 30 minutes
    ],
    
    // Search indexing settings
    'indexing' => [
        'batch_size' => 100,
        'max_content_length' => 65535,   // MySQL TEXT limit
        'auto_index_new_content' => true,
        'index_update_delay' => 0,       // Seconds to delay indexing (0 = immediate)
    ],
    
    // Relevance scoring weights
    'relevance_weights' => [
        'title_exact' => 10.0,
        'title_partial' => 7.0,
        'description_exact' => 5.0,
        'description_partial' => 3.0,
        'comment_exact' => 4.0,
        'comment_partial' => 2.0,
        'tag_match' => 6.0,
        'user_match' => 3.0,
        'domain_match' => 2.0,
    ],
    
    // Engagement multipliers for relevance scoring
    'engagement_multipliers' => [
        'max_score_boost' => 2.0,
        'max_comment_boost' => 1.5,
        'max_karma_boost' => 1.3,
        'score_divisor' => 100,          // Normalize score
        'comment_divisor' => 50,         // Normalize comment count
        'karma_divisor' => 1000,         // Normalize user karma
    ],
    
    // Recency boost settings
    'recency_boost' => [
        'very_recent_days' => 1,         // Days for maximum boost
        'recent_days' => 7,              // Days for high boost
        'moderate_days' => 30,           // Days for moderate boost
        'old_days' => 365,               // Days before penalty
        'very_recent_multiplier' => 1.5,
        'recent_multiplier' => 1.2,
        'moderate_multiplier' => 1.1,
        'neutral_multiplier' => 1.0,
        'old_multiplier' => 0.8,
    ],
    
    // Search suggestion settings
    'suggestions' => [
        'min_query_length' => 2,
        'max_suggestions' => 20,
        'fuzzy_matching' => true,
        'max_levenshtein_distance' => 3,
        'popular_suggestions_count' => 5,
    ],
    
    // Analytics settings
    'analytics' => [
        'track_searches' => true,
        'track_clicks' => true,
        'track_anonymous_users' => true,
        'retention_days' => 365,         // How long to keep analytics data
        'trending_threshold' => 1.5,     // Minimum ratio for trending queries
        'trending_min_searches' => 5,    // Minimum searches for trending
        'popular_query_days' => 30,      // Days to look back for popular queries
    ],
    
    // Search query parsing
    'query_parsing' => [
        'max_query_length' => 500,
        'max_terms' => 20,
        'enable_wildcards' => true,
        'enable_phrases' => true,
        'enable_boolean_operators' => true,
        'enable_field_filters' => true,
        'stop_words' => ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'],
    ],
    
    // Search result highlighting
    'highlighting' => [
        'enabled' => true,
        'tag_open' => '<mark>',
        'tag_close' => '</mark>',
        'max_highlight_length' => 200,
        'context_chars' => 50,           // Characters around highlighted terms
    ],
    
    // Performance settings
    'performance' => [
        'enable_query_cache' => true,
        'slow_query_threshold_ms' => 1000,  // Log queries slower than this
        'max_concurrent_searches' => 100,   // Rate limiting
        'search_timeout_seconds' => 30,
    ],
    
    // Feature flags
    'features' => [
        'faceted_search' => true,
        'user_search' => true,
        'advanced_filters' => true,
        'search_analytics' => true,
        'trending_searches' => true,
        'search_suggestions' => true,
        'result_highlighting' => true,
        'real_time_indexing' => true,
    ],
    
    // Search types and their settings
    'search_types' => [
        'stories' => [
            'enabled' => true,
            'fields' => ['title', 'description', 'url'],
            'boost_fields' => ['title' => 2.0, 'description' => 1.0],
            'filters' => ['tags', 'domain', 'user', 'score', 'date', 'expired'],
        ],
        'comments' => [
            'enabled' => true,
            'fields' => ['comment'],
            'boost_fields' => ['comment' => 1.0],
            'filters' => ['user', 'story', 'score', 'confidence', 'date'],
        ],
        'users' => [
            'enabled' => true,
            'fields' => ['username', 'about'],
            'boost_fields' => ['username' => 2.0, 'about' => 1.0],
            'filters' => ['karma', 'moderator', 'date'],
        ],
    ],
    
    // Default search ordering options
    'sort_options' => [
        'newest' => 'Most Recent',
        'relevance' => 'Most Relevant',
        'score' => 'Highest Score',
        'comments' => 'Most Comments',
        'karma' => 'User Karma',
        'confidence' => 'Comment Confidence',
    ],
    
    // API rate limiting
    'rate_limiting' => [
        'enabled' => true,
        'max_requests_per_minute' => 60,
        'max_requests_per_hour' => 1000,
        'block_duration_minutes' => 15,
    ],
    
    // Search index maintenance
    'maintenance' => [
        'auto_cleanup_enabled' => true,
        'cleanup_interval_hours' => 24,
        'remove_deleted_content' => true,
        'optimize_index_interval_days' => 7,
    ],
    
];