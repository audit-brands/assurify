<?php

declare(strict_types=1);

namespace App\Controllers;

use League\Plates\Engine;
use Psr\Http\Message\ResponseInterface as Response;

abstract class BaseController
{
    protected Engine $templates;

    public function __construct(Engine $templates)
    {
        $this->templates = $templates;
    }

    protected function render(Response $response, string $template, array $data = []): Response
    {
        $html = $this->templates->render($template, $data);
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html');
    }

    protected function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    protected function redirect(Response $response, string $url, int $status = 302): Response
    {
        return $response
            ->withHeader('Location', $url)
            ->withStatus($status);
    }
}
