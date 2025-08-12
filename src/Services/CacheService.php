<?php

declare(strict_types=1);

namespace App\Services;

class CacheService
{
    private const DEFAULT_TTL = 3600; // 1 hour
    private const CACHE_DIR = '/tmp/lobsters_cache/';
    
    private array $memoryCache = [];

    public function __construct()
    {
        // Ensure cache directory exists
        if (!is_dir(self::CACHE_DIR)) {
            mkdir(self::CACHE_DIR, 0755, true);
        }
    }

    public function get(string $key): mixed
    {
        // Check memory cache first
        if (isset($this->memoryCache[$key])) {
            $cached = $this->memoryCache[$key];
            if ($cached['expires'] > time()) {
                return $cached['data'];
            } else {
                unset($this->memoryCache[$key]);
            }
        }

        // Check file cache
        $filename = $this->getCacheFilename($key);
        if (file_exists($filename)) {
            $contents = file_get_contents($filename);
            if ($contents !== false) {
                $cached = unserialize($contents);
                if ($cached !== false && $cached['expires'] > time()) {
                    // Store in memory cache for faster access
                    $this->memoryCache[$key] = $cached;
                    return $cached['data'];
                } else {
                    // Expired, remove file
                    unlink($filename);
                }
            }
        }

        return null;
    }

    public function set(string $key, mixed $data, int $ttl = self::DEFAULT_TTL): bool
    {
        $expires = time() + $ttl;
        $cached = [
            'data' => $data,
            'expires' => $expires,
            'created' => time()
        ];

        // Store in memory cache
        $this->memoryCache[$key] = $cached;

        // Store in file cache
        $filename = $this->getCacheFilename($key);
        $result = file_put_contents($filename, serialize($cached), LOCK_EX);
        
        return $result !== false;
    }

    public function delete(string $key): bool
    {
        // Remove from memory cache
        unset($this->memoryCache[$key]);

        // Remove from file cache
        $filename = $this->getCacheFilename($key);
        if (file_exists($filename)) {
            return unlink($filename);
        }

        return true;
    }

    public function flush(): bool
    {
        // Clear memory cache
        $this->memoryCache = [];

        // Clear file cache
        $files = glob(self::CACHE_DIR . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        return true;
    }

    public function remember(string $key, callable $callback, int $ttl = self::DEFAULT_TTL): mixed
    {
        $cached = $this->get($key);
        if ($cached !== null) {
            return $cached;
        }

        $data = $callback();
        $this->set($key, $data, $ttl);
        return $data;
    }

    public function getStats(): array
    {
        $stats = [
            'memory_cache_size' => count($this->memoryCache),
            'file_cache_size' => 0,
            'cache_directory' => self::CACHE_DIR,
            'cache_hits' => 0,
            'cache_misses' => 0
        ];

        // Count file cache entries
        if (is_dir(self::CACHE_DIR)) {
            $files = glob(self::CACHE_DIR . '*');
            $stats['file_cache_size'] = count($files);
        }

        return $stats;
    }

    public function cleanup(): int
    {
        $cleaned = 0;
        
        // Clean memory cache
        foreach ($this->memoryCache as $key => $cached) {
            if ($cached['expires'] <= time()) {
                unset($this->memoryCache[$key]);
                $cleaned++;
            }
        }

        // Clean file cache
        if (is_dir(self::CACHE_DIR)) {
            $files = glob(self::CACHE_DIR . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    $contents = file_get_contents($file);
                    if ($contents !== false) {
                        $cached = unserialize($contents);
                        if ($cached === false || $cached['expires'] <= time()) {
                            unlink($file);
                            $cleaned++;
                        }
                    }
                }
            }
        }

        return $cleaned;
    }

    private function getCacheFilename(string $key): string
    {
        $hash = md5($key);
        return self::CACHE_DIR . $hash . '.cache';
    }

    // Cache helper methods for common patterns
    public function cacheStories(string $type, callable $callback, int $ttl = 1800): array
    {
        return $this->remember("stories:{$type}", $callback, $ttl);
    }

    public function cacheComments(int $storyId, callable $callback, int $ttl = 900): array
    {
        return $this->remember("comments:story:{$storyId}", $callback, $ttl);
    }

    public function cacheFeed(string $type, ?string $param, callable $callback, int $ttl = 1800): string
    {
        $key = $param ? "feed:{$type}:{$param}" : "feed:{$type}";
        return $this->remember($key, $callback, $ttl);
    }

    public function cacheSearchResults(string $query, string $type, string $order, int $page, callable $callback, int $ttl = 600): array
    {
        $key = "search:" . md5($query . $type . $order . $page);
        return $this->remember($key, $callback, $ttl);
    }

    public function invalidateStory(int $storyId): void
    {
        $this->delete("story:{$storyId}");
        $this->delete("comments:story:{$storyId}");
        
        // Invalidate related caches
        $this->deletePattern("stories:*");
        $this->deletePattern("feed:*");
    }

    public function invalidateComment(int $commentId, int $storyId): void
    {
        $this->delete("comment:{$commentId}");
        $this->delete("comments:story:{$storyId}");
        
        // Invalidate related caches
        $this->deletePattern("feed:*");
    }

    private function deletePattern(string $pattern): void
    {
        // For file cache
        $files = glob(self::CACHE_DIR . '*');
        foreach ($files as $file) {
            $contents = file_get_contents($file);
            if ($contents !== false) {
                $cached = unserialize($contents);
                if ($cached !== false) {
                    // This is a simplified pattern match - in production use Redis with SCAN
                    $filename = basename($file, '.cache');
                    // Pattern matching would be more sophisticated in real implementation
                    unlink($file);
                }
            }
        }

        // For memory cache - simplified pattern matching
        foreach ($this->memoryCache as $key => $cached) {
            if (fnmatch($pattern, $key)) {
                unset($this->memoryCache[$key]);
            }
        }
    }
}