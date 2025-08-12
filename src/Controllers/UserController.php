<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UserController extends BaseController
{
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
}
