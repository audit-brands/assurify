<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Duplicate Detection and Content Similarity Service
 * 
 * Provides intelligent duplicate detection and similarity scoring:
 * - URL normalization and duplicate detection
 * - Content similarity using multiple algorithms
 * - Title similarity and fuzzy matching
 * - Domain clustering and related content detection
 * - Plagiarism detection and content fingerprinting
 */
class DuplicateDetectionService
{
    private CacheService $cache;
    private array $config;
    private array $stopWords;
    
    public function __construct(CacheService $cache = null)
    {
        $this->cache = $cache ?? new CacheService();
        $this->config = $this->loadConfig();
        $this->stopWords = $this->loadStopWords();
    }
    
    /**
     * Check for duplicates and similar content
     */
    public function checkDuplicates(array $content, array $options = []): array
    {
        $url = $content['url'] ?? '';
        $title = $content['title'] ?? '';
        $text = $this->extractText($content);
        
        $results = [
            'is_duplicate' => false,
            'similarity_score' => 0.0,
            'duplicate_type' => null,
            'similar_content' => [],
            'exact_duplicates' => [],
            'url_variants' => [],
            'content_fingerprint' => $this->generateContentFingerprint($content),
            'recommendations' => []
        ];
        
        // Check exact URL duplicates
        $exactDuplicates = $this->findExactUrlDuplicates($url);
        if (!empty($exactDuplicates)) {
            $results['is_duplicate'] = true;
            $results['duplicate_type'] = 'exact_url';
            $results['exact_duplicates'] = $exactDuplicates;
            return $results;
        }
        
        // Check URL variants (different protocols, www, query params, etc.)
        $urlVariants = $this->findUrlVariants($url);
        if (!empty($urlVariants)) {
            $results['url_variants'] = $urlVariants;
            
            // Check if any variant has high similarity
            $highSimilarity = array_filter($urlVariants, fn($variant) => $variant['similarity'] > 0.8);
            if (!empty($highSimilarity)) {
                $results['is_duplicate'] = true;
                $results['duplicate_type'] = 'url_variant';
                $results['similarity_score'] = max(array_column($highSimilarity, 'similarity'));
                return $results;
            }
        }
        
        // Check title similarity
        $titleSimilar = $this->findTitleSimilar($title, $options['limit'] ?? 50);
        
        // Check content similarity
        $contentSimilar = $this->findContentSimilar($text, $options['limit'] ?? 50);
        
        // Combine and score all similar content
        $allSimilar = $this->combineAndScoreSimilarity($titleSimilar, $contentSimilar, $content);
        
        if (!empty($allSimilar)) {
            $maxSimilarity = max(array_column($allSimilar, 'total_similarity'));
            $results['similarity_score'] = $maxSimilarity;
            $results['similar_content'] = array_slice($allSimilar, 0, $options['limit'] ?? 10);
            
            // Check if it's a probable duplicate
            if ($maxSimilarity > $this->config['duplicate_threshold']) {
                $results['is_duplicate'] = true;
                $results['duplicate_type'] = 'content_similarity';
            }
            
            $results['recommendations'] = $this->generateRecommendations($results, $content);
        }
        
        return $results;
    }
    
    /**
     * Find exact URL duplicates
     */
    private function findExactUrlDuplicates(string $url): array
    {
        if (empty($url)) return [];
        
        $normalizedUrl = $this->normalizeUrl($url);
        $cacheKey = "exact_duplicates_" . md5($normalizedUrl);
        
        if ($cached = $this->cache->get($cacheKey)) {
            return $cached;
        }
        
        // In a real implementation, this would query the database
        $duplicates = $this->queryExactDuplicates($normalizedUrl);
        
        $this->cache->set($cacheKey, $duplicates, $this->config['cache_ttl']);
        return $duplicates;
    }
    
    /**
     * Find URL variants (different protocols, parameters, etc.)
     */
    private function findUrlVariants(string $url): array
    {
        if (empty($url)) return [];
        
        $variants = [];
        $baseUrl = $this->getBaseUrl($url);
        $domain = $this->extractDomain($url);
        
        if (empty($baseUrl) || empty($domain)) return [];
        
        // Generate possible variants
        $urlVariations = $this->generateUrlVariations($url);
        
        foreach ($urlVariations as $variant) {
            $similar = $this->findSimilarUrls($variant);
            foreach ($similar as $item) {
                $similarity = $this->calculateUrlSimilarity($url, $item['url']);
                if ($similarity > 0.7) {
                    $variants[] = [
                        'id' => $item['id'],
                        'url' => $item['url'],
                        'title' => $item['title'],
                        'similarity' => $similarity,
                        'variant_type' => $this->getVariantType($url, $item['url'])
                    ];
                }
            }
        }
        
        // Remove duplicates and sort by similarity
        $variants = $this->deduplicateAndSort($variants);
        
        return array_slice($variants, 0, 20);
    }
    
    /**
     * Find content with similar titles
     */
    private function findTitleSimilar(string $title, int $limit = 50): array
    {
        if (empty($title)) return [];
        
        $cacheKey = "title_similar_" . md5($title) . "_{$limit}";
        
        if ($cached = $this->cache->get($cacheKey)) {
            return $cached;
        }
        
        $similar = [];
        
        // Exact title match
        $exact = $this->findExactTitleMatches($title);
        foreach ($exact as $item) {
            $similar[] = [
                'id' => $item['id'],
                'title' => $item['title'],
                'url' => $item['url'],
                'title_similarity' => 1.0,
                'match_type' => 'exact'
            ];
        }
        
        // Fuzzy title matching
        $fuzzy = $this->findFuzzyTitleMatches($title, $limit);
        foreach ($fuzzy as $item) {
            $similarity = $this->calculateTitleSimilarity($title, $item['title']);
            if ($similarity > 0.6) {
                $similar[] = [
                    'id' => $item['id'],
                    'title' => $item['title'],
                    'url' => $item['url'],
                    'title_similarity' => $similarity,
                    'match_type' => 'fuzzy'
                ];
            }
        }
        
        // Remove duplicates and sort
        $similar = $this->deduplicateAndSort($similar, 'title_similarity');
        
        $this->cache->set($cacheKey, $similar, $this->config['cache_ttl']);
        return $similar;
    }
    
    /**
     * Find content with similar text content
     */
    private function findContentSimilar(string $text, int $limit = 50): array
    {
        if (empty($text)) return [];
        
        $fingerprint = $this->generateTextFingerprint($text);
        $cacheKey = "content_similar_" . md5($fingerprint) . "_{$limit}";
        
        if ($cached = $this->cache->get($cacheKey)) {
            return $cached;
        }
        
        // Find content by similar fingerprints
        $similar = $this->findByContentFingerprint($fingerprint, $limit);
        
        // Calculate detailed similarity scores
        foreach ($similar as &$item) {
            $item['content_similarity'] = $this->calculateContentSimilarity($text, $item['content']);
            $item['cosine_similarity'] = $this->calculateCosineSimilarity($text, $item['content']);
            $item['jaccard_similarity'] = $this->calculateJaccardSimilarity($text, $item['content']);
        }
        
        // Filter by minimum similarity
        $similar = array_filter($similar, fn($item) => $item['content_similarity'] > 0.3);
        
        // Sort by similarity
        usort($similar, fn($a, $b) => $b['content_similarity'] <=> $a['content_similarity']);
        
        $this->cache->set($cacheKey, $similar, $this->config['cache_ttl']);
        return array_slice($similar, 0, $limit);
    }
    
    /**
     * Combine and score similarity from multiple sources
     */
    private function combineAndScoreSimilarity(array $titleSimilar, array $contentSimilar, array $originalContent): array
    {
        $combined = [];
        $weights = $this->config['similarity_weights'];
        
        // Process title similarity results
        foreach ($titleSimilar as $item) {
            $id = $item['id'];
            $combined[$id] = [
                'id' => $id,
                'title' => $item['title'],
                'url' => $item['url'],
                'title_similarity' => $item['title_similarity'],
                'content_similarity' => 0.0,
                'url_similarity' => 0.0,
                'total_similarity' => 0.0,
                'match_reasons' => []
            ];
            
            if ($item['title_similarity'] > 0.8) {
                $combined[$id]['match_reasons'][] = 'Very similar title';
            } elseif ($item['title_similarity'] > 0.6) {
                $combined[$id]['match_reasons'][] = 'Similar title';
            }
        }
        
        // Process content similarity results
        foreach ($contentSimilar as $item) {
            $id = $item['id'];
            
            if (!isset($combined[$id])) {
                $combined[$id] = [
                    'id' => $id,
                    'title' => $item['title'] ?? '',
                    'url' => $item['url'] ?? '',
                    'title_similarity' => 0.0,
                    'content_similarity' => 0.0,
                    'url_similarity' => 0.0,
                    'total_similarity' => 0.0,
                    'match_reasons' => []
                ];
            }
            
            $combined[$id]['content_similarity'] = $item['content_similarity'];
            $combined[$id]['cosine_similarity'] = $item['cosine_similarity'] ?? 0.0;
            $combined[$id]['jaccard_similarity'] = $item['jaccard_similarity'] ?? 0.0;
            
            if ($item['content_similarity'] > 0.8) {
                $combined[$id]['match_reasons'][] = 'Very similar content';
            } elseif ($item['content_similarity'] > 0.6) {
                $combined[$id]['match_reasons'][] = 'Similar content';
            }
        }
        
        // Calculate URL similarity and total scores
        $originalUrl = $originalContent['url'] ?? '';
        
        foreach ($combined as $id => &$item) {
            if (!empty($originalUrl) && !empty($item['url'])) {
                $item['url_similarity'] = $this->calculateUrlSimilarity($originalUrl, $item['url']);
                
                if ($item['url_similarity'] > 0.7) {
                    $item['match_reasons'][] = 'Similar URL';
                }
            }
            
            // Calculate weighted total similarity
            $item['total_similarity'] = (
                $item['title_similarity'] * $weights['title'] +
                $item['content_similarity'] * $weights['content'] +
                $item['url_similarity'] * $weights['url']
            ) / array_sum($weights);
        }
        
        // Sort by total similarity
        uasort($combined, fn($a, $b) => $b['total_similarity'] <=> $a['total_similarity']);
        
        return array_values($combined);
    }
    
    /**
     * Calculate similarity between two text strings using multiple algorithms
     */
    public function calculateContentSimilarity(string $text1, string $text2): float
    {
        if (empty($text1) || empty($text2)) return 0.0;
        
        // Normalize texts
        $text1 = $this->normalizeText($text1);
        $text2 = $this->normalizeText($text2);
        
        // Calculate different similarity metrics
        $cosine = $this->calculateCosineSimilarity($text1, $text2);
        $jaccard = $this->calculateJaccardSimilarity($text1, $text2);
        $levenshtein = $this->calculateLevenshteinSimilarity($text1, $text2);
        $shingle = $this->calculateShingleSimilarity($text1, $text2);
        
        // Weighted combination
        $weights = $this->config['content_similarity_weights'];
        $similarity = (
            $cosine * $weights['cosine'] +
            $jaccard * $weights['jaccard'] +
            $levenshtein * $weights['levenshtein'] +
            $shingle * $weights['shingle']
        ) / array_sum($weights);
        
        return min(1.0, max(0.0, $similarity));
    }
    
    /**
     * Calculate cosine similarity between two texts
     */
    public function calculateCosineSimilarity(string $text1, string $text2): float
    {
        $vector1 = $this->createTfIdfVector($text1);
        $vector2 = $this->createTfIdfVector($text2);
        
        if (empty($vector1) || empty($vector2)) return 0.0;
        
        $dotProduct = 0.0;
        $magnitude1 = 0.0;
        $magnitude2 = 0.0;
        
        $allTerms = array_unique(array_merge(array_keys($vector1), array_keys($vector2)));
        
        foreach ($allTerms as $term) {
            $tf1 = $vector1[$term] ?? 0.0;
            $tf2 = $vector2[$term] ?? 0.0;
            
            $dotProduct += $tf1 * $tf2;
            $magnitude1 += $tf1 * $tf1;
            $magnitude2 += $tf2 * $tf2;
        }
        
        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);
        
        if ($magnitude1 == 0 || $magnitude2 == 0) return 0.0;
        
        return $dotProduct / ($magnitude1 * $magnitude2);
    }
    
    /**
     * Calculate Jaccard similarity
     */
    public function calculateJaccardSimilarity(string $text1, string $text2): float
    {
        $words1 = $this->extractWords($text1);
        $words2 = $this->extractWords($text2);
        
        if (empty($words1) || empty($words2)) return 0.0;
        
        $intersection = count(array_intersect($words1, $words2));
        $union = count(array_unique(array_merge($words1, $words2)));
        
        return $union > 0 ? $intersection / $union : 0.0;
    }
    
    /**
     * Calculate Levenshtein-based similarity
     */
    public function calculateLevenshteinSimilarity(string $text1, string $text2): float
    {
        $maxLength = max(strlen($text1), strlen($text2));
        if ($maxLength == 0) return 1.0;
        
        $distance = levenshtein(substr($text1, 0, 255), substr($text2, 0, 255));
        return 1.0 - ($distance / $maxLength);
    }
    
    /**
     * Calculate shingle similarity
     */
    public function calculateShingleSimilarity(string $text1, string $text2, int $shingleSize = 3): float
    {
        $shingles1 = $this->generateShingles($text1, $shingleSize);
        $shingles2 = $this->generateShingles($text2, $shingleSize);
        
        if (empty($shingles1) || empty($shingles2)) return 0.0;
        
        $intersection = count(array_intersect($shingles1, $shingles2));
        $union = count(array_unique(array_merge($shingles1, $shingles2)));
        
        return $union > 0 ? $intersection / $union : 0.0;
    }
    
    /**
     * Calculate title similarity using fuzzy matching
     */
    public function calculateTitleSimilarity(string $title1, string $title2): float
    {
        $title1 = $this->normalizeTitle($title1);
        $title2 = $this->normalizeTitle($title2);
        
        if ($title1 === $title2) return 1.0;
        if (empty($title1) || empty($title2)) return 0.0;
        
        // Multiple similarity measures for titles
        $levenshtein = $this->calculateLevenshteinSimilarity($title1, $title2);
        $words = $this->calculateWordSimilarity($title1, $title2);
        $ngrams = $this->calculateNgramSimilarity($title1, $title2);
        
        // Weighted combination
        return ($levenshtein * 0.3 + $words * 0.4 + $ngrams * 0.3);
    }
    
    /**
     * Calculate URL similarity
     */
    public function calculateUrlSimilarity(string $url1, string $url2): float
    {
        $url1 = $this->normalizeUrl($url1);
        $url2 = $this->normalizeUrl($url2);
        
        if ($url1 === $url2) return 1.0;
        if (empty($url1) || empty($url2)) return 0.0;
        
        $domain1 = $this->extractDomain($url1);
        $domain2 = $this->extractDomain($url2);
        
        // Same domain gets high similarity
        if ($domain1 === $domain2) {
            $pathSim = $this->calculateLevenshteinSimilarity(
                parse_url($url1, PHP_URL_PATH) ?? '',
                parse_url($url2, PHP_URL_PATH) ?? ''
            );
            return 0.7 + (0.3 * $pathSim);
        }
        
        // Different domains - check if they're related
        $domainSim = $this->calculateLevenshteinSimilarity($domain1, $domain2);
        return $domainSim * 0.5;
    }
    
    /**
     * Generate content fingerprint for quick similarity checks
     */
    public function generateContentFingerprint(array $content): string
    {
        $text = $this->extractText($content);
        $title = $content['title'] ?? '';
        $url = $content['url'] ?? '';
        
        // Create multiple hash components
        $components = [
            'title_hash' => $this->hashText($title),
            'content_hash' => $this->hashText($text),
            'url_hash' => $this->hashText($this->normalizeUrl($url)),
            'combined_hash' => $this->hashText($title . ' ' . $text)
        ];
        
        return json_encode($components);
    }
    
    // Helper methods
    
    private function normalizeUrl(string $url): string
    {
        $url = trim(strtolower($url));
        
        // Remove common URL variations
        $url = preg_replace('/^https?:\/\//', '', $url);
        $url = preg_replace('/^www\./', '', $url);
        $url = rtrim($url, '/');
        
        // Parse and reconstruct to normalize
        $parsed = parse_url('http://' . $url);
        if (!$parsed) return $url;
        
        $normalized = ($parsed['host'] ?? '');
        if (isset($parsed['path']) && $parsed['path'] !== '/') {
            $normalized .= $parsed['path'];
        }
        
        return $normalized;
    }
    
    private function normalizeText(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^\w\s]/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return $text;
    }
    
    private function normalizeTitle(string $title): string
    {
        $title = strtolower(trim($title));
        $title = preg_replace('/[^\w\s]/', ' ', $title);
        $title = preg_replace('/\s+/', ' ', $title);
        return $title;
    }
    
    private function extractText(array $content): string
    {
        return trim(
            ($content['title'] ?? '') . ' ' . 
            ($content['description'] ?? '') . ' ' . 
            ($content['content'] ?? '')
        );
    }
    
    private function extractWords(string $text): array
    {
        $words = preg_split('/\W+/', strtolower($text));
        return array_filter($words, fn($word) => 
            strlen($word) >= 3 && !in_array($word, $this->stopWords)
        );
    }
    
    private function createTfIdfVector(string $text): array
    {
        $words = $this->extractWords($text);
        $wordCount = array_count_values($words);
        $totalWords = count($words);
        
        $vector = [];
        foreach ($wordCount as $word => $count) {
            $tf = $count / $totalWords;
            // Simplified TF-IDF (IDF component would require document corpus)
            $vector[$word] = $tf;
        }
        
        return $vector;
    }
    
    private function generateShingles(string $text, int $size): array
    {
        $words = $this->extractWords($text);
        $shingles = [];
        
        for ($i = 0; $i <= count($words) - $size; $i++) {
            $shingle = implode(' ', array_slice($words, $i, $size));
            $shingles[] = $shingle;
        }
        
        return array_unique($shingles);
    }
    
    private function generateTextFingerprint(string $text): string
    {
        $words = $this->extractWords($text);
        $significant = array_slice($words, 0, 50); // First 50 significant words
        return hash('md5', implode('', $significant));
    }
    
    private function hashText(string $text): string
    {
        return hash('md5', $this->normalizeText($text));
    }
    
    private function loadConfig(): array
    {
        return [
            'duplicate_threshold' => 0.85,
            'cache_ttl' => 3600,
            'similarity_weights' => [
                'title' => 0.4,
                'content' => 0.5,
                'url' => 0.1
            ],
            'content_similarity_weights' => [
                'cosine' => 0.4,
                'jaccard' => 0.3,
                'levenshtein' => 0.2,
                'shingle' => 0.1
            ]
        ];
    }
    
    private function loadStopWords(): array
    {
        return [
            'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by',
            'is', 'are', 'was', 'were', 'be', 'been', 'have', 'has', 'had', 'do', 'does', 'did'
        ];
    }
    
    // Mock methods (would be implemented with real data access)
    private function queryExactDuplicates(string $url): array { return []; }
    private function getBaseUrl(string $url): string { return parse_url($url, PHP_URL_HOST) ?? ''; }
    private function extractDomain(string $url): string { return parse_url($url, PHP_URL_HOST) ?? ''; }
    private function generateUrlVariations(string $url): array { return [$url]; }
    private function findSimilarUrls(string $url): array { return []; }
    private function getVariantType(string $url1, string $url2): string { return 'parameter_difference'; }
    private function deduplicateAndSort(array $items, string $sortField = 'similarity'): array {
        usort($items, fn($a, $b) => $b[$sortField] <=> $a[$sortField]);
        return array_values(array_unique($items, SORT_REGULAR));
    }
    private function findExactTitleMatches(string $title): array { return []; }
    private function findFuzzyTitleMatches(string $title, int $limit): array { return []; }
    private function findByContentFingerprint(string $fingerprint, int $limit): array { return []; }
    private function calculateWordSimilarity(string $title1, string $title2): float { return 0.5; }
    private function calculateNgramSimilarity(string $title1, string $title2): float { return 0.5; }
    private function generateRecommendations(array $results, array $content): array {
        $recommendations = [];
        
        if ($results['is_duplicate']) {
            $recommendations[] = "This content appears to be a duplicate. Consider merging with existing content.";
        } elseif ($results['similarity_score'] > 0.7) {
            $recommendations[] = "High similarity detected. Review for potential duplication.";
        } elseif ($results['similarity_score'] > 0.5) {
            $recommendations[] = "Similar content exists. Consider cross-referencing.";
        }
        
        return $recommendations;
    }
}