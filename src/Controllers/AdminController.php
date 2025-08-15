<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AdminService;
use App\Services\PerformanceService;
use App\Services\CacheService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use League\Plates\Engine;

class AdminController extends BaseController
{
    public function __construct(
        Engine $templates,
        private AdminService $adminService,
        private PerformanceService $performanceService,
        private CacheService $cacheService
    ) {
        parent::__construct($templates);
    }

    public function dashboard(Request $request, Response $response): Response
    {
        if (!$this->requireAdmin()) {
            return $this->render($response, 'errors/forbidden', [
                'title' => 'Access Denied | Lobsters'
            ])->withStatus(403);
        }

        $overview = $this->adminService->getSystemOverview();

        return $this->render($response, 'admin/dashboard', [
            'title' => 'Admin Dashboard | Lobsters',
            'overview' => $overview
        ]);
    }

    public function performance(Request $request, Response $response): Response
    {
        if (!$this->requireAdmin()) {
            return $this->render($response, 'errors/forbidden', [
                'title' => 'Access Denied | Lobsters'
            ])->withStatus(403);
        }

        $metrics = $this->performanceService->getMetrics();
        $report = $this->performanceService->generateReport();

        return $this->render($response, 'admin/performance', [
            'title' => 'Performance Monitoring | Lobsters',
            'metrics' => $metrics,
            'report' => $report
        ]);
    }

    public function cache(Request $request, Response $response): Response
    {
        if (!$this->requireAdmin()) {
            return $this->render($response, 'errors/forbidden', [
                'title' => 'Access Denied | Lobsters'
            ])->withStatus(403);
        }

        $stats = $this->cacheService->getStats();

        return $this->render($response, 'admin/cache', [
            'title' => 'Cache Management | Lobsters',
            'stats' => $stats
        ]);
    }

    public function users(Request $request, Response $response): Response
    {
        if (!$this->requireAdmin()) {
            return $this->render($response, 'errors/forbidden', [
                'title' => 'Access Denied | Lobsters'
            ])->withStatus(403);
        }

        $queryParams = $request->getQueryParams();
        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $search = $queryParams['search'] ?? '';

        // This would implement user management functionality
        $users = [];
        $totalUsers = 0;

        return $this->render($response, 'admin/users', [
            'title' => 'User Management | Lobsters',
            'users' => $users,
            'total_users' => $totalUsers,
            'page' => $page,
            'search' => $search
        ]);
    }

    public function settings(Request $request, Response $response): Response
    {
        if (!$this->requireAdmin()) {
            return $this->render($response, 'errors/forbidden', [
                'title' => 'Access Denied | Lobsters'
            ])->withStatus(403);
        }

        return $this->render($response, 'admin/settings', [
            'title' => 'System Settings | Lobsters'
        ]);
    }

    public function tags(Request $request, Response $response): Response
    {
        if (!$this->requireAdmin()) {
            return $this->render($response, 'errors/forbidden', [
                'title' => 'Access Denied | Assurify'
            ])->withStatus(403);
        }

        // Get all tags ordered by tag name
        $tags = \App\Models\Tag::orderBy('tag')->get();

        return $this->render($response, 'admin/tags', [
            'title' => 'Tag Management | Assurify',
            'tags' => $tags
        ]);
    }

    public function categories(Request $request, Response $response): Response
    {
        if (!$this->requireAdmin()) {
            return $this->render($response, 'errors/forbidden', [
                'title' => 'Access Denied | Assurify'
            ])->withStatus(403);
        }

        // Get all categories with their tags
        $categories = \App\Models\TagCategory::with(['activeTags'])->active()->ordered()->get();
        
        // Get uncategorized tags
        $uncategorizedTags = \App\Models\Tag::whereNull('category_id')
                                           ->where('inactive', false)
                                           ->orderBy('tag')
                                           ->get();

        $categoriesData = $categories->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'sort_order' => $category->sort_order,
                'tags' => $category->activeTags->sortBy('tag')->map(function ($tag) {
                    return [
                        'id' => $tag->id,
                        'tag' => $tag->tag,
                        'description' => $tag->description
                    ];
                })->values()->toArray()
            ];
        })->toArray();

        $uncategorizedData = $uncategorizedTags->map(function ($tag) {
            return [
                'id' => $tag->id,
                'tag' => $tag->tag,
                'description' => $tag->description
            ];
        })->toArray();

        return $this->render($response, 'admin/categories', [
            'title' => 'Tag Categories | Assurify',
            'categories' => $categoriesData,
            'uncategorized_tags' => $uncategorizedData
        ]);
    }

    public function addCategory(Request $request, Response $response): Response
    {
        if (!$this->requireAdmin()) {
            return $this->json($response, ['error' => 'Access denied'], 403);
        }

        $data = $request->getParsedBody();
        $name = trim($data['name'] ?? '');
        $description = trim($data['description'] ?? '');

        if (empty($name)) {
            return $this->json($response, ['error' => 'Category name is required'], 400);
        }

        try {
            // Check if category already exists
            $existing = \App\Models\TagCategory::where('name', $name)->first();
            if ($existing) {
                return $this->json($response, ['error' => 'Category already exists'], 400);
            }

            // Get next sort order
            $maxOrder = \App\Models\TagCategory::max('sort_order') ?? 0;

            $category = \App\Models\TagCategory::create([
                'name' => $name,
                'description' => $description ?: null,
                'sort_order' => $maxOrder + 1,
                'is_active' => true
            ]);

            return $this->json($response, [
                'success' => true,
                'message' => 'Category added successfully',
                'category_id' => $category->id
            ]);
        } catch (\Exception $e) {
            return $this->json($response, [
                'error' => 'Failed to add category: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateCategory(Request $request, Response $response, array $args): Response
    {
        if (!$this->requireAdmin()) {
            return $this->json($response, ['error' => 'Access denied'], 403);
        }

        $categoryId = $args['id'] ?? null;
        $data = $request->getParsedBody();
        $name = trim($data['name'] ?? '');
        $description = trim($data['description'] ?? '');

        if (!$categoryId || empty($name)) {
            return $this->json($response, ['error' => 'Category ID and name are required'], 400);
        }

        try {
            $category = \App\Models\TagCategory::findOrFail($categoryId);
            
            // Check if another category has this name
            $existing = \App\Models\TagCategory::where('name', $name)
                                              ->where('id', '!=', $categoryId)
                                              ->first();
            if ($existing) {
                return $this->json($response, ['error' => 'Category name already exists'], 400);
            }

            $category->update([
                'name' => $name,
                'description' => $description ?: null
            ]);

            return $this->json($response, [
                'success' => true,
                'message' => 'Category updated successfully'
            ]);
        } catch (\Exception $e) {
            return $this->json($response, [
                'error' => 'Failed to update category: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteCategory(Request $request, Response $response, array $args): Response
    {
        if (!$this->requireAdmin()) {
            return $this->json($response, ['error' => 'Access denied'], 403);
        }

        $categoryId = $args['id'] ?? null;

        if (!$categoryId) {
            return $this->json($response, ['error' => 'Category ID is required'], 400);
        }

        try {
            $category = \App\Models\TagCategory::findOrFail($categoryId);
            
            // Move all tags in this category to uncategorized
            \App\Models\Tag::where('category_id', $categoryId)->update(['category_id' => null]);
            
            // Delete the category
            $category->delete();

            return $this->json($response, [
                'success' => true,
                'message' => 'Category deleted successfully'
            ]);
        } catch (\Exception $e) {
            return $this->json($response, [
                'error' => 'Failed to delete category: ' . $e->getMessage()
            ], 500);
        }
    }

    public function assignTagToCategory(Request $request, Response $response): Response
    {
        if (!$this->requireAdmin()) {
            return $this->json($response, ['error' => 'Access denied'], 403);
        }

        $data = $request->getParsedBody();
        $tagId = $data['tag_id'] ?? null;
        $categoryId = $data['category_id'] ?? null;

        if (!$tagId || !$categoryId) {
            return $this->json($response, ['error' => 'Tag ID and Category ID are required'], 400);
        }

        try {
            $tag = \App\Models\Tag::findOrFail($tagId);
            $category = \App\Models\TagCategory::findOrFail($categoryId);
            
            $tag->update(['category_id' => $categoryId]);

            return $this->json($response, [
                'success' => true,
                'message' => 'Tag assigned to category successfully'
            ]);
        } catch (\Exception $e) {
            return $this->json($response, [
                'error' => 'Failed to assign tag: ' . $e->getMessage()
            ], 500);
        }
    }

    public function removeTagFromCategory(Request $request, Response $response): Response
    {
        if (!$this->requireAdmin()) {
            return $this->json($response, ['error' => 'Access denied'], 403);
        }

        $data = $request->getParsedBody();
        $tagId = $data['tag_id'] ?? null;

        if (!$tagId) {
            return $this->json($response, ['error' => 'Tag ID is required'], 400);
        }

        try {
            $tag = \App\Models\Tag::findOrFail($tagId);
            $currentCategory = $tag->category;
            
            // If tag is already in "Other" category, move it to a different default category
            if ($currentCategory && $currentCategory->name === 'Other') {
                // Find the first non-Other category to move it to
                $defaultCategory = \App\Models\TagCategory::where('name', '!=', 'Other')
                                                         ->active()
                                                         ->ordered()
                                                         ->first();
                
                if ($defaultCategory) {
                    $tag->update(['category_id' => $defaultCategory->id]);
                    return $this->json($response, [
                        'success' => true,
                        'message' => "Tag moved to {$defaultCategory->name} category"
                    ]);
                } else {
                    return $this->json($response, [
                        'error' => 'No other categories available to move tag to'
                    ], 400);
                }
            } else {
                // For non-Other categories, move to "Other" category
                $otherCategory = \App\Models\TagCategory::firstOrCreate(
                    ['name' => 'Other'],
                    [
                        'description' => 'Uncategorized tags',
                        'sort_order' => 999,
                        'is_active' => true
                    ]
                );
                
                $tag->update(['category_id' => $otherCategory->id]);
                return $this->json($response, [
                    'success' => true,
                    'message' => 'Tag moved to Other category'
                ]);
            }
        } catch (\Exception $e) {
            return $this->json($response, [
                'error' => 'Failed to remove tag: ' . $e->getMessage()
            ], 500);
        }
    }

    public function logs(Request $request, Response $response): Response
    {
        if (!$this->requireAdmin()) {
            return $this->render($response, 'errors/forbidden', [
                'title' => 'Access Denied | Lobsters'
            ])->withStatus(403);
        }

        $queryParams = $request->getQueryParams();
        $type = $queryParams['type'] ?? 'error';
        $lines = min(1000, max(10, (int) ($queryParams['lines'] ?? 100)));

        $logs = $this->getLogEntries($type, $lines);

        return $this->render($response, 'admin/logs', [
            'title' => 'System Logs | Lobsters',
            'logs' => $logs,
            'type' => $type,
            'lines' => $lines
        ]);
    }

    // API endpoints for admin actions
    public function flushCache(Request $request, Response $response): Response
    {
        if (!$this->requireAdmin()) {
            return $this->json($response, ['error' => 'Access denied'], 403);
        }

        try {
            $this->cacheService->flush();
            return $this->json($response, [
                'success' => true,
                'message' => 'Cache flushed successfully'
            ]);
        } catch (\Exception $e) {
            return $this->json($response, [
                'error' => 'Failed to flush cache: ' . $e->getMessage()
            ], 500);
        }
    }

    public function cleanupSystem(Request $request, Response $response): Response
    {
        if (!$this->requireAdmin()) {
            return $this->json($response, ['error' => 'Access denied'], 403);
        }

        try {
            $results = $this->adminService->cleanupSystem();
            return $this->json($response, [
                'success' => true,
                'message' => 'System cleanup completed',
                'results' => $results
            ]);
        } catch (\Exception $e) {
            return $this->json($response, [
                'error' => 'Cleanup failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function exportData(Request $request, Response $response): Response
    {
        if (!$this->requireAdmin()) {
            return $this->json($response, ['error' => 'Access denied'], 403);
        }

        try {
            $data = $this->adminService->exportSystemData();
            
            // Set headers for file download
            $response = $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Content-Disposition', 'attachment; filename="lobsters_export_' . date('Y-m-d_H-i-s') . '.json"');
            
            $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
            return $response;
        } catch (\Exception $e) {
            return $this->json($response, [
                'error' => 'Export failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function metricsApi(Request $request, Response $response): Response
    {
        if (!$this->requireAdmin()) {
            return $this->json($response, ['error' => 'Access denied'], 403);
        }

        $metrics = $this->performanceService->getMetrics();
        return $this->json($response, $metrics);
    }

    public function healthCheck(Request $request, Response $response): Response
    {
        // Health check can be accessed without admin privileges for monitoring
        $health = $this->adminService->runHealthChecks();
        
        $statusCode = 200;
        if ($health['overall'] === 'error') {
            $statusCode = 503; // Service Unavailable
        } elseif ($health['overall'] === 'warning') {
            $statusCode = 200; // OK but with warnings
        }
        
        return $this->json($response, $health, $statusCode);
    }

    public function systemInfo(Request $request, Response $response): Response
    {
        if (!$this->requireAdmin()) {
            return $this->json($response, ['error' => 'Access denied'], 403);
        }

        $info = $this->adminService->getSystemInfo();
        return $this->json($response, $info);
    }

    private function requireAdmin(): bool
    {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        try {
            $user = \App\Models\User::find($_SESSION['user_id']);
            return $user && ($user->is_admin ?? false);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getLogEntries(string $type, int $lines): array
    {
        // This would read actual log files in production
        // For now, return sample log entries
        $sampleLogs = [
            [
                'timestamp' => date('Y-m-d H:i:s'),
                'level' => 'INFO',
                'message' => 'Application started successfully',
                'context' => []
            ],
            [
                'timestamp' => date('Y-m-d H:i:s', time() - 3600),
                'level' => 'WARNING',
                'message' => 'Cache miss for key: stories:newest',
                'context' => ['key' => 'stories:newest']
            ],
            [
                'timestamp' => date('Y-m-d H:i:s', time() - 7200),
                'level' => 'ERROR',
                'message' => 'Database query timeout',
                'context' => ['query' => 'SELECT * FROM stories WHERE...', 'timeout' => 5.0]
            ]
        ];

        return array_slice($sampleLogs, 0, $lines);
    }
}