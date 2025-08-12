<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\StoryService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use League\Plates\Engine;

class HomeController extends BaseController
{
    public function __construct(
        Engine $templates,
        private StoryService $storyService
    ) {
        parent::__construct($templates);
    }

    public function index(Request $request, Response $response): Response
    {
        // Temporary: Return empty stories until database is configured
        $stories = [];

        return $this->render($response, 'home/index', [
            'title' => 'Lobsters',
            'stories' => $stories
        ]);
    }

    public function newest(Request $request, Response $response): Response
    {
        // Temporary: Return empty stories until database is configured
        $stories = [];

        return $this->render($response, 'home/newest', [
            'title' => 'Newest | Lobsters',
            'stories' => $stories
        ]);
    }

    public function recent(Request $request, Response $response): Response
    {
        // Temporary: Return empty stories until database is configured
        $stories = [];

        return $this->render($response, 'home/recent', [
            'title' => 'Recent | Lobsters',
            'stories' => $stories
        ]);
    }

    public function top(Request $request, Response $response): Response
    {
        // Temporary: Return empty stories until database is configured
        $stories = [];

        return $this->render($response, 'home/top', [
            'title' => 'Top | Lobsters',
            'stories' => $stories
        ]);
    }

    public function search(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams()['q'] ?? '';
        $results = [];

        // TODO: Implement search functionality in Phase 5
        if (!empty($query)) {
            // Placeholder search - will be implemented in Phase 5
            $results = [];
        }

        return $this->render($response, 'search/index', [
            'title' => 'Search | Lobsters',
            'query' => $query,
            'results' => $results
        ]);
    }

    public function storiesFeed(Request $request, Response $response): Response
    {
        // TODO: Implement RSS feed in Phase 5
        $response->getBody()->write('<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>Lobsters</title></channel></rss>');
        return $response->withHeader('Content-Type', 'application/rss+xml');
    }
}
