<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Content Categorization and AI Tagging Service
 * 
 * Provides intelligent content classification and automatic tagging:
 * - Automatic category detection from content
 * - AI-powered tag suggestion and extraction
 * - Content quality scoring
 * - Language and sentiment analysis
 * - Topic modeling and clustering
 */
class ContentCategorizationService
{
    private CacheService $cache;
    private array $config;
    private array $categories;
    private array $stopWords;
    private array $technicalKeywords;
    
    public function __construct(CacheService $cache = null)
    {
        $this->cache = $cache ?? new CacheService();
        $this->config = $this->loadConfig();
        $this->categories = $this->loadCategories();
        $this->stopWords = $this->loadStopWords();
        $this->technicalKeywords = $this->loadTechnicalKeywords();
    }
    
    /**
     * Analyze and categorize content
     */
    public function analyzeContent(array $content): array
    {
        $text = $this->extractText($content);
        $url = $content['url'] ?? '';
        $existingTags = $content['tags'] ?? [];
        
        $analysis = [
            'categories' => $this->detectCategories($text, $url),
            'suggested_tags' => $this->suggestTags($text, $url, $existingTags),
            'quality_score' => $this->calculateQualityScore($content),
            'readability' => $this->calculateReadability($text),
            'sentiment' => $this->analyzeSentiment($text),
            'language' => $this->detectLanguage($text),
            'language_confidence' => 0.95, // Mock confidence score
            'topics' => $this->extractTopics($text),
            'technical_level' => $this->assessTechnicalLevel($text),
            'content_type' => $this->detectContentType($content),
            'engagement_prediction' => $this->predictEngagement($content)
        ];
        
        return $analysis;
    }
    
    /**
     * Detect content categories
     */
    public function detectCategories(string $text, string $url): array
    {
        $detectedCategories = [];
        $text = strtolower($text);
        $url = strtolower($url);
        
        foreach ($this->categories as $category => $rules) {
            $score = 0;
            
            // Check keywords
            foreach ($rules['keywords'] as $keyword => $weight) {
                $count = substr_count($text, strtolower($keyword));
                $score += $count * $weight;
            }
            
            // Check URL patterns
            foreach ($rules['url_patterns'] as $pattern => $weight) {
                if (strpos($url, $pattern) !== false) {
                    $score += $weight;
                }
            }
            
            // Check domain patterns
            foreach ($rules['domains'] as $domain => $weight) {
                if (strpos($url, $domain) !== false) {
                    $score += $weight;
                }
            }
            
            if ($score >= $rules['threshold']) {
                $detectedCategories[$category] = [
                    'score' => $score,
                    'confidence' => min(1.0, $score / $rules['max_score'])
                ];
            }
        }
        
        // Sort by score and limit results
        arsort($detectedCategories);
        return array_slice($detectedCategories, 0, $this->config['max_categories'], true);
    }
    
    /**
     * Suggest tags based on content analysis
     */
    public function suggestTags(string $text, string $url = '', array $existingTags = []): array
    {
        $suggestions = [];
        
        // Extract keywords using TF-IDF
        $keywords = $this->extractKeywords($text);
        
        // Technical keyword detection
        $technicalTags = $this->detectTechnicalTags($text);
        
        // URL-based tag extraction
        $urlTags = $this->extractTagsFromUrl($url);
        
        // Domain-based tags
        $domainTags = $this->extractTagsFromDomain($url);
        
        // Combine all suggestions
        $allSuggestions = array_merge($keywords, $technicalTags, $urlTags, $domainTags);
        
        // Score and filter suggestions
        foreach ($allSuggestions as $tag => $score) {
            if (in_array($tag, $existingTags)) {
                continue; // Skip existing tags
            }
            
            if (strlen($tag) < 2 || strlen($tag) > 50) {
                continue; // Skip too short or long tags
            }
            
            if (in_array(strtolower($tag), $this->stopWords)) {
                continue; // Skip stop words
            }
            
            $suggestions[$tag] = [
                'score' => $score,
                'confidence' => min(1.0, $score / $this->config['tag_max_score']),
                'source' => $this->getTagSource($tag, $keywords, $technicalTags, $urlTags, $domainTags)
            ];
        }
        
        // Sort by score and limit results
        arsort($suggestions);
        return array_slice($suggestions, 0, $this->config['max_suggested_tags'], true);
    }
    
    /**
     * Calculate content quality score
     */
    public function calculateQualityScore(array $content): array
    {
        $text = $this->extractText($content);
        $title = $content['title'] ?? '';
        $url = $content['url'] ?? '';
        
        // Handle empty content
        if (empty($text) && empty($title)) {
            return [
                'overall' => 0.1,
                'content_length' => 0.0,
                'readability' => 0.0,
                'structure' => 0.0,
                'uniqueness' => 0.0,
                'aspects' => [
                    'length' => 0.0,
                    'readability' => 0.0,
                    'structure' => 0.0,
                    'originality' => 0.0,
                    'technical_depth' => 0.0,
                    'title_quality' => 0.0,
                    'source_credibility' => 0.0
                ],
                'grade' => 'F'
            ];
        }
        
        $scores = [
            'length' => $this->scoreLengthQuality($text),
            'readability' => $this->scoreReadability($text),
            'structure' => $this->scoreStructure($text),
            'originality' => $this->scoreOriginality($text),
            'technical_depth' => $this->scoreTechnicalDepth($text),
            'title_quality' => $this->scoreTitleQuality($title),
            'source_credibility' => $this->scoreSourceCredibility($url)
        ];
        
        // Calculate weighted overall score
        $weights = $this->config['quality_weights'];
        $overallScore = 0;
        
        foreach ($scores as $aspect => $score) {
            $overallScore += $score * ($weights[$aspect] ?? 1.0);
        }
        
        $overall = min(1.0, $overallScore / array_sum($weights));
        
        return [
            'overall' => $overall,
            'content_length' => $scores['length'],
            'readability' => $scores['readability'],
            'structure' => $scores['structure'],
            'uniqueness' => $scores['originality'],
            'aspects' => $scores,
            'grade' => $this->getQualityGrade($overall)
        ];
    }
    
    /**
     * Analyze sentiment of content
     */
    public function analyzeSentiment(string $text): array
    {
        $text = strtolower($text);
        $positiveWords = $this->config['sentiment']['positive_words'];
        $negativeWords = $this->config['sentiment']['negative_words'];
        
        $positiveScore = 0;
        $negativeScore = 0;
        
        foreach ($positiveWords as $word => $weight) {
            $positiveScore += substr_count($text, $word) * $weight;
        }
        
        foreach ($negativeWords as $word => $weight) {
            $negativeScore += substr_count($text, $word) * $weight;
        }
        
        $totalScore = $positiveScore + $negativeScore;
        if ($totalScore == 0) {
            return ['sentiment' => 'neutral', 'confidence' => 0.5, 'score' => 0];
        }
        
        $sentimentScore = ($positiveScore - $negativeScore) / $totalScore;
        
        if ($sentimentScore > 0.1) {
            $sentiment = 'positive';
        } elseif ($sentimentScore < -0.1) {
            $sentiment = 'negative';
        } else {
            $sentiment = 'neutral';
        }
        
        return [
            'sentiment' => $sentiment,
            'label' => $sentiment,
            'score' => $sentimentScore,
            'confidence' => abs($sentimentScore),
            'positive_score' => $positiveScore,
            'negative_score' => $negativeScore
        ];
    }
    
    /**
     * Extract topics from content using simple keyword clustering
     */
    private function extractTopics(string $text): array
    {
        $keywords = $this->extractKeywords($text);
        $topics = [];
        
        // Group related keywords into topics
        $topicClusters = $this->config['topic_clusters'];
        
        foreach ($topicClusters as $topic => $cluster) {
            $topicScore = 0;
            $matchedKeywords = [];
            
            foreach ($cluster['keywords'] as $keyword => $weight) {
                if (isset($keywords[$keyword])) {
                    $topicScore += $keywords[$keyword] * $weight;
                    $matchedKeywords[] = $keyword;
                }
            }
            
            if ($topicScore > $cluster['threshold']) {
                $topics[$topic] = [
                    'score' => $topicScore,
                    'confidence' => min(1.0, $topicScore / $cluster['max_score']),
                    'keywords' => $matchedKeywords
                ];
            }
        }
        
        arsort($topics);
        return array_slice($topics, 0, $this->config['max_topics'], true);
    }
    
    /**
     * Assess technical level of content
     */
    public function assessTechnicalLevel(string $text): string
    {
        $text = strtolower($text);
        $technicalScore = 0;
        $indicators = [];
        
        // Check for technical keywords
        foreach ($this->technicalKeywords as $category => $keywords) {
            $categoryScore = 0;
            foreach ($keywords as $keyword => $weight) {
                $count = substr_count($text, $keyword);
                if ($count > 0) {
                    $categoryScore += $count * $weight;
                    $indicators[$category][] = $keyword;
                }
            }
            $technicalScore += $categoryScore;
        }
        
        // Check for code patterns
        $codePatterns = [
            '/\b[a-zA-Z_][a-zA-Z0-9_]*\s*\([^)]*\)\s*{/' => 3.0, // Function definitions
            '/\b(class|function|var|let|const|def|import|from)\b/' => 2.0, // Keywords
            '/[{}();]/' => 0.5, // Code punctuation
            '/\b[A-Z][a-zA-Z]*[A-Z][a-zA-Z]*\b/' => 1.0, // CamelCase
            '/\b[a-z_]+\.[a-z_]+/' => 1.5, // Object notation
        ];
        
        foreach ($codePatterns as $pattern => $weight) {
            if (preg_match_all($pattern, $text, $matches)) {
                $technicalScore += count($matches[0]) * $weight;
                $indicators['code_patterns'][] = $pattern;
            }
        }
        
        // Determine technical level
        $level = 'beginner';
        if ($technicalScore > $this->config['technical_thresholds']['expert']) {
            $level = 'expert';
        } elseif ($technicalScore > $this->config['technical_thresholds']['intermediate']) {
            $level = 'intermediate';
        } elseif ($technicalScore > $this->config['technical_thresholds']['advanced_beginner']) {
            $level = 'advanced_beginner';
        }
        
        return $level;
    }
    
    /**
     * Detect content type (article, tutorial, news, discussion, etc.)
     */
    private function detectContentType(array $content): array
    {
        $text = $this->extractText($content);
        $title = $content['title'] ?? '';
        $url = $content['url'] ?? '';
        
        $typeScores = [];
        
        foreach ($this->config['content_types'] as $type => $rules) {
            $score = 0;
            
            // Title patterns
            foreach ($rules['title_patterns'] as $pattern => $weight) {
                if (preg_match('/' . $pattern . '/i', $title)) {
                    $score += $weight;
                }
            }
            
            // Content patterns
            foreach ($rules['content_patterns'] as $pattern => $weight) {
                if (preg_match('/' . $pattern . '/i', $text)) {
                    $score += $weight;
                }
            }
            
            // URL patterns
            foreach ($rules['url_patterns'] as $pattern => $weight) {
                if (preg_match('/' . $pattern . '/i', $url)) {
                    $score += $weight;
                }
            }
            
            if ($score > $rules['threshold']) {
                $typeScores[$type] = [
                    'score' => $score,
                    'confidence' => min(1.0, $score / $rules['max_score'])
                ];
            }
        }
        
        arsort($typeScores);
        $primaryType = array_key_first($typeScores) ?? 'article';
        
        return [
            'primary' => $primaryType,
            'all_scores' => $typeScores,
            'confidence' => $typeScores[$primaryType]['confidence'] ?? 0.5
        ];
    }
    
    /**
     * Predict engagement potential
     */
    private function predictEngagement(array $content): array
    {
        $factors = [
            'title_appeal' => $this->scoreTitleAppeal($content['title'] ?? ''),
            'content_length' => $this->scoreContentLength($this->extractText($content)),
            'readability' => $this->calculateReadability($this->extractText($content)),
            'topic_popularity' => $this->scoreTopicPopularity($content),
            'source_authority' => $this->scoreSourceAuthority($content['url'] ?? ''),
            'freshness' => $this->scoreFreshness($content),
            'controversy' => $this->scoreControversy($this->extractText($content))
        ];
        
        $weights = $this->config['engagement_weights'];
        $engagementScore = 0;
        
        foreach ($factors as $factor => $score) {
            $engagementScore += $score * ($weights[$factor] ?? 1.0);
        }
        
        $normalizedScore = min(1.0, $engagementScore / array_sum($weights));
        
        return [
            'predicted_score' => $normalizedScore,
            'factors' => $factors,
            'level' => $this->getEngagementLevel($normalizedScore),
            'recommendations' => $this->getEngagementRecommendations($factors)
        ];
    }
    
    // Helper methods
    
    private function extractText(array $content): string
    {
        $text = ($content['title'] ?? '') . ' ' . ($content['description'] ?? '') . ' ' . ($content['content'] ?? '');
        return trim($text);
    }
    
    private function extractKeywords(string $text, int $minWordLength = 3): array
    {
        // Simple TF-IDF-like keyword extraction
        $words = preg_split('/\W+/', strtolower($text));
        $wordFreq = [];
        $totalWords = count($words);
        
        foreach ($words as $word) {
            if (strlen($word) >= $minWordLength && !in_array($word, $this->stopWords)) {
                $wordFreq[$word] = ($wordFreq[$word] ?? 0) + 1;
            }
        }
        
        // Calculate TF scores
        $keywords = [];
        foreach ($wordFreq as $word => $freq) {
            $tf = $freq / $totalWords;
            $keywords[$word] = $tf * 100; // Scale for easier handling
        }
        
        arsort($keywords);
        return array_slice($keywords, 0, 50, true);
    }
    
    private function detectTechnicalTags(string $text): array
    {
        $text = strtolower($text);
        $tags = [];
        
        foreach ($this->technicalKeywords as $category => $keywords) {
            foreach ($keywords as $keyword => $weight) {
                if (strpos($text, $keyword) !== false) {
                    $tags[$keyword] = $weight;
                }
            }
        }
        
        return $tags;
    }
    
    private function calculateReadability(string $text): float
    {
        if (empty($text)) return 0;
        
        $sentences = preg_split('/[.!?]+/', $text);
        $words = preg_split('/\s+/', $text);
        $syllables = 0;
        
        foreach ($words as $word) {
            $syllables += $this->countSyllables($word);
        }
        
        $sentenceCount = count($sentences);
        $wordCount = count($words);
        
        if ($sentenceCount == 0 || $wordCount == 0) return 0;
        
        // Flesch Reading Ease Score
        $score = 206.835 - (1.015 * ($wordCount / $sentenceCount)) - (84.6 * ($syllables / $wordCount));
        
        return max(0, min(100, $score)) / 100; // Normalize to 0-1
    }
    
    private function countSyllables(string $word): int
    {
        $word = strtolower(preg_replace('/[^a-z]/', '', $word));
        if (strlen($word) <= 3) return 1;
        
        $word = preg_replace('/(?:[^laeiouy]es|ed|[^laeiouy]e)$/', '', $word);
        $word = preg_replace('/^y/', '', $word);
        
        $matches = preg_match_all('/[aeiouy]{1,2}/', $word);
        return max(1, $matches);
    }
    
    private function loadConfig(): array
    {
        return [
            'max_categories' => 3,
            'max_suggested_tags' => 10,
            'max_topics' => 5,
            'tag_max_score' => 10.0,
            'quality_weights' => [
                'length' => 1.0,
                'readability' => 1.5,
                'structure' => 1.0,
                'originality' => 2.0,
                'technical_depth' => 1.0,
                'title_quality' => 1.5,
                'source_credibility' => 2.0
            ],
            'technical_thresholds' => [
                'advanced_beginner' => 10,
                'intermediate' => 25,
                'expert' => 50
            ],
            'engagement_weights' => [
                'title_appeal' => 2.0,
                'content_length' => 1.0,
                'readability' => 1.5,
                'topic_popularity' => 1.5,
                'source_authority' => 1.0,
                'freshness' => 0.5,
                'controversy' => 0.5
            ],
            'sentiment' => [
                'positive_words' => [
                    'awesome' => 2, 'great' => 1, 'excellent' => 2, 'amazing' => 2,
                    'good' => 1, 'best' => 2, 'fantastic' => 2, 'wonderful' => 2
                ],
                'negative_words' => [
                    'terrible' => 2, 'awful' => 2, 'bad' => 1, 'horrible' => 2,
                    'worst' => 2, 'hate' => 2, 'sucks' => 2, 'disappointing' => 1
                ]
            ],
            'topic_clusters' => [
                'programming' => [
                    'keywords' => ['code' => 2, 'programming' => 3, 'development' => 2, 'software' => 2],
                    'threshold' => 5,
                    'max_score' => 20
                ],
                'web_development' => [
                    'keywords' => ['web' => 2, 'html' => 2, 'css' => 2, 'javascript' => 3, 'frontend' => 2],
                    'threshold' => 4,
                    'max_score' => 15
                ]
            ],
            'content_types' => [
                'tutorial' => [
                    'title_patterns' => ['how to' => 3, 'tutorial' => 3, 'guide' => 2, 'step by step' => 3],
                    'content_patterns' => ['step 1' => 2, 'first' => 1, 'next' => 1],
                    'url_patterns' => ['tutorial' => 2, 'guide' => 2],
                    'threshold' => 3,
                    'max_score' => 10
                ],
                'news' => [
                    'title_patterns' => ['announces' => 2, 'releases' => 2, 'breaking' => 3],
                    'content_patterns' => ['today' => 1, 'announced' => 2],
                    'url_patterns' => ['news' => 2, 'blog' => 1],
                    'threshold' => 2,
                    'max_score' => 8
                ]
            ]
        ];
    }
    
    private function loadCategories(): array
    {
        return [
            'programming' => [
                'keywords' => [
                    'programming' => 3, 'code' => 2, 'development' => 2, 'software' => 2,
                    'algorithm' => 2, 'data structure' => 3, 'function' => 1, 'variable' => 1
                ],
                'url_patterns' => ['github.com' => 3, 'stackoverflow.com' => 2, 'dev.to' => 2],
                'domains' => ['github.com' => 3, 'gitlab.com' => 2],
                'threshold' => 5,
                'max_score' => 20
            ],
            'web_development' => [
                'keywords' => [
                    'web' => 2, 'html' => 2, 'css' => 2, 'javascript' => 3,
                    'frontend' => 2, 'backend' => 2, 'react' => 2, 'vue' => 2
                ],
                'url_patterns' => ['codepen.io' => 3, 'jsfiddle.net' => 2],
                'domains' => [],
                'threshold' => 4,
                'max_score' => 15
            ],
            'artificial_intelligence' => [
                'keywords' => [
                    'ai' => 3, 'artificial intelligence' => 4, 'machine learning' => 4,
                    'neural network' => 3, 'deep learning' => 3, 'ml' => 2
                ],
                'url_patterns' => ['arxiv.org' => 3, 'kaggle.com' => 2],
                'domains' => [],
                'threshold' => 5,
                'max_score' => 18
            ]
        ];
    }
    
    private function loadStopWords(): array
    {
        return [
            'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by',
            'is', 'are', 'was', 'were', 'be', 'been', 'have', 'has', 'had', 'do', 'does', 'did',
            'will', 'would', 'could', 'should', 'may', 'might', 'can', 'this', 'that', 'these',
            'those', 'i', 'you', 'he', 'she', 'it', 'we', 'they', 'me', 'him', 'her', 'us', 'them'
        ];
    }
    
    private function loadTechnicalKeywords(): array
    {
        return [
            'programming_languages' => [
                'javascript' => 3, 'python' => 3, 'java' => 3, 'c++' => 3, 'c#' => 3,
                'php' => 2, 'ruby' => 2, 'go' => 2, 'rust' => 2, 'swift' => 2, 'kotlin' => 2
            ],
            'frameworks' => [
                'react' => 3, 'angular' => 3, 'vue' => 3, 'laravel' => 2, 'django' => 2,
                'express' => 2, 'spring' => 2, 'rails' => 2, 'flask' => 2
            ],
            'technologies' => [
                'docker' => 2, 'kubernetes' => 2, 'aws' => 2, 'azure' => 2, 'gcp' => 2,
                'redis' => 2, 'mongodb' => 2, 'postgresql' => 2, 'mysql' => 2, 'nginx' => 2
            ],
            'concepts' => [
                'api' => 2, 'rest' => 2, 'graphql' => 2, 'microservices' => 2, 'devops' => 2,
                'cicd' => 2, 'testing' => 1, 'security' => 2, 'performance' => 1
            ]
        ];
    }
    
    // Additional helper methods (simplified implementations)
    private function extractTagsFromUrl(string $url): array { return []; }
    private function extractTagsFromDomain(string $url): array { return []; }
    private function getTagSource(string $tag, array ...$sources): string { return 'content'; }
    private function scoreLengthQuality(string $text): float { return min(1.0, strlen($text) / 2000); }
    private function scoreReadability(string $text): float { return $this->calculateReadability($text); }
    private function scoreStructure(string $text): float { 
        if (empty($text)) return 0.0;
        
        $score = 0.0;
        
        // Check for headings (markdown style)
        $headingMatches = preg_match_all('/^#+\s+.+$/m', $text);
        if ($headingMatches > 0) {
            $score += min(0.4, $headingMatches * 0.1);
        }
        
        // Check for paragraph structure
        $paragraphs = preg_split('/\n\s*\n/', trim($text));
        if (count($paragraphs) > 1) {
            $score += min(0.3, count($paragraphs) * 0.05);
        }
        
        // Check for lists
        $listMatches = preg_match_all('/^[\*\-\+]\s+.+$/m', $text);
        if ($listMatches > 0) {
            $score += min(0.2, $listMatches * 0.05);
        }
        
        // Check for numbered lists
        $numberedMatches = preg_match_all('/^\d+\.\s+.+$/m', $text);
        if ($numberedMatches > 0) {
            $score += min(0.1, $numberedMatches * 0.02);
        }
        
        // Base score for any text
        $score += 0.1;
        
        return min(1.0, $score);
    }
    private function scoreOriginality(string $text): float { return 0.8; }
    private function scoreTechnicalDepth(string $text): float { return 0.6; }
    private function scoreTitleQuality(string $title): float { return min(1.0, strlen($title) / 60); }
    private function scoreSourceCredibility(string $url): float { return 0.7; }
    private function getQualityGrade(float $score): string {
        if ($score > 0.8) return 'A';
        if ($score > 0.6) return 'B';
        if ($score > 0.4) return 'C';
        return 'D';
    }
    private function detectLanguage(string $text): string { return 'en'; }
    private function scoreTitleAppeal(string $title): float { return 0.7; }
    private function scoreContentLength(string $text): float { return min(1.0, strlen($text) / 1500); }
    private function scoreTopicPopularity(array $content): float { return 0.6; }
    private function scoreSourceAuthority(string $url): float { return 0.7; }
    private function scoreFreshness(array $content): float { return 0.8; }
    private function scoreControversy(string $text): float { return 0.3; }
    private function getEngagementLevel(float $score): string {
        if ($score > 0.8) return 'high';
        if ($score > 0.5) return 'medium';
        return 'low';
    }
    private function getEngagementRecommendations(array $factors): array { return []; }
    
    /**
     * Get category suggestions from domain
     */
    public function getCategoryFromDomain(string $url): array
    {
        $domain = parse_url($url, PHP_URL_HOST);
        if (!$domain) return [];
        
        $domainMappings = [
            'github.com' => ['programming', 'development', 'open-source'],
            'stackoverflow.com' => ['programming', 'development', 'qa'],
            'medium.com' => ['blog', 'articles', 'writing'],
            'dev.to' => ['programming', 'development', 'community'],
            'hackernews.ycombinator.com' => ['tech', 'startup', 'news'],
            'reddit.com' => ['discussion', 'community', 'social'],
            'arxiv.org' => ['research', 'academic', 'science'],
            'youtube.com' => ['video', 'tutorial', 'entertainment'],
            'techcrunch.com' => ['tech', 'startup', 'news'],
            'arstechnica.com' => ['tech', 'science', 'news']
        ];
        
        return $domainMappings[$domain] ?? ['general'];
    }
    
    /**
     * Analyze tag frequency patterns
     */
    public function analyzeTagFrequency(array $tags): array
    {
        $analysis = [
            'related_tags' => [],
            'category_mapping' => [],
            'popularity_scores' => []
        ];
        
        // Mock implementation for tag relationship analysis
        foreach ($tags as $tag) {
            $analysis['related_tags'][$tag] = $this->getRelatedTags($tag);
            $analysis['category_mapping'][$tag] = $this->getTagCategory($tag);
            $analysis['popularity_scores'][$tag] = rand(1, 100) / 100;
        }
        
        return $analysis;
    }
    
    private function getRelatedTags(string $tag): array
    {
        $relatedMappings = [
            'javascript' => ['js', 'web', 'frontend', 'react', 'node'],
            'python' => ['programming', 'data-science', 'ai', 'django', 'flask'],
            'programming' => ['coding', 'development', 'software', 'tech'],
            'ai' => ['machine-learning', 'deep-learning', 'neural-networks', 'data']
        ];
        
        return $relatedMappings[$tag] ?? [];
    }
    
    private function getTagCategory(string $tag): string
    {
        $categoryMappings = [
            'javascript' => 'programming-language',
            'python' => 'programming-language',
            'react' => 'framework',
            'vue' => 'framework',
            'ai' => 'technology',
            'machine-learning' => 'technology'
        ];
        
        return $categoryMappings[$tag] ?? 'general';
    }
}