<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;

// Initialize the database connection
$capsule = new DB;
$capsule->addConnection([
    'driver' => 'sqlite',
    'database' => __DIR__ . '/database.sqlite',
    'prefix' => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "=== CREATING PRIVATE MESSAGING SCHEMA ===\n";

try {
    // Create messages table
    DB::statement("
        CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            author_user_id INTEGER NOT NULL,
            recipient_user_id INTEGER NOT NULL,
            subject VARCHAR(100) NOT NULL,
            body TEXT NOT NULL,
            short_id VARCHAR(6) NOT NULL UNIQUE,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            deleted_by_author BOOLEAN DEFAULT 0,
            deleted_by_recipient BOOLEAN DEFAULT 0,
            has_been_read BOOLEAN DEFAULT 0,
            
            FOREIGN KEY (author_user_id) REFERENCES users(id),
            FOREIGN KEY (recipient_user_id) REFERENCES users(id)
        )
    ");
    echo "✓ Created messages table\n";

    // Create message_replies table for threaded conversations
    DB::statement("
        CREATE TABLE IF NOT EXISTS message_replies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            message_id INTEGER NOT NULL,
            author_user_id INTEGER NOT NULL,
            recipient_user_id INTEGER NOT NULL,
            body TEXT NOT NULL,
            short_id VARCHAR(6) NOT NULL UNIQUE,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            deleted_by_author BOOLEAN DEFAULT 0,
            deleted_by_recipient BOOLEAN DEFAULT 0,
            has_been_read BOOLEAN DEFAULT 0,
            
            FOREIGN KEY (message_id) REFERENCES messages(id),
            FOREIGN KEY (author_user_id) REFERENCES users(id),
            FOREIGN KEY (recipient_user_id) REFERENCES users(id)
        )
    ");
    echo "✓ Created message_replies table\n";

    // Add messaging preferences to users table
    $columns = DB::select("PRAGMA table_info(users)");
    $columnNames = array_column($columns, 'name');
    
    $messagingColumns = [
        'email_message_notifications' => 'BOOLEAN DEFAULT 1',
        'pushover_message_notifications' => 'BOOLEAN DEFAULT 0',
        'disable_private_messages' => 'BOOLEAN DEFAULT 0'
    ];

    foreach ($messagingColumns as $column => $definition) {
        if (!in_array($column, $columnNames)) {
            echo "Adding column: $column\n";
            DB::statement("ALTER TABLE users ADD COLUMN $column $definition");
        } else {
            echo "Column $column already exists\n";
        }
    }

    echo "\n=== MESSAGING SCHEMA CREATED SUCCESSFULLY ===\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}