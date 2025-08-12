<?php

declare(strict_types=1);

namespace App\Services;

class RateLimitService
{
    private const CACHE_PREFIX = 'rate_limit:';
    private const DEFAULT_WINDOW = 3600; // 1 hour
    
    public function __construct(
        private CacheService $cacheService,
        private LoggerService $loggerService
    ) {}

    public function checkLimit(string $identifier, int $limit, int $window = self::DEFAULT_WINDOW, string $action = 'general'): bool
    {
        $key = self::CACHE_PREFIX . $action . ':' . md5($identifier);
        $currentCount = $this->cacheService->get($key) ?? 0;
        
        if ($currentCount >= $limit) {
            $this->loggerService->logSecurityEvent('Rate limit exceeded', [
                'identifier' => $identifier,
                'action' => $action,
                'current_count' => $currentCount,
                'limit' => $limit,
                'window' => $window
            ]);
            
            return false;
        }
        
        $this->cacheService->set($key, $currentCount + 1, $window);
        return true;
    }

    public function checkIpLimit(string $action = 'general', int $limit = 100, int $window = self::DEFAULT_WINDOW): bool
    {
        $ip = $this->getClientIp();
        return $this->checkLimit($ip, $limit, $window, $action);
    }

    public function checkUserLimit(int $userId, string $action = 'general', int $limit = 50, int $window = self::DEFAULT_WINDOW): bool
    {
        return $this->checkLimit("user:{$userId}", $limit, $window, $action);
    }

    public function checkLoginAttempts(string $identifier, int $limit = 5, int $window = 900): bool
    {
        return $this->checkLimit($identifier, $limit, $window, 'login_attempts');
    }

    public function checkApiRequests(string $apiKey, int $limit = 1000, int $window = self::DEFAULT_WINDOW): bool
    {
        return $this->checkLimit("api:{$apiKey}", $limit, $window, 'api_requests');
    }

    public function checkStorySubmission(int $userId, int $limit = 5, int $window = 86400): bool
    {
        return $this->checkUserLimit($userId, 'story_submission', $limit, $window);
    }

    public function checkCommentSubmission(int $userId, int $limit = 50, int $window = self::DEFAULT_WINDOW): bool
    {
        return $this->checkUserLimit($userId, 'comment_submission', $limit, $window);
    }

    public function checkVoting(int $userId, int $limit = 200, int $window = self::DEFAULT_WINDOW): bool
    {
        return $this->checkUserLimit($userId, 'voting', $limit, $window);
    }

    public function checkPasswordReset(string $identifier, int $limit = 3, int $window = 3600): bool
    {
        return $this->checkLimit($identifier, $limit, $window, 'password_reset');
    }

    public function checkSearchRequests(string $identifier, int $limit = 100, int $window = self::DEFAULT_WINDOW): bool
    {
        return $this->checkLimit($identifier, $limit, $window, 'search_requests');
    }

    public function getRemainingAttempts(string $identifier, int $limit, int $window = self::DEFAULT_WINDOW, string $action = 'general'): int
    {
        $key = self::CACHE_PREFIX . $action . ':' . md5($identifier);
        $currentCount = $this->cacheService->get($key) ?? 0;
        
        return max(0, $limit - $currentCount);
    }

    public function getTimeUntilReset(string $identifier, string $action = 'general'): ?int
    {
        $key = self::CACHE_PREFIX . $action . ':' . md5($identifier);
        
        // This would need to be implemented based on cache TTL
        // For now, return approximate time
        return 3600; // 1 hour
    }

    public function resetLimit(string $identifier, string $action = 'general'): void
    {
        $key = self::CACHE_PREFIX . $action . ':' . md5($identifier);
        $this->cacheService->delete($key);
    }

    public function resetUserLimits(int $userId): void
    {
        $actions = ['general', 'story_submission', 'comment_submission', 'voting'];
        
        foreach ($actions as $action) {
            $this->resetLimit("user:{$userId}", $action);
        }
    }

    public function resetIpLimits(string $ip): void
    {
        $actions = ['general', 'login_attempts', 'search_requests', 'password_reset'];
        
        foreach ($actions as $action) {
            $this->resetLimit($ip, $action);
        }
    }

    public function getRateLimitStats(): array
    {
        // This would collect statistics about rate limiting
        // For now, return basic info
        return [
            'total_rate_limits' => 0,
            'active_limits' => 0,
            'blocked_requests_today' => 0,
            'top_limited_ips' => [],
            'top_limited_actions' => []
        ];
    }

    public function isIpWhitelisted(string $ip): bool
    {
        // Whitelist for trusted IPs (admin, monitoring, etc.)
        $whitelist = [
            '127.0.0.1',
            '::1'
        ];
        
        return in_array($ip, $whitelist);
    }

    public function isIpBlacklisted(string $ip): bool
    {
        // Blacklist for known bad IPs
        $blacklist = [
            // This would be populated from threat intelligence feeds
        ];
        
        return in_array($ip, $blacklist);
    }

    public function addToBlacklist(string $ip, string $reason = ''): void
    {
        $key = 'blacklist:' . $ip;
        $data = [
            'ip' => $ip,
            'reason' => $reason,
            'added_at' => time(),
            'added_by' => $_SESSION['user_id'] ?? null
        ];
        
        $this->cacheService->set($key, $data, 86400 * 30); // 30 days
        
        $this->loggerService->logSecurityEvent('IP added to blacklist', [
            'ip' => $ip,
            'reason' => $reason
        ]);
    }

    public function removeFromBlacklist(string $ip): void
    {
        $key = 'blacklist:' . $ip;
        $this->cacheService->delete($key);
        
        $this->loggerService->logSecurityEvent('IP removed from blacklist', [
            'ip' => $ip
        ]);
    }

    public function checkSuspiciousActivity(string $identifier): bool
    {
        // Check for patterns that might indicate abuse
        $patterns = [
            'rapid_requests' => $this->checkRapidRequests($identifier),
            'repeated_failures' => $this->checkRepeatedFailures($identifier),
            'unusual_patterns' => $this->checkUnusualPatterns($identifier)
        ];
        
        $suspiciousCount = array_sum($patterns);
        
        if ($suspiciousCount >= 2) {
            $this->loggerService->logSecurityEvent('Suspicious activity detected', [
                'identifier' => $identifier,
                'patterns' => $patterns,
                'suspicious_count' => $suspiciousCount
            ]);
            
            return true;
        }
        
        return false;
    }

    public function applyDynamicLimits(string $identifier, string $action): array
    {
        // Adjust limits based on user behavior, time of day, system load, etc.
        $baseLimits = [
            'general' => 100,
            'story_submission' => 5,
            'comment_submission' => 50,
            'voting' => 200,
            'search_requests' => 100
        ];
        
        $limit = $baseLimits[$action] ?? 100;
        $window = self::DEFAULT_WINDOW;
        
        // Reduce limits during high load
        if ($this->isHighLoad()) {
            $limit = (int) ($limit * 0.7);
        }
        
        // Reduce limits for new users
        if ($this->isNewUser($identifier)) {
            $limit = (int) ($limit * 0.5);
        }
        
        // Increase limits for trusted users
        if ($this->isTrustedUser($identifier)) {
            $limit = (int) ($limit * 1.5);
        }
        
        return [
            'limit' => $limit,
            'window' => $window
        ];
    }

    private function getClientIp(): string
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    private function checkRapidRequests(string $identifier): bool
    {
        // Check if requests are coming too rapidly (more than 10 per minute)
        $key = 'rapid_check:' . md5($identifier);
        $count = $this->cacheService->get($key) ?? 0;
        
        $this->cacheService->set($key, $count + 1, 60); // 1 minute window
        
        return $count > 10;
    }

    private function checkRepeatedFailures(string $identifier): bool
    {
        // Check for repeated failed attempts
        $key = 'failures:' . md5($identifier);
        $failures = $this->cacheService->get($key) ?? 0;
        
        return $failures > 5;
    }

    private function checkUnusualPatterns(string $identifier): bool
    {
        // Check for unusual request patterns
        // This would be more sophisticated in a real implementation
        return false;
    }

    private function isHighLoad(): bool
    {
        // Check system load
        $load = sys_getloadavg();
        return $load && $load[0] > 2.0;
    }

    private function isNewUser(string $identifier): bool
    {
        // Check if this is a new user (registered within last 24 hours)
        if (strpos($identifier, 'user:') === 0) {
            $userId = (int) str_replace('user:', '', $identifier);
            try {
                $user = \App\Models\User::find($userId);
                if ($user) {
                    $accountAge = time() - strtotime($user->created_at);
                    return $accountAge < 86400; // 24 hours
                }
            } catch (\Exception $e) {
                // Ignore database errors
            }
        }
        
        return false;
    }

    private function isTrustedUser(string $identifier): bool
    {
        // Check if this is a trusted user (moderator, long-time user, etc.)
        if (strpos($identifier, 'user:') === 0) {
            $userId = (int) str_replace('user:', '', $identifier);
            try {
                $user = \App\Models\User::find($userId);
                if ($user) {
                    return $user->is_moderator || $user->is_admin;
                }
            } catch (\Exception $e) {
                // Ignore database errors
            }
        }
        
        return false;
    }
}