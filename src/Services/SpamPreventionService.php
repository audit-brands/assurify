<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Spam and Abuse Prevention Service
 * 
 * Comprehensive anti-spam and abuse prevention system:
 * - Multi-layered spam detection (content, behavior, reputation)
 * - Real-time abuse prevention and auto-blocking
 * - Honeypot and bot detection mechanisms
 * - Community-based reporting and moderation
 * - Shadow banning and graduated response system
 * - Integration with external anti-spam services
 */
class SpamPreventionService
{
    private ContentModerationService $moderation;
    private SecurityMonitorService $security;
    private RateLimitingService $rateLimit;
    private CacheService $cache;
    private array $config;
    private array $honeypots;
    private array $spamIndicators;
    
    public function __construct(
        ContentModerationService $moderation = null,
        SecurityMonitorService $security = null,
        RateLimitingService $rateLimit = null,
        CacheService $cache = null
    ) {
        $this->moderation = $moderation ?? new ContentModerationService();
        $this->security = $security ?? new SecurityMonitorService();
        $this->rateLimit = $rateLimit ?? new RateLimitingService();
        $this->cache = $cache ?? new CacheService();
        $this->config = $this->loadSpamConfig();
        $this->honeypots = $this->initializeHoneypots();
        $this->spamIndicators = $this->loadSpamIndicators();
    }
    
    /**
     * Comprehensive spam check for content submissions
     */
    public function checkContentForSpam(
        string $content,
        string $contentType,
        int $userId,
        array $context = []
    ): array {
        $startTime = microtime(true);
        
        try {
            $spamScore = 0.0;
            $indicators = [];
            $blockReasons = [];
            
            // 1. Content analysis
            $contentAnalysis = $this->analyzeContentSpam($content);
            $spamScore += $contentAnalysis['score'];
            $indicators = array_merge($indicators, $contentAnalysis['indicators']);
            
            // 2. User behavior analysis
            $behaviorAnalysis = $this->analyzeBehaviorSpam($userId, $context);
            $spamScore += $behaviorAnalysis['score'];
            $indicators = array_merge($indicators, $behaviorAnalysis['indicators']);
            
            // 3. IP reputation check
            if (isset($context['ip'])) {
                $ipAnalysis = $this->analyzeIPSpam($context['ip']);
                $spamScore += $ipAnalysis['score'];
                $indicators = array_merge($indicators, $ipAnalysis['indicators']);
            }
            
            // 4. Honeypot detection
            $honeypotDetection = $this->checkHoneypots($context);
            if ($honeypotDetection['triggered']) {
                $spamScore += 100.0; // Instant spam
                $indicators[] = 'Honeypot triggered';
                $blockReasons[] = 'Bot detected via honeypot';
            }
            
            // 5. Velocity and frequency checks
            $velocityAnalysis = $this->analyzeVelocitySpam($userId, $contentType);
            $spamScore += $velocityAnalysis['score'];
            $indicators = array_merge($indicators, $velocityAnalysis['indicators']);
            
            // 6. Duplicate content detection
            $duplicateAnalysis = $this->analyzeDuplicateSpam($content, $contentType, $userId);
            $spamScore += $duplicateAnalysis['score'];
            $indicators = array_merge($indicators, $duplicateAnalysis['indicators']);
            
            // 7. External service checks (if enabled)
            if ($this->config['external_services_enabled']) {
                $externalAnalysis = $this->checkExternalSpamServices($content, $context);
                $spamScore += $externalAnalysis['score'];
                $indicators = array_merge($indicators, $externalAnalysis['indicators']);
            }
            
            // Calculate final decision
            $decision = $this->calculateSpamDecision($spamScore, $indicators, $userId);
            
            // Apply graduated response if needed
            if ($decision['is_spam']) {
                $response = $this->applyGraduatedResponse($userId, $spamScore, $decision);
                $decision['response_applied'] = $response;
            }
            
            $result = [
                'is_spam' => $decision['is_spam'],
                'spam_score' => min($spamScore, 100.0),
                'confidence' => $decision['confidence'],
                'action' => $decision['action'],
                'indicators' => $indicators,
                'block_reasons' => $blockReasons,
                'analysis_details' => [
                    'content' => $contentAnalysis,
                    'behavior' => $behaviorAnalysis,
                    'ip' => $ipAnalysis ?? null,
                    'velocity' => $velocityAnalysis,
                    'duplicates' => $duplicateAnalysis
                ],
                'processing_time' => microtime(true) - $startTime,
                'timestamp' => date('c')
            ];
            
            // Log spam event if detected
            if ($decision['is_spam']) {
                $this->logSpamEvent($userId, $contentType, $result, $context);
            }
            
            return $result;
            
        } catch (\Exception $e) {
            error_log("Spam check failed: " . $e->getMessage());
            
            // Fail safe - allow content but flag for review
            return [
                'is_spam' => false,
                'spam_score' => 0.0,
                'confidence' => 0.0,
                'action' => 'flag_for_review',
                'indicators' => ['System error during spam check'],
                'block_reasons' => [],
                'analysis_details' => [],
                'processing_time' => microtime(true) - $startTime,
                'timestamp' => date('c'),
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check if user should be blocked for abuse
     */
    public function checkUserForAbuse(int $userId, array $context = []): array
    {
        try {
            $abuseScore = 0.0;
            $violations = [];
            
            // Check spam history
            $spamHistory = $this->getUserSpamHistory($userId);
            if ($spamHistory['spam_ratio'] > $this->config['user_spam_threshold']) {
                $abuseScore += 40.0;
                $violations[] = 'High spam content ratio';
            }
            
            // Check rate limiting violations
            $rateLimitViolations = $this->getRateLimitViolations($userId);
            if ($rateLimitViolations['count'] > $this->config['max_rate_violations']) {
                $abuseScore += 30.0;
                $violations[] = 'Excessive rate limit violations';
            }
            
            // Check security events
            $securityEvents = $this->getSecurityEvents($userId);
            if ($securityEvents['count'] > $this->config['max_security_events']) {
                $abuseScore += 35.0;
                $violations[] = 'Multiple security violations';
            }
            
            // Check community reports
            $communityReports = $this->getCommunityReports($userId);
            if ($communityReports['count'] > $this->config['max_community_reports']) {
                $abuseScore += 25.0;
                $violations[] = 'Multiple community reports';
            }
            
            // Check for coordinated attacks
            $coordinatedAttack = $this->detectCoordinatedAttack($userId, $context);
            if ($coordinatedAttack['detected']) {
                $abuseScore += 50.0;
                $violations[] = 'Potential coordinated attack';
            }
            
            $shouldBlock = $abuseScore >= $this->config['abuse_block_threshold'];
            $action = $this->determineAbuseAction($abuseScore, $violations);
            
            return [
                'should_block' => $shouldBlock,
                'abuse_score' => min($abuseScore, 100.0),
                'violations' => $violations,
                'action' => $action,
                'history' => [
                    'spam' => $spamHistory,
                    'rate_limits' => $rateLimitViolations,
                    'security' => $securityEvents,
                    'reports' => $communityReports
                ],
                'coordinated_attack' => $coordinatedAttack
            ];
            
        } catch (\Exception $e) {
            error_log("Abuse check failed: " . $e->getMessage());
            return [
                'should_block' => false,
                'abuse_score' => 0.0,
                'violations' => [],
                'action' => 'monitor',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Apply graduated response to spam/abuse
     */
    private function applyGraduatedResponse(int $userId, float $spamScore, array $decision): array
    {
        $response = [];
        
        // Get user's spam history
        $history = $this->getUserSpamHistory($userId);
        $previousViolations = $history['total_violations'];
        
        // Determine response level
        if ($spamScore >= 90.0 || $previousViolations >= 5) {
            // Level 5: Permanent ban
            $response = $this->applyPermanentBan($userId, 'Severe spam violations');
        } elseif ($spamScore >= 80.0 || $previousViolations >= 3) {
            // Level 4: Temporary ban (7 days)
            $response = $this->applyTemporaryBan($userId, 7 * 24 * 60, 'Repeated spam violations');
        } elseif ($spamScore >= 70.0 || $previousViolations >= 2) {
            // Level 3: Shadow ban (3 days)
            $response = $this->applyShadowBan($userId, 3 * 24 * 60, 'Multiple spam attempts');
        } elseif ($spamScore >= 60.0 || $previousViolations >= 1) {
            // Level 2: Rate limiting (24 hours)
            $response = $this->applyStrictRateLimit($userId, 24 * 60, 'Spam content detected');
        } else {
            // Level 1: Content quarantine
            $response = $this->applyContentQuarantine($userId, 60, 'Suspicious content');
        }
        
        // Record the violation
        $this->recordSpamViolation($userId, $spamScore, $response['action']);
        
        return $response;
    }
    
    /**
     * Analyze content for spam indicators
     */
    private function analyzeContentSpam(string $content): array
    {
        $score = 0.0;
        $indicators = [];
        
        // Check for spam keywords
        foreach ($this->spamIndicators['keywords'] as $keyword => $weight) {
            if (stripos($content, $keyword) !== false) {
                $score += $weight;
                $indicators[] = "Contains spam keyword: {$keyword}";
            }
        }
        
        // Check for excessive URLs
        $urlCount = preg_match_all('/https?:\/\/[^\s]+/', $content);
        if ($urlCount > $this->config['max_urls_per_content']) {
            $score += 20.0;
            $indicators[] = "Excessive URLs ({$urlCount})";
        }
        
        // Check for suspicious patterns
        $patterns = $this->spamIndicators['patterns'];
        foreach ($patterns as $pattern => $weight) {
            if (preg_match($pattern, $content)) {
                $score += $weight;
                $indicators[] = "Matches spam pattern";
            }
        }
        
        // Check content length and quality
        $contentLength = strlen($content);
        if ($contentLength < $this->config['min_content_length']) {
            $score += 15.0;
            $indicators[] = "Content too short";
        }
        
        // Check for repetitive content
        $repetitionScore = $this->calculateRepetition($content);
        if ($repetitionScore > $this->config['max_repetition_threshold']) {
            $score += 25.0;
            $indicators[] = "Repetitive content detected";
        }
        
        return [
            'score' => $score,
            'indicators' => $indicators,
            'url_count' => $urlCount,
            'content_length' => $contentLength,
            'repetition_score' => $repetitionScore
        ];
    }
    
    /**
     * Analyze user behavior for spam patterns
     */
    private function analyzeBehaviorSpam(int $userId, array $context): array
    {
        $score = 0.0;
        $indicators = [];
        
        // Check account age
        $accountAge = $this->getUserAccountAge($userId);
        if ($accountAge < $this->config['new_account_threshold']) {
            $score += 15.0;
            $indicators[] = "New account (less than {$this->config['new_account_threshold']} hours)";
        }
        
        // Check posting velocity
        $velocity = $this->getUserPostingVelocity($userId);
        if ($velocity > $this->config['max_posting_velocity']) {
            $score += 20.0;
            $indicators[] = "High posting velocity ({$velocity} posts/hour)";
        }
        
        // Check for bot-like behavior
        $botScore = $this->calculateBotLikeScore($userId, $context);
        if ($botScore > $this->config['bot_threshold']) {
            $score += 30.0;
            $indicators[] = "Bot-like behavior detected";
        }
        
        // Check user engagement
        $engagement = $this->getUserEngagement($userId);
        if ($engagement['ratio'] < $this->config['min_engagement_ratio']) {
            $score += 10.0;
            $indicators[] = "Low user engagement";
        }
        
        return [
            'score' => $score,
            'indicators' => $indicators,
            'account_age' => $accountAge,
            'velocity' => $velocity,
            'bot_score' => $botScore,
            'engagement' => $engagement
        ];
    }
    
    /**
     * Community reporting system
     */
    public function reportAbuse(
        int $reporterId,
        string $contentType,
        int $contentId,
        string $reason,
        string $description = ''
    ): array {
        try {
            // Validate reporter
            if (!$this->isValidReporter($reporterId)) {
                return ['success' => false, 'error' => 'Invalid reporter'];
            }
            
            // Check for duplicate reports
            if ($this->isDuplicateReport($reporterId, $contentType, $contentId)) {
                return ['success' => false, 'error' => 'Already reported'];
            }
            
            // Create report
            $reportId = DB::table('abuse_reports')->insertGetId([
                'reporter_id' => $reporterId,
                'content_type' => $contentType,
                'content_id' => $contentId,
                'reason' => $reason,
                'description' => $description,
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Auto-escalate if multiple reports
            $reportCount = $this->getContentReportCount($contentType, $contentId);
            if ($reportCount >= $this->config['auto_escalate_threshold']) {
                $this->autoEscalateContent($contentType, $contentId);
            }
            
            return [
                'success' => true,
                'report_id' => $reportId,
                'total_reports' => $reportCount
            ];
            
        } catch (\Exception $e) {
            error_log("Abuse report failed: " . $e->getMessage());
            return ['success' => false, 'error' => 'Report submission failed'];
        }
    }
    
    /**
     * Get anti-spam statistics
     */
    public function getStatistics(string $period = '24h'): array
    {
        $cacheKey = "spam_stats_{$period}";
        
        if ($cached = $this->cache->get($cacheKey)) {
            return $cached;
        }
        
        $stats = [
            'period' => $period,
            'spam_detected' => $this->getSpamDetected($period),
            'users_blocked' => $this->getUsersBlocked($period),
            'content_quarantined' => $this->getContentQuarantined($period),
            'community_reports' => $this->getCommunityReportsCount($period),
            'honeypot_triggers' => $this->getHoneypotTriggers($period),
            'false_positives' => $this->getFalsePositives($period),
            'accuracy_rate' => $this->calculateAccuracyRate($period),
            'top_spam_indicators' => $this->getTopSpamIndicators($period),
            'geographic_distribution' => $this->getGeographicSpamDistribution($period)
        ];
        
        $this->cache->set($cacheKey, $stats, 300); // Cache for 5 minutes
        
        return $stats;
    }
    
    // Private helper methods
    
    private function loadSpamConfig(): array
    {
        return [
            'spam_threshold' => 60.0,
            'abuse_block_threshold' => 70.0,
            'user_spam_threshold' => 0.3,
            'max_rate_violations' => 5,
            'max_security_events' => 3,
            'max_community_reports' => 3,
            'max_urls_per_content' => 3,
            'min_content_length' => 10,
            'max_repetition_threshold' => 0.7,
            'new_account_threshold' => 24, // hours
            'max_posting_velocity' => 10, // posts per hour
            'bot_threshold' => 0.8,
            'min_engagement_ratio' => 0.1,
            'auto_escalate_threshold' => 3,
            'external_services_enabled' => false,
            'honeypot_enabled' => true
        ];
    }
    
    private function initializeHoneypots(): array
    {
        return [
            'hidden_fields' => ['honeypot', 'url', 'website', 'homepage'],
            'time_traps' => ['min_submit_time' => 3, 'max_submit_time' => 3600],
            'mouse_tracking' => true,
            'javascript_checks' => true
        ];
    }
    
    private function loadSpamIndicators(): array
    {
        return [
            'keywords' => [
                'click here' => 15.0,
                'free money' => 20.0,
                'viagra' => 25.0,
                'casino' => 20.0,
                'lottery' => 18.0,
                'earn money' => 15.0,
                'work from home' => 12.0
            ],
            'patterns' => [
                '/\$\d+/' => 10.0, // Money amounts
                '/bit\.ly|tinyurl|goo\.gl/' => 15.0, // URL shorteners
                '/\b[A-Z]{3,}\b/' => 8.0, // ALL CAPS words
                '/\d{10,}/' => 12.0 // Long numbers (phone, etc)
            ]
        ];
    }
    
    private function calculateSpamDecision(float $spamScore, array $indicators, int $userId): array
    {
        $isSpam = $spamScore >= $this->config['spam_threshold'];
        $confidence = min($spamScore / 100.0, 1.0);
        
        // Adjust based on user reputation
        $userReputation = $this->getUserReputation($userId);
        if ($userReputation > 80.0) {
            $confidence *= 0.8; // Reduce confidence for trusted users
        } elseif ($userReputation < 20.0) {
            $confidence *= 1.2; // Increase confidence for untrusted users
        }
        
        $action = $this->determineSpamAction($spamScore, $confidence);
        
        return [
            'is_spam' => $isSpam,
            'confidence' => min($confidence, 1.0),
            'action' => $action
        ];
    }
    
    private function determineSpamAction(float $score, float $confidence): string
    {
        if ($score >= 90.0 && $confidence >= 0.9) {
            return 'block';
        } elseif ($score >= 80.0 && $confidence >= 0.8) {
            return 'shadow_ban';
        } elseif ($score >= 70.0 && $confidence >= 0.7) {
            return 'rate_limit';
        } elseif ($score >= 60.0) {
            return 'quarantine';
        } else {
            return 'flag_for_review';
        }
    }
    
    private function determineAbuseAction(float $score, array $violations): string
    {
        if ($score >= 90.0) {
            return 'permanent_ban';
        } elseif ($score >= 80.0) {
            return 'temporary_ban';
        } elseif ($score >= 70.0) {
            return 'shadow_ban';
        } elseif ($score >= 60.0) {
            return 'strict_rate_limit';
        } else {
            return 'monitor';
        }
    }
    
    // Mock implementations for complex features
    private function analyzeIPSpam(string $ip): array { return ['score' => 0.0, 'indicators' => []]; }
    private function checkHoneypots(array $context): array { return ['triggered' => false]; }
    private function analyzeVelocitySpam(int $userId, string $contentType): array { return ['score' => 0.0, 'indicators' => []]; }
    private function analyzeDuplicateSpam(string $content, string $contentType, int $userId): array { return ['score' => 0.0, 'indicators' => []]; }
    private function checkExternalSpamServices(string $content, array $context): array { return ['score' => 0.0, 'indicators' => []]; }
    private function getUserSpamHistory(int $userId): array { return ['spam_ratio' => 0.0, 'total_violations' => 0]; }
    private function getRateLimitViolations(int $userId): array { return ['count' => 0]; }
    private function getSecurityEvents(int $userId): array { return ['count' => 0]; }
    private function getCommunityReports(int $userId): array { return ['count' => 0]; }
    private function detectCoordinatedAttack(int $userId, array $context): array { return ['detected' => false]; }
    private function calculateRepetition(string $content): float { return 0.0; }
    private function getUserAccountAge(int $userId): int { return 168; } // 1 week in hours
    private function getUserPostingVelocity(int $userId): int { return 1; }
    private function calculateBotLikeScore(int $userId, array $context): float { return 0.0; }
    private function getUserEngagement(int $userId): array { return ['ratio' => 0.5]; }
    private function getUserReputation(int $userId): float { return 50.0; }
    private function applyPermanentBan(int $userId, string $reason): array { return ['action' => 'permanent_ban', 'reason' => $reason]; }
    private function applyTemporaryBan(int $userId, int $minutes, string $reason): array { return ['action' => 'temporary_ban', 'duration' => $minutes, 'reason' => $reason]; }
    private function applyShadowBan(int $userId, int $minutes, string $reason): array { return ['action' => 'shadow_ban', 'duration' => $minutes, 'reason' => $reason]; }
    private function applyStrictRateLimit(int $userId, int $minutes, string $reason): array { return ['action' => 'rate_limit', 'duration' => $minutes, 'reason' => $reason]; }
    private function applyContentQuarantine(int $userId, int $minutes, string $reason): array { return ['action' => 'quarantine', 'duration' => $minutes, 'reason' => $reason]; }
    private function recordSpamViolation(int $userId, float $score, string $action): void {}
    private function logSpamEvent(int $userId, string $contentType, array $result, array $context): void {}
    private function isValidReporter(int $reporterId): bool { return true; }
    private function isDuplicateReport(int $reporterId, string $contentType, int $contentId): bool { return false; }
    private function getContentReportCount(string $contentType, int $contentId): int { return 1; }
    private function autoEscalateContent(string $contentType, int $contentId): void {}
    private function getSpamDetected(string $period): int { return 0; }
    private function getUsersBlocked(string $period): int { return 0; }
    private function getContentQuarantined(string $period): int { return 0; }
    private function getCommunityReportsCount(string $period): int { return 0; }
    private function getHoneypotTriggers(string $period): int { return 0; }
    private function getFalsePositives(string $period): int { return 0; }
    private function calculateAccuracyRate(string $period): float { return 95.0; }
    private function getTopSpamIndicators(string $period): array { return []; }
    private function getGeographicSpamDistribution(string $period): array { return []; }
}