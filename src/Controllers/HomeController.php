<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\StoryService;
use App\Services\FeedService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use League\Plates\Engine;

class HomeController extends BaseController
{
    public function __construct(
        Engine $templates,
        private StoryService $storyService,
        private FeedService $feedService
    ) {
        parent::__construct($templates);
    }

    public function index(Request $request, Response $response): Response
    {
        // Get page parameter
        $page = (int) ($request->getQueryParams()['page'] ?? 1);
        if ($page < 1) $page = 1;
        
        $perPage = 25;
        $offset = ($page - 1) * $perPage;
        
        try {
            // Get user and filtered tags
            $user = null;
            $isModerator = false;
            if (isset($_SESSION['user_id'])) {
                $user = \App\Models\User::find($_SESSION['user_id']);
                $isModerator = $user && ($user->is_admin || $user->is_moderator);
            }
            $excludeTagIds = $this->getFilteredTagIds($user);
            
            // Get stories from database with pagination, excluding filtered tags
            $stories = $this->storyService->getStories($perPage, $offset, 'hot', $excludeTagIds);
            $totalStories = $this->storyService->getTotalStories($excludeTagIds);
        } catch (\Exception $e) {
            // Fallback to empty array if database issues
            $stories = [];
            $totalStories = 0;
        }

        $success = $_SESSION['story_success'] ?? null;
        if ($success) {
            unset($_SESSION['story_success']); // Clear after showing
        }

        // Check if this is the /active route to set appropriate title
        $path = $request->getUri()->getPath();
        $title = ($path === '/active') ? 'Active | Assurify' : 'Assurify';
        
        // Set section header - both homepage and /active should show "Active Stories"
        $sectionHeader = 'Active Stories';

        // Calculate pagination data
        $totalPages = (int) ceil($totalStories / $perPage);
        $pagination = [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'has_prev' => $page > 1,
            'has_next' => $page < $totalPages,
            'base_url' => ($path === '/active') ? '/active' : '/'
        ];

        return $this->render($response, 'home/index', [
            'title' => $title,
            'section_header' => $sectionHeader,
            'stories' => $stories,
            'success' => $success,
            'pagination' => $pagination,
            'is_moderator' => $isModerator
        ]);
    }

    public function newest(Request $request, Response $response): Response
    {
        try {
            $stories = $this->storyService->getNewestStories();
        } catch (\Exception $e) {
            $stories = [];
        }

        return $this->render($response, 'home/newest', [
            'title' => 'Newest | Assurify',
            'stories' => $stories
        ]);
    }

    public function recent(Request $request, Response $response): Response
    {
        // Get page parameter
        $page = (int) ($request->getQueryParams()['page'] ?? 1);
        if ($page < 1) $page = 1;
        
        $perPage = 25;
        $offset = ($page - 1) * $perPage;
        
        try {
            // Get user and filtered tags
            $user = null;
            if (isset($_SESSION['user_id'])) {
                $user = \App\Models\User::find($_SESSION['user_id']);
            }
            $excludeTagIds = $this->getFilteredTagIds($user);
            
            $stories = $this->storyService->getStories($perPage, $offset, 'recent', $excludeTagIds);
            $totalStories = $this->storyService->getTotalStories($excludeTagIds);
        } catch (\Exception $e) {
            $stories = [];
            $totalStories = 0;
        }

        // Calculate pagination data
        $totalPages = (int) ceil($totalStories / $perPage);
        $pagination = [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'has_prev' => $page > 1,
            'has_next' => $page < $totalPages,
            'base_url' => '/recent'
        ];

        return $this->render($response, 'home/recent', [
            'title' => 'Recent | Assurify',
            'stories' => $stories,
            'pagination' => $pagination
        ]);
    }

    public function top(Request $request, Response $response): Response
    {
        try {
            $stories = $this->storyService->getTopStories();
        } catch (\Exception $e) {
            $stories = [];
        }

        return $this->render($response, 'home/top', [
            'title' => 'Top | Assurify',
            'stories' => $stories
        ]);
    }


    public function storiesFeed(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $tag = $queryParams['tag'] ?? null;
        
        $rssContent = $this->feedService->generateStoriesFeed($tag);
        
        $response->getBody()->write($rssContent);
        return $response->withHeader('Content-Type', 'application/rss+xml; charset=utf-8');
    }

    private function getFilteredTagIds($user): array
    {
        if ($user) {
            // Get from user's database filters
            // TODO: Implement database filtering for logged users
            return [];
        } else {
            // Get from cookie
            return $this->getCookieFilteredTagIds();
        }
    }
    
    private function getCookieFilteredTagIds(): array
    {
        $cookieValue = $_COOKIE['tag_filters'] ?? '';
        if (empty($cookieValue)) {
            return [];
        }
        
        $tagNames = explode(',', $cookieValue);
        $tags = $this->tagService->getTagsByNames($tagNames);
        
        return array_column($tags, 'id');
    }
}
