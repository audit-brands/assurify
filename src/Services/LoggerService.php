<?php

declare(strict_types=1);

namespace App\Services;

class LoggerService
{
    private const LOG_DIR = '/tmp/lobsters_logs/';
    private const MAX_LOG_SIZE = 10 * 1024 * 1024; // 10MB
    private const MAX_LOG_FILES = 5;

    public function __construct()
    {
        if (!is_dir(self::LOG_DIR)) {
            mkdir(self::LOG_DIR, 0755, true);
        }
    }

    public function emergency(string $message, array $context = []): void
    {
        $this->log('EMERGENCY', $message, $context);
    }

    public function alert(string $message, array $context = []): void
    {
        $this->log('ALERT', $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log('CRITICAL', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    public function notice(string $message, array $context = []): void
    {
        $this->log('NOTICE', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }

    public function logException(\Throwable $exception, array $context = []): void
    {
        $message = sprintf(
            '%s: %s in %s:%d',
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );

        $context['exception'] = [
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ];

        $this->error($message, $context);
    }

    public function logHttpRequest(string $method, string $path, int $statusCode, float $duration, array $context = []): void
    {
        $message = sprintf('%s %s - %d (%sms)', $method, $path, $statusCode, number_format($duration * 1000, 2));
        
        $context['http'] = [
            'method' => $method,
            'path' => $path,
            'status_code' => $statusCode,
            'duration' => $duration,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip' => $this->getClientIp(),
            'referer' => $_SERVER['HTTP_REFERER'] ?? ''
        ];

        $this->info($message, $context);
    }

    public function logDatabaseQuery(string $query, float $duration, array $bindings = []): void
    {
        $message = sprintf('DB Query (%sms): %s', number_format($duration * 1000, 2), substr($query, 0, 100));
        
        $context = [
            'database' => [
                'query' => $query,
                'bindings' => $bindings,
                'duration' => $duration
            ]
        ];

        if ($duration > 1.0) { // Slow query threshold
            $this->warning('Slow database query detected', $context);
        } else {
            $this->debug($message, $context);
        }
    }

    public function logCacheOperation(string $operation, string $key, bool $hit = null, float $duration = null): void
    {
        $message = sprintf('Cache %s: %s', $operation, $key);
        
        $context = [
            'cache' => [
                'operation' => $operation,
                'key' => $key,
                'hit' => $hit,
                'duration' => $duration
            ]
        ];

        $this->debug($message, $context);
    }

    public function logSecurityEvent(string $event, array $context = []): void
    {
        $message = sprintf('Security Event: %s', $event);
        
        $context['security'] = array_merge($context, [
            'ip' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'user_id' => $_SESSION['user_id'] ?? null,
            'timestamp' => time()
        ]);

        $this->warning($message, $context);
    }

    public function logPerformanceMetric(string $metric, float $value, array $context = []): void
    {
        $message = sprintf('Performance: %s = %s', $metric, $value);
        
        $context['performance'] = [
            'metric' => $metric,
            'value' => $value,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true)
        ];

        $this->info($message, $context);
    }

    public function getLogEntries(string $level = 'all', int $limit = 100): array
    {
        $entries = [];
        $files = $this->getLogFiles();
        
        foreach (array_reverse($files) as $file) {
            $handle = fopen($file, 'r');
            if ($handle) {
                $lines = [];
                while (($line = fgets($handle)) !== false) {
                    $lines[] = $line;
                }
                fclose($handle);
                
                // Process lines in reverse order (newest first)
                foreach (array_reverse($lines) as $line) {
                    $entry = $this->parseLogLine($line);
                    if ($entry && ($level === 'all' || $entry['level'] === strtoupper($level))) {
                        $entries[] = $entry;
                        if (count($entries) >= $limit) {
                            break 2;
                        }
                    }
                }
            }
        }
        
        return $entries;
    }

    public function getLogStats(): array
    {
        $stats = [
            'total_logs' => 0,
            'log_files' => 0,
            'total_size' => 0,
            'levels' => [
                'ERROR' => 0,
                'WARNING' => 0,
                'INFO' => 0,
                'DEBUG' => 0
            ],
            'oldest_entry' => null,
            'newest_entry' => null
        ];

        $files = $this->getLogFiles();
        $stats['log_files'] = count($files);
        
        foreach ($files as $file) {
            $stats['total_size'] += filesize($file);
            
            $handle = fopen($file, 'r');
            if ($handle) {
                while (($line = fgets($handle)) !== false) {
                    $stats['total_logs']++;
                    $entry = $this->parseLogLine($line);
                    if ($entry) {
                        if (isset($stats['levels'][$entry['level']])) {
                            $stats['levels'][$entry['level']]++;
                        }
                        
                        if (!$stats['oldest_entry'] || $entry['timestamp'] < $stats['oldest_entry']) {
                            $stats['oldest_entry'] = $entry['timestamp'];
                        }
                        
                        if (!$stats['newest_entry'] || $entry['timestamp'] > $stats['newest_entry']) {
                            $stats['newest_entry'] = $entry['timestamp'];
                        }
                    }
                }
                fclose($handle);
            }
        }
        
        $stats['total_size_formatted'] = $this->formatBytes($stats['total_size']);
        
        return $stats;
    }

    public function cleanupLogs(): int
    {
        $files = $this->getLogFiles();
        $cleaned = 0;
        
        // Remove old log files beyond the retention limit
        if (count($files) > self::MAX_LOG_FILES) {
            $filesToRemove = array_slice($files, 0, count($files) - self::MAX_LOG_FILES);
            foreach ($filesToRemove as $file) {
                if (unlink($file)) {
                    $cleaned++;
                }
            }
        }
        
        return $cleaned;
    }

    private function log(string $level, string $message, array $context = []): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextJson = !empty($context) ? json_encode($context, JSON_UNESCAPED_SLASHES) : '';
        
        $logEntry = sprintf(
            "[%s] %s: %s %s\n",
            $timestamp,
            $level,
            $message,
            $contextJson
        );
        
        $logFile = $this->getCurrentLogFile();
        
        // Rotate log if it's too large
        if (file_exists($logFile) && filesize($logFile) > self::MAX_LOG_SIZE) {
            $this->rotateLog();
            $logFile = $this->getCurrentLogFile();
        }
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        // Also log critical errors to PHP error log
        if (in_array($level, ['EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR'])) {
            error_log("Lobsters [{$level}]: {$message}");
        }
    }

    private function getCurrentLogFile(): string
    {
        return self::LOG_DIR . 'lobsters.log';
    }

    private function rotateLog(): void
    {
        $baseFile = $this->getCurrentLogFile();
        
        if (!file_exists($baseFile)) {
            return;
        }
        
        // Rotate existing logs
        for ($i = self::MAX_LOG_FILES - 1; $i >= 1; $i--) {
            $oldFile = self::LOG_DIR . "lobsters.log.{$i}";
            $newFile = self::LOG_DIR . "lobsters.log." . ($i + 1);
            
            if (file_exists($oldFile)) {
                if ($i + 1 <= self::MAX_LOG_FILES) {
                    rename($oldFile, $newFile);
                } else {
                    unlink($oldFile);
                }
            }
        }
        
        // Move current log to .1
        rename($baseFile, self::LOG_DIR . 'lobsters.log.1');
    }

    private function getLogFiles(): array
    {
        $pattern = self::LOG_DIR . 'lobsters.log*';
        $files = glob($pattern);
        
        // Sort by modification time
        usort($files, function ($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        return $files;
    }

    private function parseLogLine(string $line): ?array
    {
        $pattern = '/^\[([^\]]+)\] ([A-Z]+): (.+)$/';
        if (preg_match($pattern, trim($line), $matches)) {
            $contextJson = '';
            $message = $matches[3];
            
            // Try to extract JSON context from the end
            if (preg_match('/^(.+) (\{.+\})$/', $message, $msgMatches)) {
                $message = $msgMatches[1];
                $contextJson = $msgMatches[2];
            }
            
            return [
                'timestamp' => $matches[1],
                'level' => $matches[2],
                'message' => $message,
                'context' => $contextJson ? json_decode($contextJson, true) : []
            ];
        }
        
        return null;
    }

    private function getClientIp(): string
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = 1024;
        
        for ($i = 0; $bytes >= $factor && $i < count($units) - 1; $i++) {
            $bytes /= $factor;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}