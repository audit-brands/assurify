<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Security Monitoring Service
 * 
 * Comprehensive security monitoring and threat detection:
 * - Real-time attack detection (SQLi, XSS, CSRF)
 * - Behavioral anomaly detection
 * - IP reputation scoring and geolocation analysis
 * - Automated threat response and blocking
 * - Security event logging and forensics
 * - Vulnerability scanning and assessment
 */
class SecurityMonitorService
{
    private CacheService $cache;
    private RateLimitingService $rateLimit;
    private PerformanceMonitorService $monitor;
    private array $config;
    private array $threatPatterns;
    private array $trustedIPs;
    
    public function __construct(
        CacheService $cache = null,
        RateLimitingService $rateLimit = null,
        PerformanceMonitorService $monitor = null
    ) {
        $this->cache = $cache ?? new CacheService();
        $this->rateLimit = $rateLimit ?? new RateLimitingService();
        $this->monitor = $monitor ?? new PerformanceMonitorService();
        $this->config = $this->loadSecurityConfig();
        $this->threatPatterns = $this->loadThreatPatterns();
        $this->trustedIPs = $this->loadTrustedIPs();
    }
    
    /**
     * Analyze request for security threats
     */
    public function analyzeRequest(array $requestData): array
    {
        $startTime = microtime(true);
        
        try {
            $threats = [];
            $riskScore = 0.0;
            
            // SQL Injection Detection
            $sqlInjection = $this->detectSQLInjection($requestData);
            if ($sqlInjection['detected']) {
                $threats[] = $sqlInjection;
                $riskScore += $sqlInjection['risk_score'];
            }
            
            // XSS Detection
            $xssAttack = $this->detectXSSAttack($requestData);
            if ($xssAttack['detected']) {
                $threats[] = $xssAttack;
                $riskScore += $xssAttack['risk_score'];
            }
            
            // CSRF Detection
            $csrfAttack = $this->detectCSRFAttack($requestData);
            if ($csrfAttack['detected']) {
                $threats[] = $csrfAttack;
                $riskScore += $csrfAttack['risk_score'];
            }
            
            // Path Traversal Detection
            $pathTraversal = $this->detectPathTraversal($requestData);
            if ($pathTraversal['detected']) {
                $threats[] = $pathTraversal;
                $riskScore += $pathTraversal['risk_score'];
            }
            
            // Command Injection Detection
            $commandInjection = $this->detectCommandInjection($requestData);
            if ($commandInjection['detected']) {
                $threats[] = $commandInjection;
                $riskScore += $commandInjection['risk_score'];
            }
            
            // Behavioral Analysis
            $behaviorAnalysis = $this->analyzeBehavior($requestData);
            if ($behaviorAnalysis['suspicious']) {
                $threats[] = $behaviorAnalysis;
                $riskScore += $behaviorAnalysis['risk_score'];
            }
            
            // IP Reputation Check
            if (isset($requestData['ip'])) {
                $ipReputation = $this->checkIPReputation($requestData['ip']);
                if ($ipReputation['suspicious']) {
                    $threats[] = $ipReputation;
                    $riskScore += $ipReputation['risk_score'];
                }
            }
            
            // Determine threat level and response
            $threatLevel = $this->calculateThreatLevel($riskScore);
            $recommendedAction = $this->getRecommendedAction($threatLevel, $threats);
            
            $analysis = [
                'threats_detected' => !empty($threats),
                'threat_count' => count($threats),
                'threats' => $threats,
                'risk_score' => min($riskScore, 100.0), // Cap at 100
                'threat_level' => $threatLevel,
                'recommended_action' => $recommendedAction,
                'analysis_time' => microtime(true) - $startTime,
                'timestamp' => date('c')
            ];
            
            // Log security event if threats detected
            if (!empty($threats)) {
                $this->logSecurityEvent($requestData, $analysis);
            }
            
            // Monitor performance
            $this->monitor->recordMetric('security_analysis_time', $analysis['analysis_time'], [
                'threats_detected' => !empty($threats),
                'threat_level' => $threatLevel
            ]);
            
            return $analysis;
            
        } catch (\Exception $e) {
            error_log("Security analysis failed: " . $e->getMessage());
            
            // Return safe defaults on error
            return [
                'threats_detected' => false,
                'threat_count' => 0,
                'threats' => [],
                'risk_score' => 0.0,
                'threat_level' => 'unknown',
                'recommended_action' => 'allow',
                'analysis_time' => microtime(true) - $startTime,
                'timestamp' => date('c'),
                'error' => 'Security analysis system error'
            ];
        }
    }
    
    /**
     * SQL Injection Detection
     */
    private function detectSQLInjection(array $requestData): array
    {
        $suspicious = false;
        $patterns = [];
        $riskScore = 0.0;
        
        // Check all input parameters
        $allInputs = array_merge(
            $requestData['get'] ?? [],
            $requestData['post'] ?? [],
            $requestData['headers'] ?? [],
            ['url' => $requestData['url'] ?? '']
        );
        
        foreach ($allInputs as $key => $value) {
            if (!is_string($value)) continue;
            
            $value = strtolower($value);
            
            // Check for SQL injection patterns
            foreach ($this->threatPatterns['sql_injection'] as $pattern) {
                if (preg_match($pattern['regex'], $value)) {
                    $suspicious = true;
                    $patterns[] = [
                        'field' => $key,
                        'pattern' => $pattern['name'],
                        'risk_score' => $pattern['risk_score'],
                        'matched_text' => $this->extractMatchedText($value, $pattern['regex'])
                    ];
                    $riskScore += $pattern['risk_score'];
                }
            }
        }
        
        return [
            'detected' => $suspicious,
            'type' => 'sql_injection',
            'risk_score' => $riskScore,
            'patterns' => $patterns,
            'description' => 'SQL injection attempt detected',
            'severity' => $this->getSeverityFromScore($riskScore)
        ];
    }
    
    /**
     * XSS Attack Detection
     */
    private function detectXSSAttack(array $requestData): array
    {
        $suspicious = false;
        $patterns = [];
        $riskScore = 0.0;
        
        $allInputs = array_merge(
            $requestData['get'] ?? [],
            $requestData['post'] ?? [],
            $requestData['headers'] ?? []
        );
        
        foreach ($allInputs as $key => $value) {
            if (!is_string($value)) continue;
            
            $decodedValue = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
            $decodedValue = urldecode($decodedValue);
            
            foreach ($this->threatPatterns['xss'] as $pattern) {
                if (preg_match($pattern['regex'], $decodedValue, $matches)) {
                    $suspicious = true;
                    $patterns[] = [
                        'field' => $key,
                        'pattern' => $pattern['name'],
                        'risk_score' => $pattern['risk_score'],
                        'matched_text' => $matches[0] ?? ''
                    ];
                    $riskScore += $pattern['risk_score'];
                }
            }
        }
        
        return [
            'detected' => $suspicious,
            'type' => 'xss_attack',
            'risk_score' => $riskScore,
            'patterns' => $patterns,
            'description' => 'Cross-site scripting (XSS) attempt detected',
            'severity' => $this->getSeverityFromScore($riskScore)
        ];
    }
    
    /**
     * CSRF Attack Detection
     */
    private function detectCSRFAttack(array $requestData): array
    {
        $suspicious = false;
        $riskScore = 0.0;
        $reasons = [];
        
        // Skip CSRF checks for safe methods
        if (in_array($requestData['method'] ?? 'GET', ['GET', 'HEAD', 'OPTIONS'])) {
            return ['detected' => false, 'type' => 'csrf_attack', 'risk_score' => 0.0];
        }
        
        // Check for missing CSRF token
        $hasCSRFToken = isset($requestData['post']['_token']) || 
                       isset($requestData['headers']['x-csrf-token']) ||
                       isset($requestData['headers']['x-requested-with']);
        
        if (!$hasCSRFToken) {
            $suspicious = true;
            $riskScore += 30.0;
            $reasons[] = 'Missing CSRF token';
        }
        
        // Check referer header
        $referer = $requestData['headers']['referer'] ?? '';
        $host = $requestData['headers']['host'] ?? '';
        
        if (empty($referer) && !empty($host)) {
            $suspicious = true;
            $riskScore += 20.0;
            $reasons[] = 'Missing referer header';
        } elseif (!empty($referer) && !empty($host)) {
            $refererHost = parse_url($referer, PHP_URL_HOST);
            if ($refererHost !== $host) {
                $suspicious = true;
                $riskScore += 40.0;
                $reasons[] = 'Referer host mismatch';
            }
        }
        
        return [
            'detected' => $suspicious,
            'type' => 'csrf_attack',
            'risk_score' => $riskScore,
            'reasons' => $reasons,
            'description' => 'Potential CSRF attack detected',
            'severity' => $this->getSeverityFromScore($riskScore)
        ];
    }
    
    /**
     * Path Traversal Detection
     */
    private function detectPathTraversal(array $requestData): array
    {
        $suspicious = false;
        $patterns = [];
        $riskScore = 0.0;
        
        $allInputs = array_merge(
            $requestData['get'] ?? [],
            $requestData['post'] ?? [],
            ['url' => $requestData['url'] ?? '']
        );
        
        foreach ($allInputs as $key => $value) {
            if (!is_string($value)) continue;
            
            foreach ($this->threatPatterns['path_traversal'] as $pattern) {
                if (preg_match($pattern['regex'], $value)) {
                    $suspicious = true;
                    $patterns[] = [
                        'field' => $key,
                        'pattern' => $pattern['name'],
                        'risk_score' => $pattern['risk_score']
                    ];
                    $riskScore += $pattern['risk_score'];
                }
            }
        }
        
        return [
            'detected' => $suspicious,
            'type' => 'path_traversal',
            'risk_score' => $riskScore,
            'patterns' => $patterns,
            'description' => 'Path traversal attempt detected',
            'severity' => $this->getSeverityFromScore($riskScore)
        ];
    }
    
    /**
     * Command Injection Detection
     */
    private function detectCommandInjection(array $requestData): array
    {
        $suspicious = false;
        $patterns = [];
        $riskScore = 0.0;
        
        $allInputs = array_merge(
            $requestData['get'] ?? [],
            $requestData['post'] ?? []
        );
        
        foreach ($allInputs as $key => $value) {
            if (!is_string($value)) continue;
            
            foreach ($this->threatPatterns['command_injection'] as $pattern) {
                if (preg_match($pattern['regex'], $value)) {
                    $suspicious = true;
                    $patterns[] = [
                        'field' => $key,
                        'pattern' => $pattern['name'],
                        'risk_score' => $pattern['risk_score']
                    ];
                    $riskScore += $pattern['risk_score'];
                }
            }
        }
        
        return [
            'detected' => $suspicious,
            'type' => 'command_injection',
            'risk_score' => $riskScore,
            'patterns' => $patterns,
            'description' => 'Command injection attempt detected',
            'severity' => $this->getSeverityFromScore($riskScore)
        ];
    }
    
    /**
     * Behavioral Analysis
     */
    private function analyzeBehavior(array $requestData): array
    {
        $suspicious = false;
        $indicators = [];
        $riskScore = 0.0;
        
        $userAgent = $requestData['headers']['user-agent'] ?? '';
        $ip = $requestData['ip'] ?? '';
        
        // Check for bot-like behavior
        if ($this->isBotUserAgent($userAgent)) {
            $suspicious = true;
            $indicators[] = 'Bot-like user agent';
            $riskScore += 15.0;
        }
        
        // Check request frequency
        if (!empty($ip)) {
            $requestFrequency = $this->getRequestFrequency($ip);
            if ($requestFrequency > $this->config['max_requests_per_minute']) {
                $suspicious = true;
                $indicators[] = 'High request frequency';
                $riskScore += 25.0;
            }
        }
        
        // Check for suspicious patterns in request
        $requestPattern = $this->analyzeRequestPattern($requestData);
        if ($requestPattern['suspicious']) {
            $suspicious = true;
            $indicators = array_merge($indicators, $requestPattern['indicators']);
            $riskScore += $requestPattern['risk_score'];
        }
        
        return [
            'detected' => $suspicious,
            'suspicious' => $suspicious,
            'type' => 'behavioral_analysis',
            'risk_score' => $riskScore,
            'indicators' => $indicators,
            'description' => 'Suspicious behavioral patterns detected',
            'severity' => $this->getSeverityFromScore($riskScore)
        ];
    }
    
    /**
     * IP Reputation Check
     */
    public function checkIPReputation(string $ip): array
    {
        // Skip trusted IPs
        if (in_array($ip, $this->trustedIPs)) {
            return [
                'suspicious' => false,
                'type' => 'ip_reputation',
                'risk_score' => 0.0,
                'reputation' => 'trusted'
            ];
        }
        
        $cacheKey = "ip_reputation_{$ip}";
        
        if ($cached = $this->cache->get($cacheKey)) {
            return $cached;
        }
        
        $reputation = [
            'suspicious' => false,
            'type' => 'ip_reputation',
            'risk_score' => 0.0,
            'reputation' => 'unknown',
            'indicators' => [],
            'geolocation' => $this->getIPGeolocation($ip),
            'history' => $this->getIPHistory($ip)
        ];
        
        // Check against threat intelligence feeds
        $threatIntel = $this->checkThreatIntelligence($ip);
        if ($threatIntel['suspicious']) {
            $reputation['suspicious'] = true;
            $reputation['risk_score'] += 50.0;
            $reputation['indicators'][] = 'Listed in threat intelligence feed';
            $reputation['reputation'] = 'malicious';
        }
        
        // Check for recent security events
        $recentEvents = $this->getRecentSecurityEvents($ip);
        if ($recentEvents['count'] > $this->config['max_security_events_per_hour']) {
            $reputation['suspicious'] = true;
            $reputation['risk_score'] += 30.0;
            $reputation['indicators'][] = 'Multiple recent security events';
        }
        
        // Geolocation analysis
        if ($reputation['geolocation']['high_risk_country']) {
            $reputation['risk_score'] += 10.0;
            $reputation['indicators'][] = 'High-risk geographic location';
        }
        
        // Cache the result
        $this->cache->set($cacheKey, $reputation, 1800); // 30 minutes
        
        return $reputation;
    }
    
    /**
     * Log security event
     */
    public function logSecurityEvent(array $requestData, array $analysis): bool
    {
        try {
            $event = [
                'event_type' => 'security_threat',
                'severity' => $analysis['threat_level'],
                'source_ip' => $requestData['ip'] ?? null,
                'user_id' => $requestData['user_id'] ?? null,
                'user_agent' => $requestData['headers']['user-agent'] ?? null,
                'request_data' => json_encode($this->sanitizeRequestData($requestData)),
                'threat_indicators' => json_encode($analysis['threats']),
                'response_action' => $analysis['recommended_action'],
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            DB::table('security_events')->insert($event);
            
            // Create alert for high-risk events
            if ($analysis['threat_level'] === 'critical') {
                $this->createSecurityAlert($event, $analysis);
            }
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Security event logging failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get security dashboard data
     */
    public function getSecurityDashboard(): array
    {
        $cacheKey = 'security_dashboard';
        
        if ($cached = $this->cache->get($cacheKey)) {
            return $cached;
        }
        
        $dashboard = [
            'threat_summary' => $this->getThreatSummary(),
            'recent_events' => $this->getRecentSecurityEvents(),
            'top_threats' => $this->getTopThreats(),
            'blocked_ips' => $this->getBlockedIPs(),
            'security_metrics' => $this->getSecurityMetrics(),
            'alert_status' => $this->getAlertStatus(),
            'generated_at' => date('c')
        ];
        
        $this->cache->set($cacheKey, $dashboard, 300); // Cache for 5 minutes
        
        return $dashboard;
    }
    
    // Private helper methods
    
    private function loadSecurityConfig(): array
    {
        return [
            'max_requests_per_minute' => 60,
            'max_security_events_per_hour' => 10,
            'threat_score_threshold' => 50.0,
            'auto_block_threshold' => 80.0,
            'log_all_requests' => false,
            'enable_geolocation' => true
        ];
    }
    
    private function loadThreatPatterns(): array
    {
        return [
            'sql_injection' => [
                ['name' => 'union_select', 'regex' => '/union\s+select/i', 'risk_score' => 40.0],
                ['name' => 'or_1_equals_1', 'regex' => '/or\s+1\s*=\s*1/i', 'risk_score' => 35.0],
                ['name' => 'drop_table', 'regex' => '/drop\s+table/i', 'risk_score' => 50.0],
                ['name' => 'exec', 'regex' => '/exec\s*\(/i', 'risk_score' => 45.0],
                ['name' => 'script_tag', 'regex' => '/<script/i', 'risk_score' => 30.0]
            ],
            'xss' => [
                ['name' => 'script_tag', 'regex' => '/<script[^>]*>.*?<\/script>/is', 'risk_score' => 40.0],
                ['name' => 'javascript_url', 'regex' => '/javascript\s*:/i', 'risk_score' => 35.0],
                ['name' => 'on_event', 'regex' => '/\son\w+\s*=/i', 'risk_score' => 30.0],
                ['name' => 'eval_function', 'regex' => '/eval\s*\(/i', 'risk_score' => 45.0]
            ],
            'path_traversal' => [
                ['name' => 'dot_dot_slash', 'regex' => '/\.\.\//', 'risk_score' => 35.0],
                ['name' => 'dot_dot_backslash', 'regex' => '/\.\.\\\/', 'risk_score' => 35.0],
                ['name' => 'etc_passwd', 'regex' => '/\/etc\/passwd/', 'risk_score' => 50.0]
            ],
            'command_injection' => [
                ['name' => 'semicolon_command', 'regex' => '/;\s*\w+/', 'risk_score' => 40.0],
                ['name' => 'pipe_command', 'regex' => '/\|\s*\w+/', 'risk_score' => 35.0],
                ['name' => 'backtick', 'regex' => '/`[^`]+`/', 'risk_score' => 45.0]
            ]
        ];
    }
    
    private function loadTrustedIPs(): array
    {
        return ['127.0.0.1', '::1']; // localhost IPs
    }
    
    private function calculateThreatLevel(float $riskScore): string
    {
        if ($riskScore >= 80) return 'critical';
        if ($riskScore >= 50) return 'high';
        if ($riskScore >= 25) return 'medium';
        if ($riskScore > 0) return 'low';
        return 'none';
    }
    
    private function getRecommendedAction(string $threatLevel, array $threats): string
    {
        switch ($threatLevel) {
            case 'critical':
                return 'block';
            case 'high':
                return 'challenge';
            case 'medium':
                return 'monitor';
            default:
                return 'allow';
        }
    }
    
    private function getSeverityFromScore(float $score): string
    {
        if ($score >= 50) return 'high';
        if ($score >= 25) return 'medium';
        if ($score > 0) return 'low';
        return 'info';
    }
    
    // Mock implementations (would be implemented with real data and external services)
    private function extractMatchedText(string $value, string $regex): string { return ''; }
    private function isBotUserAgent(string $userAgent): bool { return false; }
    private function getRequestFrequency(string $ip): int { return 0; }
    private function analyzeRequestPattern(array $requestData): array { return ['suspicious' => false, 'indicators' => [], 'risk_score' => 0.0]; }
    private function getIPGeolocation(string $ip): array { return ['high_risk_country' => false]; }
    private function getIPHistory(string $ip): array { return []; }
    private function checkThreatIntelligence(string $ip): array { return ['suspicious' => false]; }
    private function getRecentSecurityEvents(string $ip = null): array { return ['count' => 0]; }
    private function sanitizeRequestData(array $requestData): array { return $requestData; }
    private function createSecurityAlert(array $event, array $analysis): void {}
    private function getThreatSummary(): array { return []; }
    private function getTopThreats(): array { return []; }
    private function getBlockedIPs(): array { return []; }
    private function getSecurityMetrics(): array { return []; }
    private function getAlertStatus(): array { return []; }
}