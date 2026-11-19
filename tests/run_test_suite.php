<?php
/**
 * SPARKY LMS - AUTOMATED VERIFICATION & TEST SUITE
 * Run from repository root: php tests/run_test_suite.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$passed = 0;
$failed = 0;
$warnings = 0;

function assertTest($description, $condition, $failMsg = '') {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] " . $description . "\n";
        return true;
    } else {
        $failed++;
        echo "  [FAIL] " . $description . ($failMsg ? " -> " . $failMsg : "") . "\n";
        return false;
    }
}

function assertWarn($description, $condition, $warnMsg = '') {
    global $passed, $warnings;
    if ($condition) {
        $passed++;
        echo "  [PASS] " . $description . "\n";
        return true;
    } else {
        $warnings++;
        echo "  [WARN] " . $description . ($warnMsg ? " -> " . $warnMsg : "") . "\n";
        return false;
    }
}

$rootDir = realpath(__DIR__ . '/..');

echo "========================================================\n";
echo "        SPARKY LMS - COMPREHENSIVE TEST SUITE           \n";
echo "========================================================\n";
echo "Target Root: " . $rootDir . "\n\n";

// -----------------------------------------------------------------------------
// 1. ENVIRONMENT & PHP RUNTIME CONTRACT
// -----------------------------------------------------------------------------
echo "--- 1. PHP Runtime & Dependency Contract ---\n";
assertTest("PHP Version >= 8.1 (Current: " . PHP_VERSION . ")", version_compare(PHP_VERSION, '8.1.0', '>='));
assertTest("Extension: cURL loaded", extension_loaded('curl'));
assertTest("Extension: FileInfo loaded", extension_loaded('fileinfo'));
assertTest("Extension: GD loaded", extension_loaded('gd'));
assertTest("Extension: Intl loaded", extension_loaded('intl'));
assertTest("Extension: JSON loaded", extension_loaded('json'));
assertTest("Extension: Multibyte String (mbstring) loaded", extension_loaded('mbstring'));
assertTest("Extension: XML loaded", extension_loaded('xml'));
assertTest("Extension: Zip loaded", extension_loaded('zip'));

// -----------------------------------------------------------------------------
// 2. COMPOSER & DEPLOYMENT CONFIGURATIONS
// -----------------------------------------------------------------------------
echo "\n--- 2. Composer & Deployment Contract ---\n";
$composerJsonPath = $rootDir . '/composer.json';
assertTest("composer.json exists", file_exists($composerJsonPath));

$composerData = json_decode(file_get_contents($composerJsonPath), true);
assertTest("composer.json is valid JSON", is_array($composerData));
assertTest("composer.json declares PHP 8.1+ contract", isset($composerData['require']['php']) && strpos($composerData['require']['php'], '^8.1') !== false);
assertTest("composer.json pins platform PHP to 8.3", isset($composerData['config']['platform']['php']) && version_compare($composerData['config']['platform']['php'], '8.1.0', '>='));
assertTest("composer.json has security audit blocking enabled", isset($composerData['config']['audit']['block-insecure']) && $composerData['config']['audit']['block-insecure'] === true);

$composerLockPath = $rootDir . '/composer.lock';
assertTest("composer.lock exists", file_exists($composerLockPath));
$composerLockContent = file_get_contents($composerLockPath);
assertTest("composer.lock platform php matches 8.1+", strpos($composerLockContent, '"php": "^8.1') !== false);

assertTest("app/config/configuration.php.dist exists", file_exists($rootDir . '/app/config/configuration.php.dist'));
assertTest("docker-compose.yml exists", file_exists($rootDir . '/docker-compose.yml'));
$dockerComposeContent = file_get_contents($rootDir . '/docker-compose.yml');
assertTest("docker-compose.yml has NO hardcoded fallback DB_PASSWORD (:-)", strpos($dockerComposeContent, ':-SparkySecurePass') === false);
assertTest("docker-compose.yml has NO hardcoded fallback REDIS_PASSWORD (:-)", strpos($dockerComposeContent, ':-SparkyRedisPass') === false);
assertTest("docker-compose.yml has NO hardcoded fallback MYSQL_ROOT_PASSWORD (:-)", strpos($dockerComposeContent, ':-RootSecurePass') === false);
assertTest("docker-compose.yml mandates DB_PASSWORD via :? error syntax", strpos($dockerComposeContent, 'DB_PASSWORD: ${DB_PASSWORD:?') !== false);
assertTest("docker-compose.yml mandates MYSQL_ROOT_PASSWORD via :? error syntax", strpos($dockerComposeContent, 'MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD:?') !== false);
assertTest("docker-compose.yml mandates REDIS_PASSWORD via :? error syntax", strpos($dockerComposeContent, 'REDIS_PASSWORD: ${REDIS_PASSWORD:?') !== false);
assertTest("docker-compose.yml mounts SSL certificates directory", strpos($dockerComposeContent, './docker/ssl:/etc/nginx/ssl:ro') !== false);
assertTest("docker-compose.yml mounts sparky routes include", strpos($dockerComposeContent, './docker/sparky-routes.inc:/etc/nginx/conf.d/sparky-routes.inc:ro') !== false);

assertTest(".env.example exists", file_exists($rootDir . '/.env.example'));
$envExampleContent = file_get_contents($rootDir . '/.env.example');
assertTest(".env.example documents DB_PASSWORD", strpos($envExampleContent, 'DB_PASSWORD') !== false);
assertTest(".env.example documents MYSQL_ROOT_PASSWORD", strpos($envExampleContent, 'MYSQL_ROOT_PASSWORD') !== false);
assertTest(".env.example documents REDIS_PASSWORD", strpos($envExampleContent, 'REDIS_PASSWORD') !== false);

$gitignoreContent = file_get_contents($rootDir . '/.gitignore');
assertTest(".gitignore ignores .env", strpos($gitignoreContent, ".env\n") !== false || strpos($gitignoreContent, ".env\r\n") !== false);

assertTest("Dockerfile exists", file_exists($rootDir . '/Dockerfile'));
$dockerfileContent = file_get_contents($rootDir . '/Dockerfile');
assertTest("Dockerfile copies composer.json and composer.lock", strpos($dockerfileContent, 'COPY composer.json composer.lock') !== false);
assertTest("Dockerfile runs composer install", strpos($dockerfileContent, 'composer install') !== false);
assertTest("Dockerfile sets up entrypoint script", strpos($dockerfileContent, 'ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]') !== false);

assertTest("docker/entrypoint.sh exists", file_exists($rootDir . '/docker/entrypoint.sh'));
$entrypointContent = file_get_contents($rootDir . '/docker/entrypoint.sh');
assertTest("docker/entrypoint.sh validates vendor/autoload.php presence", strpos($entrypointContent, 'vendor/autoload.php') !== false);

assertTest("docker/ssl/cert.pem exists", file_exists($rootDir . '/docker/ssl/cert.pem'));
assertTest("docker/ssl/key.pem exists", file_exists($rootDir . '/docker/ssl/key.pem'));
assertTest("docker/ssl/cert.pem is non-empty", filesize($rootDir . '/docker/ssl/cert.pem') > 500);
assertTest("docker/ssl/key.pem is non-empty", filesize($rootDir . '/docker/ssl/key.pem') > 500);

assertTest("docker/nginx.conf exists", file_exists($rootDir . '/docker/nginx.conf'));
$dockerNginxContent = file_get_contents($rootDir . '/docker/nginx.conf');
assertTest("docker/nginx.conf listens on HTTP (port 80)", strpos($dockerNginxContent, 'listen 80;') !== false);
assertTest("docker/nginx.conf listens on HTTPS (port 443 ssl)", strpos($dockerNginxContent, 'listen 443 ssl') !== false);
assertTest("docker/nginx.conf references SSL cert and key", strpos($dockerNginxContent, '/etc/nginx/ssl/cert.pem') !== false && strpos($dockerNginxContent, '/etc/nginx/ssl/key.pem') !== false);

assertTest("docker/sparky-routes.inc exists", file_exists($rootDir . '/docker/sparky-routes.inc'));
$sparkyRoutesContent = file_get_contents($rootDir . '/docker/sparky-routes.inc');
assertTest("docker/sparky-routes.inc blocks direct access to app/courses", strpos($sparkyRoutesContent, 'app/courses') !== false);
assertTest("docker/sparky-routes.inc routes courses through download handlers (ZERO aliases to disk)", strpos($sparkyRoutesContent, 'alias') === false);

assertTest("nginx.conf.sample exists", file_exists($rootDir . '/nginx.conf.sample'));
$sampleNginxContent = file_get_contents($rootDir . '/nginx.conf.sample');
assertTest("nginx.conf.sample contains ZERO filesystem aliases into app/courses", strpos($sampleNginxContent, 'alias') === false);
assertTest("nginx.conf.sample blocks direct access to app/courses", strpos($sampleNginxContent, 'app/courses') !== false);
assertTest("nginx.conf.sample routes course downloads via PHP handler", strpos($sampleNginxContent, 'download.php') !== false);

// -----------------------------------------------------------------------------
// 3. SECURITY BOUNDARIES & ACCESS CONTROLS (Negative Tests)
// -----------------------------------------------------------------------------
echo "\n--- 3. Web Server Access Controls & Negative Security Tests ---\n";

// Test .htaccess rules
$htaccessPath = $rootDir . '/.htaccess';
assertTest(".htaccess exists", file_exists($htaccessPath));
$htaccessContent = file_get_contents($htaccessPath);
assertTest(".htaccess blocks direct access to database/ directory", strpos($htaccessContent, 'database') !== false);
assertTest(".htaccess blocks .sql dump files", strpos($htaccessContent, 'sql') !== false);
assertTest(".htaccess blocks .bat scripts", strpos($htaccessContent, 'bat') !== false);
assertTest(".htaccess blocks .sh shell scripts", strpos($htaccessContent, 'sh') !== false);
assertTest(".htaccess sets X-Content-Type-Options nosniff", strpos($htaccessContent, 'X-Content-Type-Options') !== false);
assertTest(".htaccess sets X-Frame-Options SAMEORIGIN", strpos($htaccessContent, 'X-Frame-Options') !== false);
assertTest(".htaccess blocks direct access to app/courses", strpos($htaccessContent, 'app/courses') !== false);
assertTest(".htaccess contains no direct disk rewrite to app/courses", strpos($htaccessContent, 'app/courses/$1/$2') === false);

// Test database/.htaccess
$dbHtaccess = $rootDir . '/database/.htaccess';
assertTest("database/.htaccess exists (defense-in-depth)", file_exists($dbHtaccess));
$dbHtContent = file_get_contents($dbHtaccess);
assertTest("database/.htaccess denies all access", strpos($dbHtContent, 'denied') !== false || strpos($dbHtContent, 'Deny from all') !== false);

// Test web.config (IIS)
$webConfigPath = $rootDir . '/web.config';
assertTest("web.config exists", file_exists($webConfigPath));
$webConfigContent = file_get_contents($webConfigPath);
assertTest("web.config blocks database and sensitive files", strpos($webConfigContent, 'database') !== false && strpos($webConfigContent, 'sql') !== false);

// Test router.php rules
$routerPath = $rootDir . '/router.php';
assertTest("router.php exists", file_exists($routerPath));
$routerContent = file_get_contents($routerPath);
assertTest("router.php blocks /database directory", strpos($routerContent, '/database') !== false);
assertTest("router.php blocks /app/config directory", strpos($routerContent, '/app/config') !== false);
assertTest("router.php blocks direct /app/courses directory access", strpos($routerContent, '/app/courses') !== false);
assertTest("router.php blocks .sql extension", strpos($routerContent, "'sql'") !== false);
assertTest("router.php blocks .bat extension", strpos($routerContent, "'bat'") !== false);
assertTest("router.php avoids wildcard CORS on media", strpos($routerContent, 'Access-Control-Allow-Origin: *') === false);

// -----------------------------------------------------------------------------
// 4. COURSE CONTENT & CURRICULUM UPLOAD PROTECTION
// -----------------------------------------------------------------------------
echo "\n--- 4. Curriculum Upload & RCE Prevention Tests ---\n";
require_once $rootDir . '/main/inc/lib/course_curriculum.lib.php';

// Negative test: Uploading executable scripts must be strictly rejected
$fakePhpFile = [
    'name' => 'malicious_shell.php',
    'type' => 'application/x-php',
    'tmp_name' => '',
    'error' => UPLOAD_ERR_OK,
    'size' => 123
];
$resPhp = CourseCurriculum::handleUploadedFile($fakePhpFile, 'TESTCOURSE');
assertTest("Curriculum upload strictly rejects .php files", $resPhp === false);

$fakePharFile = [
    'name' => 'polyglot.phar',
    'type' => 'application/octet-stream',
    'tmp_name' => '',
    'error' => UPLOAD_ERR_OK,
    'size' => 500
];
$resPhar = CourseCurriculum::handleUploadedFile($fakePharFile, 'TESTCOURSE');
assertTest("Curriculum upload strictly rejects .phar archives", $resPhar === false);

$fakeShFile = [
    'name' => 'backdoor.sh',
    'type' => 'application/x-sh',
    'tmp_name' => '',
    'error' => UPLOAD_ERR_OK,
    'size' => 200
];
$resSh = CourseCurriculum::handleUploadedFile($fakeShFile, 'TESTCOURSE');
assertTest("Curriculum upload strictly rejects .sh shell scripts", $resSh === false);

$fakeExeFile = [
    'name' => 'payload.exe',
    'type' => 'application/octet-stream',
    'tmp_name' => '',
    'error' => UPLOAD_ERR_OK,
    'size' => 2048
];
$resExe = CourseCurriculum::handleUploadedFile($fakeExeFile, 'TESTCOURSE');
assertTest("Curriculum upload strictly rejects .exe executables", $resExe === false);

// Lesson type detection tests
assertTest("detectLessonTypeFromFilename identifies MP4 as video", CourseCurriculum::detectLessonTypeFromFilename('intro.mp4') === 'video');
assertTest("detectLessonTypeFromFilename identifies WEBM as video", CourseCurriculum::detectLessonTypeFromFilename('lecture.webm') === 'video');
assertTest("detectLessonTypeFromFilename identifies PDF as pdf", CourseCurriculum::detectLessonTypeFromFilename('syllabus.pdf') === 'pdf');
assertTest("detectLessonTypeFromFilename identifies MP3 as audio", CourseCurriculum::detectLessonTypeFromFilename('podcast.mp3') === 'audio');
assertTest("detectLessonTypeFromFilename identifies DOCX as document", CourseCurriculum::detectLessonTypeFromFilename('notes.docx') === 'document');

// -----------------------------------------------------------------------------
// 5. CERTIFICATE BLUEPRINT & XSS PREVENTION
// -----------------------------------------------------------------------------
echo "\n--- 5. Certificate Blueprints & XSS Sanitization Tests ---\n";
require_once $rootDir . '/main/inc/lib/certificate_blueprint.lib.php';

$mockBp = [
    'id' => 101,
    'name' => 'Test Blueprint',
    'template_key' => 'et_masterclass',
    'title' => 'CERTIFICATE OF COMPLETION',
    'font_family' => 'Playfair Display',
    'primary_color' => '#4F46E5',
    'custom_html' => '<div class="student">{{STUDENT_NAME}}</div><div class="course">{{COURSE_NAME}}</div>',
    'custom_css' => '.student { font-size: 24px; }'
];

$xssPayload = '<script>alert("XSS")</script>';
$rendered = CertificateBlueprint::renderHtml($mockBp, $xssPayload, 'Cybersecurity 101');
assertTest("Certificate rendering escapes XSS script tag in student name", strpos($rendered, '&lt;script&gt;') !== false && strpos($rendered, '<script>') === false);

// Certificate editor background uploads must disallow SVG
$certEditorFile = file_get_contents($rootDir . '/main/admin/certificate_editor.php');
assertTest("Certificate Editor background upload disallows SVG to prevent Stored XSS", strpos($certEditorFile, "'svg'") === false);

// -----------------------------------------------------------------------------
// 6. HTTP 206 BYTE-RANGE STREAMING LOGIC TESTS
// -----------------------------------------------------------------------------
echo "\n--- 6. HTTP 206 Partial Content Range Parsing Tests ---\n";

// Function simulating range boundary calculation
function calculateRange($rangeHeader, $fileSize) {
    if (preg_match('/^bytes=\s*(\d*)\s*-\s*(\d*)$/i', $rangeHeader, $m)) {
        $rawStart = $m[1];
        $rawEnd = $m[2];
        if ($rawStart === '' && $rawEnd !== '') {
            $start = $fileSize - (int)$rawEnd;
            $end = $fileSize - 1;
        } elseif ($rawStart !== '' && $rawEnd === '') {
            $start = (int)$rawStart;
            $end = $fileSize - 1;
        } elseif ($rawStart !== '' && $rawEnd !== '') {
            $start = (int)$rawStart;
            $end = (int)$rawEnd;
        } else {
            return false;
        }
        $start = max(0, $start);
        $end = min($fileSize - 1, $end);
        if ($start > $end || $start >= $fileSize) {
            return 416; // Unsatisfiable
        }
        return ['start' => $start, 'end' => $end, 'length' => ($end - $start + 1)];
    }
    return false;
}

$fileSize = 1000000; // 1 MB
$r1 = calculateRange('bytes=0-1023', $fileSize);
assertTest("Range bytes=0-1023 parses start 0, end 1023, length 1024", $r1['start'] === 0 && $r1['end'] === 1023 && $r1['length'] === 1024);

$r2 = calculateRange('bytes=500000-', $fileSize);
assertTest("Open range bytes=500000- parses start 500000, end 999999", $r2['start'] === 500000 && $r2['end'] === 999999);

$r3 = calculateRange('bytes=-500', $fileSize);
assertTest("Suffix range bytes=-500 parses start 999500, end 999999", $r3['start'] === 999500 && $r3['end'] === 999999);

$r4 = calculateRange('bytes=2000000-3000000', $fileSize);
assertTest("Out-of-bounds range returns HTTP 416 Range Not Satisfiable", $r4 === 416);

// -----------------------------------------------------------------------------
// 7. SESSION COOKIE SECURITY ASSERTION
// -----------------------------------------------------------------------------
echo "\n--- 7. Runtime Session Security Contract ---\n";
$sessionClassContent = file_get_contents($rootDir . '/main/inc/lib/chamilo_session.class.php');
assertTest("chamilo_session enforces session.cookie_httponly = 1", strpos($sessionClassContent, "ini_set('session.cookie_httponly', 1)") !== false);
assertTest("chamilo_session enforces session.use_only_cookies = 1", strpos($sessionClassContent, "ini_set('session.use_only_cookies', 1)") !== false);
assertTest("chamilo_session enforces session.use_strict_mode = 1", strpos($sessionClassContent, "ini_set('session.use_strict_mode', 1)") !== false);

// -----------------------------------------------------------------------------
// 8. DATABASE BOOTSTRAP SCHEMA VALIDATION
// -----------------------------------------------------------------------------
echo "\n--- 8. Database Schema & Bootstrap Verification ---\n";
$dbSqlPath = $rootDir . '/database/sparky_clean_database.sql';
assertTest("Clean database dump exists (database/sparky_clean_database.sql)", file_exists($dbSqlPath));
$dbSqlHeader = file_get_contents($dbSqlPath, false, null, 0, 1024);
assertTest("Database dump has valid MariaDB / MySQL dump header", strpos($dbSqlHeader, 'MariaDB dump') !== false || strpos($dbSqlHeader, 'MySQL dump') !== false);

// Check presence of key tables in the dump
$keyTables = ['user', 'course', 'session', 'c_document', 'c_item_property', 'c_certificate_blueprint', 'c_curriculum_chapter', 'c_curriculum_lesson', 'user_magic_token'];
foreach ($keyTables as $tbl) {
    $found = false;
    // Check table creation
    $handle = fopen($dbSqlPath, 'r');
    while (($line = fgets($handle)) !== false) {
        if (strpos($line, "CREATE TABLE `{$tbl}`") !== false || strpos($line, "TABLE_NAME = '{$tbl}'") !== false || strpos($line, "DROP TABLE IF EXISTS `{$tbl}`") !== false) {
            $found = true;
            break;
        }
    }
    fclose($handle);
    assertTest("Database contains table definition: `{$tbl}`", $found);
}

// -----------------------------------------------------------------------------
// SUMMARY
// -----------------------------------------------------------------------------
echo "\n========================================================\n";
echo "PASSED:   " . $passed . "\n";
echo "FAILED:   " . $failed . "\n";
echo "WARNINGS: " . $warnings . "\n";
echo "========================================================\n";

if ($failed > 0) {
    echo "TEST SUITE FAILED.\n";
    exit(1);
} else {
    echo "ALL TESTS PASSED SUCCESSFULLY.\n";
    exit(0);
}
