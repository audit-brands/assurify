<?php

/**
 * SQLite database setup script for local development
 * Creates database and admin user for testing
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    // Create SQLite database file if it doesn't exist
    $dbPath = $_ENV['DB_DATABASE'];
    if (!file_exists($dbPath)) {
        touch($dbPath);
        echo "Created SQLite database at: $dbPath\n";
    }
    
    // Configure database connection
    $capsule = new DB;
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => $dbPath,
        'prefix' => '',
    ]);
    
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    
    echo "Connected to SQLite database successfully!\n";
    
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
        });
        echo "Stories table created!\n";
    }
    
    // Comments table
    if (!DB::schema()->hasTable('comments')) {
        DB::schema()->create('comments', function ($table) {
            $table->id();
            $table->integer('user_id');
            $table->integer('story_id');
            $table->integer('parent_comment_id')->nullable();
            $table->text('comment');
            $table->text('markeddown_comment')->nullable();
            $table->string('short_id', 10)->unique();
            $table->integer('score')->default(1);
            $table->boolean('is_deleted')->default(false);
            $table->boolean('is_moderated')->default(false);
            $table->timestamps();
        });
        echo "Comments table created!\n";
    }
    
    // Votes table
    if (!DB::schema()->hasTable('votes')) {
        DB::schema()->create('votes', function ($table) {
            $table->id();
            $table->integer('user_id');
            $table->integer('story_id')->nullable();
            $table->integer('comment_id')->nullable();
            $table->integer('vote'); // 1 for upvote, -1 for downvote
            $table->timestamps();
            
            $table->unique(['user_id', 'story_id']);
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
        });
        echo "Taggings table created!\n";
    }
    
    // Invitations table
    if (!DB::schema()->hasTable('invitations')) {
        DB::schema()->create('invitations', function ($table) {
            $table->id();
            $table->integer('user_id');
            $table->string('email');
            $table->string('code', 32)->unique();
            $table->text('memo')->nullable();
            $table->boolean('is_used')->default(false);
            $table->timestamp('used_at')->nullable();
            $table->integer('new_user_id')->nullable();
            $table->timestamps();
        });
        echo "Invitations table created!\n";
    }
    
    // Create admin and test users
    if (DB::table('users')->count() == 0) {
        // Create admin user
        DB::table('users')->insert([
            'username' => 'admin',
            'email' => 'admin@assurify.local',
            'password_hash' => password_hash('admin123', PASSWORD_ARGON2ID),
            'karma' => 1000,
            'is_admin' => true,
            'is_moderator' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        echo "✅ Admin user created!\n";
        echo "   Username: admin\n";
        echo "   Password: admin123\n";
        echo "   Email: admin@assurify.local\n\n";
        
        // Create regular test user
        DB::table('users')->insert([
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password_hash' => password_hash('password', PASSWORD_ARGON2ID),
            'karma' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        echo "✅ Test user created!\n";
        echo "   Username: testuser\n";
        echo "   Password: password\n";
        echo "   Email: test@example.com\n\n";
    }
    
    // Create starter tags
    if (DB::table('tags')->count() == 0) {
        $tags = [
            ['tag' => 'auditing', 'description' => 'Audit topics and best practices'],
            ['tag' => 'risk', 'description' => 'Risk management and assessment'],
            ['tag' => 'jobs', 'description' => 'Job postings and career opportunities'],
            ['tag' => 'compliance', 'description' => 'Regulatory compliance discussions'],
            ['tag' => 'security', 'description' => 'Security and cybersecurity topics'],
            ['tag' => 'finance', 'description' => 'Financial auditing and reporting'],
            ['tag' => 'technology', 'description' => 'Technology and IT audit topics'],
            ['tag' => 'governance', 'description' => 'Corporate governance and controls'],
        ];
        
        foreach ($tags as $tag) {
            DB::table('tags')->insert(array_merge($tag, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
        echo "✅ Starter tags created (auditing, risk, jobs, compliance, security, finance, technology, governance)!\n\n";
    }
    
    // Create a sample story
    if (DB::table('stories')->count() == 0) {
        DB::table('stories')->insert([
            'user_id' => 1, // admin user
            'title' => 'Welcome to Assurify - A Platform for Audit and Risk Professionals',
            'url' => 'https://example.com/welcome',
            'description' => 'Welcome to Assurify! This is a community platform designed specifically for audit and risk management professionals. Share knowledge, discuss best practices, and connect with peers in the industry.',
            'short_id' => 'abc123',
            'score' => 1,
            'upvotes' => 1,
            'downvotes' => 0,
            'user_is_author' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Tag the story
        DB::table('taggings')->insert([
            'story_id' => 1,
            'tag_id' => 1, // auditing tag
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        echo "✅ Sample welcome story created!\n\n";
    }
    
    echo "🎉 Database setup completed successfully!\n";
    echo "🚀 You can now start the server and test the application:\n";
    echo "   php -S localhost:8080 -t public\n";
    echo "   Then visit: http://localhost:8080\n\n";
    echo "📝 Admin login credentials:\n";
    echo "   Username: admin\n";
    echo "   Password: admin123\n\n";
    echo "🧪 Test user credentials:\n";
    echo "   Username: testuser\n";
    echo "   Password: password\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}