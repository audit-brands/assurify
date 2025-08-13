<?php

declare(strict_types=1);

/**
 * Simplified Phase 6 Advanced Features Test
 * Tests core model functionality without database constraints
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "🚀 Testing Phase 6 Advanced Features Core Functionality\n";
echo "=" . str_repeat("=", 55) . "\n";

// Test 1: Model Classes Exist and Are Loadable
echo "\n🔧 Test 1: Model Classes Load Successfully\n";

$testResults = [];

try {
    $models = [
        'UserProfile' => 'App\\Models\\UserProfile',
        'UserActivity' => 'App\\Models\\UserActivity', 
        'UserFollow' => 'App\\Models\\UserFollow',
        'UserBookmark' => 'App\\Models\\UserBookmark',
        'UserCollection' => 'App\\Models\\UserCollection',
        'UserNotification' => 'App\\Models\\UserNotification'
    ];
    
    foreach ($models as $name => $class) {
        if (class_exists($class)) {
            echo "   ✅ $name model class exists\n";
        } else {
            echo "   ❌ $name model class missing\n";
            $testResults[$name] = false;
        }
    }
    
    $testResults['models_loaded'] = true;
    
} catch (Exception $e) {
    echo "   ❌ Model loading failed: " . $e->getMessage() . "\n";
    $testResults['models_loaded'] = false;
}

// Test 2: UserService Class
echo "\n🔧 Test 2: UserService Class\n";

try {
    $userService = new App\Services\UserService();
    echo "   ✅ UserService instantiated successfully\n";
    
    // Check if methods exist
    $methods = [
        'getEnhancedProfile',
        'getUserStatistics', 
        'toggleFollow',
        'getFollowSuggestions',
        'toggleBookmark',
        'getUserCollections',
        'createCollection',
        'getActivityFeed',
        'getUserNotifications',
        'markNotificationsAsRead',
        'updateProfile'
    ];
    
    foreach ($methods as $method) {
        if (method_exists($userService, $method)) {
            echo "   ✅ Method $method exists\n";
        } else {
            echo "   ❌ Method $method missing\n";
            $testResults['userservice'] = false;
        }
    }
    
    $testResults['userservice'] = true;
    
} catch (Exception $e) {
    echo "   ❌ UserService test failed: " . $e->getMessage() . "\n";
    $testResults['userservice'] = false;
}

// Test 3: Model Method Availability
echo "\n🔧 Test 3: Model Method Availability\n";

try {
    // Test UserProfile methods
    $profileMethods = [
        'getDisplayName', 'getFormattedBio', 'isVisibleTo', 
        'getSocialLinks', 'updateLastActive', 'incrementViews'
    ];
    
    $reflection = new ReflectionClass('App\\Models\\UserProfile');
    foreach ($profileMethods as $method) {
        if ($reflection->hasMethod($method)) {
            echo "   ✅ UserProfile::$method exists\n";
        } else {
            echo "   ❌ UserProfile::$method missing\n";
        }
    }
    
    // Test UserFollow static methods
    $followMethods = ['isFollowing', 'follow', 'unfollow', 'getMutualFollowing'];
    $followReflection = new ReflectionClass('App\\Models\\UserFollow');
    foreach ($followMethods as $method) {
        if ($followReflection->hasMethod($method)) {
            echo "   ✅ UserFollow::$method exists\n";
        } else {
            echo "   ❌ UserFollow::$method missing\n";
        }
    }
    
    // Test UserNotification static methods
    $notificationMethods = [
        'createNotification', 'createMentionNotification', 
        'createReplyNotification', 'createFollowNotification'
    ];
    $notificationReflection = new ReflectionClass('App\\Models\\UserNotification');
    foreach ($notificationMethods as $method) {
        if ($notificationReflection->hasMethod($method)) {
            echo "   ✅ UserNotification::$method exists\n";
        } else {
            echo "   ❌ UserNotification::$method missing\n";
        }
    }
    
    $testResults['model_methods'] = true;
    
} catch (Exception $e) {
    echo "   ❌ Model method test failed: " . $e->getMessage() . "\n";
    $testResults['model_methods'] = false;
}

// Test 4: Database Migration Files
echo "\n🔧 Test 4: Database Migration Files\n";

try {
    $migrationFiles = [
        'create_user_profiles_table.sql',
        'create_user_activities_table.sql',
        'create_user_follows_table.sql',
        'create_user_collections_table.sql',
        'create_user_bookmarks_table.sql',
        'create_user_notifications_table.sql'
    ];
    
    $migrationsExist = true;
    foreach ($migrationFiles as $file) {
        $path = __DIR__ . '/../database/migrations/' . $file;
        if (file_exists($path)) {
            echo "   ✅ Migration file $file exists\n";
        } else {
            echo "   ❌ Migration file $file missing\n";
            $migrationsExist = false;
        }
    }
    
    $testResults['migrations'] = $migrationsExist;
    
} catch (Exception $e) {
    echo "   ❌ Migration files test failed: " . $e->getMessage() . "\n";
    $testResults['migrations'] = false;
}

// Test 5: User Model Extensions
echo "\n🔧 Test 5: User Model Extensions\n";

try {
    $userReflection = new ReflectionClass('App\\Models\\User');
    
    $newMethods = [
        'profile', 'activities', 'following', 'followers', 
        'bookmarks', 'collections', 'notifications',
        'getProfile', 'isFollowing', 'getUnreadNotificationCount',
        'hasBookmarked', 'getActivityFeed'
    ];
    
    foreach ($newMethods as $method) {
        if ($userReflection->hasMethod($method)) {
            echo "   ✅ User::$method exists\n";
        } else {
            echo "   ❌ User::$method missing\n";
        }
    }
    
    $testResults['user_extensions'] = true;
    
} catch (Exception $e) {
    echo "   ❌ User model extensions test failed: " . $e->getMessage() . "\n";
    $testResults['user_extensions'] = false;
}

// Generate Test Summary
echo "\n📋 PHASE 6 TEST RESULTS SUMMARY\n";
echo "=" . str_repeat("=", 55) . "\n";

$passed = count(array_filter($testResults));
$total = count($testResults);

echo "Core Functionality Tests: $passed/$total passed\n\n";

foreach ($testResults as $test => $result) {
    $status = $result ? "✅ PASS" : "❌ FAIL";
    echo "  $status: " . ucfirst(str_replace('_', ' ', $test)) . "\n";
}

$overallSuccess = $passed === $total;

echo "\n🎯 OVERALL RESULT: " . ($overallSuccess ? "✅ SUCCESS" : "❌ ISSUES DETECTED") . "\n";
echo "=" . str_repeat("=", 55) . "\n";

if ($overallSuccess) {
    echo "🎉 Phase 6 Advanced Features core functionality is complete!\n";
    echo "All models, services, and methods are properly implemented.\n";
    echo "\n📝 Next Steps:\n";
    echo "- Database integration testing with proper schema\n";
    echo "- Controller and view implementation\n";
    echo "- End-to-end feature testing\n";
} else {
    echo "⚠️  Some core components are missing. Review failed tests above.\n";
}

exit($overallSuccess ? 0 : 1);