<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\TagService;
use App\Services\ModerationService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use League\Plates\Engine;

class PageController extends BaseController
{
    public function __construct(
        Engine $templates,
        private TagService $tagService,
        private ModerationService $moderationService
    ) {
        parent::__construct($templates);
    }

    public function about(Request $request, Response $response): Response
    {
        return $this->render($response, 'pages/about', [
            'title' => 'About | Assurify'
        ]);
    }

    public function tags(Request $request, Response $response): Response
    {
        // Get all active tags with story counts
        $allTags = $this->tagService->getAllTags('story_count');
        
        // Group tags by category for better organization
        $categorizedTags = $this->groupTagsByCategory($allTags);
        
        // Get most popular tags
        $popularTags = array_slice($allTags, 0, 20);
        
        // Check if user is admin or moderator
        $canEdit = false;
        if (isset($_SESSION['user_id'])) {
            $user = \App\Models\User::find($_SESSION['user_id']);
            $canEdit = $user && (($user->is_admin ?? false) || ($user->is_moderator ?? false));
        }
        
        return $this->render($response, 'pages/tags', [
            'title' => 'Tags | Assurify',
            'tags' => $allTags,
            'categorized_tags' => $categorizedTags,
            'popular_tags' => $popularTags,
            'can_edit' => $canEdit
        ]);
    }

    public function updateTag(Request $request, Response $response, array $args): Response
    {
        // Check admin permission
        if (!isset($_SESSION['user_id'])) {
            return $this->json($response, ['error' => 'Not authenticated'], 401);
        }
        
        $user = \App\Models\User::find($_SESSION['user_id']);
        if (!$user || !(($user->is_admin ?? false) || ($user->is_moderator ?? false))) {
            return $this->json($response, ['error' => 'Access denied'], 403);
        }

        $data = $request->getParsedBody();
        $tagId = $args['id'] ?? null;
        
        if (!$tagId) {
            error_log("No tag ID found in route arguments");
            return $this->json($response, ['error' => 'Tag ID not found'], 400);
        }
        
        // Debug logging
        error_log("UpdateTag called - TagID: $tagId, Data: " . print_r($data, true));
        
        try {
            $tag = \App\Models\Tag::findOrFail($tagId);
            
            if (isset($data['description'])) {
                $oldDescription = $tag->description;
                $tag->description = trim($data['description']) ?: null;
                $tag->save();
                
                error_log("Tag updated - ID: $tagId, Old: '$oldDescription', New: '{$tag->description}'");
            }
            
            return $this->json($response, [
                'success' => true,
                'message' => 'Tag description updated successfully',
                'tag_id' => $tagId,
                'new_description' => $tag->description
            ]);
        } catch (\Exception $e) {
            error_log("Error updating tag $tagId: " . $e->getMessage());
            return $this->json($response, [
                'error' => 'Failed to update tag: ' . $e->getMessage()
            ], 500);
        }
    }
    
    private function groupTagsByCategory(array $tags): array
    {
        $categories = [
            'Programming Languages' => ['javascript', 'python', 'php', 'java', 'go', 'rust', 'c', 'cpp', 'nodejs', 'react', 'vue', 'angular'],
            'Security' => ['security', 'privacy', 'encryption', 'vulnerability', 'malware', 'audit', 'penetration', 'firewall'],
            'Infrastructure' => ['devops', 'docker', 'kubernetes', 'aws', 'azure', 'gcp', 'linux', 'unix', 'windows', 'cloud'],
            'Data & AI' => ['data', 'database', 'analytics', 'ml', 'ai', 'blockchain', 'api', 'bigdata', 'nosql', 'sql'],
            'Business' => ['business', 'startup', 'management', 'compliance', 'risk', 'finance', 'enterprise'],
            'Content Types' => ['video', 'pdf', 'slides', 'audio', 'book', 'research', 'tutorial', 'news']
        ];
        
        $categorized = [];
        $uncategorized = [];
        
        foreach ($tags as $tag) {
            $placed = false;
            foreach ($categories as $categoryName => $categoryTags) {
                if (in_array(strtolower($tag['tag']), $categoryTags)) {
                    $categorized[$categoryName][] = $tag;
                    $placed = true;
                    break;
                }
            }
            if (!$placed) {
                $uncategorized[] = $tag;
            }
        }
        
        if (!empty($uncategorized)) {
            $categorized['Other'] = $uncategorized;
        }
        
        return $categorized;
    }

    public function filter(Request $request, Response $response): Response
    {
        // Get user if logged in
        $user = null;
        if (isset($_SESSION['user_id'])) {
            $user = \App\Models\User::find($_SESSION['user_id']);
        }
        
        // Handle POST request to save filters
        if ($request->getMethod() === 'POST') {
            return $this->saveFilters($request, $response, $user);
        }
        
        // Get all tags with story counts
        $allTags = $this->tagService->getAllTags('story_count');
        
        // Group tags by category for better organization
        $categorizedTags = $this->groupTagsByCategory($allTags);
        
        // Get current user's filtered tags
        $filteredTags = $this->getFilteredTags($user);
        
        return $this->render($response, 'pages/filter', [
            'title' => 'Filtered Tags | Assurify',
            'tags' => $allTags,
            'categorized_tags' => $categorizedTags,
            'filtered_tags' => $filteredTags,
            'user' => $user
        ]);
    }
    
    private function saveFilters(Request $request, Response $response, $user): Response
    {
        $body = $request->getParsedBody();
        $selectedTags = $body['tags'] ?? [];
        
        if ($user) {
            // Save to database for logged-in users
            $this->saveUserFilters($user, array_keys($selectedTags));
        } else {
            // Save to cookie for non-logged-in users  
            $this->saveFilterCookie($response, array_keys($selectedTags));
        }
        
        // Redirect back to filter page with success message
        $_SESSION['flash_success'] = 'Your filters have been updated.';
        return $response->withHeader('Location', '/filter')->withStatus(302);
    }
    
    private function getFilteredTags($user): array
    {
        if ($user) {
            // Get from user's database filters
            return $this->getUserFilteredTags($user);
        } else {
            // Get from cookie
            return $this->getCookieFilteredTags();
        }
    }
    
    private function saveUserFilters($user, array $tagNames): void
    {
        // This would integrate with a user filters service
        // For now, we'll implement cookie-based filtering
        // TODO: Implement database storage for user filters
    }
    
    private function getUserFilteredTags($user): array
    {
        // This would get from database
        // For now, return empty array
        // TODO: Implement database retrieval for user filters
        return [];
    }
    
    private function saveFilterCookie(Response $response, array $tagNames): void
    {
        $cookieValue = implode(',', $tagNames);
        setcookie('tag_filters', $cookieValue, time() + (365 * 24 * 60 * 60), '/'); // 1 year
    }
    
    private function getCookieFilteredTags(): array
    {
        $cookieValue = $_COOKIE['tag_filters'] ?? '';
        if (empty($cookieValue)) {
            return [];
        }
        
        $tagNames = explode(',', $cookieValue);
        $tags = $this->tagService->getTagsByNames($tagNames);
        
        $filteredTags = [];
        foreach ($tags as $tag) {
            $filteredTags[$tag['id']] = $tag;
        }
        
        return $filteredTags;
    }

    public function moderationLog(Request $request, Response $response): Response
    {
        // Get page parameter
        $page = (int) ($request->getQueryParams()['page'] ?? 1);
        $perPage = 50;
        
        $moderations = $this->moderationService->getModerationLog($page, $perPage);
        $totalModerations = $this->moderationService->getTotalModerationCount();
        $totalPages = (int) ceil($totalModerations / $perPage);
        
        return $this->render($response, 'pages/moderation-log', [
            'title' => 'Moderation Log | Assurify',
            'moderations' => $moderations,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_count' => $totalModerations
        ]);
    }
}