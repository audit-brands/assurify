<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchAnalytic extends Model
{
    protected $table = 'search_analytics';

    protected $fillable = [
        'user_id',
        'query',
        'query_normalized',
        'type',
        'filters',
        'results_count',
        'clicked_result_id',
        'clicked_result_type',
        'search_time_ms',
        'ip_address',
        'user_agent',
        'created_at'
    ];

    protected $casts = [
        'user_id' => 'integer',
        'filters' => 'array',
        'results_count' => 'integer',
        'clicked_result_id' => 'integer',
        'search_time_ms' => 'integer',
        'created_at' => 'datetime'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get popular search queries
     */
    public static function getPopularQueries(int $limit = 10, int $days = 30): array
    {
        return static::select('query_normalized')
            ->selectRaw('COUNT(*) as search_count')
            ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime("-{$days} days")))
            ->whereNotNull('query_normalized')
            ->where('query_normalized', '!=', '')
            ->groupBy('query_normalized')
            ->orderByDesc('search_count')
            ->limit($limit)
            ->pluck('search_count', 'query_normalized')
            ->toArray();
    }

    /**
     * Get trending searches (recent surge in popularity)
     */
    public static function getTrendingQueries(int $limit = 10): array
    {
        $recentQueries = static::select('query_normalized')
            ->selectRaw('COUNT(*) as recent_count')
            ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-3 days')))
            ->groupBy('query_normalized')
            ->having('recent_count', '>=', 5) // Minimum threshold
            ->pluck('recent_count', 'query_normalized');

        $historicalQueries = static::select('query_normalized')
            ->selectRaw('COUNT(*) as historical_count')
            ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-30 days')))
            ->where('created_at', '<', date('Y-m-d H:i:s', strtotime('-3 days')))
            ->groupBy('query_normalized')
            ->pluck('historical_count', 'query_normalized');

        $trending = [];
        foreach ($recentQueries as $query => $recentCount) {
            $historicalCount = $historicalQueries[$query] ?? 0;
            $trend_ratio = $historicalCount > 0 ? $recentCount / $historicalCount : $recentCount;
            
            if ($trend_ratio > 1.5) { // 50% increase threshold
                $trending[$query] = $trend_ratio;
            }
        }

        arsort($trending);
        return array_slice($trending, 0, $limit, true);
    }

    /**
     * Get search statistics
     */
    public static function getSearchStats(int $days = 30): array
    {
        $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $totalSearches = static::where('created_at', '>=', $startDate)->count();
        
        $uniqueQueries = static::where('created_at', '>=', $startDate)
            ->distinct('query_normalized')
            ->count('query_normalized');
        
        $avgResultsCount = static::where('created_at', '>=', $startDate)
            ->avg('results_count') ?? 0;
        
        $avgSearchTime = static::where('created_at', '>=', $startDate)
            ->avg('search_time_ms') ?? 0;
        
        $clickThroughRate = static::where('created_at', '>=', $startDate)
            ->whereNotNull('clicked_result_id')
            ->count() / max($totalSearches, 1) * 100;

        $noResultsQueries = static::where('created_at', '>=', $startDate)
            ->where('results_count', 0)
            ->count();

        $noResultsRate = $noResultsQueries / max($totalSearches, 1) * 100;

        return [
            'total_searches' => $totalSearches,
            'unique_queries' => $uniqueQueries,
            'avg_results_count' => round($avgResultsCount, 2),
            'avg_search_time_ms' => round($avgSearchTime, 2),
            'click_through_rate' => round($clickThroughRate, 2),
            'no_results_rate' => round($noResultsRate, 2),
            'period_days' => $days
        ];
    }

    /**
     * Get failed searches (no results)
     */
    public static function getFailedSearches(int $limit = 20, int $days = 7): array
    {
        return static::select('query_normalized')
            ->selectRaw('COUNT(*) as failure_count')
            ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime("-{$days} days")))
            ->where('results_count', 0)
            ->groupBy('query_normalized')
            ->orderByDesc('failure_count')
            ->limit($limit)
            ->pluck('failure_count', 'query_normalized')
            ->toArray();
    }

    /**
     * Track search result click
     */
    public function trackClick(int $resultId, string $resultType): bool
    {
        return $this->update([
            'clicked_result_id' => $resultId,
            'clicked_result_type' => $resultType
        ]);
    }
}