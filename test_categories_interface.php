<?php

require __DIR__ . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Create Container using PHP-DI
$containerBuilder = new DI\ContainerBuilder();
$containerBuilder->addDefinitions(__DIR__ . '/config/dependencies.php');
$container = $containerBuilder->build();

// Initialize database connection
$container->get(\Illuminate\Database\Capsule\Manager::class);

echo "=== Testing Tag Categories System ===\n\n";

try {
    // 1. Check existing categories
    echo "1. Existing Categories:\n";
    $categories = \App\Models\TagCategory::with(['activeTags'])->active()->ordered()->get();
    
    foreach ($categories as $category) {
        echo "- {$category->name}: {$category->description} (Sort: {$category->sort_order})\n";
        echo "  Tags: " . $category->activeTags->pluck('tag')->implode(', ') . "\n";
    }
    echo "\n";
    
    // 2. Check uncategorized tags
    echo "2. Uncategorized Tags:\n";
    $uncategorizedTags = \App\Models\Tag::whereNull('category_id')
                                        ->where('inactive', false)
                                        ->orderBy('tag')
                                        ->get();
    
    foreach ($uncategorizedTags as $tag) {
        echo "- {$tag->tag}: {$tag->description}\n";
    }
    echo "\n";
    
    // 3. Assign some tags to categories for testing
    echo "3. Assigning some tags to categories...\n";
    
    $securityCategory = \App\Models\TagCategory::where('name', 'Security')->first();
    $businessCategory = \App\Models\TagCategory::where('name', 'Business')->first();
    $technologyCategory = \App\Models\TagCategory::where('name', 'Technology')->first();
    
    if ($securityCategory) {
        $securityTags = ['security', 'cpe'];
        foreach ($securityTags as $tagName) {
            $tag = \App\Models\Tag::where('tag', $tagName)->first();
            if ($tag && !$tag->category_id) {
                $tag->update(['category_id' => $securityCategory->id]);
                echo "  Assigned '{$tag->tag}' to Security category\n";
            }
        }
    }
    
    if ($technologyCategory) {
        $techTags = ['test', 'example'];
        foreach ($techTags as $tagName) {
            $tag = \App\Models\Tag::where('tag', $tagName)->first();
            if ($tag && !$tag->category_id) {
                $tag->update(['category_id' => $technologyCategory->id]);
                echo "  Assigned '{$tag->tag}' to Technology category\n";
            }
        }
    }
    
    echo "\n";
    
    // 4. Test the PageController grouping function
    echo "4. Testing PageController groupTagsByCategory...\n";
    
    // Simulate tag data as returned by TagService
    $allTags = \App\Models\Tag::where('inactive', false)
                              ->with('category')
                              ->get()
                              ->map(function($tag) {
                                  return [
                                      'id' => $tag->id,
                                      'tag' => $tag->tag,
                                      'description' => $tag->description,
                                      'category_id' => $tag->category_id,
                                      'story_count' => 0 // Mock story count
                                  ];
                              })
                              ->toArray();
    
    // Create a mock PageController to test the grouping
    $reflection = new ReflectionClass(\App\Controllers\PageController::class);
    $method = $reflection->getMethod('groupTagsByCategory');
    $method->setAccessible(true);
    
    // Create a minimal PageController instance
    $templates = $container->get(\League\Plates\Engine::class);
    $tagService = $container->get(\App\Services\TagService::class);
    $moderationService = $container->get(\App\Services\ModerationService::class);
    $pageController = new \App\Controllers\PageController($templates, $tagService, $moderationService);
    
    $categorizedTags = $method->invoke($pageController, $allTags);
    
    foreach ($categorizedTags as $categoryName => $tags) {
        echo "  {$categoryName}: " . implode(', ', array_column($tags, 'tag')) . "\n";
    }
    
    echo "\n=== Test Complete ===\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}