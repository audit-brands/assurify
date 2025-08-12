<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\StoryService;
use App\Services\FeedService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use League\Plates\Engine;

class HomeController extends BaseController
{
    public function __construct(
        Engine $templates,
        private StoryService $storyService,
        private FeedService $feedService
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


    public function storiesFeed(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $tag = $queryParams['tag'] ?? null;
        
        $rssContent = $this->feedService->generateStoriesFeed($tag);
        
        $response->getBody()->write($rssContent);
        return $response->withHeader('Content-Type', 'application/rss+xml; charset=utf-8');
    }
}
