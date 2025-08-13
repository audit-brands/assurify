<?php

header('Content-Type: text/plain');

echo "=== SQLite Test in Web Context ===\n\n";

// Load environment
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "DB_CONNECTION: " . ($_ENV['DB_CONNECTION'] ?? 'not set') . "\n";
echo "DB_DATABASE: " . ($_ENV['DB_DATABASE'] ?? 'not set') . "\n";
echo "PDO SQLite loaded: " . (extension_loaded('pdo_sqlite') ? 'YES' : 'NO') . "\n";
echo "SQLite3 loaded: " . (extension_loaded('sqlite3') ? 'YES' : 'NO') . "\n\n";

// Test direct PDO
try {
    $dbPath = $_ENV['DB_DATABASE'] ?? '/home/jamieontiveros/Development/github/assurify/database.sqlite';
    echo "Testing direct PDO to: $dbPath\n";
    $pdo = new PDO("sqlite:$dbPath");
    echo "✓ Direct PDO connection successful\n";
    
    $result = $pdo->query("SELECT COUNT(*) as count FROM users");
    $count = $result->fetch(PDO::FETCH_ASSOC);
    echo "✓ User count: " . $count['count'] . "\n";
    
} catch (Exception $e) {
    echo "✗ Direct PDO failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test Capsule
use Illuminate\Database\Capsule\Manager as DB;

try {
    
    $capsule = new DB;
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => $_ENV['DB_DATABASE'] ?? '/home/jamieontiveros/Development/github/assurify/database.sqlite',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    
    echo "✓ Capsule initialized\n";
    
    // Test connection
    $capsule->getConnection()->getPdo();
    echo "✓ Capsule connection successful\n";
    
    // Test query
    $users = $capsule->getConnection()->table('users')->count();
    echo "✓ Capsule query successful - Users: $users\n";
    
} catch (Exception $e) {
    echo "✗ Capsule failed: " . $e->getMessage() . "\n";
    echo "Error class: " . get_class($e) . "\n";
    echo "Error trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";