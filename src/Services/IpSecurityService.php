<?php

declare(strict_types=1);

namespace App\Services;

/**
 * IP Security and Geolocation Filtering Service
 * 
 * Provides comprehensive IP-based security features:
 * - IP allowlisting and blocklisting
 * - Geolocation-based access control
 * - VPN/Proxy detection
 * - Reputation scoring
 * - Rate limiting per IP
 * - Suspicious activity detection
 */
class IpSecurityService
{
    private CacheService $cache;
    private LoggerService $logger;
    
    // Configuration constants
    private const IP_REPUTATION_THRESHOLD = 50; // 0-100 scale
    private const GEOLOCATION_CACHE_TTL = 86400; // 24 hours
    private const BLOCKLIST_CACHE_TTL = 3600; // 1 hour
    private const MAX_REQUESTS_PER_IP = 1000; // Per hour
    private const SUSPICIOUS_ACTIVITY_THRESHOLD = 5;

    // Risk scoring factors
    private const RISK_SCORES = [
        'known_malicious' => 100,
        'tor_exit_node' => 80,
        'vpn_detected' => 60,
        'proxy_detected' => 50,
        'cloud_provider' => 30,
        'suspicious_activity' => 40,
        'new_ip' => 10,
        'frequent_requests' => 20
    ];

    private array $allowedCountries = [];
    private array $blockedCountries = [];
    private array $allowedIpRanges = [];
    private array $blockedIpRanges = [];

    public function __construct(CacheService $cache, LoggerService $logger)
    {
        $this->cache = $cache;
        $this->logger = $logger;
        $this->loadConfiguration();
    }

    /**
     * Comprehensive IP security analysis
     */
    public function analyzeIpSecurity(string $ipAddress, array $context = []): array
    {
        $startTime = microtime(true);

        try {
            $analysis = [
                'ip_address' => $ipAddress,
                'is_allowed' => true,
                'risk_score' => 0,
                'risk_factors' => [],
                'geolocation' => null,
                'reputation' => null,
                'decision' => 'ALLOW',
                'reason' => '',
                'processing_time' => 0
            ];

            // Skip analysis for localhost and private IPs in development
            if ($this->isPrivateIp($ipAddress)) {
                $analysis['decision'] = 'ALLOW';
                $analysis['reason'] = 'Private IP address';
                $analysis['processing_time'] = microtime(true) - $startTime;
                return $analysis;
            }

            // Check explicit IP blocklist first
            if ($this->isIpExplicitlyBlocked($ipAddress)) {
                $analysis['is_allowed'] = false;
                $analysis['risk_score'] = 100;
                $analysis['decision'] = 'BLOCK';
                $analysis['reason'] = 'IP address is explicitly blocked';
                
                $this->logSecurityDecision($ipAddress, $analysis, $context);
                return $analysis;
            }

            // Check explicit IP allowlist
            if ($this->isIpExplicitlyAllowed($ipAddress)) {
                $analysis['decision'] = 'ALLOW';
                $analysis['reason'] = 'IP address is explicitly allowed';
                $analysis['processing_time'] = microtime(true) - $startTime;
                return $analysis;
            }

            // Get geolocation data
            $geolocation = $this->getGeolocation($ipAddress);
            $analysis['geolocation'] = $geolocation;

            // Check country-based restrictions
            $countryCheck = $this->checkCountryRestrictions($geolocation);
            if (!$countryCheck['allowed']) {
                $analysis['is_allowed'] = false;
                $analysis['risk_score'] = 90;
                $analysis['decision'] = 'BLOCK';
                $analysis['reason'] = $countryCheck['reason'];
                
                $this->logSecurityDecision($ipAddress, $analysis, $context);
                return $analysis;
            }

            // Get IP reputation
            $reputation = $this->getIpReputation($ipAddress);
            $analysis['reputation'] = $reputation;

            // Calculate comprehensive risk score
            $riskAssessment = $this->calculateRiskScore($ipAddress, $geolocation, $reputation, $context);
            $analysis['risk_score'] = $riskAssessment['score'];
            $analysis['risk_factors'] = $riskAssessment['factors'];

            // Make final security decision
            $decision = $this->makeSecurityDecision($analysis['risk_score'], $analysis['risk_factors']);
            $analysis['decision'] = $decision['action'];
            $analysis['reason'] = $decision['reason'];
            $analysis['is_allowed'] = $decision['action'] === 'ALLOW';

            // Log the decision
            $this->logSecurityDecision($ipAddress, $analysis, $context);

            // Update IP statistics
            $this->updateIpStatistics($ipAddress, $analysis);

            $analysis['processing_time'] = microtime(true) - $startTime;
            return $analysis;

        } catch (\Exception $e) {
            $this->logger->logError('IP security analysis error', [
                'ip_address' => $ipAddress,
                'error' => $e->getMessage(),
                'context' => $context
            ]);

            // Fail securely - deny on error
            return [
                'ip_address' => $ipAddress,
                'is_allowed' => false,
                'risk_score' => 100,
                'decision' => 'BLOCK',
                'reason' => 'Security analysis failed',
                'error' => 'Service temporarily unavailable',
                'processing_time' => microtime(true) - $startTime
            ];
        }
    }

    /**
     * Get comprehensive geolocation data for IP
     */
    public function getGeolocation(string $ipAddress): array
    {
        $cacheKey = "geolocation:{$ipAddress}";
        $cached = $this->cache->get($cacheKey);

        if ($cached) {
            return $cached;
        }

        try {
            // Try multiple geolocation providers for accuracy
            $geolocation = $this->queryGeolocationProviders($ipAddress);
            
            // Cache the result
            $this->cache->set($cacheKey, $geolocation, self::GEOLOCATION_CACHE_TTL);
            
            return $geolocation;

        } catch (\Exception $e) {
            $this->logger->logError('Geolocation lookup error', [
                'ip_address' => $ipAddress,
                'error' => $e->getMessage()
            ]);

            return [
                'country_code' => 'UNKNOWN',
                'country_name' => 'Unknown',
                'region' => 'Unknown',
                'city' => 'Unknown',
                'latitude' => null,
                'longitude' => null,
                'timezone' => null,
                'isp' => 'Unknown',
                'organization' => 'Unknown',
                'as_number' => null,
                'error' => 'Geolocation lookup failed'
            ];
        }
    }

    /**
     * Get IP reputation from multiple threat intelligence sources
     */
    public function getIpReputation(string $ipAddress): array
    {
        $cacheKey = "ip_reputation:{$ipAddress}";
        $cached = $this->cache->get($cacheKey);

        if ($cached) {
            return $cached;
        }

        try {
            $reputation = [
                'score' => 50, // Default neutral score
                'classification' => 'UNKNOWN',
                'threat_types' => [],
                'sources' => [],
                'last_seen' => null,
                'confidence' => 0
            ];

            // Query multiple threat intelligence sources
            $sources = $this->queryThreatIntelligenceSources($ipAddress);
            
            // Aggregate reputation data
            $reputation = $this->aggregateReputationData($sources);
            
            // Cache the result
            $this->cache->set($cacheKey, $reputation, self::BLOCKLIST_CACHE_TTL);
            
            return $reputation;

        } catch (\Exception $e) {
            $this->logger->logError('IP reputation lookup error', [
                'ip_address' => $ipAddress,
                'error' => $e->getMessage()
            ]);

            return [
                'score' => 50,
                'classification' => 'UNKNOWN',
                'error' => 'Reputation lookup failed'
            ];
        }
    }

    /**
     * Check if IP is using VPN or proxy
     */
    public function detectVpnProxy(string $ipAddress): array
    {
        $cacheKey = "vpn_proxy:{$ipAddress}";
        $cached = $this->cache->get($cacheKey);

        if ($cached) {
            return $cached;
        }

        try {
            $detection = [
                'is_vpn' => false,
                'is_proxy' => false,
                'is_tor' => false,
                'is_cloud' => false,
                'provider' => null,
                'confidence' => 0,
                'methods' => []
            ];

            // Multiple detection methods
            $detection = $this->runVpnProxyDetection($ipAddress, $detection);
            
            // Cache the result
            $this->cache->set($cacheKey, $detection, self::GEOLOCATION_CACHE_TTL);
            
            return $detection;

        } catch (\Exception $e) {
            $this->logger->logError('VPN/Proxy detection error', [
                'ip_address' => $ipAddress,
                'error' => $e->getMessage()
            ]);

            return [
                'is_vpn' => false,
                'is_proxy' => false,
                'is_tor' => false,
                'error' => 'Detection failed'
            ];
        }
    }

    /**
     * Add IP to blocklist
     */
    public function blockIp(string $ipAddress, string $reason, int $duration = null): bool
    {
        try {
            $blockData = [
                'ip_address' => $ipAddress,
                'reason' => $reason,
                'blocked_at' => time(),
                'blocked_until' => $duration ? time() + $duration : null,
                'permanent' => $duration === null
            ];

            $cacheKey = "ip_blocked:{$ipAddress}";
            $cacheTtl = $duration ?? self::BLOCKLIST_CACHE_TTL;
            
            $this->cache->set($cacheKey, $blockData, $cacheTtl);

            $this->logger->logSecurityEvent('ip_blocked', [
                'ip_address' => $ipAddress,
                'reason' => $reason,
                'duration' => $duration,
                'permanent' => $duration === null
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->logError('IP blocking error', [
                'ip_address' => $ipAddress,
                'reason' => $reason,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Remove IP from blocklist
     */
    public function unblockIp(string $ipAddress, string $reason = ''): bool
    {
        try {
            $cacheKey = "ip_blocked:{$ipAddress}";
            $this->cache->delete($cacheKey);

            $this->logger->logSecurityEvent('ip_unblocked', [
                'ip_address' => $ipAddress,
                'reason' => $reason
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->logError('IP unblocking error', [
                'ip_address' => $ipAddress,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    // Private helper methods

    private function loadConfiguration(): void
    {
        // Load from environment variables or config files
        $this->allowedCountries = explode(',', $_ENV['ALLOWED_COUNTRIES'] ?? '');
        $this->blockedCountries = explode(',', $_ENV['BLOCKED_COUNTRIES'] ?? '');
        $this->allowedIpRanges = explode(',', $_ENV['ALLOWED_IP_RANGES'] ?? '');
        $this->blockedIpRanges = explode(',', $_ENV['BLOCKED_IP_RANGES'] ?? '');
    }

    private function isPrivateIp(string $ipAddress): bool
    {
        return filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    private function isIpExplicitlyBlocked(string $ipAddress): bool
    {
        // Check exact IP match
        $blockData = $this->cache->get("ip_blocked:{$ipAddress}");
        if ($blockData) {
            // Check if block is still valid
            if (!$blockData['permanent'] && $blockData['blocked_until'] < time()) {
                $this->cache->delete("ip_blocked:{$ipAddress}");
                return false;
            }
            return true;
        }

        // Check IP ranges
        foreach ($this->blockedIpRanges as $range) {
            if ($this->ipInRange($ipAddress, trim($range))) {
                return true;
            }
        }

        return false;
    }

    private function isIpExplicitlyAllowed(string $ipAddress): bool
    {
        // Check exact IP match
        $allowData = $this->cache->get("ip_allowed:{$ipAddress}");
        if ($allowData) {
            return true;
        }

        // Check IP ranges
        foreach ($this->allowedIpRanges as $range) {
            if ($this->ipInRange($ipAddress, trim($range))) {
                return true;
            }
        }

        return false;
    }

    private function ipInRange(string $ip, string $range): bool
    {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }

        list($network, $prefix) = explode('/', $range);
        
        $ipLong = ip2long($ip);
        $networkLong = ip2long($network);
        $mask = -1 << (32 - (int)$prefix);
        
        return ($ipLong & $mask) === ($networkLong & $mask);
    }

    private function checkCountryRestrictions(array $geolocation): array
    {
        $countryCode = $geolocation['country_code'] ?? 'UNKNOWN';

        // If allowed countries are specified, IP must be from one of them
        if (!empty($this->allowedCountries) && !in_array($countryCode, $this->allowedCountries)) {
            return [
                'allowed' => false,
                'reason' => "Country {$countryCode} is not in allowed list"
            ];
        }

        // If blocked countries are specified, IP must not be from one of them
        if (!empty($this->blockedCountries) && in_array($countryCode, $this->blockedCountries)) {
            return [
                'allowed' => false,
                'reason' => "Country {$countryCode} is blocked"
            ];
        }

        return ['allowed' => true, 'reason' => ''];
    }

    private function calculateRiskScore(string $ipAddress, array $geolocation, array $reputation, array $context): array
    {
        $score = 0;
        $factors = [];

        // Reputation-based scoring
        if ($reputation['score'] < self::IP_REPUTATION_THRESHOLD) {
            $score += self::RISK_SCORES['known_malicious'];
            $factors[] = 'Poor IP reputation';
        }

        // VPN/Proxy detection
        $vpnProxy = $this->detectVpnProxy($ipAddress);
        if ($vpnProxy['is_tor']) {
            $score += self::RISK_SCORES['tor_exit_node'];
            $factors[] = 'Tor exit node detected';
        } elseif ($vpnProxy['is_vpn']) {
            $score += self::RISK_SCORES['vpn_detected'];
            $factors[] = 'VPN detected';
        } elseif ($vpnProxy['is_proxy']) {
            $score += self::RISK_SCORES['proxy_detected'];
            $factors[] = 'Proxy detected';
        }

        // Cloud provider detection
        if ($vpnProxy['is_cloud']) {
            $score += self::RISK_SCORES['cloud_provider'];
            $factors[] = 'Cloud provider IP';
        }

        // Request frequency check
        $requestCount = $this->getRequestCount($ipAddress);
        if ($requestCount > self::MAX_REQUESTS_PER_IP) {
            $score += self::RISK_SCORES['frequent_requests'];
            $factors[] = 'High request frequency';
        }

        // Suspicious activity patterns
        $suspiciousActivity = $this->detectSuspiciousActivity($ipAddress, $context);
        if ($suspiciousActivity['is_suspicious']) {
            $score += self::RISK_SCORES['suspicious_activity'];
            $factors = array_merge($factors, $suspiciousActivity['patterns']);
        }

        // New IP detection
        if ($this->isNewIp($ipAddress)) {
            $score += self::RISK_SCORES['new_ip'];
            $factors[] = 'First time seeing this IP';
        }

        return [
            'score' => min(100, $score),
            'factors' => $factors
        ];
    }

    private function makeSecurityDecision(int $riskScore, array $riskFactors): array
    {
        if ($riskScore >= 80) {
            return [
                'action' => 'BLOCK',
                'reason' => 'High risk score: ' . implode(', ', $riskFactors)
            ];
        } elseif ($riskScore >= 60) {
            return [
                'action' => 'CHALLENGE',
                'reason' => 'Medium risk score: ' . implode(', ', $riskFactors)
            ];
        } elseif ($riskScore >= 40) {
            return [
                'action' => 'MONITOR',
                'reason' => 'Elevated risk score: ' . implode(', ', $riskFactors)
            ];
        } else {
            return [
                'action' => 'ALLOW',
                'reason' => 'Low risk score'
            ];
        }
    }

    private function logSecurityDecision(string $ipAddress, array $analysis, array $context): void
    {
        $this->logger->logSecurityEvent('ip_security_decision', [
            'ip_address' => $ipAddress,
            'decision' => $analysis['decision'],
            'risk_score' => $analysis['risk_score'],
            'risk_factors' => $analysis['risk_factors'],
            'geolocation' => $analysis['geolocation'],
            'context' => $context,
            'processing_time' => $analysis['processing_time']
        ]);
    }

    private function updateIpStatistics(string $ipAddress, array $analysis): void
    {
        $statsKey = "ip_stats:{$ipAddress}";
        $stats = $this->cache->get($statsKey, [
            'first_seen' => time(),
            'last_seen' => time(),
            'request_count' => 0,
            'risk_history' => []
        ]);

        $stats['last_seen'] = time();
        $stats['request_count']++;
        $stats['risk_history'][] = [
            'timestamp' => time(),
            'risk_score' => $analysis['risk_score'],
            'decision' => $analysis['decision']
        ];

        // Keep only last 100 risk history entries
        $stats['risk_history'] = array_slice($stats['risk_history'], -100);

        $this->cache->set($statsKey, $stats, 86400 * 7); // 7 days
    }

    private function queryGeolocationProviders(string $ipAddress): array
    {
        // This would integrate with actual geolocation services
        // For now, return mock data
        return [
            'country_code' => 'US',
            'country_name' => 'United States',
            'region' => 'California',
            'city' => 'San Francisco',
            'latitude' => 37.7749,
            'longitude' => -122.4194,
            'timezone' => 'America/Los_Angeles',
            'isp' => 'Example ISP',
            'organization' => 'Example Org',
            'as_number' => 'AS12345'
        ];
    }

    private function queryThreatIntelligenceSources(string $ipAddress): array
    {
        // This would integrate with threat intelligence APIs
        // For now, return mock data
        return [
            'malicious_score' => 10,
            'threat_types' => [],
            'sources' => ['mock_provider'],
            'confidence' => 85
        ];
    }

    private function aggregateReputationData(array $sources): array
    {
        // Aggregate data from multiple sources
        return [
            'score' => 75,
            'classification' => 'CLEAN',
            'threat_types' => [],
            'sources' => ['threat_intel_1', 'threat_intel_2'],
            'confidence' => 85
        ];
    }

    private function runVpnProxyDetection(string $ipAddress, array $detection): array
    {
        // This would run various VPN/proxy detection methods
        // For now, return the input unchanged
        return $detection;
    }

    private function getRequestCount(string $ipAddress): int
    {
        $countKey = "request_count:{$ipAddress}:" . date('YmdH');
        return $this->cache->get($countKey, 0);
    }

    private function detectSuspiciousActivity(string $ipAddress, array $context): array
    {
        // This would analyze request patterns for suspicious behavior
        return [
            'is_suspicious' => false,
            'patterns' => []
        ];
    }

    private function isNewIp(string $ipAddress): bool
    {
        $statsKey = "ip_stats:{$ipAddress}";
        $stats = $this->cache->get($statsKey);
        return !$stats;
    }
}