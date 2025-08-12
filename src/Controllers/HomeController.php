<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class HomeController extends BaseController
{
    public function index(Request $request, Response $response): Response
    {
        return $this->render($response, 'home/index', [
            'title' => 'Lobsters',
            'stories' => []
        ]);
    }

    public function newest(Request $request, Response $response): Response
    {
        return $this->render($response, 'home/newest', [
            'title' => 'Newest | Lobsters',
            'stories' => []
        ]);
    }

    public function recent(Request $request, Response $response): Response
    {
        return $this->render($response, 'home/recent', [
            'title' => 'Recent | Lobsters',
            'stories' => []
        ]);
    }

    public function top(Request $request, Response $response): Response
    {
        return $this->render($response, 'home/top', [
            'title' => 'Top | Lobsters',
            'stories' => []
        ]);
    }

    public function search(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams()['q'] ?? '';

        return $this->render($response, 'search/index', [
            'title' => 'Search | Lobsters',
            'query' => $query,
            'results' => []
        ]);
    }

    public function storiesFeed(Request $request, Response $response): Response
    {
        // TODO: Implement RSS feed
        $response->getBody()->write('<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>Lobsters</title></channel></rss>');
        return $response->withHeader('Content-Type', 'application/rss+xml');
    }
}
