<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as DB;

require __DIR__ . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Setup database connection
$capsule = new DB;
$capsule->addConnection([
    'driver' => $_ENV['DB_CONNECTION'] ?? 'sqlite',
    'database' => $_ENV['DB_DATABASE'] ?? __DIR__ . '/database.sqlite',
    'prefix' => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

// Create migrations table if it doesn't exist
if (!DB::schema()->hasTable('migrations')) {
    DB::schema()->create('migrations', function ($table) {
        $table->id();
        $table->string('migration');
        $table->integer('batch');
    });
    echo "Created migrations table\n";
}

// Get existing migrations
$existingMigrations = DB::table('migrations')->pluck('migration')->toArray();

// Run migrations
$migrationFiles = glob(__DIR__ . '/database/migrations/*.php');
sort($migrationFiles);

$batch = DB::table('migrations')->max('batch') + 1;

foreach ($migrationFiles as $file) {
    $filename = basename($file, '.php');
    
    if (in_array($filename, $existingMigrations)) {
        echo "Skipping migration: {$filename} (already run)\n";
        continue;
    }
    
    echo "Running migration: {$filename}\n";
    
    try {
        $migration = require $file;
        $migration['up']();
        
        // Record migration
        DB::table('migrations')->insert([
            'migration' => $filename,
            'batch' => $batch
        ]);
        
        echo "Completed migration: {$filename}\n";
    } catch (Exception $e) {
        echo "Error running migration {$filename}: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "All migrations completed!\n";