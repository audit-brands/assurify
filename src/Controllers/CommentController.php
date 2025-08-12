<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class CommentController extends BaseController
{
    public function store(Request $request, Response $response): Response
    {
        // TODO: Implement comment creation
        return $this->json($response, ['success' => true]);
    }

    public function vote(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        // TODO: Implement comment voting
        return $this->json($response, ['success' => true]);
    }

    public function commentsFeed(Request $request, Response $response): Response
    {
        // TODO: Implement RSS feed
        $response->getBody()->write('<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>Lobsters Comments</title></channel></rss>');
        return $response->withHeader('Content-Type', 'application/rss+xml');
    }
}
