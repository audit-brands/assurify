<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController extends BaseController
{
    public function loginForm(Request $request, Response $response): Response
    {
        return $this->render($response, 'auth/login', [
            'title' => 'Login | Lobsters'
        ]);
    }

    public function login(Request $request, Response $response): Response
    {
        // TODO: Implement login logic
        return $this->redirect($response, '/');
    }

    public function logout(Request $request, Response $response): Response
    {
        // TODO: Implement logout logic
        session_destroy();
        return $this->redirect($response, '/');
    }

    public function signupForm(Request $request, Response $response): Response
    {
        return $this->render($response, 'auth/signup', [
            'title' => 'Sign Up | Lobsters'
        ]);
    }

    public function signup(Request $request, Response $response): Response
    {
        // TODO: Implement signup logic
        return $this->redirect($response, '/');
    }
}
