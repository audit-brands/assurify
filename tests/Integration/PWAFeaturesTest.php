<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class PWAFeaturesTest extends TestCase
{
    public function testManifestFileExists(): void
    {
        $manifestPath = __DIR__ . '/../../public/manifest.json';
        
        $this->assertFileExists($manifestPath);
        
        $manifestContent = file_get_contents($manifestPath);
        $manifest = json_decode($manifestContent, true);
        
        $this->assertIsArray($manifest);
        $this->assertArrayHasKey('name', $manifest);
        $this->assertArrayHasKey('short_name', $manifest);
        $this->assertArrayHasKey('start_url', $manifest);
        $this->assertArrayHasKey('display', $manifest);
        $this->assertArrayHasKey('theme_color', $manifest);
        $this->assertArrayHasKey('background_color', $manifest);
        $this->assertArrayHasKey('icons', $manifest);
        
        $this->assertEquals('Lobsters Community', $manifest['name']);
        $this->assertEquals('Lobsters', $manifest['short_name']);
        $this->assertEquals('standalone', $manifest['display']);
        $this->assertEquals('#ff6600', $manifest['theme_color']);
        $this->assertEquals('#ffffff', $manifest['background_color']);
        
        // Validate icons structure
        $this->assertIsArray($manifest['icons']);
        $this->assertGreaterThan(0, count($manifest['icons']));
        
        foreach ($manifest['icons'] as $icon) {
            $this->assertArrayHasKey('src', $icon);
            $this->assertArrayHasKey('sizes', $icon);
            $this->assertArrayHasKey('type', $icon);
            $this->assertEquals('image/png', $icon['type']);
        }
    }
    
    public function testServiceWorkerFileExists(): void
    {
        $swPath = __DIR__ . '/../../public/sw.js';
        
        $this->assertFileExists($swPath);
        
        $swContent = file_get_contents($swPath);
        
        // Check for essential service worker functionality
        $this->assertStringContainsString('addEventListener', $swContent);
        $this->assertStringContainsString('install', $swContent);
        $this->assertStringContainsString('activate', $swContent);
        $this->assertStringContainsString('fetch', $swContent);
        $this->assertStringContainsString('caches', $swContent);
        $this->assertStringContainsString('CACHE_NAME', $swContent);
    }
    
    public function testOfflinePageExists(): void
    {
        $offlinePath = __DIR__ . '/../../public/offline.html';
        
        $this->assertFileExists($offlinePath);
        
        $offlineContent = file_get_contents($offlinePath);
        
        $this->assertStringContainsString('<html', $offlineContent);
        $this->assertStringContainsString('Offline', $offlineContent);
        $this->assertStringContainsString('Lobsters', $offlineContent);
        $this->assertStringContainsString('script', $offlineContent);
    }
    
    public function testPWAJavaScriptExists(): void
    {
        $pwaJsPath = __DIR__ . '/../../public/assets/pwa.js';
        
        $this->assertFileExists($pwaJsPath);
        
        $pwaJsContent = file_get_contents($pwaJsPath);
        
        // Check for essential PWA functionality
        $this->assertStringContainsString('PWAManager', $pwaJsContent);
        $this->assertStringContainsString('serviceWorker', $pwaJsContent);
        $this->assertStringContainsString('beforeinstallprompt', $pwaJsContent);
        $this->assertStringContainsString('PushManager', $pwaJsContent);
        $this->assertStringContainsString('Notification', $pwaJsContent);
    }
    
    public function testWebSocketJavaScriptExists(): void
    {
        $wsJsPath = __DIR__ . '/../../public/assets/websocket.js';
        
        $this->assertFileExists($wsJsPath);
        
        $wsJsContent = file_get_contents($wsJsPath);
        
        // Check for essential WebSocket functionality
        $this->assertStringContainsString('WebSocketClient', $wsJsContent);
        $this->assertStringContainsString('WebSocket', $wsJsContent);
        $this->assertStringContainsString('onopen', $wsJsContent);
        $this->assertStringContainsString('onmessage', $wsJsContent);
        $this->assertStringContainsString('onclose', $wsJsContent);
        $this->assertStringContainsString('onerror', $wsJsContent);
    }
    
    public function testPWAMetaTagsInLayout(): void
    {
        $layoutPath = __DIR__ . '/../../src/Views/layout.php';
        
        $this->assertFileExists($layoutPath);
        
        $layoutContent = file_get_contents($layoutPath);
        
        // Check for PWA meta tags
        $this->assertStringContainsString('manifest.json', $layoutContent);
        $this->assertStringContainsString('theme-color', $layoutContent);
        $this->assertStringContainsString('mobile-web-app-capable', $layoutContent);
        $this->assertStringContainsString('apple-mobile-web-app-capable', $layoutContent);
        $this->assertStringContainsString('apple-touch-icon', $layoutContent);
        $this->assertStringContainsString('pwa.js', $layoutContent);
        $this->assertStringContainsString('websocket.js', $layoutContent);
    }
    
    public function testPWAStylesInCSS(): void
    {
        $cssPath = __DIR__ . '/../../public/assets/application.css';
        
        $this->assertFileExists($cssPath);
        
        $cssContent = file_get_contents($cssPath);
        
        // Check for PWA-specific styles
        $this->assertStringContainsString('PWA and Mobile Enhancements', $cssContent);
        $this->assertStringContainsString('body.offline', $cssContent);
        $this->assertStringContainsString('display-mode: standalone', $cssContent);
        $this->assertStringContainsString('safe-area-inset', $cssContent);
        $this->assertStringContainsString('prefers-color-scheme', $cssContent);
        $this->assertStringContainsString('prefers-reduced-motion', $cssContent);
        $this->assertStringContainsString('notification-prompt', $cssContent);
    }
    
    public function testManifestValidStructure(): void
    {
        $manifestPath = __DIR__ . '/../../public/manifest.json';
        $manifestContent = file_get_contents($manifestPath);
        $manifest = json_decode($manifestContent, true);
        
        // Test required PWA manifest fields
        $requiredFields = [
            'name', 'short_name', 'start_url', 'display', 
            'theme_color', 'background_color', 'icons'
        ];
        
        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $manifest, "Manifest missing required field: {$field}");
        }
        
        // Test display mode
        $validDisplayModes = ['fullscreen', 'standalone', 'minimal-ui', 'browser'];
        $this->assertContains($manifest['display'], $validDisplayModes);
        
        // Test start URL
        $this->assertStringStartsWith('/', $manifest['start_url']);
        
        // Test color format
        $this->assertMatchesRegularExpression('/^#[0-9a-fA-F]{6}$/', $manifest['theme_color']);
        $this->assertMatchesRegularExpression('/^#[0-9a-fA-F]{6}$/', $manifest['background_color']);
        
        // Test icons
        $this->assertIsArray($manifest['icons']);
        $this->assertGreaterThan(0, count($manifest['icons']));
        
        // Test for required icon sizes
        $requiredSizes = ['192x192', '512x512'];
        $iconSizes = array_column($manifest['icons'], 'sizes');
        
        foreach ($requiredSizes as $requiredSize) {
            $this->assertContains($requiredSize, $iconSizes, "Missing required icon size: {$requiredSize}");
        }
    }
    
    public function testManifestShortcuts(): void
    {
        $manifestPath = __DIR__ . '/../../public/manifest.json';
        $manifestContent = file_get_contents($manifestPath);
        $manifest = json_decode($manifestContent, true);
        
        if (isset($manifest['shortcuts'])) {
            $this->assertIsArray($manifest['shortcuts']);
            
            foreach ($manifest['shortcuts'] as $shortcut) {
                $this->assertArrayHasKey('name', $shortcut);
                $this->assertArrayHasKey('short_name', $shortcut);
                $this->assertArrayHasKey('description', $shortcut);
                $this->assertArrayHasKey('url', $shortcut);
                
                // URL should start with /
                $this->assertStringStartsWith('/', $shortcut['url']);
            }
        }
    }
    
    public function testManifestShareTarget(): void
    {
        $manifestPath = __DIR__ . '/../../public/manifest.json';
        $manifestContent = file_get_contents($manifestPath);
        $manifest = json_decode($manifestContent, true);
        
        if (isset($manifest['share_target'])) {
            $this->assertArrayHasKey('action', $manifest['share_target']);
            $this->assertArrayHasKey('method', $manifest['share_target']);
            $this->assertArrayHasKey('params', $manifest['share_target']);
            
            $this->assertStringStartsWith('/', $manifest['share_target']['action']);
            $this->assertContains($manifest['share_target']['method'], ['GET', 'POST']);
        }
    }
    
    public function testServiceWorkerCacheNames(): void
    {
        $swPath = __DIR__ . '/../../public/sw.js';
        $swContent = file_get_contents($swPath);
        
        // Check for proper cache naming
        $this->assertStringContainsString('CACHE_NAME', $swContent);
        $this->assertStringContainsString('API_CACHE_NAME', $swContent);
        $this->assertStringContainsString('STATIC_CACHE_NAME', $swContent);
        
        // Check for cache versioning
        $this->assertMatchesRegularExpression('/lobsters-v\d+\.\d+\.\d+/', $swContent);
    }
    
    public function testServiceWorkerOfflineStrategy(): void
    {
        $swPath = __DIR__ . '/../../public/sw.js';
        $swContent = file_get_contents($swPath);
        
        // Check for offline strategies
        $this->assertStringContainsString('handleApiRequest', $swContent);
        $this->assertStringContainsString('handleStaticAsset', $swContent);
        $this->assertStringContainsString('handlePageRequest', $swContent);
        
        // Check for offline fallbacks
        $this->assertStringContainsString('offline.html', $swContent);
        $this->assertStringContainsString('You are currently offline', $swContent);
    }
    
    public function testServiceWorkerPushNotifications(): void
    {
        $swPath = __DIR__ . '/../../public/sw.js';
        $swContent = file_get_contents($swPath);
        
        // Check for push notification handling
        $this->assertStringContainsString('push', $swContent);
        $this->assertStringContainsString('notificationclick', $swContent);
        $this->assertStringContainsString('showNotification', $swContent);
    }
    
    public function testServiceWorkerBackgroundSync(): void
    {
        $swPath = __DIR__ . '/../../public/sw.js';
        $swContent = file_get_contents($swPath);
        
        // Check for background sync
        $this->assertStringContainsString('sync', $swContent);
        $this->assertStringContainsString('background-sync', $swContent);
        $this->assertStringContainsString('syncOfflineActions', $swContent);
    }
}