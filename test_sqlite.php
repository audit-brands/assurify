<?php

echo "Testing SQLite connection...\n";

// Test 1: Check if extension is loaded
echo "PDO SQLite extension loaded: " . (extension_loaded('pdo_sqlite') ? 'YES' : 'NO') . "\n";

// Test 2: Try to create SQLite connection directly
try {
    $pdo = new PDO('sqlite:/home/jamieontiveros/Development/github/assurify/database.sqlite');
    echo "Direct PDO connection: SUCCESS\n";
    
    // Test a simple query
    $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
    $tables = $result->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables found: " . implode(', ', $tables) . "\n";
    
} catch (PDOException $e) {
    echo "Direct PDO connection FAILED: " . $e->getMessage() . "\n";
}

// Test 3: Try with Illuminate Capsule
require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;

try {
    $capsule = new DB;
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => '/home/jamieontiveros/Development/github/assurify/database.sqlite',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    
    // Test the connection
    $pdo = $capsule->getConnection()->getPdo();
    echo "Capsule connection: SUCCESS\n";
    
    // Test query
    $result = $capsule->getConnection()->select("SELECT name FROM sqlite_master WHERE type='table'");
    echo "Capsule query result: " . count($result) . " tables\n";
    
} catch (Exception $e) {
    echo "Capsule connection FAILED: " . $e->getMessage() . "\n";
}