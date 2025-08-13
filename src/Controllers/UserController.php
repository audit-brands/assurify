<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\FeedService;
use App\Services\UserService;
use App\Services\TagService;
use App\Models\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use League\Plates\Engine;

class UserController extends BaseController
{
    public function __construct(
        Engine $templates,
        private FeedService $feedService,
        private UserService $userService,
        private TagService $tagService
    ) {
        parent::__construct($templates);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $username = $args['username'];
        $tab = $request->getQueryParams()['tab'] ?? 'stories';

        $userProfile = $this->userService->getUserProfile($username);
        
        if (!$userProfile) {
            return $this->render($response, 'users/not-found', [
                'title' => 'User Not Found | Assurify',
                'username' => $username
            ]);
        }

        // Update karma if needed (this could be moved to a background job)
        $user = $this->userService->getUserByUsername($username);
        $this->userService->updateUserKarma($user);
        $userProfile['karma'] = $user->fresh()->karma;

        return $this->render($response, 'users/show', [
            'title' => $username . ' | Assurify',
            'user' => $userProfile,
            'tab' => $tab,
            'current_user_id' => $_SESSION['user_id'] ?? null
        ]);
    }

    public function index(Request $request, Response $response): Response
    {
        // Get top users by karma
        $topUsers = User::orderBy('karma', 'desc')
                       ->where('karma', '>', 0)
                       ->limit(50)
                       ->get();

        $formattedUsers = $topUsers->map(function ($user) {
            return [
                'username' => $user->username,
                'karma' => $user->karma,
                'created_at_formatted' => $user->created_at ? $user->created_at->format('M j, Y') : 'Unknown',
                'is_admin' => $user->is_admin,
                'is_moderator' => $user->is_moderator,
                'hats' => $this->userService->getUserHats($user)
            ];
        });

        return $this->render($response, 'users/index', [
            'title' => 'Users | Assurify',
            'users' => $formattedUsers
        ]);
    }

    public function feed(Request $request, Response $response, array $args): Response
    {
        $username = $args['username'];
        
        $rssContent = $this->feedService->generateUserActivityFeed($username);
        
        $response->getBody()->write($rssContent);
        return $response->withHeader('Content-Type', 'application/rss+xml; charset=utf-8');
    }

    public function settings(Request $request, Response $response): Response
    {
        // Require authentication
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['auth_redirect'] = '/settings';
            return $this->redirect($response, '/auth/login');
        }

        $user = User::find($_SESSION['user_id']);
        if (!$user) {
            return $this->redirect($response, '/auth/login');
        }

        $settings = $this->userService->getUserSettings($user);
        $tagPreferences = $this->userService->getUserTagPreferences($user);
        $allTags = $this->tagService->getAllTags('alphabetical');
        
        // Get any saved messages
        $success = $_SESSION['settings_success'] ?? null;
        $error = $_SESSION['settings_error'] ?? null;
        unset($_SESSION['settings_success'], $_SESSION['settings_error']);

        return $this->render($response, 'users/settings', [
            'title' => 'Settings | Assurify',
            'user' => $user,
            'settings' => $settings,
            'tag_preferences' => $tagPreferences,
            'all_tags' => $allTags,
            'success' => $success,
            'error' => $error
        ]);
    }

    public function updateSettings(Request $request, Response $response): Response
    {
        // Require authentication
        if (!isset($_SESSION['user_id'])) {
            return $this->redirect($response, '/auth/login');
        }

        $user = User::find($_SESSION['user_id']);
        if (!$user) {
            return $this->redirect($response, '/auth/login');
        }

        $data = $request->getParsedBody();
        
        // Convert checkbox values to booleans
        $settings = [
            'show_avatars' => isset($data['show_avatars']),
            'show_story_previews' => isset($data['show_story_previews']),
            'show_read_ribbons' => isset($data['show_read_ribbons']),
            'hide_dragons' => isset($data['hide_dragons']),
            'show_email' => isset($data['show_email']),
            'homepage' => $data['homepage'] ?? '',
            'github_username' => $data['github_username'] ?? '',
            'twitter_username' => $data['twitter_username'] ?? '',
            'about' => $data['about'] ?? ''
        ];

        // Handle tag preferences
        $filteredTags = [];
        $favoriteTags = [];
        
        if (!empty($data['filtered_tags'])) {
            $filteredTags = explode(',', $data['filtered_tags']);
            $filteredTags = array_map('trim', $filteredTags);
            $filteredTags = array_filter($filteredTags);
        }
        
        if (!empty($data['favorite_tags'])) {
            $favoriteTags = explode(',', $data['favorite_tags']);
            $favoriteTags = array_map('trim', $favoriteTags);
            $favoriteTags = array_filter($favoriteTags);
        }

        $settingsUpdated = $this->userService->updateUserSettings($user, $settings);
        $tagPrefsUpdated = $this->userService->updateUserTagPreferences($user, $filteredTags, $favoriteTags);

        if ($settingsUpdated && $tagPrefsUpdated) {
            $_SESSION['settings_success'] = 'Settings and tag preferences updated successfully!';
        } elseif ($settingsUpdated) {
            $_SESSION['settings_success'] = 'Settings updated successfully, but tag preferences failed to update.';
        } elseif ($tagPrefsUpdated) {
            $_SESSION['settings_success'] = 'Tag preferences updated successfully, but settings failed to update.';
        } else {
            $_SESSION['settings_error'] = 'Failed to update settings. Please try again.';
        }

        return $this->redirect($response, '/settings');
    }

    public function saved(Request $request, Response $response, array $args): Response
    {
        $username = $args['username'];
        
        // Check if user can view saved stories (own profile or admin)
        $viewerCanSee = false;
        if (isset($_SESSION['user_id'])) {
            $viewer = User::find($_SESSION['user_id']);
            $profileUser = $this->userService->getUserByUsername($username);
            
            $viewerCanSee = $viewer && $profileUser && 
                           ($viewer->id === $profileUser->id || $viewer->is_admin);
        }

        if (!$viewerCanSee) {
            return $this->render($response, 'errors/403', [
                'title' => 'Access Denied | Assurify',
                'message' => 'You can only view your own saved stories.'
            ]);
        }

        $profileUser = $this->userService->getUserByUsername($username);
        $savedStories = $profileUser->savedStories()
                                  ->with(['story'])
                                  ->orderBy('created_at', 'desc')
                                  ->get()
                                  ->pluck('story');

        return $this->render($response, 'users/saved', [
            'title' => $username . "'s Saved Stories | Assurify",
            'user' => ['username' => $username],
            'stories' => $this->formatStoriesForView($savedStories)
        ]);
    }

    private function formatStoriesForView($stories): array
    {
        if (empty($stories)) {
            return [];
        }

        return $stories->map(function ($story) {
            return [
                'id' => $story->id,
                'title' => $story->title,
                'url' => $story->url,
                'short_id' => $story->short_id,
                'slug' => $this->generateSlug($story->title),
                'score' => $story->score ?? 0,
                'comments_count' => $story->comments_count ?? 0,
                'username' => $story->user ? $story->user->username : 'Unknown',
                'created_at_formatted' => $story->created_at ? $story->created_at->format('M j, Y') : 'Unknown',
                'domain' => $this->extractDomain($story->url)
            ];
        })->toArray();
    }

    private function generateSlug(string $title): string
    {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9-]/', '_', $slug);
        $slug = preg_replace('/_+/', '_', $slug);
        $slug = trim($slug, '_');
        return substr($slug ?: 'untitled', 0, 50);
    }

    private function extractDomain(?string $url): string
    {
        if (!$url) {
            return '';
        }
        
        $parsed = parse_url($url);
        return $parsed['host'] ?? '';
    }
}
