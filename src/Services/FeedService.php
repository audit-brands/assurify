<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Story;
use App\Models\Comment;
use App\Models\Tag;

class FeedService
{
    private const FEED_LIMIT = 50;

    public function generateStoriesFeed(?string $tag = null): string
    {
        $stories = $this->getStoriesForFeed($tag);
        
        $title = 'Lobsters';
        $description = 'Latest stories from Lobsters';
        $link = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        if ($tag) {
            $title .= ": {$tag}";
            $description = "Latest stories tagged with '{$tag}' from Lobsters";
        }

        return $this->buildRssFeed($title, $description, $link, $stories, 'story');
    }

    public function generateCommentsFeed(): string
    {
        $comments = $this->getCommentsForFeed();
        
        $title = 'Lobsters: Comments';
        $description = 'Latest comments from Lobsters';
        $link = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $this->buildRssFeed($title, $description, $link, $comments, 'comment');
    }

    public function generateUserActivityFeed(string $username): string
    {
        $activities = $this->getUserActivityForFeed($username);
        
        $title = "Lobsters: {$username}'s activity";
        $description = "Latest activity from {$username} on Lobsters";
        $link = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $this->buildRssFeed($title, $description, $link, $activities, 'activity');
    }

    private function getStoriesForFeed(?string $tag = null): array
    {
        $query = Story::with(['user', 'tags'])
            ->orderBy('created_at', 'desc')
            ->limit(self::FEED_LIMIT);

        if ($tag) {
            $query->whereHas('tags', function ($q) use ($tag) {
                $q->where('tag', $tag);
            });
        }

        try {
            return $query->get()->map(function ($story) {
                return [
                    'type' => 'story',
                    'id' => $story->id,
                    'short_id' => $story->short_id,
                    'title' => $story->title,
                    'description' => $story->description,
                    'url' => $story->url,
                    'score' => $story->score,
                    'user' => $story->user->username ?? 'Anonymous',
                    'tags' => $story->tags->pluck('tag')->toArray(),
                    'created_at' => $story->created_at,
                    'comment_count' => $story->comments_count ?? 0,
                    'domain' => $this->extractDomain($story->url)
                ];
            })->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getCommentsForFeed(): array
    {
        try {
            return Comment::with(['user', 'story'])
                ->where('is_deleted', false)
                ->orderBy('created_at', 'desc')
                ->limit(self::FEED_LIMIT)
                ->get()
                ->map(function ($comment) {
                    return [
                        'type' => 'comment',
                        'id' => $comment->id,
                        'short_id' => $comment->short_id,
                        'comment' => $comment->comment,
                        'score' => $comment->score,
                        'user' => $comment->user->username ?? 'Anonymous',
                        'story' => [
                            'id' => $comment->story->id,
                            'short_id' => $comment->story->short_id,
                            'title' => $comment->story->title
                        ],
                        'created_at' => $comment->created_at
                    ];
                })->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getUserActivityForFeed(string $username): array
    {
        $activities = [];

        try {
            // Get user stories
            $stories = Story::with(['user', 'tags'])
                ->whereHas('user', function ($q) use ($username) {
                    $q->where('username', $username);
                })
                ->orderBy('created_at', 'desc')
                ->limit(25)
                ->get();

            foreach ($stories as $story) {
                $activities[] = [
                    'type' => 'story',
                    'id' => $story->id,
                    'short_id' => $story->short_id,
                    'title' => $story->title,
                    'description' => $story->description,
                    'url' => $story->url,
                    'score' => $story->score,
                    'user' => $story->user->username ?? 'Anonymous',
                    'tags' => $story->tags->pluck('tag')->toArray(),
                    'created_at' => $story->created_at,
                    'domain' => $this->extractDomain($story->url)
                ];
            }

            // Get user comments
            $comments = Comment::with(['user', 'story'])
                ->whereHas('user', function ($q) use ($username) {
                    $q->where('username', $username);
                })
                ->where('is_deleted', false)
                ->orderBy('created_at', 'desc')
                ->limit(25)
                ->get();

            foreach ($comments as $comment) {
                $activities[] = [
                    'type' => 'comment',
                    'id' => $comment->id,
                    'short_id' => $comment->short_id,
                    'comment' => $comment->comment,
                    'score' => $comment->score,
                    'user' => $comment->user->username ?? 'Anonymous',
                    'story' => [
                        'id' => $comment->story->id,
                        'short_id' => $comment->story->short_id,
                        'title' => $comment->story->title
                    ],
                    'created_at' => $comment->created_at
                ];
            }

            // Sort by date
            usort($activities, function ($a, $b) {
                return $b['created_at'] <=> $a['created_at'];
            });

            return array_slice($activities, 0, self::FEED_LIMIT);
        } catch (\Exception $e) {
            return [];
        }
    }

    private function buildRssFeed(string $title, string $description, string $link, array $items, string $type): string
    {
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        // Root RSS element
        $rss = $xml->createElement('rss');
        $rss->setAttribute('version', '2.0');
        $rss->setAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
        $xml->appendChild($rss);

        // Channel element
        $channel = $xml->createElement('channel');
        $rss->appendChild($channel);

        // Channel metadata
        $channel->appendChild($xml->createElement('title', htmlspecialchars($title)));
        $channel->appendChild($xml->createElement('link', 'https://' . $link));
        $channel->appendChild($xml->createElement('description', htmlspecialchars($description)));
        $channel->appendChild($xml->createElement('language', 'en-us'));
        $channel->appendChild($xml->createElement('ttl', '60'));

        if (!empty($items)) {
            $pubDate = $items[0]['created_at']->format('r');
            $channel->appendChild($xml->createElement('pubDate', $pubDate));
        }

        // Self-link
        $atomLink = $xml->createElement('atom:link');
        $atomLink->setAttribute('href', 'https://' . $link . $_SERVER['REQUEST_URI']);
        $atomLink->setAttribute('rel', 'self');
        $atomLink->setAttribute('type', 'application/rss+xml');
        $channel->appendChild($atomLink);

        // Add items
        foreach ($items as $item) {
            $itemElement = $xml->createElement('item');
            
            if ($item['type'] === 'story') {
                $this->addStoryToFeed($xml, $itemElement, $item, $link);
            } elseif ($item['type'] === 'comment') {
                $this->addCommentToFeed($xml, $itemElement, $item, $link);
            }
            
            $channel->appendChild($itemElement);
        }

        return $xml->saveXML();
    }

    private function addStoryToFeed(\DOMDocument $xml, \DOMElement $item, array $story, string $link): void
    {
        $item->appendChild($xml->createElement('title', htmlspecialchars($story['title'])));
        
        $storyUrl = 'https://' . $link . '/s/' . $story['short_id'];
        $item->appendChild($xml->createElement('link', $storyUrl));
        $item->appendChild($xml->createElement('guid', $storyUrl));
        
        $author = htmlspecialchars($story['user']);
        if ($story['domain']) {
            $author = htmlspecialchars($story['domain']) . ' via ' . $author;
        }
        $item->appendChild($xml->createElement('author', $author));
        
        $item->appendChild($xml->createElement('pubDate', $story['created_at']->format('r')));
        
        // Description
        $description = '';
        if ($story['description']) {
            $description = htmlspecialchars($story['description']);
        }
        
        if ($story['url']) {
            $description .= '<p><a href="' . htmlspecialchars($story['url']) . '">External Link</a></p>';
        }
        
        $description .= '<p><a href="' . $storyUrl . '">Comments (' . $story['comment_count'] . ')</a></p>';
        
        $item->appendChild($xml->createElement('description', $description));
        
        // Categories (tags)
        foreach ($story['tags'] as $tag) {
            $item->appendChild($xml->createElement('category', htmlspecialchars($tag)));
        }
    }

    private function addCommentToFeed(\DOMDocument $xml, \DOMElement $item, array $comment, string $link): void
    {
        $title = 'Comment on: ' . $comment['story']['title'];
        $item->appendChild($xml->createElement('title', htmlspecialchars($title)));
        
        $commentUrl = 'https://' . $link . '/s/' . $comment['story']['short_id'] . '#comment-' . $comment['short_id'];
        $item->appendChild($xml->createElement('link', $commentUrl));
        $item->appendChild($xml->createElement('guid', $commentUrl));
        
        $author = htmlspecialchars($comment['user']);
        $item->appendChild($xml->createElement('author', $author));
        
        $item->appendChild($xml->createElement('pubDate', $comment['created_at']->format('r')));
        
        $description = htmlspecialchars($comment['comment']);
        $item->appendChild($xml->createElement('description', $description));
    }

    private function extractDomain(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        $parsed = parse_url($url);
        return $parsed['host'] ?? null;
    }

    public function getAvailableTagFeeds(): array
    {
        try {
            return Tag::orderBy('tag')
                ->limit(50)
                ->pluck('tag')
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }
}