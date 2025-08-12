<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Intelligent Rate Limiting Service
 * 
 * Comprehensive rate limiting with adaptive throttling:
 * - Multi-dimensional rate limiting (global, user, IP, endpoint)
 * - Intelligent ML-based anomaly detection
 * - Dynamic adjustment based on system load
 * - User reputation-based limits
 * - Distributed rate limiting coordination
 * - Token bucket and sliding window algorithms
 */
class RateLimitingService
{
    private CacheService $cache;
    private PerformanceMonitorService $monitor;
    private array $config;
    private array $limits;
    private array $algorithms;
    
    public function __construct(
        CacheService $cache = null,
        PerformanceMonitorService $monitor = null
    ) {
        $this->cache = $cache ?? new CacheService();
        $this->monitor = $monitor ?? new PerformanceMonitorService();
        $this->config = $this->loadConfig();
        $this->limits = $this->loadLimits();
        $this->algorithms = $this->initializeAlgorithms();
    }
    
    /**
     * Check if request is allowed under current rate limits
     */
    public function isAllowed(
        string $identifier,
        string $limitType,
        array $context = []
    ): array {
        try {
            $startTime = microtime(true);
            
            // Get applicable limits for this request
            $applicableLimits = $this->getApplicableLimits($identifier, $limitType, $context);
            
            $result = [
                'allowed' => true,
                'limits_checked' => [],
                'remaining' => [],
                'reset_times' => [],
                'retry_after' => null,
                'reason' => null
            ];
            
            // Check each applicable limit
            foreach ($applicableLimits as $limitKey => $limitConfig) {
                $limitResult = $this->checkLimit($identifier, $limitKey, $limitConfig, $context);
                
                $result['limits_checked'][] = $limitKey;
                $result['remaining'][$limitKey] = $limitResult['remaining'];
                $result['reset_times'][$limitKey] = $limitResult['reset_time'];
                
                if (!$limitResult['allowed']) {
                    $result['allowed'] = false;
                    $result['retry_after'] = max($result['retry_after'] ?? 0, $limitResult['retry_after']);
                    $result['reason'] = $limitResult['reason'];
                    
                    // Record rate limit violation
                    $this->recordViolation($identifier, $limitKey, $context);
                    break; // Stop checking once we hit a limit
                }
            }
            
            // Record successful check if allowed
            if ($result['allowed']) {
                $this->recordSuccessfulRequest($identifier, $limitType, $context);
            }
            
            // Monitor performance
            $this->monitor->recordMetric('rate_limit_check_time', microtime(true) - $startTime, [
                'limit_type' => $limitType,
                'allowed' => $result['allowed']
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            error_log("Rate limiting check failed: " . $e->getMessage());
            
            // Fail open - allow request if rate limiting system fails
            return [
                'allowed' => true,
                'limits_checked' => [],
                'remaining' => [],
                'reset_times' => [],
                'retry_after' => null,
                'reason' => 'rate_limit_system_error'
            ];
        }
    }
    
    /**
     * Adaptive rate limiting based on system load and user reputation
     */
    public function getAdaptiveLimit(
        string $identifier,
        string $baseLimitType,
        array $context = []
    ): array {
        $baseLimit = $this->limits[$baseLimitType] ?? $this->limits['default'];
        
        // Get current system load factor
        $loadFactor = $this->getSystemLoadFactor();
        
        // Get user reputation factor
        $reputationFactor = $this->getUserReputationFactor($identifier, $context);
        
        // Get threat assessment factor
        $threatFactor = $this->getThreatAssessmentFactor($identifier, $context);
        
        // Calculate adaptive limits
        $adaptiveLimit = [
            'requests' => (int)($baseLimit['requests'] * $reputationFactor * $threatFactor / $loadFactor),
            'window' => $baseLimit['window'],
            'burst' => (int)($baseLimit['burst'] * $reputationFactor * $threatFactor / $loadFactor),
            'factors' => [
                'base' => $baseLimit['requests'],
                'load_factor' => $loadFactor,
                'reputation_factor' => $reputationFactor,
                'threat_factor' => $threatFactor,
                'final_multiplier' => $reputationFactor * $threatFactor / $loadFactor
            ]
        ];
        
        // Ensure minimum limits
        $adaptiveLimit['requests'] = max($adaptiveLimit['requests'], $this->config['min_requests_per_window']);
        $adaptiveLimit['burst'] = max($adaptiveLimit['burst'], $this->config['min_burst_requests']);
        
        return $adaptiveLimit;
    }
    
    /**
     * Machine learning based anomaly detection
     */
    public function detectAnomalies(string $identifier, array $context = []): array
    {
        try {
            $patterns = $this->getRequestPatterns($identifier);
            $anomalies = [];
            
            // Check for velocity anomalies
            $velocityAnomaly = $this->detectVelocityAnomaly($patterns);
            if ($velocityAnomaly['detected']) {
                $anomalies[] = $velocityAnomaly;
            }
            
            // Check for pattern anomalies
            $patternAnomaly = $this->detectPatternAnomaly($patterns);
            if ($patternAnomaly['detected']) {
                $anomalies[] = $patternAnomaly;
            }
            
            // Check for geographical anomalies
            if (isset($context['ip'])) {
                $geoAnomaly = $this->detectGeographicalAnomaly($identifier, $context['ip']);
                if ($geoAnomaly['detected']) {
                    $anomalies[] = $geoAnomaly;
                }
            }
            
            // Check for behavioral anomalies
            $behaviorAnomaly = $this->detectBehavioralAnomaly($identifier, $context);
            if ($behaviorAnomaly['detected']) {
                $anomalies[] = $behaviorAnomaly;
            }
            
            return [
                'anomalies_detected' => !empty($anomalies),
                'anomaly_count' => count($anomalies),
                'anomalies' => $anomalies,
                'risk_score' => $this->calculateRiskScore($anomalies),
                'recommended_action' => $this->getRecommendedAction($anomalies)
            ];
            
        } catch (\Exception $e) {
            error_log("Anomaly detection failed: " . $e->getMessage());
            return [
                'anomalies_detected' => false,
                'anomaly_count' => 0,
                'anomalies' => [],
                'risk_score' => 0.0,
                'recommended_action' => 'allow'
            ];
        }
    }
    
    /**
     * Distributed rate limiting coordination
     */
    public function coordinateDistributedLimits(
        string $identifier,
        string $limitType,
        int $requestCount = 1
    ): array {
        if (!$this->config['distributed_mode']) {
            return ['coordinated' => false, 'local_only' => true];
        }
        
        try {
            $key = "distributed_limit_{$limitType}_{$identifier}";
            
            // Get current distributed count
            $distributedCount = $this->getDistributedCount($key);
            
            // Update local contribution
            $localContribution = $this->updateLocalContribution($key, $requestCount);
            
            // Broadcast to other nodes
            $this->broadcastUpdate($key, $localContribution);
            
            // Calculate total distributed count
            $totalCount = $this->calculateTotalDistributedCount($key);
            
            return [
                'coordinated' => true,
                'local_count' => $localContribution,
                'distributed_count' => $distributedCount,
                'total_count' => $totalCount,
                'nodes_participating' => $this->getParticipatingNodes($key)
            ];
            
        } catch (\Exception $e) {
            error_log("Distributed coordination failed: " . $e->getMessage());
            return ['coordinated' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Token bucket algorithm implementation
     */
    public function checkTokenBucket(
        string $identifier,
        array $limitConfig,
        int $requestTokens = 1
    ): array {
        $key = "token_bucket_{$identifier}";
        $bucketData = $this->cache->get($key) ?? $this->initializeTokenBucket($limitConfig);
        
        $now = time();
        $timePassed = $now - $bucketData['last_refill'];
        
        // Calculate tokens to add based on time passed
        $tokensToAdd = min(
            $timePassed * $limitConfig['refill_rate'],
            $limitConfig['capacity'] - $bucketData['tokens']
        );
        
        $bucketData['tokens'] += $tokensToAdd;
        $bucketData['last_refill'] = $now;
        
        // Check if we have enough tokens
        if ($bucketData['tokens'] >= $requestTokens) {
            $bucketData['tokens'] -= $requestTokens;
            $allowed = true;
            $retryAfter = null;
        } else {
            $allowed = false;
            $retryAfter = ceil(($requestTokens - $bucketData['tokens']) / $limitConfig['refill_rate']);
        }
        
        // Update bucket state
        $this->cache->set($key, $bucketData, $limitConfig['window']);
        
        return [
            'allowed' => $allowed,
            'remaining' => (int)$bucketData['tokens'],
            'capacity' => $limitConfig['capacity'],
            'retry_after' => $retryAfter,
            'reset_time' => $now + $limitConfig['window']
        ];
    }
    
    /**
     * Sliding window algorithm implementation
     */
    public function checkSlidingWindow(
        string $identifier,
        array $limitConfig,
        int $requestCount = 1
    ): array {
        $key = "sliding_window_{$identifier}";
        $now = time();
        $windowStart = $now - $limitConfig['window'];
        
        // Get current window data
        $windowData = $this->cache->get($key) ?? [];
        
        // Remove old entries outside the window
        $windowData = array_filter($windowData, function($timestamp) use ($windowStart) {
            return $timestamp > $windowStart;
        });
        
        $currentCount = count($windowData);
        
        // Check if request would exceed limit
        if ($currentCount + $requestCount <= $limitConfig['requests']) {
            // Add new request timestamps
            for ($i = 0; $i < $requestCount; $i++) {
                $windowData[] = $now;
            }
            
            $this->cache->set($key, $windowData, $limitConfig['window'] + 60); // Extra TTL buffer
            
            return [
                'allowed' => true,
                'remaining' => $limitConfig['requests'] - ($currentCount + $requestCount),
                'current_count' => $currentCount + $requestCount,
                'reset_time' => min($windowData) + $limitConfig['window'],
                'retry_after' => null
            ];
        } else {
            // Find when oldest request will expire
            $oldestRequest = min($windowData);
            $retryAfter = ($oldestRequest + $limitConfig['window']) - $now;
            
            return [
                'allowed' => false,
                'remaining' => 0,
                'current_count' => $currentCount,
                'reset_time' => $oldestRequest + $limitConfig['window'],
                'retry_after' => max(1, $retryAfter)
            ];
        }
    }
    
    /**
     * Get comprehensive rate limiting statistics
     */
    public function getStatistics(string $period = '1h'): array
    {
        $cacheKey = "rate_limit_stats_{$period}";
        
        if ($cached = $this->cache->get($cacheKey)) {
            return $cached;
        }
        
        $stats = [
            'period' => $period,
            'total_requests' => $this->getTotalRequests($period),
            'blocked_requests' => $this->getBlockedRequests($period),
            'block_rate' => 0.0,
            'top_blocked_ips' => $this->getTopBlockedIPs($period),
            'top_violated_limits' => $this->getTopViolatedLimits($period),
            'adaptive_adjustments' => $this->getAdaptiveAdjustments($period),
            'anomalies_detected' => $this->getAnomaliesDetected($period),
            'average_response_time' => $this->getAverageResponseTime($period),
            'distributed_coordination' => $this->getDistributedStats($period)
        ];
        
        if ($stats['total_requests'] > 0) {
            $stats['block_rate'] = ($stats['blocked_requests'] / $stats['total_requests']) * 100;
        }
        
        $this->cache->set($cacheKey, $stats, 300); // Cache for 5 minutes
        
        return $stats;
    }
    
    /**
     * Optimize rate limiting configuration
     */
    public function optimizeConfiguration(): array
    {
        $analysis = $this->analyzeRateLimitingPatterns();
        $optimizations = [];
        
        // Analyze false positive rate
        if ($analysis['false_positive_rate'] > $this->config['max_false_positive_rate']) {
            $optimizations['increase_limits'] = [
                'current_rate' => $analysis['false_positive_rate'],
                'suggested_increase' => 20,
                'affected_limits' => $analysis['high_false_positive_limits']
            ];
        }
        
        // Analyze threat prevention effectiveness
        if ($analysis['threat_prevention_rate'] < $this->config['min_threat_prevention_rate']) {
            $optimizations['strengthen_security'] = [
                'current_rate' => $analysis['threat_prevention_rate'],
                'suggested_measures' => $analysis['suggested_security_measures']
            ];
        }
        
        // Analyze performance impact
        if ($analysis['avg_check_time'] > $this->config['max_check_time']) {
            $optimizations['improve_performance'] = [
                'current_time' => $analysis['avg_check_time'],
                'suggested_optimizations' => $analysis['performance_optimizations']
            ];
        }
        
        return $optimizations;
    }
    
    // Private helper methods
    
    private function loadConfig(): array
    {
        return [
            'distributed_mode' => false,
            'min_requests_per_window' => 1,
            'min_burst_requests' => 1,
            'max_false_positive_rate' => 0.05, // 5%
            'min_threat_prevention_rate' => 0.95, // 95%
            'max_check_time' => 10, // milliseconds
            'anomaly_detection_enabled' => true,
            'adaptive_limits_enabled' => true,
            'ml_models_enabled' => false
        ];
    }
    
    private function loadLimits(): array
    {
        return [
            'global' => ['requests' => 10000, 'window' => 3600, 'burst' => 20, 'refill_rate' => 2.78],
            'user' => ['requests' => 1000, 'window' => 3600, 'burst' => 15, 'refill_rate' => 0.28],
            'ip' => ['requests' => 500, 'window' => 3600, 'burst' => 10, 'refill_rate' => 0.14],
            'login' => ['requests' => 5, 'window' => 900, 'burst' => 2, 'refill_rate' => 0.006],
            'search' => ['requests' => 50, 'window' => 60, 'burst' => 5, 'refill_rate' => 0.83],
            'api_create' => ['requests' => 100, 'window' => 3600, 'burst' => 5, 'refill_rate' => 0.028],
            'suspicious' => ['requests' => 10, 'window' => 3600, 'burst' => 1, 'refill_rate' => 0.003],
            'default' => ['requests' => 100, 'window' => 3600, 'burst' => 5, 'refill_rate' => 0.028]
        ];
    }
    
    private function initializeAlgorithms(): array
    {
        return [
            'token_bucket' => [$this, 'checkTokenBucket'],
            'sliding_window' => [$this, 'checkSlidingWindow']
        ];
    }
    
    private function getApplicableLimits(string $identifier, string $limitType, array $context): array
    {
        $limits = [];
        
        // Base limit type
        if (isset($this->limits[$limitType])) {
            $limits[$limitType] = $this->limits[$limitType];
        }
        
        // Global limit always applies
        $limits['global'] = $this->limits['global'];
        
        // IP-based limits
        if (isset($context['ip'])) {
            $limits['ip'] = $this->getAdaptiveLimit($context['ip'], 'ip', $context);
        }
        
        // User-based limits
        if (isset($context['user_id'])) {
            $limits['user'] = $this->getAdaptiveLimit($context['user_id'], 'user', $context);
        }
        
        // Endpoint-specific limits
        if (isset($context['endpoint'])) {
            $endpointLimitType = $this->getEndpointLimitType($context['endpoint']);
            if ($endpointLimitType && isset($this->limits[$endpointLimitType])) {
                $limits[$endpointLimitType] = $this->limits[$endpointLimitType];
            }
        }
        
        return $limits;
    }
    
    private function checkLimit(string $identifier, string $limitKey, array $limitConfig, array $context): array
    {
        $algorithm = $this->config['default_algorithm'] ?? 'sliding_window';
        
        // Choose algorithm based on limit type
        if (in_array($limitKey, ['login', 'api_create'])) {
            $algorithm = 'token_bucket'; // Better for burst protection
        }
        
        switch ($algorithm) {
            case 'token_bucket':
                return $this->checkTokenBucket($identifier . '_' . $limitKey, $limitConfig);
            case 'sliding_window':
                return $this->checkSlidingWindow($identifier . '_' . $limitKey, $limitConfig);
            default:
                throw new \InvalidArgumentException("Unknown algorithm: {$algorithm}");
        }
    }
    
    private function initializeTokenBucket(array $limitConfig): array
    {
        return [
            'tokens' => $limitConfig['capacity'] ?? $limitConfig['requests'],
            'last_refill' => time()
        ];
    }
    
    // Mock implementations for ML and advanced features
    private function getSystemLoadFactor(): float { return 1.0; }
    private function getUserReputationFactor(string $identifier, array $context): float { return 1.0; }
    private function getThreatAssessmentFactor(string $identifier, array $context): float { return 1.0; }
    private function getRequestPatterns(string $identifier): array { return []; }
    private function detectVelocityAnomaly(array $patterns): array { return ['detected' => false]; }
    private function detectPatternAnomaly(array $patterns): array { return ['detected' => false]; }
    private function detectGeographicalAnomaly(string $identifier, string $ip): array { return ['detected' => false]; }
    private function detectBehavioralAnomaly(string $identifier, array $context): array { return ['detected' => false]; }
    private function calculateRiskScore(array $anomalies): float { return 0.0; }
    private function getRecommendedAction(array $anomalies): string { return 'allow'; }
    private function recordViolation(string $identifier, string $limitKey, array $context): void {}
    private function recordSuccessfulRequest(string $identifier, string $limitType, array $context): void {}
    private function getEndpointLimitType(string $endpoint): ?string { return null; }
    private function getDistributedCount(string $key): int { return 0; }
    private function updateLocalContribution(string $key, int $count): int { return $count; }
    private function broadcastUpdate(string $key, int $contribution): void {}
    private function calculateTotalDistributedCount(string $key): int { return 0; }
    private function getParticipatingNodes(string $key): array { return []; }
    private function getTotalRequests(string $period): int { return 0; }
    private function getBlockedRequests(string $period): int { return 0; }
    private function getTopBlockedIPs(string $period): array { return []; }
    private function getTopViolatedLimits(string $period): array { return []; }
    private function getAdaptiveAdjustments(string $period): array { return []; }
    private function getAnomaliesDetected(string $period): array { return []; }
    private function getAverageResponseTime(string $period): float { return 0.0; }
    private function getDistributedStats(string $period): array { return []; }
    private function analyzeRateLimitingPatterns(): array { return []; }
}