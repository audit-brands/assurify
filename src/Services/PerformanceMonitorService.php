<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Performance Monitoring Service
 * 
 * Comprehensive system performance monitoring and alerting:
 * - Real-time performance metrics collection
 * - System resource monitoring (CPU, memory, disk)
 * - Database performance analysis
 * - API endpoint monitoring
 * - Alert generation and notification
 * - Performance optimization recommendations
 */
class PerformanceMonitorService
{
    private CacheService $cache;
    private array $config;
    private array $metrics = [];
    private array $thresholds;
    
    public function __construct(CacheService $cache = null)
    {
        $this->cache = $cache ?? new CacheService();
        $this->config = $this->loadConfig();
        $this->thresholds = $this->loadThresholds();
    }
    
    /**
     * Record a performance metric
     */
    public function recordMetric(string $metricName, float $value, array $labels = []): bool
    {
        try {
            $metric = [
                'metric_name' => $metricName,
                'metric_value' => $value,
                'metric_labels' => json_encode($labels),
                'recorded_at' => date('Y-m-d H:i:s')
            ];
            
            // Store in database
            DB::table('performance_metrics')->insert($metric);
            
            // Update real-time cache
            $this->updateRealTimeMetric($metricName, $value, $labels);
            
            // Check thresholds and generate alerts if needed
            $this->checkThresholds($metricName, $value, $labels);
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Performance metric recording failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Monitor API endpoint performance
     */
    public function monitorEndpoint(string $endpoint, string $method, float $responseTime, int $statusCode, array $context = []): void
    {
        $this->recordMetric('api_response_time', $responseTime, [
            'endpoint' => $endpoint,
            'method' => $method,
            'status_code' => $statusCode
        ]);
        
        // Track error rates
        if ($statusCode >= 400) {
            $this->recordMetric('api_error_count', 1, [
                'endpoint' => $endpoint,
                'method' => $method,
                'status_code' => $statusCode
            ]);
        }
        
        // Track throughput
        $this->recordMetric('api_request_count', 1, [
            'endpoint' => $endpoint,
            'method' => $method
        ]);
    }
    
    /**
     * Monitor database query performance
     */
    public function monitorDatabaseQuery(string $query, float $executionTime, array $context = []): void
    {
        $queryType = $this->getQueryType($query);
        
        $this->recordMetric('db_query_time', $executionTime, [
            'query_type' => $queryType,
            'table' => $this->extractTableName($query)
        ]);
        
        // Track slow queries
        if ($executionTime > $this->thresholds['slow_query_threshold']) {
            $this->recordSlowQuery($query, $executionTime, $context);
        }
    }
    
    /**
     * Monitor system resources
     */
    public function monitorSystemResources(): array
    {
        $metrics = [];
        
        // CPU usage
        $cpuUsage = $this->getCpuUsage();
        $metrics['cpu_usage'] = $cpuUsage;
        $this->recordMetric('system_cpu_usage', $cpuUsage);
        
        // Memory usage
        $memoryUsage = $this->getMemoryUsage();
        $metrics['memory_usage'] = $memoryUsage;
        $this->recordMetric('system_memory_usage', $memoryUsage['percentage']);
        
        // Disk usage
        $diskUsage = $this->getDiskUsage();
        $metrics['disk_usage'] = $diskUsage;
        $this->recordMetric('system_disk_usage', $diskUsage['percentage']);
        
        // Load average
        $loadAverage = $this->getLoadAverage();
        $metrics['load_average'] = $loadAverage;
        $this->recordMetric('system_load_average', $loadAverage['1min']);
        
        return $metrics;
    }
    
    /**
     * Monitor cache performance
     */
    public function monitorCachePerformance(): array
    {
        $stats = $this->cache->getStats();
        
        $hitRate = $stats['hits'] / max($stats['requests'], 1);
        $this->recordMetric('cache_hit_rate', $hitRate);
        
        $this->recordMetric('cache_memory_usage', $stats['memory_usage']);
        $this->recordMetric('cache_evictions', $stats['evictions']);
        
        return [
            'hit_rate' => $hitRate,
            'memory_usage' => $stats['memory_usage'],
            'evictions' => $stats['evictions'],
            'requests' => $stats['requests'],
            'hits' => $stats['hits'],
            'misses' => $stats['misses']
        ];
    }
    
    /**
     * Get real-time performance dashboard data
     */
    public function getDashboardData(): array
    {
        $cacheKey = 'performance_dashboard';
        
        if ($cached = $this->cache->get($cacheKey)) {
            return $cached;
        }
        
        $dashboard = [
            'system_health' => $this->getSystemHealthStatus(),
            'api_performance' => $this->getApiPerformanceMetrics(),
            'database_performance' => $this->getDatabasePerformanceMetrics(),
            'cache_performance' => $this->getCachePerformanceMetrics(),
            'recent_alerts' => $this->getRecentAlerts(10),
            'performance_trends' => $this->getPerformanceTrends(),
            'generated_at' => date('c')
        ];
        
        $this->cache->set($cacheKey, $dashboard, 30); // Cache for 30 seconds
        
        return $dashboard;
    }
    
    /**
     * Get system health status
     */
    public function getSystemHealthStatus(): array
    {
        $health = [
            'overall_status' => 'healthy',
            'components' => [],
            'alerts_count' => 0
        ];
        
        // Check each component
        $components = ['api', 'database', 'cache', 'disk', 'memory', 'cpu'];
        
        foreach ($components as $component) {
            $status = $this->checkComponentHealth($component);
            $health['components'][$component] = $status;
            
            if ($status['status'] !== 'healthy') {
                $health['overall_status'] = 'degraded';
                if ($status['status'] === 'critical') {
                    $health['overall_status'] = 'critical';
                }
            }
        }
        
        $health['alerts_count'] = $this->getActiveAlertsCount();
        
        return $health;
    }
    
    /**
     * Generate performance report
     */
    public function generatePerformanceReport(string $startDate, string $endDate): array
    {
        $cacheKey = "performance_report_{$startDate}_{$endDate}";
        
        if ($cached = $this->cache->get($cacheKey)) {
            return $cached;
        }
        
        $report = [
            'period' => ['start' => $startDate, 'end' => $endDate],
            'summary' => $this->getPerformanceSummary($startDate, $endDate),
            'api_analysis' => $this->getApiAnalysis($startDate, $endDate),
            'database_analysis' => $this->getDatabaseAnalysis($startDate, $endDate),
            'system_analysis' => $this->getSystemAnalysis($startDate, $endDate),
            'alerts_summary' => $this->getAlertsSummary($startDate, $endDate),
            'recommendations' => $this->generateRecommendations($startDate, $endDate),
            'generated_at' => date('c')
        ];
        
        $this->cache->set($cacheKey, $report, 3600); // Cache for 1 hour
        
        return $report;
    }
    
    /**
     * Get performance trends
     */
    public function getPerformanceTrends(int $hours = 24): array
    {
        $cacheKey = "performance_trends_{$hours}";
        
        if ($cached = $this->cache->get($cacheKey)) {
            return $cached;
        }
        
        $endTime = date('Y-m-d H:i:s');
        $startTime = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        
        $trends = [
            'response_time_trend' => $this->getMetricTrend('api_response_time', $startTime, $endTime),
            'error_rate_trend' => $this->getMetricTrend('api_error_rate', $startTime, $endTime),
            'cpu_usage_trend' => $this->getMetricTrend('system_cpu_usage', $startTime, $endTime),
            'memory_usage_trend' => $this->getMetricTrend('system_memory_usage', $startTime, $endTime),
            'database_performance_trend' => $this->getMetricTrend('db_query_time', $startTime, $endTime),
            'cache_hit_rate_trend' => $this->getMetricTrend('cache_hit_rate', $startTime, $endTime)
        ];
        
        $this->cache->set($cacheKey, $trends, 300); // Cache for 5 minutes
        
        return $trends;
    }
    
    /**
     * Optimize performance based on current metrics
     */
    public function optimizePerformance(): array
    {
        $optimizations = [];
        
        // Analyze current performance data
        $currentMetrics = $this->getCurrentMetrics();
        
        // Database optimization suggestions
        $dbOptimizations = $this->suggestDatabaseOptimizations($currentMetrics);
        if (!empty($dbOptimizations)) {
            $optimizations['database'] = $dbOptimizations;
        }
        
        // Cache optimization suggestions
        $cacheOptimizations = $this->suggestCacheOptimizations($currentMetrics);
        if (!empty($cacheOptimizations)) {
            $optimizations['cache'] = $cacheOptimizations;
        }
        
        // API optimization suggestions
        $apiOptimizations = $this->suggestApiOptimizations($currentMetrics);
        if (!empty($apiOptimizations)) {
            $optimizations['api'] = $apiOptimizations;
        }
        
        // System optimization suggestions
        $systemOptimizations = $this->suggestSystemOptimizations($currentMetrics);
        if (!empty($systemOptimizations)) {
            $optimizations['system'] = $systemOptimizations;
        }
        
        return $optimizations;
    }
    
    /**
     * Create performance alert
     */
    public function createAlert(string $alertType, string $message, string $severity = 'warning', array $context = []): bool
    {
        try {
            $alert = [
                'alert_type' => $alertType,
                'message' => $message,
                'severity' => $severity,
                'context' => json_encode($context),
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            DB::table('performance_alerts')->insert($alert);
            
            // Send notifications for critical alerts
            if ($severity === 'critical') {
                $this->sendCriticalAlertNotification($alert);
            }
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Alert creation failed: " . $e->getMessage());
            return false;
        }
    }
    
    // Private helper methods
    
    private function updateRealTimeMetric(string $metricName, float $value, array $labels): void
    {
        $key = "realtime_metric_{$metricName}";
        $data = [
            'value' => $value,
            'labels' => $labels,
            'timestamp' => time()
        ];
        
        $this->cache->set($key, $data, 300); // 5 minutes TTL
    }
    
    private function checkThresholds(string $metricName, float $value, array $labels): void
    {
        if (!isset($this->thresholds[$metricName])) {
            return;
        }
        
        $threshold = $this->thresholds[$metricName];
        
        if ($value > $threshold['critical']) {
            $this->createAlert(
                $metricName,
                "Critical threshold exceeded: {$metricName} = {$value}",
                'critical',
                ['metric' => $metricName, 'value' => $value, 'threshold' => $threshold['critical']]
            );
        } elseif ($value > $threshold['warning']) {
            $this->createAlert(
                $metricName,
                "Warning threshold exceeded: {$metricName} = {$value}",
                'warning',
                ['metric' => $metricName, 'value' => $value, 'threshold' => $threshold['warning']]
            );
        }
    }
    
    private function getCpuUsage(): float
    {
        // Mock implementation - would use system monitoring
        return rand(10, 80) + (rand(0, 100) / 100);
    }
    
    private function getMemoryUsage(): array
    {
        $total = memory_get_peak_usage(true);
        $current = memory_get_usage(true);
        
        return [
            'total' => $total,
            'current' => $current,
            'percentage' => ($current / $total) * 100
        ];
    }
    
    private function getDiskUsage(): array
    {
        $total = disk_total_space('/');
        $free = disk_free_space('/');
        $used = $total - $free;
        
        return [
            'total' => $total,
            'used' => $used,
            'free' => $free,
            'percentage' => ($used / $total) * 100
        ];
    }
    
    private function getLoadAverage(): array
    {
        $load = sys_getloadavg();
        
        return [
            '1min' => $load[0] ?? 0,
            '5min' => $load[1] ?? 0,
            '15min' => $load[2] ?? 0
        ];
    }
    
    private function getQueryType(string $query): string
    {
        $query = strtoupper(trim($query));
        if (strpos($query, 'SELECT') === 0) return 'SELECT';
        if (strpos($query, 'INSERT') === 0) return 'INSERT';
        if (strpos($query, 'UPDATE') === 0) return 'UPDATE';
        if (strpos($query, 'DELETE') === 0) return 'DELETE';
        return 'OTHER';
    }
    
    private function extractTableName(string $query): ?string
    {
        // Simple table name extraction
        if (preg_match('/(?:FROM|INTO|UPDATE|JOIN)\s+`?(\w+)`?/i', $query, $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    private function loadConfig(): array
    {
        return [
            'monitoring_interval' => 60, // seconds
            'retention_days' => 30,
            'alert_cooldown' => 300, // 5 minutes
            'batch_size' => 1000
        ];
    }
    
    private function loadThresholds(): array
    {
        return [
            'api_response_time' => ['warning' => 1000, 'critical' => 3000], // milliseconds
            'system_cpu_usage' => ['warning' => 80, 'critical' => 95], // percentage
            'system_memory_usage' => ['warning' => 85, 'critical' => 95], // percentage
            'system_disk_usage' => ['warning' => 80, 'critical' => 90], // percentage
            'db_query_time' => ['warning' => 1000, 'critical' => 5000], // milliseconds
            'cache_hit_rate' => ['warning' => 0.8, 'critical' => 0.6], // percentage (inverted)
            'slow_query_threshold' => 1000 // milliseconds
        ];
    }
    
    // Mock implementations (would be implemented with real monitoring)
    private function recordSlowQuery(string $query, float $time, array $context): void {}
    private function checkComponentHealth(string $component): array { 
        return ['status' => 'healthy', 'message' => 'All systems operational']; 
    }
    private function getActiveAlertsCount(): int { return 0; }
    private function getApiPerformanceMetrics(): array { return []; }
    private function getDatabasePerformanceMetrics(): array { return []; }
    private function getCachePerformanceMetrics(): array { return []; }
    private function getRecentAlerts(int $limit): array { return []; }
    private function getPerformanceSummary(string $startDate, string $endDate): array { return []; }
    private function getApiAnalysis(string $startDate, string $endDate): array { return []; }
    private function getDatabaseAnalysis(string $startDate, string $endDate): array { return []; }
    private function getSystemAnalysis(string $startDate, string $endDate): array { return []; }
    private function getAlertsSummary(string $startDate, string $endDate): array { return []; }
    private function generateRecommendations(string $startDate, string $endDate): array { return []; }
    private function getMetricTrend(string $metric, string $startTime, string $endTime): array { return []; }
    private function getCurrentMetrics(): array { return []; }
    private function suggestDatabaseOptimizations(array $metrics): array { return []; }
    private function suggestCacheOptimizations(array $metrics): array { return []; }
    private function suggestApiOptimizations(array $metrics): array { return []; }
    private function suggestSystemOptimizations(array $metrics): array { return []; }
    private function sendCriticalAlertNotification(array $alert): void {}
}