<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\AnalyticsService;
use App\Services\PerformanceMonitorService;
use App\Services\CacheService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Performance Dashboard Controller
 * 
 * Provides admin interface for monitoring system performance:
 * - Real-time performance metrics
 * - System health monitoring
 * - Analytics dashboard
 * - Alert management
 * - Performance optimization tools
 */
class PerformanceDashboardController extends BaseController
{
    private AnalyticsService $analyticsService;
    private PerformanceMonitorService $performanceService;
    private CacheService $cacheService;
    
    public function __construct(
        AnalyticsService $analyticsService,
        PerformanceMonitorService $performanceService,
        CacheService $cacheService
    ) {
        $this->analyticsService = $analyticsService;
        $this->performanceService = $performanceService;
        $this->cacheService = $cacheService;
    }
    
    /**
     * Main performance dashboard
     */
    public function dashboard(Request $request, Response $response): Response
    {
        try {
            // Get real-time performance data
            $performanceData = $this->performanceService->getDashboardData();
            
            // Get analytics overview
            $analyticsData = $this->analyticsService->getRealTimeAnalytics();
            
            // Get system health status
            $healthStatus = $this->performanceService->getSystemHealthStatus();
            
            // Get recent trends
            $trends = $this->performanceService->getPerformanceTrends(24);
            
            $dashboardData = [
                'performance' => $performanceData,
                'analytics' => $analyticsData,
                'health' => $healthStatus,
                'trends' => $trends,
                'generated_at' => date('c')
            ];
            
            return $this->render($response, 'admin/performance/dashboard', [
                'title' => 'Performance Dashboard',
                'data' => $dashboardData
            ]);
            
        } catch (\Exception $e) {
            return $this->handleError($response, $e, 'Failed to load performance dashboard');
        }
    }
    
    /**
     * Real-time metrics API endpoint
     */
    public function realTimeMetrics(Request $request, Response $response): Response
    {
        try {
            $metrics = [
                'system' => $this->performanceService->monitorSystemResources(),
                'cache' => $this->performanceService->monitorCachePerformance(),
                'health' => $this->performanceService->getSystemHealthStatus(),
                'timestamp' => time()
            ];
            
            return $this->jsonResponse($response, $metrics);
            
        } catch (\Exception $e) {
            return $this->jsonErrorResponse($response, 'Failed to fetch real-time metrics', 500);
        }
    }
    
    /**
     * Analytics overview
     */
    public function analytics(Request $request, Response $response): Response
    {
        try {
            $params = $request->getQueryParams();
            $period = $params['period'] ?? '7d';
            $metrics = $params['metrics'] ?? [];
            
            // Parse period
            [$days, $startDate, $endDate] = $this->parsePeriod($period);
            
            // Get analytics summary
            $summary = $this->analyticsService->getAnalyticsSummary($startDate, $endDate, $metrics);
            
            // Get insights
            $insights = $this->analyticsService->generateInsights($period);
            
            // Get trending content
            $trending = $this->analyticsService->getTrendingAnalysis(24);
            
            return $this->render($response, 'admin/performance/analytics', [
                'title' => 'Analytics Dashboard',
                'summary' => $summary,
                'insights' => $insights,
                'trending' => $trending,
                'period' => $period
            ]);
            
        } catch (\Exception $e) {
            return $this->handleError($response, $e, 'Failed to load analytics dashboard');
        }
    }
    
    /**
     * System health monitoring
     */
    public function systemHealth(Request $request, Response $response): Response
    {
        try {
            $healthStatus = $this->performanceService->getSystemHealthStatus();
            $systemMetrics = $this->performanceService->monitorSystemResources();
            $trends = $this->performanceService->getPerformanceTrends(24);
            
            return $this->render($response, 'admin/performance/health', [
                'title' => 'System Health',
                'health' => $healthStatus,
                'metrics' => $systemMetrics,
                'trends' => $trends
            ]);
            
        } catch (\Exception $e) {
            return $this->handleError($response, $e, 'Failed to load system health data');
        }
    }
    
    /**
     * Performance reports
     */
    public function reports(Request $request, Response $response): Response
    {
        try {
            $params = $request->getQueryParams();
            $startDate = $params['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
            $endDate = $params['end_date'] ?? date('Y-m-d');
            
            $report = $this->performanceService->generatePerformanceReport($startDate, $endDate);
            
            return $this->render($response, 'admin/performance/reports', [
                'title' => 'Performance Reports',
                'report' => $report,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
            
        } catch (\Exception $e) {
            return $this->handleError($response, $e, 'Failed to generate performance report');
        }
    }
    
    /**
     * Alert management
     */
    public function alerts(Request $request, Response $response): Response
    {
        try {
            $params = $request->getQueryParams();
            $status = $params['status'] ?? 'active';
            $severity = $params['severity'] ?? null;
            
            $alerts = $this->getAlerts($status, $severity);
            $alertsStats = $this->getAlertsStatistics();
            
            return $this->render($response, 'admin/performance/alerts', [
                'title' => 'Performance Alerts',
                'alerts' => $alerts,
                'stats' => $alertsStats,
                'current_status' => $status,
                'current_severity' => $severity
            ]);
            
        } catch (\Exception $e) {
            return $this->handleError($response, $e, 'Failed to load alerts');
        }
    }
    
    /**
     * Acknowledge alert
     */
    public function acknowledgeAlert(Request $request, Response $response): Response
    {
        try {
            $data = json_decode($request->getBody()->getContents(), true);
            $alertId = (int)($data['alert_id'] ?? 0);
            
            if ($alertId <= 0) {
                return $this->jsonErrorResponse($response, 'Invalid alert ID', 400);
            }
            
            $success = $this->acknowledgeAlertById($alertId);
            
            if ($success) {
                return $this->jsonResponse($response, [
                    'success' => true,
                    'message' => 'Alert acknowledged successfully'
                ]);
            } else {
                return $this->jsonErrorResponse($response, 'Failed to acknowledge alert', 500);
            }
            
        } catch (\Exception $e) {
            return $this->jsonErrorResponse($response, 'Failed to acknowledge alert: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Performance optimization suggestions
     */
    public function optimizations(Request $request, Response $response): Response
    {
        try {
            $optimizations = $this->performanceService->optimizePerformance();
            $currentMetrics = $this->performanceService->getDashboardData();
            
            return $this->render($response, 'admin/performance/optimizations', [
                'title' => 'Performance Optimizations',
                'optimizations' => $optimizations,
                'metrics' => $currentMetrics
            ]);
            
        } catch (\Exception $e) {
            return $this->handleError($response, $e, 'Failed to load optimization suggestions');
        }
    }
    
    /**
     * Cache management
     */
    public function cacheManagement(Request $request, Response $response): Response
    {
        try {
            $cacheStats = $this->performanceService->monitorCachePerformance();
            $cacheInfo = $this->cacheService->getInfo();
            
            return $this->render($response, 'admin/performance/cache', [
                'title' => 'Cache Management',
                'stats' => $cacheStats,
                'info' => $cacheInfo
            ]);
            
        } catch (\Exception $e) {
            return $this->handleError($response, $e, 'Failed to load cache management');
        }
    }
    
    /**
     * Flush cache
     */
    public function flushCache(Request $request, Response $response): Response
    {
        try {
            $data = json_decode($request->getBody()->getContents(), true);
            $cacheType = $data['type'] ?? 'all';
            
            $success = $this->cacheService->flush($cacheType);
            
            if ($success) {
                return $this->jsonResponse($response, [
                    'success' => true,
                    'message' => 'Cache flushed successfully'
                ]);
            } else {
                return $this->jsonErrorResponse($response, 'Failed to flush cache', 500);
            }
            
        } catch (\Exception $e) {
            return $this->jsonErrorResponse($response, 'Failed to flush cache: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Export performance data
     */
    public function exportData(Request $request, Response $response): Response
    {
        try {
            $params = $request->getQueryParams();
            $format = $params['format'] ?? 'csv';
            $dataType = $params['type'] ?? 'performance';
            $startDate = $params['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
            $endDate = $params['end_date'] ?? date('Y-m-d');
            
            $data = $this->getExportData($dataType, $startDate, $endDate);
            
            switch ($format) {
                case 'csv':
                    return $this->exportCsv($response, $data, $dataType);
                case 'json':
                    return $this->exportJson($response, $data);
                case 'excel':
                    return $this->exportExcel($response, $data, $dataType);
                default:
                    return $this->jsonErrorResponse($response, 'Unsupported export format', 400);
            }
            
        } catch (\Exception $e) {
            return $this->jsonErrorResponse($response, 'Failed to export data: ' . $e->getMessage(), 500);
        }
    }
    
    // Private helper methods
    
    private function parsePeriod(string $period): array
    {
        switch ($period) {
            case '1d':
                $days = 1;
                break;
            case '7d':
                $days = 7;
                break;
            case '30d':
                $days = 30;
                break;
            case '90d':
                $days = 90;
                break;
            default:
                $days = 7;
        }
        
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        
        return [$days, $startDate, $endDate];
    }
    
    private function handleError(Response $response, \Exception $e, string $message): Response
    {
        error_log($message . ': ' . $e->getMessage());
        
        return $this->render($response, 'admin/error', [
            'title' => 'Error',
            'message' => $message,
            'error' => $e->getMessage()
        ], 500);
    }
    
    // Mock implementations (would be implemented with real data access)
    private function getAlerts(string $status, ?string $severity): array { return []; }
    private function getAlertsStatistics(): array { return []; }
    private function acknowledgeAlertById(int $alertId): bool { return true; }
    private function getExportData(string $type, string $startDate, string $endDate): array { return []; }
    private function exportCsv(Response $response, array $data, string $type): Response { return $response; }
    private function exportJson(Response $response, array $data): Response { return $response; }
    private function exportExcel(Response $response, array $data, string $type): Response { return $response; }
}