<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\FeedService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use League\Plates\Engine;

class UserController extends BaseController
{
    public function __construct(
        Engine $templates,
        private FeedService $feedService
    ) {
        parent::__construct($templates);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $username = $args['username'];

        return $this->render($response, 'users/show', [
            'title' => $username . ' | Lobsters',
            'user' => null,
            'stories' => [],
            'comments' => []
        ]);
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->render($response, 'users/index', [
            'title' => 'Users | Lobsters',
            'users' => []
        ]);
    }

    public function feed(Request $request, Response $response, array $args): Response
    {
        $username = $args['username'];
        
        $rssContent = $this->feedService->generateUserActivityFeed($username);
        
        $response->getBody()->write($rssContent);
        return $response->withHeader('Content-Type', 'application/rss+xml; charset=utf-8');
    }
}
