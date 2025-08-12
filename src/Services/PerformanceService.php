<?php

declare(strict_types=1);

namespace App\Services;

class PerformanceService
{
    private const METRICS_FILE = '/tmp/lobsters_metrics.json';
    private array $timers = [];
    private array $counters = [];
    private array $gauges = [];

    public function startTimer(string $name): void
    {
        $this->timers[$name] = [
            'start' => microtime(true),
            'memory_start' => memory_get_usage(true)
        ];
    }

    public function endTimer(string $name): float
    {
        if (!isset($this->timers[$name])) {
            return 0.0;
        }

        $duration = microtime(true) - $this->timers[$name]['start'];
        $memoryUsed = memory_get_usage(true) - $this->timers[$name]['memory_start'];
        
        $this->recordMetric('timer', $name, [
            'duration' => $duration,
            'memory_used' => $memoryUsed,
            'timestamp' => time()
        ]);

        unset($this->timers[$name]);
        return $duration;
    }

    public function incrementCounter(string $name, int $value = 1): void
    {
        if (!isset($this->counters[$name])) {
            $this->counters[$name] = 0;
        }
        $this->counters[$name] += $value;
        
        $this->recordMetric('counter', $name, $this->counters[$name]);
    }

    public function setGauge(string $name, float $value): void
    {
        $this->gauges[$name] = $value;
        $this->recordMetric('gauge', $name, $value);
    }

    public function recordDatabaseQuery(string $query, float $duration): void
    {
        $this->recordMetric('db_query', 'query_time', [
            'query' => substr($query, 0, 100), // First 100 chars
            'duration' => $duration,
            'timestamp' => time()
        ]);
        
        $this->incrementCounter('db_queries_total');
    }

    public function recordCacheHit(string $key): void
    {
        $this->incrementCounter('cache_hits');
        $this->recordMetric('cache', 'hit', [
            'key' => $key,
            'timestamp' => time()
        ]);
    }

    public function recordCacheMiss(string $key): void
    {
        $this->incrementCounter('cache_misses');
        $this->recordMetric('cache', 'miss', [
            'key' => $key,
            'timestamp' => time()
        ]);
    }

    public function recordHttpRequest(string $method, string $path, int $statusCode, float $duration): void
    {
        $this->recordMetric('http_request', 'request', [
            'method' => $method,
            'path' => $path,
            'status_code' => $statusCode,
            'duration' => $duration,
            'timestamp' => time()
        ]);
        
        $this->incrementCounter("http_requests_{$statusCode}");
        $this->incrementCounter('http_requests_total');
    }

    public function getMetrics(): array
    {
        return [
            'system' => $this->getSystemMetrics(),
            'application' => $this->getApplicationMetrics(),
            'database' => $this->getDatabaseMetrics(),
            'cache' => $this->getCacheMetrics(),
            'http' => $this->getHttpMetrics(),
            'memory' => $this->getMemoryMetrics(),
            'performance' => $this->getPerformanceMetrics()
        ];
    }

    public function getSystemMetrics(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'memory_limit' => ini_get('memory_limit'),
            'uptime' => $this->getUptime(),
            'load_average' => $this->getLoadAverage(),
            'disk_usage' => $this->getDiskUsage(),
            'timestamp' => time()
        ];
    }

    public function getApplicationMetrics(): array
    {
        $metrics = $this->loadMetrics();
        
        return [
            'counters' => $this->counters,
            'gauges' => $this->gauges,
            'active_timers' => count($this->timers),
            'total_metrics' => count($metrics),
            'metrics_file_size' => file_exists(self::METRICS_FILE) ? filesize(self::METRICS_FILE) : 0
        ];
    }

    public function getDatabaseMetrics(): array
    {
        $metrics = $this->loadMetrics();
        $queryTimes = [];
        $totalQueries = 0;
        
        foreach ($metrics as $metric) {
            if ($metric['type'] === 'db_query') {
                $queryTimes[] = $metric['value']['duration'];
                $totalQueries++;
            }
        }
        
        return [
            'total_queries' => $this->counters['db_queries_total'] ?? 0,
            'average_query_time' => !empty($queryTimes) ? array_sum($queryTimes) / count($queryTimes) : 0,
            'max_query_time' => !empty($queryTimes) ? max($queryTimes) : 0,
            'min_query_time' => !empty($queryTimes) ? min($queryTimes) : 0,
            'queries_last_hour' => $this->countMetricsInTimeframe('db_query', 3600)
        ];
    }

    public function getCacheMetrics(): array
    {
        return [
            'hits' => $this->counters['cache_hits'] ?? 0,
            'misses' => $this->counters['cache_misses'] ?? 0,
            'hit_rate' => $this->calculateCacheHitRate(),
            'hits_last_hour' => $this->countMetricsInTimeframe('cache', 3600, 'hit'),
            'misses_last_hour' => $this->countMetricsInTimeframe('cache', 3600, 'miss')
        ];
    }

    public function getHttpMetrics(): array
    {
        $status_codes = [];
        foreach ($this->counters as $key => $value) {
            if (str_starts_with($key, 'http_requests_') && $key !== 'http_requests_total') {
                $code = str_replace('http_requests_', '', $key);
                $status_codes[$code] = $value;
            }
        }
        
        return [
            'total_requests' => $this->counters['http_requests_total'] ?? 0,
            'status_codes' => $status_codes,
            'requests_last_hour' => $this->countMetricsInTimeframe('http_request', 3600),
            'average_response_time' => $this->getAverageResponseTime()
        ];
    }

    public function getMemoryMetrics(): array
    {
        return [
            'current_usage' => memory_get_usage(true),
            'current_usage_formatted' => $this->formatBytes(memory_get_usage(true)),
            'peak_usage' => memory_get_peak_usage(true),
            'peak_usage_formatted' => $this->formatBytes(memory_get_peak_usage(true)),
            'limit' => ini_get('memory_limit'),
            'usage_percentage' => $this->getMemoryUsagePercentage()
        ];
    }

    public function getPerformanceMetrics(): array
    {
        $metrics = $this->loadMetrics();
        $timers = array_filter($metrics, fn($m) => $m['type'] === 'timer');
        
        $durations = array_column(array_column($timers, 'value'), 'duration');
        
        return [
            'average_execution_time' => !empty($durations) ? array_sum($durations) / count($durations) : 0,
            'max_execution_time' => !empty($durations) ? max($durations) : 0,
            'min_execution_time' => !empty($durations) ? min($durations) : 0,
            'total_timers' => count($timers),
            '95th_percentile' => $this->calculatePercentile($durations, 95),
            '99th_percentile' => $this->calculatePercentile($durations, 99)
        ];
    }

    public function generateReport(): string
    {
        $metrics = $this->getMetrics();
        $report = "Performance Report - " . date('Y-m-d H:i:s') . "\n";
        $report .= str_repeat('=', 50) . "\n\n";
        
        $report .= "System Metrics:\n";
        $report .= "- Memory Usage: " . $this->formatBytes($metrics['memory']['current_usage']) . "\n";
        $report .= "- Peak Memory: " . $this->formatBytes($metrics['memory']['peak_usage']) . "\n";
        $report .= "- Memory Usage: " . number_format($metrics['memory']['usage_percentage'], 2) . "%\n\n";
        
        $report .= "Database Metrics:\n";
        $report .= "- Total Queries: " . number_format($metrics['database']['total_queries']) . "\n";
        $report .= "- Average Query Time: " . number_format($metrics['database']['average_query_time'] * 1000, 2) . "ms\n";
        $report .= "- Max Query Time: " . number_format($metrics['database']['max_query_time'] * 1000, 2) . "ms\n\n";
        
        $report .= "Cache Metrics:\n";
        $report .= "- Cache Hits: " . number_format($metrics['cache']['hits']) . "\n";
        $report .= "- Cache Misses: " . number_format($metrics['cache']['misses']) . "\n";
        $report .= "- Hit Rate: " . number_format($metrics['cache']['hit_rate'], 2) . "%\n\n";
        
        $report .= "HTTP Metrics:\n";
        $report .= "- Total Requests: " . number_format($metrics['http']['total_requests']) . "\n";
        $report .= "- Average Response Time: " . number_format($metrics['http']['average_response_time'] * 1000, 2) . "ms\n\n";
        
        return $report;
    }

    private function recordMetric(string $type, string $name, mixed $value): void
    {
        $metric = [
            'type' => $type,
            'name' => $name,
            'value' => $value,
            'timestamp' => time()
        ];
        
        $metrics = $this->loadMetrics();
        $metrics[] = $metric;
        
        // Keep only last 10000 metrics to prevent file from growing too large
        if (count($metrics) > 10000) {
            $metrics = array_slice($metrics, -10000);
        }
        
        file_put_contents(self::METRICS_FILE, json_encode($metrics), LOCK_EX);
    }

    private function loadMetrics(): array
    {
        if (!file_exists(self::METRICS_FILE)) {
            return [];
        }
        
        $contents = file_get_contents(self::METRICS_FILE);
        if ($contents === false) {
            return [];
        }
        
        $metrics = json_decode($contents, true);
        return is_array($metrics) ? $metrics : [];
    }

    private function getUptime(): int
    {
        // Simple uptime calculation - in production would use system uptime
        return time() - (filemtime(__FILE__) ?? time());
    }

    private function getLoadAverage(): array
    {
        if (function_exists('sys_getloadavg')) {
            return sys_getloadavg();
        }
        return [0, 0, 0];
    }

    private function getDiskUsage(): array
    {
        $total = disk_total_space('/');
        $free = disk_free_space('/');
        
        return [
            'total' => $total,
            'free' => $free,
            'used' => $total - $free,
            'usage_percentage' => $total > 0 ? (($total - $free) / $total) * 100 : 0
        ];
    }

    private function calculateCacheHitRate(): float
    {
        $hits = $this->counters['cache_hits'] ?? 0;
        $misses = $this->counters['cache_misses'] ?? 0;
        $total = $hits + $misses;
        
        return $total > 0 ? ($hits / $total) * 100 : 0;
    }

    private function getMemoryUsagePercentage(): float
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') {
            return 0; // No limit
        }
        
        $limitBytes = $this->parseBytes($limit);
        $currentUsage = memory_get_usage(true);
        
        return $limitBytes > 0 ? ($currentUsage / $limitBytes) * 100 : 0;
    }

    private function countMetricsInTimeframe(string $type, int $seconds, ?string $name = null): int
    {
        $metrics = $this->loadMetrics();
        $cutoff = time() - $seconds;
        $count = 0;
        
        foreach ($metrics as $metric) {
            if ($metric['type'] === $type && $metric['timestamp'] >= $cutoff) {
                if ($name === null || $metric['name'] === $name) {
                    $count++;
                }
            }
        }
        
        return $count;
    }

    private function getAverageResponseTime(): float
    {
        $metrics = $this->loadMetrics();
        $responseTimes = [];
        
        foreach ($metrics as $metric) {
            if ($metric['type'] === 'http_request') {
                $responseTimes[] = $metric['value']['duration'];
            }
        }
        
        return !empty($responseTimes) ? array_sum($responseTimes) / count($responseTimes) : 0;
    }

    private function calculatePercentile(array $values, int $percentile): float
    {
        if (empty($values)) {
            return 0;
        }
        
        sort($values);
        $index = ($percentile / 100) * (count($values) - 1);
        
        if ($index == intval($index)) {
            return $values[$index];
        }
        
        $lower = $values[intval($index)];
        $upper = $values[intval($index) + 1];
        $fraction = $index - intval($index);
        
        return $lower + ($fraction * ($upper - $lower));
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = 1024;
        
        for ($i = 0; $bytes >= $factor && $i < count($units) - 1; $i++) {
            $bytes /= $factor;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    private function parseBytes(string $value): int
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;
        
        switch ($last) {
            case 'g':
                $value *= 1024;
                // no break
            case 'm':
                $value *= 1024;
                // no break
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
}