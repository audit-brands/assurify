<?php

declare(strict_types=1);

namespace App\Controllers\Mod;

use App\Controllers\ModController;
use App\Models\Story;
use App\Models\Tag;
use App\Models\Moderation;
use App\Services\StoryService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class StoriesController extends ModController
{
    private function generateSlug(string $title): string
    {
        if (empty($title)) {
            return 'untitled';
        }
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9-]/', '_', $slug);
        $slug = preg_replace('/_+/', '_', $slug);
        $slug = trim($slug, '_');
        return substr($slug ?: 'untitled', 0, 50);
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        if (!$this->requireModerator()) {
            return $this->requireModeratorResponse($response);
        }

        $shortId = $args['short_id'] ?? null;
        if (!$shortId) {
            return $this->render($response, 'errors/404', [
                'title' => 'Story Not Found | Assurify'
            ])->withStatus(404);
        }

        try {
            $story = Story::where('short_id', $shortId)->firstOrFail();
            
            // Get all available tags for the dropdown
            $allTags = Tag::where('inactive', false)->orderBy('tag')->get();
            
            // Get moderation history for this story
            $moderations = Moderation::forStory($story)
                                   ->with('moderator')
                                   ->orderBy('created_at', 'desc')
                                   ->limit(20)
                                   ->get();

            return $this->render($response, 'mod/stories/edit', [
                'title' => 'Edit Story | Moderation | Assurify',
                'story' => $story,
                'all_tags' => $allTags,
                'moderations' => $moderations
            ]);
        } catch (\Exception $e) {
            return $this->render($response, 'errors/404', [
                'title' => 'Story Not Found | Assurify'
            ])->withStatus(404);
        }
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        if (!$this->requireModerator()) {
            return $this->requireModeratorJsonResponse($response);
        }

        $shortId = $args['short_id'] ?? null;
        if (!$shortId) {
            return $this->json($response, ['error' => 'Story not found'], 404);
        }

        try {
            $story = Story::where('short_id', $shortId)->firstOrFail();
            $data = $request->getParsedBody();
            
            // Handle JSON request body if getParsedBody returns null
            if ($data === null) {
                $jsonBody = (string) $request->getBody();
                $data = json_decode($jsonBody, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return $this->json($response, ['error' => 'Invalid JSON data'], 400);
                }
            }
            
            // Store original values for logging
            $originalTitle = $story->title;
            $originalDescription = $story->description;
            $originalUrl = $story->url;
            $originalTags = $story->tags->pluck('tag')->toArray();
            
            // Update story fields
            $story->title = trim($data['title'] ?? $story->title);
            $story->description = trim($data['description'] ?? '') ?: null;
            $story->url = trim($data['url'] ?? '') ?: null;
            $story->moderation_reason = trim($data['moderation_reason'] ?? '') ?: null;
            $story->is_unavailable = !empty($data['is_unavailable']);
            $story->updated_at = date('Y-m-d H:i:s');

            // Handle tag updates
            if (isset($data['tags']) && is_array($data['tags'])) {
                $tagNames = array_filter(array_map('trim', $data['tags']));
                $tags = Tag::whereIn('tag', $tagNames)->get();
                $story->tags()->sync($tags->pluck('id'));
            }

            // Handle merge functionality
            if (!empty($data['merge_story_short_id'])) {
                $mergeIntoStory = Story::where('short_id', trim($data['merge_story_short_id']))->first();
                if ($mergeIntoStory && $mergeIntoStory->id !== $story->id) {
                    $story->merged_into_story_id = $mergeIntoStory->id;
                    $story->is_deleted = true;
                }
            }

            $story->save();

            // Log the moderation action
            $changes = [];
            if ($originalTitle !== $story->title) {
                $changes['title'] = ['from' => $originalTitle, 'to' => $story->title];
            }
            if ($originalDescription !== $story->description) {
                $changes['description'] = ['from' => $originalDescription, 'to' => $story->description];
            }
            if ($originalUrl !== $story->url) {
                $changes['url'] = ['from' => $originalUrl, 'to' => $story->url];
            }
            
            $newTags = $story->tags->pluck('tag')->toArray();
            if ($originalTags !== $newTags) {
                $changes['tags'] = ['from' => $originalTags, 'to' => $newTags];
            }

            if (!empty($changes) || !empty($data['moderation_reason'])) {
                $this->logModerationAction(
                    $story->merged_into_story_id ? Moderation::ACTION_MERGED_STORY : Moderation::ACTION_EDITED_STORY,
                    $story,
                    $data['moderation_reason'] ?? null,
                    [
                        'changes' => $changes,
                        'merge_into' => $story->merged_into_story_id ? $data['merge_story_short_id'] : null
                    ]
                );
            }

            $slug = $this->generateSlug($story->title);
            return $this->json($response, [
                'success' => true,
                'message' => 'Story updated successfully',
                'redirect' => "/s/{$story->short_id}/{$slug}"
            ]);

        } catch (\Exception $e) {
            return $this->json($response, [
                'error' => 'Failed to update story: ' . $e->getMessage()
            ], 500);
        }
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        if (!$this->requireModerator()) {
            return $this->requireModeratorJsonResponse($response);
        }

        $shortId = $args['short_id'] ?? null;
        if (!$shortId) {
            return $this->json($response, ['error' => 'Story not found'], 404);
        }

        try {
            $story = Story::where('short_id', $shortId)->firstOrFail();
            $data = $request->getParsedBody();
            
            $moderationReason = trim($data['reason'] ?? '');
            
            // Require reason for deleting other users' stories
            $currentUser = $this->getCurrentUser();
            if ($story->user_id !== $currentUser->id && empty($moderationReason)) {
                return $this->json($response, [
                    'error' => 'Moderation reason is required when deleting other users\' stories'
                ], 400);
            }

            $story->is_deleted = true;
            $story->moderation_reason = $moderationReason ?: null;
            $story->updated_at = date('Y-m-d H:i:s');
            $story->save();

            // Log the moderation action
            $this->logModerationAction(
                Moderation::ACTION_DELETED_STORY,
                $story,
                $moderationReason,
                ['deleted_by_author' => $story->user_id === $currentUser->id]
            );

            $slug = $this->generateSlug($story->title);
            return $this->json($response, [
                'success' => true,
                'message' => 'Story deleted successfully',
                'redirect' => "/s/{$story->short_id}/{$slug}"
            ]);

        } catch (\Exception $e) {
            return $this->json($response, [
                'error' => 'Failed to delete story: ' . $e->getMessage()
            ], 500);
        }
    }

    public function undelete(Request $request, Response $response, array $args): Response
    {
        if (!$this->requireModerator()) {
            return $this->requireModeratorJsonResponse($response);
        }

        $shortId = $args['short_id'] ?? null;
        if (!$shortId) {
            return $this->json($response, ['error' => 'Story not found'], 404);
        }

        try {
            $story = Story::where('short_id', $shortId)->firstOrFail();
            $data = $request->getParsedBody();
            
            $moderationReason = trim($data['reason'] ?? '');

            $story->is_deleted = false;
            $story->moderation_reason = $moderationReason ?: null;
            $story->merged_into_story_id = null; // Unmerge if it was merged
            $story->updated_at = date('Y-m-d H:i:s');
            $story->save();

            // Log the moderation action
            $this->logModerationAction(
                Moderation::ACTION_UNDELETED_STORY,
                $story,
                $moderationReason
            );

            $slug = $this->generateSlug($story->title);
            return $this->json($response, [
                'success' => true,
                'message' => 'Story undeleted successfully',
                'redirect' => "/s/{$story->short_id}/{$slug}"
            ]);

        } catch (\Exception $e) {
            return $this->json($response, [
                'error' => 'Failed to undelete story: ' . $e->getMessage()
            ], 500);
        }
    }
}