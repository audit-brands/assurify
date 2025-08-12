<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Story;
use App\Models\Comment;

class AdminService
{
    public function __construct(
        private PerformanceService $performanceService,
        private CacheService $cacheService
    ) {}

    public function getSystemOverview(): array
    {
        return [
            'system_info' => $this->getSystemInfo(),
            'application_stats' => $this->getApplicationStats(),
            'performance_metrics' => $this->performanceService->getMetrics(),
            'cache_stats' => $this->cacheService->getStats(),
            'health_checks' => $this->runHealthChecks(),
            'recent_activity' => $this->getRecentActivity()
        ];
    }

    public function getSystemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'os' => PHP_OS,
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'timezone' => date_default_timezone_get(),
            'current_time' => date('Y-m-d H:i:s'),
            'uptime' => $this->getUptime(),
            'disk_space' => $this->getDiskSpace()
        ];
    }

    public function getApplicationStats(): array
    {
        try {
            $stats = [
                'total_users' => User::count(),
                'total_stories' => Story::count(),
                'total_comments' => Comment::where('is_deleted', false)->count(),
                'active_users_24h' => $this->getActiveUsers(24),
                'active_users_7d' => $this->getActiveUsers(168),
                'stories_today' => $this->getStoriesInTimeframe(24),
                'comments_today' => $this->getCommentsInTimeframe(24),
                'top_tags' => $this->getTopTags(),
                'database_size' => $this->getDatabaseSize()
            ];
        } catch (\Exception $e) {
            $stats = [
                'total_users' => 0,
                'total_stories' => 0,
                'total_comments' => 0,
                'active_users_24h' => 0,
                'active_users_7d' => 0,
                'stories_today' => 0,
                'comments_today' => 0,
                'top_tags' => [],
                'database_size' => 'N/A',
                'error' => 'Database connection failed'
            ];
        }

        return $stats;
    }

    public function runHealthChecks(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'file_permissions' => $this->checkFilePermissions(),
            'memory_usage' => $this->checkMemoryUsage(),
            'disk_space' => $this->checkDiskSpace(),
            'php_extensions' => $this->checkPhpExtensions(),
            'configuration' => $this->checkConfiguration()
        ];

        $overall = 'healthy';
        foreach ($checks as $check) {
            if ($check['status'] === 'error') {
                $overall = 'error';
                break;
            } elseif ($check['status'] === 'warning' && $overall === 'healthy') {
                $overall = 'warning';
            }
        }

        return [
            'overall' => $overall,
            'checks' => $checks,
            'last_check' => time()
        ];
    }

    public function getRecentActivity(): array
    {
        try {
            return [
                'recent_stories' => Story::with('user')
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get()
                    ->map(function ($story) {
                        return [
                            'id' => $story->id,
                            'title' => $story->title,
                            'user' => $story->user->username ?? 'Unknown',
                            'created_at' => $story->created_at,
                            'score' => $story->score
                        ];
                    })->toArray(),
                
                'recent_comments' => Comment::with(['user', 'story'])
                    ->where('is_deleted', false)
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get()
                    ->map(function ($comment) {
                        return [
                            'id' => $comment->id,
                            'comment' => substr($comment->comment, 0, 100) . '...',
                            'user' => $comment->user->username ?? 'Unknown',
                            'story_title' => $comment->story->title ?? 'Unknown',
                            'created_at' => $comment->created_at,
                            'score' => $comment->score
                        ];
                    })->toArray(),
                
                'recent_users' => User::orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get()
                    ->map(function ($user) {
                        return [
                            'id' => $user->id,
                            'username' => $user->username,
                            'email' => $user->email,
                            'created_at' => $user->created_at,
                            'is_active' => $user->is_active ?? true
                        ];
                    })->toArray()
            ];
        } catch (\Exception $e) {
            return [
                'recent_stories' => [],
                'recent_comments' => [],
                'recent_users' => [],
                'error' => 'Failed to fetch recent activity'
            ];
        }
    }

    public function cleanupSystem(): array
    {
        $results = [];
        
        // Clean cache
        $results['cache_cleanup'] = $this->cacheService->cleanup();
        
        // Clean temporary files
        $results['temp_cleanup'] = $this->cleanupTempFiles();
        
        // Clean old performance metrics
        $results['metrics_cleanup'] = $this->cleanupOldMetrics();
        
        // Clean old logs (if implemented)
        $results['logs_cleanup'] = $this->cleanupOldLogs();
        
        return $results;
    }

    public function exportSystemData(): array
    {
        try {
            return [
                'users' => User::count(),
                'stories' => Story::count(),
                'comments' => Comment::where('is_deleted', false)->count(),
                'export_timestamp' => time(),
                'format_version' => '1.0',
                'application_version' => '1.0.0'
            ];
        } catch (\Exception $e) {
            return [
                'error' => 'Export failed: ' . $e->getMessage(),
                'export_timestamp' => time()
            ];
        }
    }

    private function getUptime(): int
    {
        // Simple uptime calculation
        $startFile = '/tmp/lobsters_start_time';
        if (!file_exists($startFile)) {
            file_put_contents($startFile, time());
        }
        
        $startTime = (int) file_get_contents($startFile);
        return time() - $startTime;
    }

    private function getDiskSpace(): array
    {
        $total = disk_total_space('/');
        $free = disk_free_space('/');
        
        return [
            'total' => $total,
            'free' => $free,
            'used' => $total - $free,
            'usage_percentage' => $total > 0 ? (($total - $free) / $total) * 100 : 0,
            'total_formatted' => $this->formatBytes($total),
            'free_formatted' => $this->formatBytes($free),
            'used_formatted' => $this->formatBytes($total - $free)
        ];
    }

    private function getActiveUsers(int $hours): int
    {
        try {
            $cutoff = new \DateTime();
            $cutoff->modify("-{$hours} hours");
            
            // This would need a last_activity field in the users table
            // For now, return a placeholder
            return User::where('created_at', '>=', $cutoff)->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getStoriesInTimeframe(int $hours): int
    {
        try {
            $cutoff = new \DateTime();
            $cutoff->modify("-{$hours} hours");
            
            return Story::where('created_at', '>=', $cutoff)->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getCommentsInTimeframe(int $hours): int
    {
        try {
            $cutoff = new \DateTime();
            $cutoff->modify("-{$hours} hours");
            
            return Comment::where('created_at', '>=', $cutoff)
                ->where('is_deleted', false)
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getTopTags(): array
    {
        try {
            // This would need proper tag counting
            return [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getDatabaseSize(): string
    {
        try {
            // This would require database-specific queries
            return 'N/A';
        } catch (\Exception $e) {
            return 'Error';
        }
    }

    private function checkDatabase(): array
    {
        try {
            User::count();
            return [
                'status' => 'healthy',
                'message' => 'Database connection successful',
                'response_time' => 0.1 // Would measure actual response time
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Database connection failed: ' . $e->getMessage(),
                'response_time' => null
            ];
        }
    }

    private function checkCache(): array
    {
        try {
            $testKey = 'health_check_' . time();
            $testValue = 'test_value';
            
            $this->cacheService->set($testKey, $testValue, 60);
            $retrieved = $this->cacheService->get($testKey);
            $this->cacheService->delete($testKey);
            
            if ($retrieved === $testValue) {
                return [
                    'status' => 'healthy',
                    'message' => 'Cache is working properly'
                ];
            } else {
                return [
                    'status' => 'warning',
                    'message' => 'Cache test failed'
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Cache error: ' . $e->getMessage()
            ];
        }
    }

    private function checkFilePermissions(): array
    {
        $paths = [
            '/tmp/lobsters_cache/' => 'Cache directory',
            '/tmp/' => 'Temp directory'
        ];
        
        $issues = [];
        foreach ($paths as $path => $description) {
            if (!is_writable($path)) {
                $issues[] = "{$description} ({$path}) is not writable";
            }
        }
        
        if (empty($issues)) {
            return [
                'status' => 'healthy',
                'message' => 'All required directories are writable'
            ];
        } else {
            return [
                'status' => 'warning',
                'message' => 'Permission issues: ' . implode(', ', $issues)
            ];
        }
    }

    private function checkMemoryUsage(): array
    {
        $usage = memory_get_usage(true);
        $limit = ini_get('memory_limit');
        
        if ($limit === '-1') {
            return [
                'status' => 'healthy',
                'message' => 'No memory limit set',
                'usage' => $this->formatBytes($usage)
            ];
        }
        
        $limitBytes = $this->parseBytes($limit);
        $percentage = ($usage / $limitBytes) * 100;
        
        if ($percentage > 90) {
            $status = 'error';
            $message = 'Memory usage critical';
        } elseif ($percentage > 75) {
            $status = 'warning';
            $message = 'Memory usage high';
        } else {
            $status = 'healthy';
            $message = 'Memory usage normal';
        }
        
        return [
            'status' => $status,
            'message' => $message,
            'usage' => $this->formatBytes($usage),
            'limit' => $limit,
            'percentage' => round($percentage, 2)
        ];
    }

    private function checkDiskSpace(): array
    {
        $diskSpace = $this->getDiskSpace();
        
        if ($diskSpace['usage_percentage'] > 95) {
            $status = 'error';
            $message = 'Disk space critical';
        } elseif ($diskSpace['usage_percentage'] > 85) {
            $status = 'warning';
            $message = 'Disk space low';
        } else {
            $status = 'healthy';
            $message = 'Disk space adequate';
        }
        
        return [
            'status' => $status,
            'message' => $message,
            'usage_percentage' => round($diskSpace['usage_percentage'], 2),
            'free_space' => $diskSpace['free_formatted']
        ];
    }

    private function checkPhpExtensions(): array
    {
        $required = ['pdo', 'json', 'mbstring'];
        $missing = [];
        
        foreach ($required as $ext) {
            if (!extension_loaded($ext)) {
                $missing[] = $ext;
            }
        }
        
        if (empty($missing)) {
            return [
                'status' => 'healthy',
                'message' => 'All required PHP extensions are loaded'
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Missing PHP extensions: ' . implode(', ', $missing)
            ];
        }
    }

    private function checkConfiguration(): array
    {
        $issues = [];
        
        if (ini_get('display_errors') === '1') {
            $issues[] = 'display_errors should be Off in production';
        }
        
        if (ini_get('expose_php') === '1') {
            $issues[] = 'expose_php should be Off for security';
        }
        
        if (empty($issues)) {
            return [
                'status' => 'healthy',
                'message' => 'PHP configuration looks good'
            ];
        } else {
            return [
                'status' => 'warning',
                'message' => 'Configuration issues: ' . implode(', ', $issues)
            ];
        }
    }

    private function cleanupTempFiles(): int
    {
        $tempDir = '/tmp/';
        $pattern = $tempDir . 'lobsters_*';
        $files = glob($pattern);
        $cleaned = 0;
        
        foreach ($files as $file) {
            if (is_file($file) && (time() - filemtime($file)) > 86400) { // 24 hours
                if (unlink($file)) {
                    $cleaned++;
                }
            }
        }
        
        return $cleaned;
    }

    private function cleanupOldMetrics(): int
    {
        // This would clean up old performance metrics
        return 0;
    }

    private function cleanupOldLogs(): int
    {
        // This would clean up old log files
        return 0;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = 1024;
        
        for ($i = 0; $bytes >= $factor && $i < count($units) - 1; $i++) {
            $bytes /= $factor;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    private function parseBytes(string $value): int
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;
        
        switch ($last) {
            case 'g':
                $value *= 1024;
                // no break
            case 'm':
                $value *= 1024;
                // no break
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
}