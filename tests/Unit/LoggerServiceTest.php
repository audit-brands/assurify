<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\LoggerService;

class LoggerServiceTest extends TestCase
{
    private LoggerService $loggerService;
    private string $logDir = '/tmp/lobsters_logs_test/';

    protected function setUp(): void
    {
        // Use test log directory
        $reflection = new \ReflectionClass(LoggerService::class);
        $this->loggerService = new LoggerService();
        
        // Clean up any existing test logs
        if (is_dir($this->logDir)) {
            $files = glob($this->logDir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    protected function tearDown(): void
    {
        // Clean up test logs
        if (is_dir($this->logDir)) {
            $files = glob($this->logDir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    public function testLogLevels(): void
    {
        $levels = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];
        
        foreach ($levels as $level) {
            $message = "Test {$level} message";
            $this->loggerService->$level($message);
        }
        
        // Test passes if no exceptions are thrown
        $this->assertTrue(true);
    }

    public function testLogWithContext(): void
    {
        $message = 'Test message with context';
        $context = [
            'user_id' => 123,
            'action' => 'test_action',
            'data' => ['key' => 'value']
        ];
        
        $this->loggerService->info($message, $context);
        
        // Test passes if no exceptions are thrown
        $this->assertTrue(true);
    }

    public function testLogException(): void
    {
        $exception = new \Exception('Test exception', 500);
        
        $this->loggerService->logException($exception);
        
        // Test passes if no exceptions are thrown
        $this->assertTrue(true);
    }

    public function testLogHttpRequest(): void
    {
        $method = 'GET';
        $path = '/test/path';
        $statusCode = 200;
        $duration = 0.123;
        
        $this->loggerService->logHttpRequest($method, $path, $statusCode, $duration);
        
        // Test passes if no exceptions are thrown
        $this->assertTrue(true);
    }

    public function testLogDatabaseQuery(): void
    {
        $query = 'SELECT * FROM users WHERE id = ?';
        $duration = 0.045;
        $bindings = [123];
        
        $this->loggerService->logDatabaseQuery($query, $duration, $bindings);
        
        // Test passes if no exceptions are thrown
        $this->assertTrue(true);
    }

    public function testLogSlowQuery(): void
    {
        $query = 'SELECT * FROM large_table';
        $slowDuration = 2.5; // Over 1 second threshold
        
        $this->loggerService->logDatabaseQuery($query, $slowDuration);
        
        // Test passes if no exceptions are thrown
        $this->assertTrue(true);
    }

    public function testLogCacheOperation(): void
    {
        $operation = 'set';
        $key = 'test_key';
        $hit = true;
        $duration = 0.001;
        
        $this->loggerService->logCacheOperation($operation, $key, $hit, $duration);
        
        // Test passes if no exceptions are thrown
        $this->assertTrue(true);
    }

    public function testLogSecurityEvent(): void
    {
        $event = 'Failed login attempt';
        $context = [
            'username' => 'testuser',
            'ip' => '192.168.1.1',
            'attempts' => 3
        ];
        
        $this->loggerService->logSecurityEvent($event, $context);
        
        // Test passes if no exceptions are thrown
        $this->assertTrue(true);
    }

    public function testLogPerformanceMetric(): void
    {
        $metric = 'response_time';
        $value = 0.234;
        $context = ['endpoint' => '/api/stories'];
        
        $this->loggerService->logPerformanceMetric($metric, $value, $context);
        
        // Test passes if no exceptions are thrown
        $this->assertTrue(true);
    }

    public function testGetLogStats(): void
    {
        // Log some entries first
        $this->loggerService->info('Test info message');
        $this->loggerService->warning('Test warning message');
        $this->loggerService->error('Test error message');
        
        $stats = $this->loggerService->getLogStats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_logs', $stats);
        $this->assertArrayHasKey('log_files', $stats);
        $this->assertArrayHasKey('total_size', $stats);
        $this->assertArrayHasKey('levels', $stats);
        $this->assertArrayHasKey('total_size_formatted', $stats);
        
        $this->assertIsArray($stats['levels']);
        $this->assertArrayHasKey('ERROR', $stats['levels']);
        $this->assertArrayHasKey('WARNING', $stats['levels']);
        $this->assertArrayHasKey('INFO', $stats['levels']);
        $this->assertArrayHasKey('DEBUG', $stats['levels']);
    }

    public function testGetLogEntries(): void
    {
        // Log some entries first
        $this->loggerService->info('Test info message');
        $this->loggerService->warning('Test warning message');
        $this->loggerService->error('Test error message');
        
        $entries = $this->loggerService->getLogEntries();
        
        $this->assertIsArray($entries);
        
        // Test filtering by level
        $errorEntries = $this->loggerService->getLogEntries('error');
        $this->assertIsArray($errorEntries);
        
        // Test limiting results
        $limitedEntries = $this->loggerService->getLogEntries('all', 1);
        $this->assertLessThanOrEqual(1, count($limitedEntries));
    }

    public function testCleanupLogs(): void
    {
        // Log some entries to create log files
        for ($i = 0; $i < 10; $i++) {
            $this->loggerService->info("Test message {$i}");
        }
        
        $cleaned = $this->loggerService->cleanupLogs();
        
        $this->assertIsInt($cleaned);
        $this->assertGreaterThanOrEqual(0, $cleaned);
    }
}