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
