<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class StoryController extends BaseController
{
    public function show(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $slug = $args['slug'];

        return $this->render($response, 'stories/show', [
            'title' => 'Story Title | Lobsters',
            'story' => null,
            'comments' => []
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        return $this->render($response, 'stories/create', [
            'title' => 'Submit Story | Lobsters'
        ]);
    }

    public function store(Request $request, Response $response): Response
    {
        // TODO: Implement story creation
        return $this->redirect($response, '/');
    }

    public function vote(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        // TODO: Implement story voting
        return $this->json($response, ['success' => true]);
    }
}
