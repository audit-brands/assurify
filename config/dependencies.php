<?php

declare(strict_types=1);

use DI\Container;
use Illuminate\Database\Capsule\Manager as DB;
use League\Plates\Engine;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Psr\Log\LoggerInterface;

return [
    // Database
    DB::class => function () {
        $capsule = new DB;
        
        $capsule->addConnection([
            'driver' => 'mysql',
            'host' => $_ENV['DB_HOST'],
            'port' => $_ENV['DB_PORT'],
            'database' => $_ENV['DB_DATABASE'],
            'username' => $_ENV['DB_USERNAME'],
            'password' => $_ENV['DB_PASSWORD'],
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ]);
        
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        
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
];