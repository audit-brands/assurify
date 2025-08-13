<?php

try {
    $dbPath = '/home/jamieontiveros/Development/github/assurify/database.sqlite';
    
    // Create SQLite database connection
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to SQLite database successfully!\n";
    
    // Read SQL file
    $sql = file_get_contents('/home/jamieontiveros/Development/github/assurify/create_sqlite_schema.sql');
    
    // Execute SQL
    $pdo->exec($sql);
    
    echo "SQLite database schema created successfully!\n";
    
    // Verify tables were created
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Tables created: " . implode(', ', $tables) . "\n";
    
    // Verify admin user exists
    $stmt = $pdo->query("SELECT username, email FROM users WHERE is_admin = 1");
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        echo "Admin user created: {$admin['username']} ({$admin['email']})\n";
    }
    
    echo "Database setup complete!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}