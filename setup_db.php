<?php

/**
 * Quick database setup script for testing
 * This will create a simple database structure without migrations
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    // First connect to MySQL without selecting a database
    $capsule = new DB;
    $capsule->addConnection([
        'driver' => 'mysql',
        'host' => $_ENV['DB_HOST'],
        'port' => $_ENV['DB_PORT'],
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ]);
    
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    
    // Create database
    DB::statement("CREATE DATABASE IF NOT EXISTS lobsters_slim CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database created successfully!\n";
    
    // Now reconnect to the specific database
    $capsule = new DB;
    $capsule->addConnection([
        'driver' => 'mysql',
        'host' => $_ENV['DB_HOST'],
        'port' => $_ENV['DB_PORT'],
        'database' => $_ENV['DB_DATABASE'],
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ]);
    
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    
    // Create basic tables for testing
    
    // Users table
    if (!DB::schema()->hasTable('users')) {
        DB::schema()->create('users', function ($table) {
            $table->id();
            $table->string('username', 50)->unique();
            $table->string('email', 100)->unique();
            $table->string('password_hash');
            $table->string('session_token', 75)->nullable()->unique();
            $table->integer('karma')->default(0);
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_moderator')->default(false);
            $table->boolean('is_banned')->default(false);
            $table->timestamps();
        });
        echo "Users table created!\n";
    }
    
    // Tags table
    if (!DB::schema()->hasTable('tags')) {
        DB::schema()->create('tags', function ($table) {
            $table->id();
            $table->string('tag', 25)->unique();
            $table->string('description')->default('');
            $table->boolean('privileged')->default(false);
            $table->boolean('is_media')->default(false);
            $table->boolean('inactive')->default(false);
            $table->float('hotness_mod')->default(0);
            $table->timestamps();
        });
        echo "Tags table created!\n";
    }
    
    // Stories table
    if (!DB::schema()->hasTable('stories')) {
        DB::schema()->create('stories', function ($table) {
            $table->id();
            $table->integer('user_id');
            $table->string('title', 150);
            $table->text('url')->nullable();
            $table->text('description')->nullable();
            $table->text('markeddown_description')->nullable();
            $table->string('short_id', 6)->unique();
            $table->integer('score')->default(1);
            $table->integer('upvotes')->default(1);
            $table->integer('downvotes')->default(0);
            $table->integer('comments_count')->default(0);
            $table->boolean('user_is_author')->default(false);
            $table->boolean('is_expired')->default(false);
            $table->boolean('is_moderated')->default(false);
            $table->timestamps();
            
            $table->index(['score', 'created_at']);
            $table->index(['user_id']);
        });
        echo "Stories table created!\n";
    }
    
    // Votes table
    if (!DB::schema()->hasTable('votes')) {
        DB::schema()->create('votes', function ($table) {
            $table->id();
            $table->integer('user_id');
            $table->integer('story_id');
            $table->integer('vote'); // 1 for upvote, -1 for downvote
            $table->timestamps();
            
            $table->unique(['user_id', 'story_id']);
            $table->index(['story_id']);
        });
        echo "Votes table created!\n";
    }
    
    // Taggings table
    if (!DB::schema()->hasTable('taggings')) {
        DB::schema()->create('taggings', function ($table) {
            $table->id();
            $table->integer('story_id');
            $table->integer('tag_id');
            $table->timestamps();
            
            $table->unique(['story_id', 'tag_id']);
            $table->index(['tag_id']);
        });
        echo "Taggings table created!\n";
    }
    
    // Create a test user
    if (DB::table('users')->count() == 0) {
        DB::table('users')->insert([
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password_hash' => password_hash('password', PASSWORD_ARGON2ID),
            'karma' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        echo "Test user created (username: testuser, password: password)!\n";
    }
    
    // Create some test tags
    if (DB::table('tags')->count() == 0) {
        $tags = [
            ['tag' => 'auditing', 'description' => 'Audit topics'],
            ['tag' => 'risk', 'description' => 'Risk management'],
            ['tag' => 'jobs', 'description' => 'share and find jobs'],
            ['tag' => 'compliance', 'description' => 'Compliance and regulatory topics'],
            ['tag' => 'security', 'description' => 'Security and cybersecurity discussions'],
        ];
        
        foreach ($tags as $tag) {
            DB::table('tags')->insert(array_merge($tag, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
        echo "Test tags created!\n";
    }
    
    echo "\nDatabase setup completed successfully!\n";
    echo "You can now test the application.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}