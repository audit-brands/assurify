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
                'title' => 'Tag Not Found | Assurify',
                'tag' => $tagName
            ]);
        }

        $queryParams = $request->getQueryParams();
        $page = (int) ($queryParams['page'] ?? 1);
        $sort = $queryParams['sort'] ?? 'hottest';
        $timeframe = $queryParams['timeframe'] ?? 'all';

        $stories = $this->storyService->getStoriesByTag($tagName, $page, $sort, $timeframe);
        $tagStats = $this->tagService->getTagStats($tagName);
        $relatedTags = $this->tagService->getRelatedTags($tagName);

        return $this->render($response, 'tags/show', [
            'title' => $tagName . ' | Assurify',
            'tag' => $tagName,
            'tag_info' => [
                'tag' => $tag->tag,
                'description' => $tag->description,
                'privileged' => $tag->privileged,
                'is_media' => $tag->is_media,
            ],
            'stories' => $stories,
            'tag_stats' => $tagStats,
            'related_tags' => $relatedTags,
            'current_sort' => $sort,
            'current_timeframe' => $timeframe,
            'current_page' => $page
        ]);
    }

    public function index(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $sortBy = $queryParams['sort'] ?? 'stories';
        $searchQuery = $queryParams['q'] ?? '';

        $tags = $this->tagService->getAllTags($sortBy, $searchQuery);
        $trendingTags = $this->tagService->getTrendingTags();
        $tagCategories = $this->tagService->getTagCategories();

        return $this->render($response, 'tags/index', [
            'title' => 'Tags | Assurify',
            'tags' => $tags,
            'trending_tags' => $trendingTags,
            'tag_categories' => $tagCategories,
            'current_sort' => $sortBy,
            'search_query' => $searchQuery
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
