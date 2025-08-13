<?php

declare(strict_types=1);

use DI\Container;
use Illuminate\Database\Capsule\Manager as DB;
use League\Plates\Engine;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Psr\Log\LoggerInterface;
use App\Services\AuthService;
use App\Services\EmailService;
use App\Services\InvitationService;
use App\Services\StoryService;
use App\Services\TagService;
use App\Services\CommentService;
use App\Services\SearchService;
use App\Services\FeedService;
use App\Services\ModerationService;
use App\Services\CacheService;
use App\Services\PerformanceService;
use App\Services\AdminService;
use App\Services\LoggerService;
use App\Services\RateLimitService;
use App\Services\JwtService;
use App\Services\PushNotificationService;
use App\Services\WebSocketService;
use App\Services\OfflineSyncService;
use App\Services\RecommendationService;
use App\Services\ContentCategorizationService;
use App\Services\DuplicateDetectionService;
use App\Services\SearchIndexService;
use App\Services\RateLimitingService;
use App\Services\SecurityMonitorService;
use App\Services\ContentModerationService;
use App\Services\SpamPreventionService;
use App\Services\AdvancedAuthService;
use App\Services\IpSecurityService;
use App\Services\VulnerabilityScanner;
use App\Services\AuditService;
use App\Services\EncryptionService;
use App\Services\BackupRecoveryService;
use App\Middleware\CsrfProtectionMiddleware;
use App\Middleware\SecurityHeadersMiddleware;

return [
    // Database
    DB::class => function () {
        $capsule = new DB;
        
        try {
            $capsule->addConnection([
                'driver' => 'mysql',
                'host' => $_ENV['DB_HOST'] ?? 'localhost',
                'port' => $_ENV['DB_PORT'] ?? '3306',
                'database' => $_ENV['DB_DATABASE'] ?? 'lobsters_slim',
                'username' => $_ENV['DB_USERNAME'] ?? 'root',
                'password' => $_ENV['DB_PASSWORD'] ?? '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => false, // Set to false for compatibility
                'engine' => null,
                'options' => [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                ]
            ]);
            
            $capsule->setAsGlobal();
            $capsule->bootEloquent();
            
            // Test the connection
            $capsule->getConnection()->getPdo();
            
        } catch (\Exception $e) {
            // Log the database connection error
            error_log("Database connection failed: " . $e->getMessage());
            
            // Still set up Eloquent with a null connection resolver to prevent errors
            $capsule->setAsGlobal();
            $capsule->bootEloquent();
            
            // For development, we can continue without database
            // In production, this should throw an exception
            if (($_ENV['APP_ENV'] ?? 'development') === 'production') {
                throw new \Exception("Database connection required in production: " . $e->getMessage());
            }
        }
        
        return $capsule;
    },

    // Template Engine
    Engine::class => function () {
        return new Engine(__DIR__ . '/../src/Views');
    },

    // Logger
    LoggerInterface::class => function () {
        $logger = new Logger('app');
        $handler = new StreamHandler(__DIR__ . '/../logs/app.log', Logger::DEBUG);
        $logger->pushHandler($handler);
        return $logger;
    },

    // Database Available Flag
    'database_available' => function (Container $c) {
        try {
            $db = $c->get(DB::class);
            $db->getConnection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    },

    // Services
    AuthService::class => function (Container $c) {
        return new AuthService();
    },

    EmailService::class => function () {
        return new EmailService();
    },

    InvitationService::class => function (Container $c) {
        return new InvitationService($c->get(EmailService::class));
    },

    TagService::class => function (Container $c) {
        return new TagService();
    },

    StoryService::class => function (Container $c) {
        return new StoryService($c->get(TagService::class));
    },

    CommentService::class => function () {
        return new CommentService();
    },

    SearchService::class => function () {
        return new SearchService();
    },

    FeedService::class => function () {
        return new FeedService();
    },

    ModerationService::class => function () {
        return new ModerationService();
    },

    CacheService::class => function () {
        return new CacheService();
    },

    PerformanceService::class => function () {
        return new PerformanceService();
    },

    LoggerService::class => function () {
        return new LoggerService();
    },

    RateLimitService::class => function (Container $c) {
        return new RateLimitService(
            $c->get(CacheService::class),
            $c->get(LoggerService::class)
        );
    },

    AdminService::class => function (Container $c) {
        return new AdminService(
            $c->get(PerformanceService::class),
            $c->get(CacheService::class)
        );
    },

    JwtService::class => function () {
        return new JwtService();
    },

    PushNotificationService::class => function () {
        return new PushNotificationService();
    },

    WebSocketService::class => function () {
        return new WebSocketService();
    },

    OfflineSyncService::class => function () {
        return new OfflineSyncService();
    },

    RecommendationService::class => function (Container $c) {
        return new RecommendationService($c->get(CacheService::class));
    },

    ContentCategorizationService::class => function (Container $c) {
        return new ContentCategorizationService($c->get(CacheService::class));
    },

    DuplicateDetectionService::class => function (Container $c) {
        return new DuplicateDetectionService($c->get(CacheService::class));
    },

    SearchIndexService::class => function (Container $c) {
        return new SearchIndexService($c->get(CacheService::class));
    },

    // Phase 12 Security Services
    RateLimitingService::class => function (Container $c) {
        return new RateLimitingService($c->get(CacheService::class));
    },

    SecurityMonitorService::class => function (Container $c) {
        return new SecurityMonitorService($c->get(LoggerService::class));
    },

    ContentModerationService::class => function (Container $c) {
        return new ContentModerationService($c->get(CacheService::class));
    },

    SpamPreventionService::class => function (Container $c) {
        return new SpamPreventionService($c->get(CacheService::class));
    },

    AdvancedAuthService::class => function (Container $c) {
        return new AdvancedAuthService(
            $c->get(CacheService::class),
            $c->get(LoggerService::class)
        );
    },

    IpSecurityService::class => function (Container $c) {
        return new IpSecurityService(
            $c->get(CacheService::class),
            $c->get(LoggerService::class)
        );
    },

    VulnerabilityScanner::class => function (Container $c) {
        return new VulnerabilityScanner(
            $c->get(LoggerService::class),
            $c->get(CacheService::class)
        );
    },

    AuditService::class => function (Container $c) {
        return new AuditService(
            $c->get(LoggerService::class),
            $c->get(CacheService::class)
        );
    },

    EncryptionService::class => function (Container $c) {
        return new EncryptionService(
            $c->get(LoggerService::class),
            $c->get(CacheService::class)
        );
    },

    BackupRecoveryService::class => function (Container $c) {
        return new BackupRecoveryService(
            $c->get(LoggerService::class),
            $c->get(CacheService::class),
            $c->get(EncryptionService::class)
        );
    },

    // Security Middleware
    CsrfProtectionMiddleware::class => function () {
        return new CsrfProtectionMiddleware();
    },

    SecurityHeadersMiddleware::class => function () {
        return new SecurityHeadersMiddleware();
    },
];