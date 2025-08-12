<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\TagService;
use App\Services\StoryService;
use App\Services\FeedService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use League\Plates\Engine;

class TagController extends BaseController
{
    public function __construct(
        Engine $templates,
        private TagService $tagService,
        private StoryService $storyService,
        private FeedService $feedService
    ) {
        parent::__construct($templates);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $tagName = $args['tag'];

        $tag = $this->tagService->getTagByName($tagName);
        if (!$tag) {
            return $this->render($response, 'tags/not-found', [
                'title' => 'Tag Not Found | Lobsters',
                'tag' => $tagName
            ]);
        }

        $stories = $this->storyService->getStoriesByTag($tagName);

        return $this->render($response, 'tags/show', [
            'title' => $tagName . ' | Lobsters',
            'tag' => $tagName,
            'tag_info' => [
                'tag' => $tag->tag,
                'description' => $tag->description,
                'privileged' => $tag->privileged,
                'is_media' => $tag->is_media,
            ],
            'stories' => $stories
        ]);
    }

    public function index(Request $request, Response $response): Response
    {
        $tags = $this->tagService->getAllTags();

        return $this->render($response, 'tags/index', [
            'title' => 'Tags | Lobsters',
            'tags' => $tags
        ]);
    }

    public function feed(Request $request, Response $response, array $args): Response
    {
        $tagName = $args['tag'];
        
        $tag = $this->tagService->getTagByName($tagName);
        if (!$tag) {
            $response->getBody()->write('<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>Tag Not Found</title></channel></rss>');
            return $response->withHeader('Content-Type', 'application/rss+xml; charset=utf-8');
        }
        
        $rssContent = $this->feedService->generateStoriesFeed($tagName);
        
        $response->getBody()->write($rssContent);
        return $response->withHeader('Content-Type', 'application/rss+xml; charset=utf-8');
    }
}
