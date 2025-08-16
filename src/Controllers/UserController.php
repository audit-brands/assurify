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

        // Get current user (viewer) for permission checks
        $viewer = null;
        if (isset($_SESSION['user_id'])) {
            $viewer = \App\Models\User::find($_SESSION['user_id']);
        }

        $userProfile = $this->userService->getUserProfile($username, $viewer);
        
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

        // Handle saved tab - check access permissions
        $savedStories = [];
        if ($tab === 'saved') {
            $viewerCanSee = false;
            if (isset($_SESSION['user_id'])) {
                $viewer = User::find($_SESSION['user_id']);
                $viewerCanSee = $viewer && ($viewer->id === $user->id || $viewer->is_admin);
            }

            if (!$viewerCanSee) {
                return $this->render($response, 'errors/403', [
                    'title' => 'Access Denied | Assurify',
                    'message' => 'You can only view your own saved stories.'
                ]);
            }

            // Get saved stories
            $savedStoriesData = $user->savedStories()
                                    ->with(['story'])
                                    ->orderBy('created_at', 'desc')
                                    ->get()
                                    ->pluck('story');
            $savedStories = $this->formatStoriesForView($savedStoriesData);
        }

        // Get user threads for threads tab
        $userThreads = [];
        if ($tab === 'threads') {
            $userThreads = $this->getUserThreads($user);
        }

        // Get user's top tags for the status section
        $userProfile['stats']['top_tags'] = $this->getUserTopTags($user);

        return $this->render($response, 'users/show', [
            'title' => $username . ' | Assurify',
            'user' => $userProfile,
            'tab' => $tab,
            'saved_stories' => $savedStories,
            'user_threads' => $userThreads,
            'current_user_id' => $_SESSION['user_id'] ?? null
        ]);
    }

    public function index(Request $request, Response $response): Response
    {
        $sortBy = $request->getQueryParams()['by'] ?? 'tree';
        
        if ($sortBy === 'karma') {
            // Show users sorted by karma
            $users = User::with(['invitedBy'])
                        ->orderBy('karma', 'desc')
                        ->limit(100)
                        ->get();
            
            $formattedUsers = $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'karma' => $user->karma,
                    'invited_by' => $user->invitedBy ? $user->invitedBy->username : null,
                    'is_new' => $this->isNewUser($user),
                    'is_admin' => $user->is_admin,
                    'is_moderator' => $user->is_moderator
                ];
            })->toArray();
            
            return $this->render($response, 'users/index', [
                'title' => 'Users by Karma | Assurify',
                'users' => $formattedUsers,
                'sort_by' => 'karma',
                'newest_users' => []
            ]);
        }
        
        // Default: Show invitation tree
        $newestUsers = $this->getNewestUsers();
        $userTree = $this->buildUserTree();
        $totalUsers = User::count();
        
        return $this->render($response, 'users/index', [
            'title' => 'Users | Assurify',
            'user_tree' => $userTree,
            'newest_users' => $newestUsers,
            'total_users' => $totalUsers,
            'sort_by' => 'tree'
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
            'username' => $data['username'] ?? '',
            'email' => $data['email'] ?? '',
            'show_avatars' => isset($data['show_avatars']),
            'show_story_previews' => isset($data['show_story_previews']),
            'show_read_ribbons' => isset($data['show_read_ribbons']),
            'hide_dragons' => isset($data['hide_dragons']),
            'show_email' => isset($data['show_email']),
            'homepage' => $data['homepage'] ?? '',
            'github_username' => $data['github_username'] ?? '',
            'twitter_username' => $data['twitter_username'] ?? '',
            'mastodon_username' => $data['mastodon_username'] ?? '',
            'linkedin_username' => $data['linkedin_username'] ?? '',
            'bluesky_username' => $data['bluesky_username'] ?? '',
            'about' => $data['about'] ?? '',
            'allow_messages_from' => $data['allow_messages_from'] ?? 'members'
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

        try {
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
        } catch (\InvalidArgumentException $e) {
            $_SESSION['settings_error'] = $e->getMessage();
        } catch (\Exception $e) {
            $_SESSION['settings_error'] = 'Failed to update settings. Please try again.';
        }

        return $this->redirect($response, '/settings');
    }

    public function stories(Request $request, Response $response, array $args): Response
    {
        $username = $args['username'];
        $page = max(1, (int) ($request->getQueryParams()['page'] ?? 1));
        $perPage = 25;
        
        // Get the user
        $user = $this->userService->getUserByUsername($username);
        if (!$user) {
            return $this->render($response, 'users/not-found', [
                'title' => 'User Not Found | Assurify',
                'username' => $username
            ]);
        }

        // Get user's submitted stories with pagination
        $offset = ($page - 1) * $perPage;
        $stories = \App\Models\Story::with(['user', 'tags'])
            ->where('user_id', $user->id)
            ->where('is_deleted', false)
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        // Get total count for pagination
        $totalStories = \App\Models\Story::where('user_id', $user->id)
            ->where('is_deleted', false)
            ->count();

        $totalPages = (int) ceil($totalStories / $perPage);

        // Format stories for display
        $formattedStories = $stories->map(function ($story) {
            return [
                'id' => $story->id,
                'title' => $story->title,
                'url' => $story->url,
                'short_id' => $story->short_id,
                'slug' => $this->generateSlug($story->title),
                'score' => $story->score ?? 0,
                'comments_count' => $story->comments_count ?? 0,
                'user' => [
                    'username' => $story->user->username ?? 'Unknown',
                    'id' => $story->user->id ?? null
                ],
                'tags' => $story->tags->map(function ($tag) {
                    return [
                        'tag' => $tag->tag,
                        'description' => $tag->description
                    ];
                })->toArray(),
                'created_at' => $story->created_at,
                'time_ago' => $this->timeAgo($story->created_at),
                'domain' => $this->extractDomain($story->url),
                'description' => $story->description,
                'is_ask' => empty($story->url),
                'can_edit' => ($_SESSION['user_id'] ?? null) === $story->user_id
            ];
        })->toArray();

        // Get user profile summary for header
        $userProfile = [
            'username' => $user->username,
            'karma' => $user->karma,
            'is_admin' => $user->is_admin ?? false,
            'is_moderator' => $user->is_moderator ?? false,
            'avatar_url' => $user->avatar_url ?? null
        ];

        return $this->render($response, 'users/stories', [
            'title' => $username . "'s Stories | Assurify",
            'user' => $userProfile,
            'stories' => $formattedStories,
            'page' => $page,
            'total_pages' => $totalPages,
            'total_stories' => $totalStories,
            'has_prev' => $page > 1,
            'has_next' => $page < $totalPages,
            'current_user_id' => $_SESSION['user_id'] ?? null
        ]);
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
    
    private function getNewestUsers(int $limit = 10): array
    {
        return User::with(['invitedBy'])
                  ->orderBy('id', 'desc')
                  ->limit($limit)
                  ->get()
                  ->map(function ($user) {
                      return [
                          'id' => $user->id,
                          'username' => $user->username,
                          'karma' => $user->karma,
                          'invited_by' => $user->invitedBy ? $user->invitedBy->username : null,
                          'is_new' => $this->isNewUser($user),
                          'is_admin' => $user->is_admin,
                          'is_moderator' => $user->is_moderator
                      ];
                  })
                  ->toArray();
    }
    
    private function isNewUser(User $user): bool
    {
        $seventyDaysAgo = new \DateTime();
        $seventyDaysAgo->sub(new \DateInterval('P70D'));
        return $user->created_at > $seventyDaysAgo;
    }
    
    private function buildUserTree(): array
    {
        // Get all users with their invitation relationships
        $users = User::with(['invitedBy'])
                    ->orderBy('karma', 'desc')
                    ->get();
        
        $userMap = [];
        $tree = [];
        
        // Create user map and format users
        foreach ($users as $user) {
            $formattedUser = [
                'id' => $user->id,
                'username' => $user->username,
                'karma' => $user->karma,
                'invited_by_user_id' => $user->invited_by_user_id,
                'invited_by' => $user->invitedBy ? $user->invitedBy->username : null,
                'is_new' => $this->isNewUser($user),
                'is_admin' => $user->is_admin,
                'is_moderator' => $user->is_moderator,
                'children' => []
            ];
            
            $userMap[$user->id] = $formattedUser;
        }
        
        // Build the tree structure
        foreach ($userMap as $user) {
            if ($user['invited_by_user_id']) {
                // This user was invited by someone, add to their children
                if (isset($userMap[$user['invited_by_user_id']])) {
                    $userMap[$user['invited_by_user_id']]['children'][] = &$userMap[$user['id']];
                } else {
                    // Invited by user not in our set, add to root
                    $tree[] = &$userMap[$user['id']];
                }
            } else {
                // Root user (not invited by anyone)
                $tree[] = &$userMap[$user['id']];
            }
        }
        
        // Sort tree by karma (highest first)
        usort($tree, function ($a, $b) {
            return $b['karma'] - $a['karma'];
        });
        
        // Recursively sort children by karma
        $this->sortTreeByKarma($tree);
        
        return $tree;
    }
    
    private function sortTreeByKarma(array &$tree): void
    {
        foreach ($tree as &$user) {
            if (!empty($user['children'])) {
                usort($user['children'], function ($a, $b) {
                    return $b['karma'] - $a['karma'];
                });
                $this->sortTreeByKarma($user['children']);
            }
        }
    }

    private function getUserThreads(User $user): array
    {
        // Get user's comments with replies to create threaded conversations
        $comments = \App\Models\Comment::with(['story', 'replies.user'])
            ->where('user_id', $user->id)
            ->where('is_deleted', false)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $threads = [];
        foreach ($comments as $comment) {
            // Get replies to this comment for threading
            $replies = $comment->replies()->with('user')
                ->where('is_deleted', false)
                ->orderBy('created_at', 'asc')
                ->limit(5)
                ->get();

            $threadReplies = [];
            foreach ($replies as $reply) {
                $threadReplies[] = [
                    'id' => $reply->id,
                    'short_id' => $reply->short_id,
                    'username' => $reply->user->username ?? 'Unknown',
                    'comment' => $reply->comment,
                    'markeddown_comment' => $reply->markeddown_comment,
                    'score' => $reply->score ?? 0,
                    'time_ago' => $this->timeAgo($reply->created_at)
                ];
            }

            $threads[] = [
                'id' => $comment->id,
                'short_id' => $comment->short_id,
                'comment' => $comment->comment,
                'markeddown_comment' => $comment->markeddown_comment,
                'score' => $comment->score ?? 0,
                'story_title' => $comment->story->title ?? 'Unknown Story',
                'story_short_id' => $comment->story->short_id ?? '',
                'story_slug' => $this->generateSlug($comment->story->title ?? ''),
                'time_ago' => $this->timeAgo($comment->created_at),
                'replies' => $threadReplies
            ];
        }

        return $threads;
    }

    private function getUserTopTags(User $user): array
    {
        try {
            // Get user's most used tags from their stories
            $topTags = \App\Models\Story::selectRaw('tags.tag as name, COUNT(*) as count')
                ->join('taggings', 'stories.id', '=', 'taggings.story_id')
                ->join('tags', 'taggings.tag_id', '=', 'tags.id')
                ->where('stories.user_id', $user->id)
                ->groupBy('tags.id', 'tags.tag')
                ->orderByRaw('COUNT(*) desc')
                ->limit(5)
                ->get();

            return $topTags->map(function ($tag) {
                return [
                    'name' => $tag->name,
                    'count' => $tag->count
                ];
            })->toArray();
        } catch (\Exception $e) {
            // Return empty array if query fails
            return [];
        }
    }

    private function timeAgo($date): string
    {
        if (is_string($date)) {
            $date = new \DateTime($date);
        } elseif (!$date instanceof \DateTime) {
            $date = new \DateTime($date);
        }
        
        $now = new \DateTime();
        $diff = $now->diff($date);

        if ($diff->y > 0) {
            return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
        } elseif ($diff->m > 0) {
            return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
        } elseif ($diff->d > 0) {
            return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
        } elseif ($diff->h > 0) {
            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        } elseif ($diff->i > 0) {
            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
        } else {
            return 'just now';
        }
    }
}
