<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\SearchService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use League\Plates\Engine;

class SearchController extends BaseController
{
    public function __construct(
        Engine $templates,
        private SearchService $searchService
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

        // Validate parameters
        if (!in_array($type, ['all', 'stories', 'comments'])) {
            $type = 'all';
        }
        
        if (!in_array($order, ['newest', 'relevance', 'score'])) {
            $order = 'newest';
        }

        $searchResults = [];
        $hasSearched = false;

        if (!empty(trim($query))) {
            $searchResults = $this->searchService->search($query, $type, $order, $page);
            $hasSearched = true;
        }

        return $this->render($response, 'search/index', [
            'title' => !empty($query) ? "Search: {$query} | Lobsters" : 'Search | Lobsters',
            'query' => $query,
            'type' => $type,
            'order' => $order,
            'page' => $page,
            'results' => $searchResults['results'] ?? [],
            'total' => $searchResults['total'] ?? 0,
            'per_page' => $searchResults['per_page'] ?? 20,
            'has_searched' => $hasSearched,
            'popular_searches' => $this->searchService->getPopularSearches()
        ]);
    }

    public function autocomplete(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $query = $queryParams['q'] ?? '';
        
        if (strlen($query) < 2) {
            return $this->json($response, ['suggestions' => []]);
        }

        $tags = $this->searchService->searchTags($query);
        
        return $this->json($response, [
            'suggestions' => $tags
        ]);
    }
}