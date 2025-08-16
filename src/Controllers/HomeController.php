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
            
            // Get newest stories
            $stories = $this->storyService->getStoriesForListing('newest', $perPage, $offset, $excludeTagIds);
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
            'base_url' => '/newest'
        ];

        return $this->render($response, 'home/index', [
            'title' => 'Newest | Assurify',
            'section_header' => 'Newest Stories',
            'stories' => $stories,
            'pagination' => $pagination,
            'is_moderator' => $isModerator
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

    public function top(Request $request, Response $response, array $args): Response
    {
        // Get duration from route parameter (optional)
        $duration = $args['duration'] ?? null;
        
        // Validate duration and redirect to default if invalid/missing
        if (!$duration || !$this->isValidDuration($duration)) {
            return $this->redirect($response, '/top/1w');
        }
        
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
            
            // Get top stories with duration filtering
            $stories = $this->storyService->getStoriesForListing('top', $perPage, $offset, $excludeTagIds, $duration);
            
            // For total count, we need to count with the same filters
            $totalStories = $this->storyService->getTotalStories($excludeTagIds);
        } catch (\Exception $e) {
            $stories = [];
            $totalStories = 0;
        }

        // Build title based on duration (following Lobsters pattern)
        $durationText = $this->formatDurationText($duration);
        $title = "Top Stories of the Past $durationText | Assurify";
        $sectionHeader = "Top Stories of the Past $durationText";
        
        // Calculate pagination data
        $totalPages = (int) ceil($totalStories / $perPage);
        $baseUrl = "/top/$duration";
        $pagination = [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'has_prev' => $page > 1,
            'has_next' => $page < $totalPages,
            'base_url' => $baseUrl
        ];

        return $this->render($response, 'home/index', [
            'title' => $title,
            'section_header' => $sectionHeader,
            'stories' => $stories,
            'pagination' => $pagination,
            'is_moderator' => $isModerator,
            'duration' => $duration
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
    
    /**
     * Validate duration format (following Lobsters pattern)
     */
    private function isValidDuration(string $duration): bool
    {
        // Match pattern like 1d, 1w, 2w, 3m, 1y, etc.
        if (!preg_match('/^(\d+)([dwmy])$/', $duration, $matches)) {
            return false;
        }
        
        $amount = (int) $matches[1];
        $unit = $matches[2];
        
        // Duration must be positive
        if ($amount <= 0) {
            return false;
        }
        
        // Check reasonable limits
        switch ($unit) {
            case 'd': // days - up to 90 days
                return $amount <= 90;
            case 'w': // weeks - up to 52 weeks (1 year)
                return $amount <= 52;
            case 'm': // months - up to 24 months (2 years)
                return $amount <= 24;
            case 'y': // years - up to 10 years
                return $amount <= 10;
            default:
                return false;
        }
    }
    
    /**
     * Format duration for display (following Lobsters pattern)
     */
    private function formatDurationText(string $duration): string
    {
        if (!preg_match('/^(\d+)([dwmy])$/', $duration, $matches)) {
            return $duration;
        }
        
        $amount = (int) $matches[1];
        $unit = $matches[2];
        
        switch ($unit) {
            case 'd':
                return $amount === 1 ? 'Day' : "$amount Days";
            case 'w':
                return $amount === 1 ? 'Week' : "$amount Weeks";
            case 'm':
                return $amount === 1 ? 'Month' : "$amount Months";
            case 'y':
                return $amount === 1 ? 'Year' : "$amount Years";
            default:
                return $duration;
        }
    }
}
