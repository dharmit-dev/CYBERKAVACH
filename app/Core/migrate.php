<?php

declare(strict_types=1);

// CLI or Web-accessible migration runner
define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/app/Core/env.php';
load_env(BASE_PATH . '/.env');
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/app/Core/database.php';

// If run from browser, restrict access to logged-in faculty_coordinators
if (PHP_SAPI !== 'cli') {
    // Start session if not started
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('CYBERKAVACH_SESSION');
        session_start();
    }
    
    // Check if user is logged in and is faculty coordinator
    require_once BASE_PATH . '/app/Models/User.php';
    require_once BASE_PATH . '/app/Helpers/functions.php';
    
    $user = current_user();
    if (!$user || $user['role_key'] !== 'faculty_coordinator') {
        http_response_code(403);
        exit('Access denied. Only Faculty Coordinators can run migrations.');
    }
}

try {
    $db = db();
    
    // Create migrations_log table if not exists
    $db->exec("
        CREATE TABLE IF NOT EXISTS migrations_log (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            migration_name VARCHAR(150) NOT NULL UNIQUE,
            executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;
    ");
    
    $migrationsDir = BASE_PATH . '/database/migrations';
    if (!is_dir($migrationsDir)) {
        throw new RuntimeException("Migrations directory not found: $migrationsDir");
    }
    
    $files = glob($migrationsDir . '/*.sql');
    sort($files);
    
    $executed = $db->query("SELECT migration_name FROM migrations_log")->fetchAll(PDO::FETCH_COLUMN);
    
    $runCount = 0;
    foreach ($files as $file) {
        $filename = basename($file);
        if (in_array($filename, $executed, true)) {
            continue;
        }
        
        echo "Running migration: $filename...\n";
        if (PHP_SAPI !== 'cli') {
            echo "Running migration: " . htmlspecialchars($filename) . "<br>";
        }
        
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException("Could not read migration file: $filename");
        }
        
        // Execute the migration SQL
        $db->exec($sql);
        
        // Log the execution
        $stmt = $db->prepare("INSERT INTO migrations_log (migration_name) VALUES (:name)");
        $stmt->execute(['name' => $filename]);
        
        $runCount++;
    }
    
    echo "Migration completed successfully. Run count: $runCount\n";
    if (PHP_SAPI !== 'cli') {
        echo "Migration completed successfully. Run count: $runCount<br>";
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    if (PHP_SAPI !== 'cli') {
        echo "<strong>ERROR:</strong> " . htmlspecialchars($e->getMessage()) . "<br>";
    }
    exit(1);
}
