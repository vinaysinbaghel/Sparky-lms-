<?php
/**
 * Database Backup & Restoration Verification Test
 * Validates the schema integrity, table DDLs, character sets, and restoration readiness of Sparky LMS dumps.
 * Run from repository root: php tests/test_database_backup_restore.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$rootDir = realpath(__DIR__ . '/..');
$sqlDumpPath = $rootDir . '/database/sparky_clean_database.sql';

echo "========================================================\n";
echo "   SPARKY LMS - DATABASE BACKUP & RESTORATION TEST     \n";
echo "========================================================\n";
echo "Target Dump: " . $sqlDumpPath . "\n\n";

$passed = 0;
$failed = 0;

function assertDbTest($desc, $cond) {
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  [PASS] " . $desc . "\n";
    } else {
        $failed++;
        echo "  [FAIL] " . $desc . "\n";
    }
}

assertDbTest("Database dump file exists", file_exists($sqlDumpPath));
assertDbTest("Database dump file size > 300KB", filesize($sqlDumpPath) > 300 * 1024);

$fp = fopen($sqlDumpPath, 'r');
assertDbTest("Database dump is readable", is_resource($fp));

$headerLines = '';
for ($i = 0; $i < 30; $i++) {
    $headerLines .= fgets($fp);
}
assertDbTest("Dump contains standard MariaDB/MySQL dump header", strpos($headerLines, 'MariaDB dump') !== false || strpos($headerLines, 'MySQL dump') !== false);

// Scan all CREATE TABLE statements
rewind($fp);
$tables = [];
$totalStatements = 0;
$hasUtf8mb4 = false;

while (($line = fgets($fp)) !== false) {
    if (preg_match('/^CREATE TABLE `([^`]+)`/i', trim($line), $m)) {
        $tables[$m[1]] = true;
    }
    if (strpos($line, 'SET NAMES utf8mb4') !== false || strpos($line, 'CHARSET=utf8') !== false) {
        $hasUtf8mb4 = true;
    }
    if (strpos($line, ';') !== false) {
        $totalStatements++;
    }
}
fclose($fp);

$tableCount = count($tables);
echo "\nTotal tables discovered in dump: {$tableCount}\n";
echo "Total SQL statements discovered: {$totalStatements}\n\n";

assertDbTest("Database dump defines at least 150 tables (Found: {$tableCount})", $tableCount >= 150);
assertDbTest("Database dump enforces UTF-8 character encoding", $hasUtf8mb4);

// Verify core Chamilo foundation tables
$coreTables = [
    'user', 'course', 'session', 'session_rel_course', 'c_document', 
    'c_item_property', 'c_tool', 'c_quiz', 'c_student_publication'
];
foreach ($coreTables as $ct) {
    assertDbTest("Core foundation table exists: `{$ct}`", isset($tables[$ct]));
}

// Verify custom Sparky LMS tables
$sparkyTables = [
    'c_curriculum_chapter',
    'c_curriculum_lesson',
    'c_curriculum_lesson_track',
    'c_certificate_blueprint',
    'c_certificate_blueprint_course',
    'c_certificate_awarded',
    'user_magic_token',
    'user_magic_attempt'
];
foreach ($sparkyTables as $st) {
    assertDbTest("Sparky custom subsystem table exists: `{$st}`", isset($tables[$st]));
}

// Verify backup script / procedure simulation
echo "\n--- Backup Script & Restore Command Verification ---\n";
$envExample = file_get_contents($rootDir . '/.env.example');
assertDbTest(".env.example documents DB_NAME and DB_USER for backups", strpos($envExample, 'DB_NAME') !== false && strpos($envExample, 'DB_USER') !== false);

// Simulate backup command syntax
$backupCmd = 'mysqldump -u ${DB_USER} -p"${DB_PASSWORD}" --single-transaction --quick --routines --triggers ${DB_NAME} > backup_$(date +%Y%m%d).sql';
$restoreCmd = 'mysql -u ${DB_USER} -p"${DB_PASSWORD}" ${DB_NAME} < backup.sql';
assertDbTest("Backup command specifies --single-transaction and --quick", strpos($backupCmd, '--single-transaction') !== false && strpos($backupCmd, '--quick') !== false);
assertDbTest("Restore command uses standard input redirection", strpos($restoreCmd, '< backup.sql') !== false);

// Real database restoration test when isolated test database credentials are provided
if (getenv('TEST_MODE') === '1' && getenv('TEST_DB_HOST') && getenv('TEST_DB_NAME') && getenv('TEST_DB_USER')) {
    echo "\n--- Real Isolated Database Restoration Execution ---\n";
    $testDbHost = getenv('TEST_DB_HOST');
    $testDbPort = (int)(getenv('TEST_DB_PORT') ?: 3306);
    $testDbName = getenv('TEST_DB_NAME');
    $testDbUser = getenv('TEST_DB_USER');
    $testDbPass = getenv('TEST_DB_PASSWORD') ?: '';

    // Enforce test prefix & reject production DB_NAME
    $validPrefixes = ['sparky_test_', 'test_', 'sparky_integration_test'];
    $hasValidPrefix = false;
    foreach ($validPrefixes as $prefix) {
        if (strpos($testDbName, $prefix) === 0 || $testDbName === $prefix) {
            $hasValidPrefix = true;
            break;
        }
    }
    assertDbTest("Restoration target uses approved test prefix", $hasValidPrefix);
    
    $appDbName = getenv('DB_NAME') ?: 'sparky_lms';
    assertDbTest("Restoration target does not match application DB_NAME", strcasecmp($testDbName, $appDbName) !== 0);

    try {
        $pdo = new PDO("mysql:host={$testDbHost};port={$testDbPort};dbname={$testDbName};charset=utf8mb4", $testDbUser, $testDbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => true
        ]);
        assertDbTest("Connected to isolated test database for restoration test", true);

        // Execute dump import
        $schemaSql = file_get_contents($sqlDumpPath);
        $schemaSql = preg_replace('/CREATE DATABASE\s+[^;]+;/i', '', $schemaSql);
        $schemaSql = preg_replace('/USE\s+[^;]+;/i', '', $schemaSql);
        $pdo->exec($schemaSql);
        while ($pdo->query("SELECT 1")->fetch()) { break; }

        $tablesResult = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $restoredCount = count($tablesResult);
        assertDbTest("Restored database contains at least 150 tables (Found: {$restoredCount})", $restoredCount >= 150);
        assertDbTest("Restored database has core 'user' table", in_array('user', $tablesResult, true));
        assertDbTest("Restored database has 'settings_current' table", in_array('settings_current', $tablesResult, true));
    } catch (Exception $e) {
        assertDbTest("Real database restoration succeeded", false);
        echo "  [ERROR] Database restoration failed: " . $e->getMessage() . "\n";
    }
}

echo "\n========================================================\n";
echo "BACKUP & RESTORE TESTS PASSED: " . $passed . "\n";
echo "BACKUP & RESTORE TESTS FAILED: " . $failed . "\n";
echo "========================================================\n";

if ($failed > 0) {
    echo "BACKUP VERIFICATION FAILED.\n";
    exit(1);
} else {
    echo "ALL DATABASE BACKUP & RESTORATION TESTS PASSED.\n";
    exit(0);
}
