<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TagController extends BaseController
{
    public function show(Request $request, Response $response, array $args): Response
    {
        $tag = $args['tag'];

        return $this->render($response, 'tags/show', [
            'title' => $tag . ' | Lobsters',
            'tag' => $tag,
            'stories' => []
        ]);
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->render($response, 'tags/index', [
            'title' => 'Tags | Lobsters',
            'tags' => []
        ]);
    }
}
