<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\SearchService;
use App\Services\SearchIndexService;
use App\Models\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use League\Plates\Engine;

class SearchController extends BaseController
{
    public function __construct(
        Engine $templates,
        private SearchService $searchService,
        private SearchIndexService $searchIndexService
    ) {
        parent::__construct($templates);
    }

    public function index(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        
        $query = $queryParams['q'] ?? '';
        $type = $queryParams['what'] ?? 'all';
        $order = $queryParams['order'] ?? 'newest';
        $page = max(1, (int) ($queryParams['page'] ?? 1));
        
        // Extract advanced filters
        $filters = $this->extractFilters($queryParams);
        
        // Get current user ID if authenticated
        $userId = $this->getCurrentUserId($request);

        // Validate parameters
        if (!in_array($type, ['all', 'stories', 'comments', 'users'])) {
            $type = 'all';
        }
        
        if (!in_array($order, ['newest', 'relevance', 'score', 'comments', 'karma', 'confidence'])) {
            $order = 'newest';
        }

        $searchResults = [];
        $hasSearched = false;

        if (!empty(trim($query))) {
            $searchResults = $this->searchService->search($query, $type, $order, $page, $filters, $userId);
            $hasSearched = true;
        }

        return $this->render($response, 'search/index', [
            'title' => !empty($query) ? "Search: {$query} | Lobsters" : 'Search | Lobsters',
            'query' => $query,
            'type' => $type,
            'order' => $order,
            'page' => $page,
            'filters' => $filters,
            'results' => $searchResults['results'] ?? [],
            'highlighted_results' => $searchResults['highlighted_results'] ?? [],
            'total' => $searchResults['total'] ?? 0,
            'per_page' => $searchResults['per_page'] ?? 20,
            'search_time_ms' => $searchResults['search_time_ms'] ?? 0,
            'has_searched' => $hasSearched,
            'popular_searches' => $this->searchService->getPopularSearches(),
            'trending_searches' => $this->searchService->getTrendingSearches(5)
        ]);
    }

    public function autocomplete(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $query = $queryParams['q'] ?? '';
        $limit = min(20, max(1, (int) ($queryParams['limit'] ?? 10)));
        
        $suggestions = $this->searchService->getSearchSuggestions($query, $limit);
        
        return $this->json($response, $suggestions);
    }
    
    /**
     * Advanced faceted search API endpoint
     */
    public function facetedSearch(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $filters = $this->extractFilters($queryParams);
        
        // Add query if provided
        if (!empty($queryParams['q'])) {
            $filters['query'] = $queryParams['q'];
        }
        
        $results = $this->searchService->facetedSearch($filters);
        
        return $this->json($response, $results);
    }
    
    /**
     * Get search suggestions endpoint
     */
    public function suggestions(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $query = $queryParams['q'] ?? '';
        $limit = min(20, max(1, (int) ($queryParams['limit'] ?? 10)));
        
        $suggestions = $this->searchService->getSearchSuggestions($query, $limit);
        
        return $this->json($response, $suggestions);
    }
    
    /**
     * Get popular/trending searches
     */
    public function popular(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $limit = min(50, max(1, (int) ($queryParams['limit'] ?? 10)));
        $days = min(365, max(1, (int) ($queryParams['days'] ?? 30)));
        $type = $queryParams['type'] ?? 'popular'; // popular or trending
        
        if ($type === 'trending') {
            $searches = $this->searchService->getTrendingSearches($limit);
        } else {
            $searches = $this->searchService->getPopularSearches($limit, $days);
        }
        
        return $this->json($response, [
            'type' => $type,
            'searches' => $searches,
            'limit' => $limit,
            'period_days' => $days
        ]);
    }
    
    /**
     * Search analytics endpoint (admin only)
     */
    public function analytics(Request $request, Response $response): Response
    {
        // Check if user is admin
        $user = $this->getCurrentUser($request);
        if (!$user || !$user->is_admin) {
            return $this->json($response, ['error' => 'Unauthorized'], 403);
        }
        
        $queryParams = $request->getQueryParams();
        $days = min(365, max(1, (int) ($queryParams['days'] ?? 30)));
        
        $stats = $this->searchService->getSearchStats($days);
        $indexStats = $this->searchIndexService->getIndexStats();
        
        return $this->json($response, [
            'search_stats' => $stats,
            'index_stats' => $indexStats,
            'period_days' => $days
        ]);
    }
    
    /**
     * Track search result click
     */
    public function trackClick(Request $request, Response $response): Response
    {
        $body = json_decode($request->getBody()->getContents(), true);
        
        $searchId = $body['search_id'] ?? '';
        $resultId = (int) ($body['result_id'] ?? 0);
        $resultType = $body['result_type'] ?? '';
        
        if (empty($searchId) || $resultId <= 0 || empty($resultType)) {
            return $this->json($response, ['error' => 'Missing required parameters'], 400);
        }
        
        $success = $this->searchService->trackClick($searchId, $resultId, $resultType);
        
        return $this->json($response, ['success' => $success]);
    }
    
    /**
     * Admin endpoint to rebuild search index
     */
    public function rebuildIndex(Request $request, Response $response): Response
    {
        // Check if user is admin
        $user = $this->getCurrentUser($request);
        if (!$user || !$user->is_admin) {
            return $this->json($response, ['error' => 'Unauthorized'], 403);
        }
        
        $results = $this->searchIndexService->rebuildIndex();
        
        return $this->json($response, [
            'message' => 'Index rebuild completed',
            'results' => $results
        ]);
    }
    
    /**
     * Admin endpoint to get index statistics
     */
    public function indexStats(Request $request, Response $response): Response
    {
        // Check if user is admin
        $user = $this->getCurrentUser($request);
        if (!$user || !$user->is_admin) {
            return $this->json($response, ['error' => 'Unauthorized'], 403);
        }
        
        $stats = $this->searchIndexService->getIndexStats();
        
        return $this->json($response, $stats);
    }
    
    /**
     * Extract search filters from query parameters
     */
    private function extractFilters(array $queryParams): array
    {
        $filters = [];
        
        // Score filters
        if (!empty($queryParams['min_score'])) {
            $filters['min_score'] = (int) $queryParams['min_score'];
        }
        
        if (!empty($queryParams['max_score'])) {
            $filters['max_score'] = (int) $queryParams['max_score'];
        }
        
        // Date filters
        if (!empty($queryParams['date_from'])) {
            $filters['date_from'] = $queryParams['date_from'];
        }
        
        if (!empty($queryParams['date_to'])) {
            $filters['date_to'] = $queryParams['date_to'];
        }
        
        // Tag filters
        if (!empty($queryParams['tags'])) {
            $filters['tags'] = is_array($queryParams['tags']) ? 
                $queryParams['tags'] : 
                explode(',', $queryParams['tags']);
        }
        
        // Domain filter
        if (!empty($queryParams['domain'])) {
            $filters['domain'] = $queryParams['domain'];
        }
        
        // User filter
        if (!empty($queryParams['user'])) {
            $filters['user'] = $queryParams['user'];
        }
        
        if (!empty($queryParams['user_id'])) {
            $filters['user_id'] = (int) $queryParams['user_id'];
        }
        
        // Story-specific filters
        if (isset($queryParams['is_expired'])) {
            $filters['is_expired'] = (bool) $queryParams['is_expired'];
        }
        
        // Comment-specific filters
        if (!empty($queryParams['min_confidence'])) {
            $filters['min_confidence'] = (float) $queryParams['min_confidence'];
        }
        
        if (!empty($queryParams['story_id'])) {
            $filters['story_id'] = (int) $queryParams['story_id'];
        }
        
        // User-specific filters
        if (!empty($queryParams['min_karma'])) {
            $filters['min_karma'] = (int) $queryParams['min_karma'];
        }
        
        if (!empty($queryParams['is_moderator'])) {
            $filters['is_moderator'] = (bool) $queryParams['is_moderator'];
        }
        
        // Pagination
        if (!empty($queryParams['per_page'])) {
            $filters['per_page'] = min(100, max(1, (int) $queryParams['per_page']));
        }
        
        return $filters;
    }
    
    /**
     * Get current user ID from request
     */
    private function getCurrentUserId(Request $request): ?int
    {
        // This would typically check session/JWT token
        // Implementation depends on your auth system
        $user = $this->getCurrentUser($request);
        return $user ? $user->id : null;
    }
    
    /**
     * Get current user from request
     */
    private function getCurrentUser(Request $request): ?User
    {
        // This would typically check session/JWT token
        // Implementation depends on your auth system
        // For now, return null - you'd implement actual auth checking
        return null;
    }
}