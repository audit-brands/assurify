<?php

declare(strict_types=1);

/**
 * Advanced Search System Routes
 * 
 * This file defines all the routes for the enhanced search functionality
 * including faceted search, analytics, and admin endpoints.
 */

use App\Controllers\SearchController;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    
    // Main search routes
    $app->group('/search', function (RouteCollectorProxy $group) {
        
        // Primary search endpoint (web interface)
        $group->get('', [SearchController::class, 'index']);
        
        // Advanced faceted search API
        $group->get('/faceted', [SearchController::class, 'facetedSearch']);
        
        // Search suggestions and autocomplete
        $group->get('/suggestions', [SearchController::class, 'suggestions']);
        $group->get('/autocomplete', [SearchController::class, 'autocomplete']); // Legacy endpoint
        
        // Popular and trending searches
        $group->get('/popular', [SearchController::class, 'popular']);
        
        // Track search result clicks (for analytics)
        $group->post('/track-click', [SearchController::class, 'trackClick']);
        
    });
    
    // Admin-only search management routes
    $app->group('/admin/search', function (RouteCollectorProxy $group) {
        
        // Search analytics and statistics
        $group->get('/analytics', [SearchController::class, 'analytics']);
        
        // Search index management
        $group->get('/index/stats', [SearchController::class, 'indexStats']);
        $group->post('/index/rebuild', [SearchController::class, 'rebuildIndex']);
        
    })->add(function ($request, $handler) {
        // Admin authentication middleware would go here
        // For now, the controller handles the auth check
        return $handler->handle($request);
    });
    
};