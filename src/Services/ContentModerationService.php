<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Content Moderation Service
 * 
 * AI-powered content moderation and spam detection:
 * - Automated spam detection using ML models
 * - Toxicity and hate speech analysis
 * - Duplicate content detection and fingerprinting
 * - Malicious link and phishing detection
 * - Image content moderation (NSFW detection)
 * - Human moderation workflow management
 * - Community-driven moderation support
 */
class ContentModerationService
{
    private CacheService $cache;
    private SecurityMonitorService $security;
    private array $config;
    private array $spamPatterns;
    private array $toxicityPatterns;
    
    public function __construct(
        CacheService $cache = null,
        SecurityMonitorService $security = null
    ) {
        $this->cache = $cache ?? new CacheService();
        $this->security = $security ?? new SecurityMonitorService();
        $this->config = $this->loadModerationConfig();
        $this->spamPatterns = $this->loadSpamPatterns();
        $this->toxicityPatterns = $this->loadToxicityPatterns();
    }
    
    /**
     * Moderate content (story or comment)
     */
    public function moderateContent(
        string $contentType,
        int $contentId,
        string $content,
        int $userId,
        array $context = []
    ): array {
        $startTime = microtime(true);
        
        try {
            // Perform AI analysis
            $aiAnalysis = $this->performAIAnalysis($content, $contentType, $context);
            
            // Calculate overall moderation decision
            $decision = $this->calculateModerationDecision($aiAnalysis, $userId, $context);
            
            // Queue for human review if needed
            if ($decision['action'] === 'flag_for_review') {
                $this->queueForHumanReview($contentType, $contentId, $userId, $aiAnalysis, $decision);
            }
            
            // Update user reputation based on content quality
            $this->updateUserReputation($userId, $decision);
            
            $result = [
                'content_type' => $contentType,
                'content_id' => $contentId,
                'user_id' => $userId,
                'action' => $decision['action'],
                'confidence' => $decision['confidence'],
                'ai_analysis' => $aiAnalysis,
                'human_review_required' => $decision['action'] === 'flag_for_review',
                'moderation_time' => microtime(true) - $startTime,
                'timestamp' => date('c')
            ];
            
            // Log moderation event
            $this->logModerationEvent($result);
            
            return $result;
            
        } catch (\Exception $e) {
            error_log("Content moderation failed: " . $e->getMessage());
            
            // Fail safe - flag for human review
            return [
                'content_type' => $contentType,
                'content_id' => $contentId,
                'user_id' => $userId,
                'action' => 'flag_for_review',
                'confidence' => 0.0,
                'ai_analysis' => ['error' => 'Moderation system error'],
                'human_review_required' => true,
                'moderation_time' => microtime(true) - $startTime,
                'timestamp' => date('c'),
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Perform comprehensive AI analysis on content
     */
    private function performAIAnalysis(string $content, string $contentType, array $context): array
    {
        $analysis = [
            'spam_analysis' => $this->analyzeSpam($content, $context),
            'toxicity_analysis' => $this->analyzeToxicity($content),
            'sentiment_analysis' => $this->analyzeSentiment($content),
            'language_detection' => $this->detectLanguage($content),
            'topic_classification' => $this->classifyTopics($content),
            'duplicate_detection' => $this->detectDuplicates($content, $contentType),
            'link_analysis' => $this->analyzeLinks($content),
            'readability_score' => $this->calculateReadability($content),
            'content_quality' => $this->assessContentQuality($content, $contentType)
        ];
        
        // Add image analysis if content contains images
        if ($this->containsImages($content)) {
            $analysis['image_moderation'] = $this->moderateImages($content);
        }
        
        return $analysis;
    }
    
    /**
     * Spam detection using pattern matching and ML
     */
    private function analyzeSpam(string $content, array $context): array
    {
        $spamScore = 0.0;
        $indicators = [];
        
        // Pattern-based detection
        foreach ($this->spamPatterns as $pattern) {
            if (preg_match($pattern['regex'], $content)) {
                $spamScore += $pattern['weight'];
                $indicators[] = $pattern['description'];
            }
        }
        
        // Statistical analysis
        $stats = $this->getContentStatistics($content);
        
        // Check for excessive URLs
        if ($stats['url_count'] > $this->config['max_urls_per_content']) {
            $spamScore += 25.0;
            $indicators[] = 'Excessive URLs';
        }
        
        // Check for excessive capitalization
        if ($stats['caps_ratio'] > $this->config['max_caps_ratio']) {
            $spamScore += 15.0;
            $indicators[] = 'Excessive capitalization';
        }
        
        // Check for repetitive content
        if ($stats['repetition_score'] > $this->config['max_repetition_score']) {
            $spamScore += 20.0;
            $indicators[] = 'Repetitive content';
        }
        
        // User history analysis
        $userSpamHistory = $this->getUserSpamHistory($context['user_id'] ?? 0);
        if ($userSpamHistory['spam_ratio'] > $this->config['user_spam_threshold']) {
            $spamScore += 30.0;
            $indicators[] = 'User has spam history';
        }
        
        // Velocity check
        $contentVelocity = $this->getContentVelocity($context['user_id'] ?? 0);
        if ($contentVelocity > $this->config['max_content_velocity']) {
            $spamScore += 20.0;
            $indicators[] = 'High content creation velocity';
        }
        
        return [
            'is_spam' => $spamScore >= $this->config['spam_threshold'],
            'spam_score' => min($spamScore, 100.0),
            'confidence' => $this->calculateConfidence($spamScore, 'spam'),
            'indicators' => $indicators,
            'statistics' => $stats
        ];
    }
    
    /**
     * Toxicity and hate speech detection
     */
    private function analyzeToxicity(string $content): array
    {
        $toxicityScore = 0.0;
        $categories = [];
        $indicators = [];
        
        // Pattern-based detection
        foreach ($this->toxicityPatterns as $category => $patterns) {
            $categoryScore = 0.0;
            $categoryIndicators = [];
            
            foreach ($patterns as $pattern) {
                if (preg_match($pattern['regex'], $content)) {
                    $categoryScore += $pattern['weight'];
                    $categoryIndicators[] = $pattern['description'];
                }
            }
            
            if ($categoryScore > 0) {
                $categories[$category] = [
                    'score' => $categoryScore,
                    'indicators' => $categoryIndicators
                ];
                $toxicityScore += $categoryScore;
                $indicators = array_merge($indicators, $categoryIndicators);
            }
        }
        
        // Context analysis
        $contextualToxicity = $this->analyzeContextualToxicity($content);
        $toxicityScore += $contextualToxicity['score'];
        
        return [
            'is_toxic' => $toxicityScore >= $this->config['toxicity_threshold'],
            'toxicity_score' => min($toxicityScore, 100.0),
            'confidence' => $this->calculateConfidence($toxicityScore, 'toxicity'),
            'categories' => $categories,
            'indicators' => $indicators,
            'contextual_analysis' => $contextualToxicity
        ];
    }
    
    /**
     * Sentiment analysis
     */
    private function analyzeSentiment(string $content): array
    {
        // Simple lexicon-based sentiment analysis
        $positiveWords = $this->getPositiveWords();
        $negativeWords = $this->getNegativeWords();
        
        $words = str_word_count(strtolower($content), 1);
        $positiveCount = 0;
        $negativeCount = 0;
        
        foreach ($words as $word) {
            if (in_array($word, $positiveWords)) {
                $positiveCount++;
            } elseif (in_array($word, $negativeWords)) {
                $negativeCount++;
            }
        }
        
        $totalSentimentWords = $positiveCount + $negativeCount;
        $sentiment = 'neutral';
        $score = 0.0;
        
        if ($totalSentimentWords > 0) {
            $score = ($positiveCount - $negativeCount) / $totalSentimentWords;
            if ($score > 0.1) {
                $sentiment = 'positive';
            } elseif ($score < -0.1) {
                $sentiment = 'negative';
            }
        }
        
        return [
            'sentiment' => $sentiment,
            'score' => $score,
            'positive_words' => $positiveCount,
            'negative_words' => $negativeCount,
            'confidence' => min(abs($score) * 2, 1.0)
        ];
    }
    
    /**
     * Detect duplicate content
     */
    private function detectDuplicates(string $content, string $contentType): array
    {
        $contentHash = $this->generateContentHash($content);
        $similarityThreshold = $this->config['similarity_threshold'];
        
        // Check for exact duplicates
        $exactDuplicate = $this->findExactDuplicate($contentHash, $contentType);
        
        if ($exactDuplicate) {
            return [
                'is_duplicate' => true,
                'duplicate_type' => 'exact',
                'similarity_score' => 1.0,
                'original_content_id' => $exactDuplicate['content_id'],
                'confidence' => 1.0
            ];
        }
        
        // Check for near duplicates using fuzzy matching
        $nearDuplicates = $this->findNearDuplicates($content, $contentType, $similarityThreshold);
        
        if (!empty($nearDuplicates)) {
            $bestMatch = $nearDuplicates[0];
            return [
                'is_duplicate' => $bestMatch['similarity'] >= $similarityThreshold,
                'duplicate_type' => 'similar',
                'similarity_score' => $bestMatch['similarity'],
                'original_content_id' => $bestMatch['content_id'],
                'confidence' => $bestMatch['similarity'],
                'all_matches' => $nearDuplicates
            ];
        }
        
        return [
            'is_duplicate' => false,
            'duplicate_type' => null,
            'similarity_score' => 0.0,
            'original_content_id' => null,
            'confidence' => 1.0
        ];
    }
    
    /**
     * Analyze links for malicious content
     */
    private function analyzeLinks(string $content): array
    {
        $urls = $this->extractUrls($content);
        $analysis = [
            'total_urls' => count($urls),
            'suspicious_urls' => [],
            'url_analysis' => []
        ];
        
        foreach ($urls as $url) {
            $urlAnalysis = $this->analyzeUrl($url);
            $analysis['url_analysis'][] = $urlAnalysis;
            
            if ($urlAnalysis['is_suspicious']) {
                $analysis['suspicious_urls'][] = $url;
            }
        }
        
        return [
            'has_suspicious_links' => !empty($analysis['suspicious_urls']),
            'suspicious_count' => count($analysis['suspicious_urls']),
            'total_links' => $analysis['total_urls'],
            'analysis' => $analysis
        ];
    }
    
    /**
     * Calculate moderation decision based on all analyses
     */
    private function calculateModerationDecision(array $aiAnalysis, int $userId, array $context): array
    {
        $riskScore = 0.0;
        $reasons = [];
        $confidence = 1.0;
        
        // Spam analysis impact
        if ($aiAnalysis['spam_analysis']['is_spam']) {
            $riskScore += $aiAnalysis['spam_analysis']['spam_score'];
            $reasons[] = 'Detected as spam';
            $confidence = min($confidence, $aiAnalysis['spam_analysis']['confidence']);
        }
        
        // Toxicity analysis impact
        if ($aiAnalysis['toxicity_analysis']['is_toxic']) {
            $riskScore += $aiAnalysis['toxicity_analysis']['toxicity_score'];
            $reasons[] = 'Contains toxic content';
            $confidence = min($confidence, $aiAnalysis['toxicity_analysis']['confidence']);
        }
        
        // Duplicate content impact
        if ($aiAnalysis['duplicate_detection']['is_duplicate']) {
            $riskScore += 40.0;
            $reasons[] = 'Duplicate content detected';
        }
        
        // Suspicious links impact
        if ($aiAnalysis['link_analysis']['has_suspicious_links']) {
            $riskScore += 30.0;
            $reasons[] = 'Contains suspicious links';
        }
        
        // User reputation factor
        $userReputation = $this->getUserReputation($userId);
        $reputationFactor = $this->calculateReputationFactor($userReputation);
        $riskScore *= $reputationFactor;
        
        // Image moderation impact
        if (isset($aiAnalysis['image_moderation']) && $aiAnalysis['image_moderation']['has_inappropriate_content']) {
            $riskScore += 50.0;
            $reasons[] = 'Contains inappropriate images';
        }
        
        // Determine action based on risk score
        $action = $this->determineAction($riskScore, $confidence);
        
        return [
            'action' => $action,
            'risk_score' => min($riskScore, 100.0),
            'confidence' => $confidence,
            'reasons' => $reasons,
            'user_reputation_factor' => $reputationFactor
        ];
    }
    
    /**
     * Queue content for human review
     */
    private function queueForHumanReview(
        string $contentType,
        int $contentId,
        int $userId,
        array $aiAnalysis,
        array $decision
    ): bool {
        try {
            $queueItem = [
                'content_type' => $contentType,
                'content_id' => $contentId,
                'user_id' => $userId,
                'moderation_status' => 'pending',
                'ai_analysis' => json_encode($aiAnalysis),
                'spam_score' => $aiAnalysis['spam_analysis']['spam_score'] / 100,
                'toxicity_score' => $aiAnalysis['toxicity_analysis']['toxicity_score'] / 100,
                'sentiment' => $aiAnalysis['sentiment_analysis']['sentiment'],
                'detected_language' => $aiAnalysis['language_detection']['language'] ?? 'unknown',
                'content_topics' => json_encode($aiAnalysis['topic_classification']['topics'] ?? []),
                'moderation_flags' => json_encode($decision['reasons']),
                'auto_decision' => false,
                'confidence_score' => $decision['confidence'],
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            DB::table('content_moderation_queue')->insert($queueItem);
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Failed to queue content for human review: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get moderation queue for human reviewers
     */
    public function getModerationQueue(array $filters = []): array
    {
        $query = DB::table('content_moderation_queue')
            ->where('moderation_status', 'pending')
            ->orderBy('created_at', 'asc');
        
        // Apply filters
        if (isset($filters['content_type'])) {
            $query->where('content_type', $filters['content_type']);
        }
        
        if (isset($filters['min_risk_score'])) {
            $query->where('spam_score', '>=', $filters['min_risk_score']);
        }
        
        if (isset($filters['language'])) {
            $query->where('detected_language', $filters['language']);
        }
        
        $limit = $filters['limit'] ?? 50;
        $items = $query->limit($limit)->get();
        
        return array_map(function($item) {
            return [
                'id' => $item->id,
                'content_type' => $item->content_type,
                'content_id' => $item->content_id,
                'user_id' => $item->user_id,
                'ai_analysis' => json_decode($item->ai_analysis, true),
                'spam_score' => $item->spam_score,
                'toxicity_score' => $item->toxicity_score,
                'sentiment' => $item->sentiment,
                'language' => $item->detected_language,
                'topics' => json_decode($item->content_topics, true),
                'flags' => json_decode($item->moderation_flags, true),
                'confidence' => $item->confidence_score,
                'created_at' => $item->created_at
            ];
        }, $items->toArray());
    }
    
    /**
     * Process human moderation decision
     */
    public function processHumanDecision(
        int $queueId,
        int $reviewerId,
        string $decision,
        string $notes = ''
    ): bool {
        try {
            $updateData = [
                'moderation_status' => $decision,
                'human_reviewer_id' => $reviewerId,
                'review_notes' => $notes,
                'reviewed_at' => date('Y-m-d H:i:s')
            ];
            
            $updated = DB::table('content_moderation_queue')
                ->where('id', $queueId)
                ->update($updateData);
            
            if ($updated) {
                // Update user reputation based on decision
                $queueItem = DB::table('content_moderation_queue')->where('id', $queueId)->first();
                if ($queueItem) {
                    $this->updateUserReputationFromHumanDecision($queueItem->user_id, $decision);
                }
            }
            
            return (bool) $updated;
            
        } catch (\Exception $e) {
            error_log("Failed to process human moderation decision: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get moderation statistics
     */
    public function getModerationStats(string $period = '24h'): array
    {
        $cacheKey = "moderation_stats_{$period}";
        
        if ($cached = $this->cache->get($cacheKey)) {
            return $cached;
        }
        
        $stats = [
            'period' => $period,
            'total_moderated' => $this->getTotalModerated($period),
            'auto_approved' => $this->getAutoApproved($period),
            'auto_rejected' => $this->getAutoRejected($period),
            'flagged_for_review' => $this->getFlaggedForReview($period),
            'human_reviewed' => $this->getHumanReviewed($period),
            'spam_detected' => $this->getSpamDetected($period),
            'toxicity_detected' => $this->getToxicityDetected($period),
            'duplicate_detected' => $this->getDuplicateDetected($period),
            'average_processing_time' => $this->getAverageProcessingTime($period),
            'false_positive_rate' => $this->getFalsePositiveRate($period),
            'queue_backlog' => $this->getQueueBacklog()
        ];
        
        $this->cache->set($cacheKey, $stats, 300); // Cache for 5 minutes
        
        return $stats;
    }
    
    // Private helper methods
    
    private function loadModerationConfig(): array
    {
        return [
            'spam_threshold' => 60.0,
            'toxicity_threshold' => 50.0,
            'similarity_threshold' => 0.8,
            'max_urls_per_content' => 3,
            'max_caps_ratio' => 0.3,
            'max_repetition_score' => 0.7,
            'user_spam_threshold' => 0.2,
            'max_content_velocity' => 10, // posts per hour
            'auto_approve_threshold' => 20.0,
            'auto_reject_threshold' => 80.0
        ];
    }
    
    private function loadSpamPatterns(): array
    {
        return [
            ['regex' => '/\b(?:click here|act now|limited time|urgent|free money)\b/i', 'weight' => 15.0, 'description' => 'Spam keywords'],
            ['regex' => '/\$\d+/i', 'weight' => 10.0, 'description' => 'Money amounts'],
            ['regex' => '/\b(?:viagra|cialis|pharmacy)\b/i', 'weight' => 25.0, 'description' => 'Pharmaceutical spam'],
            ['regex' => '/\b(?:casino|gambling|poker)\b/i', 'weight' => 20.0, 'description' => 'Gambling content'],
            ['regex' => '/bit\.ly|tinyurl|goo\.gl/i', 'weight' => 10.0, 'description' => 'URL shorteners']
        ];
    }
    
    private function loadToxicityPatterns(): array
    {
        return [
            'hate_speech' => [
                ['regex' => '/\b(?:hate|kill|die)\b/i', 'weight' => 30.0, 'description' => 'Violent language'],
                ['regex' => '/\b(?:stupid|idiot|moron)\b/i', 'weight' => 15.0, 'description' => 'Insulting language']
            ],
            'harassment' => [
                ['regex' => '/\b(?:shut up|go away|nobody cares)\b/i', 'weight' => 20.0, 'description' => 'Dismissive language']
            ]
        ];
    }
    
    private function determineAction(float $riskScore, float $confidence): string
    {
        if ($riskScore >= $this->config['auto_reject_threshold'] && $confidence >= 0.8) {
            return 'auto_reject';
        } elseif ($riskScore <= $this->config['auto_approve_threshold'] && $confidence >= 0.8) {
            return 'auto_approve';
        } else {
            return 'flag_for_review';
        }
    }
    
    private function calculateConfidence(float $score, string $type): float
    {
        // Simple confidence calculation based on score magnitude
        $normalizedScore = $score / 100.0;
        return min(abs($normalizedScore), 1.0);
    }
    
    // Mock implementations for complex analysis functions
    private function detectLanguage(string $content): array { return ['language' => 'en', 'confidence' => 0.9]; }
    private function classifyTopics(string $content): array { return ['topics' => ['technology'], 'confidence' => 0.8]; }
    private function calculateReadability(string $content): array { return ['score' => 75.0, 'level' => 'readable']; }
    private function assessContentQuality(string $content, string $type): array { return ['score' => 80.0, 'quality' => 'good']; }
    private function containsImages(string $content): bool { return false; }
    private function moderateImages(string $content): array { return ['has_inappropriate_content' => false]; }
    private function getContentStatistics(string $content): array { return ['url_count' => 0, 'caps_ratio' => 0.1, 'repetition_score' => 0.2]; }
    private function getUserSpamHistory(int $userId): array { return ['spam_ratio' => 0.0]; }
    private function getContentVelocity(int $userId): int { return 1; }
    private function analyzeContextualToxicity(string $content): array { return ['score' => 0.0]; }
    private function getPositiveWords(): array { return ['good', 'great', 'excellent', 'awesome', 'fantastic']; }
    private function getNegativeWords(): array { return ['bad', 'terrible', 'awful', 'horrible', 'disgusting']; }
    private function generateContentHash(string $content): string { return md5($content); }
    private function findExactDuplicate(string $hash, string $type): ?array { return null; }
    private function findNearDuplicates(string $content, string $type, float $threshold): array { return []; }
    private function extractUrls(string $content): array { return []; }
    private function analyzeUrl(string $url): array { return ['is_suspicious' => false]; }
    private function getUserReputation(int $userId): float { return 75.0; }
    private function calculateReputationFactor(float $reputation): float { return 1.0; }
    private function updateUserReputation(int $userId, array $decision): void {}
    private function updateUserReputationFromHumanDecision(int $userId, string $decision): void {}
    private function logModerationEvent(array $result): void {}
    private function getTotalModerated(string $period): int { return 0; }
    private function getAutoApproved(string $period): int { return 0; }
    private function getAutoRejected(string $period): int { return 0; }
    private function getFlaggedForReview(string $period): int { return 0; }
    private function getHumanReviewed(string $period): int { return 0; }
    private function getSpamDetected(string $period): int { return 0; }
    private function getToxicityDetected(string $period): int { return 0; }
    private function getDuplicateDetected(string $period): int { return 0; }
    private function getAverageProcessingTime(string $period): float { return 0.0; }
    private function getFalsePositiveRate(string $period): float { return 0.0; }
    private function getQueueBacklog(): int { return 0; }
}