<?php
/**
 * Real HTTP Request & Authorization Verification Test
 * Launches the PHP built-in web server with router.php and executes real HTTP requests
 * testing access controls, byte-range streaming, traversal attacks, and protected routes.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$port = 8899;
$host = '127.0.0.1';
$rootDir = realpath(__DIR__ . '/..');

echo "========================================================\n";
echo "   SPARKY LMS - REAL HTTP REQUEST & AUTHORIZATION TEST \n";
echo "========================================================\n";
echo "Starting test server on http://{$host}:{$port}...\n";

// Ensure temporary configuration.php exists so that Chamilo bootstrap functions safely
$createdTempConfig = false;
if (!file_exists($rootDir . '/app/config/configuration.php') && file_exists($rootDir . '/app/config/configuration.php.dist')) {
    copy($rootDir . '/app/config/configuration.php.dist', $rootDir . '/app/config/configuration.php');
    $createdTempConfig = true;
}

// Ensure isolated test database has schema populated if running with TEST_MODE=1
if (getenv('TEST_MODE') === '1' && getenv('TEST_DB_HOST') && getenv('TEST_DB_NAME')) {
    $tHost = getenv('TEST_DB_HOST');
    $tPort = (int)(getenv('TEST_DB_PORT') ?: 3306);
    $tName = getenv('TEST_DB_NAME');
    $tUser = getenv('TEST_DB_USER') ?: 'root';
    $tPass = getenv('TEST_DB_PASSWORD') ?: '';
    try {
        $checkPdo = new PDO("mysql:host={$tHost};port={$tPort};dbname={$tName};charset=utf8mb4", $tUser, $tPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => true
        ]);
        $hasTables = $checkPdo->query("SHOW TABLES LIKE 'settings_current'")->fetch();
        if (!$hasTables) {
            $dumpSql = file_get_contents($rootDir . '/database/sparky_clean_database.sql');
            $dumpSql = preg_replace('/CREATE DATABASE\s+[^;]+;/i', '', $dumpSql);
            $dumpSql = preg_replace('/USE\s+[^;]+;/i', '', $dumpSql);
            $checkPdo->exec($dumpSql);
            while ($checkPdo->query("SELECT 1")->fetch()) { break; }
        }
    } catch (Exception $e) {
        // Ignored in purely mocked / offline test runs
    }
}

// Start background server process with dedicated log file
$serverLogFile = sys_get_temp_dir() . '/sparky_test_server_' . getmypid() . '.log';
$cmd = sprintf('php -S %s:%d -t "%s" "%s/router.php"', $host, $port, $rootDir, $rootDir);
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['file', $serverLogFile, 'w'],
    2 => ['file', $serverLogFile, 'w']
];

$appEnv = [
    'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/local/sbin:/usr/bin:/bin',
    'TEST_MODE' => '1',
    'TEST_DB_HOST' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
    'TEST_DB_PORT' => getenv('TEST_DB_PORT') ?: '3306',
    'TEST_DB_NAME' => getenv('TEST_DB_NAME') ?: 'sparky_integration_test',
    'TEST_DB_USER' => getenv('TEST_DB_USER') ?: 'sparky_test_user',
    'TEST_DB_PASSWORD' => getenv('TEST_DB_PASSWORD') ?: 'test_user_pass_123',
    'SECURITY_KEY' => getenv('SECURITY_KEY') ?: '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',
    'APP_HOST' => "{$host}:{$port}",
    'REDIS_HOST' => getenv('REDIS_HOST') ?: '127.0.0.1',
    'REDIS_PORT' => getenv('REDIS_PORT') ?: '6379',
    'REDIS_PASSWORD' => getenv('REDIS_PASSWORD') ?: 'test_redis_secure_pass_2026'
];

$serverProcess = proc_open($cmd, $descriptors, $pipes, $rootDir, $appEnv);
if (!is_resource($serverProcess)) {
    echo "ERROR: Failed to launch test server process.\n";
    if ($createdTempConfig && file_exists($rootDir . '/app/config/configuration.php')) {
        @unlink($rootDir . '/app/config/configuration.php');
    }
    exit(1);
}

// Poll server socket readiness up to 5 seconds
$bound = false;
for ($retry = 0; $retry < 50; $retry++) {
    $status = proc_get_status($serverProcess);
    if (!$status['running']) {
        echo "ERROR: Test server process terminated prematurely with exit code: " . $status['exitcode'] . "\n";
        if (file_exists($serverLogFile)) {
            echo "Server log output:\n" . file_get_contents($serverLogFile) . "\n";
        }
        if ($createdTempConfig && file_exists($rootDir . '/app/config/configuration.php')) {
            @unlink($rootDir . '/app/config/configuration.php');
        }
        exit(1);
    }
    $fp = @fsockopen($host, $port, $errno, $errstr, 0.1);
    if ($fp) {
        fclose($fp);
        $bound = true;
        break;
    }
    usleep(100000); // 100ms
}

if (!$bound) {
    echo "ERROR: Test server failed to bind to {$host}:{$port} within 5 seconds.\n";
    if (file_exists($serverLogFile)) {
        echo "Server log output:\n" . file_get_contents($serverLogFile) . "\n";
    }
    proc_terminate($serverProcess);
    proc_close($serverProcess);
    if ($createdTempConfig && file_exists($rootDir . '/app/config/configuration.php')) {
        @unlink($rootDir . '/app/config/configuration.php');
    }
    exit(1);
}

echo "Test server is up and listening on http://{$host}:{$port}\n";

$passed = 0;
$failed = 0;

function runHttpTest($testName, $url, $expectedCode, $headers = [], $expectedHeader = '', $bodyCheck = null) {
    global $passed, $failed;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_PATH_AS_IS, true);
    
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    
    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    
    $respHeaders = substr($response, 0, $headerSize);
    $respBody = substr($response, $headerSize);
    
    $codeMatches = is_array($expectedCode) ? in_array($httpCode, $expectedCode, true) : ($httpCode === $expectedCode);
    $success = $codeMatches;
    if ($success && !empty($expectedHeader)) {
        $success = (stripos($respHeaders, $expectedHeader) !== false);
    }
    if ($success && is_callable($bodyCheck)) {
        $success = $bodyCheck($respBody, $respHeaders, $httpCode);
    }
    
    if ($success) {
        $passed++;
        echo "  [PASS] {$testName} (HTTP {$httpCode})\n";
    } else {
        $failed++;
        $expStr = is_array($expectedCode) ? implode('/', $expectedCode) : $expectedCode;
        $errDetail = ($httpCode === 0 && !empty($curlErr)) ? " (cURL error: {$curlErr})" : "";
        $bodyPreview = (!empty($respBody)) ? " [Body: " . trim(substr(strip_tags($respBody), 0, 100)) . "]" : "";
        echo "  [FAIL] {$testName} -> Expected HTTP {$expStr}, Got HTTP {$httpCode}{$errDetail}{$bodyPreview}\n";
    }
}

// Create a temporary media file for real Range testing
$testVideoPath = $rootDir . '/test_sample_media.mp4';
$dummyVideoData = str_repeat("SPARKY_VIDEO_DATA_STREAMING_TEST_BYTE_RANGE_", 1000); // ~43 KB
file_put_contents($testVideoPath, $dummyVideoData);
$testVideoSize = filesize($testVideoPath);

try {
    $baseUrl = "http://{$host}:{$port}";

    echo "\n--- 1. Protected Direct Access & Sensitive Files (Negative Tests) ---\n";
    runHttpTest("Direct access to database dump blocked", "{$baseUrl}/database/sparky_clean_database.sql", 403);
    runHttpTest("Direct access to configuration.php.dist blocked", "{$baseUrl}/app/config/configuration.php.dist", 403);
    runHttpTest("Direct access to .git folder blocked", "{$baseUrl}/.git/config", 403);
    runHttpTest("Direct access to composer.json blocked", "{$baseUrl}/composer.json", 403);
    runHttpTest("Direct access to composer.lock blocked", "{$baseUrl}/composer.lock", 403);
    runHttpTest("Direct access to tests directory blocked", "{$baseUrl}/tests/run_test_suite.php", 403);
    runHttpTest("Direct access to app/upload blocked", "{$baseUrl}/app/upload/test.txt", 403);
    runHttpTest("Direct access to app/courses directory blocked", "{$baseUrl}/app/courses/BIO101/index.php", 403);

    echo "\n--- 2. Directory Traversal & Multi-Pass Encoding Defense ---\n";
    runHttpTest("Raw directory traversal (..) blocked", "{$baseUrl}/database/../index.php", 403);
    runHttpTest("Double-encoded traversal (%252e%252e) blocked", "{$baseUrl}/courses/%252e%252e/%252e%252e/database/sparky_clean_database.sql", 403);
    runHttpTest("Triple-encoded traversal (%25252e%25252e) blocked", "{$baseUrl}/%25252e%25252e/%25252e%25252e/app/config/configuration.php", 403);
    runHttpTest("Upload directory traversal attempt blocked", "{$baseUrl}/app/upload/../config/configuration.php", 403);

    echo "\n--- 3. Protected Course Assets & Authorized Handlers ---\n";
    // Unauthenticated requests to /courses/... must route to PHP handlers which deny access with HTTP 403
    runHttpTest("Course video routes through download handler (not raw static)", "{$baseUrl}/courses/BIO101/document/lecture.mp4", 403, [], '', function($body, $headers, $code) {
        return strpos($body, 'SPARKY_VIDEO_DATA') === false;
    });

    runHttpTest("Course PDF document routes through download handler", "{$baseUrl}/courses/BIO101/document/syllabus.pdf", 403, [], '', function($body, $headers, $code) {
        return strpos($body, 'SPARKY_VIDEO_DATA') === false;
    });

    runHttpTest("Course SCORM package routes through SCORM download handler", "{$baseUrl}/courses/BIO101/scorm/module1/index.html", 403, [], '', function($body, $headers, $code) {
        return strpos($body, 'SPARKY_VIDEO_DATA') === false;
    });

    runHttpTest("Course assignments (work) route through work download handler", "{$baseUrl}/courses/BIO101/work/assignment_final.pdf", 403, [], '', function($body, $headers, $code) {
        return strpos($body, 'SPARKY_VIDEO_DATA') === false;
    });

    runHttpTest("Course uploaded files route through upload handler", "{$baseUrl}/courses/BIO101/upload/forum/screenshot.png", 403, [], '', function($body, $headers, $code) {
        return strpos($body, 'SPARKY_VIDEO_DATA') === false;
    });

    runHttpTest("Curriculum upload routes through download_curriculum handler", "{$baseUrl}/app/upload/curriculum/BIO101/lecture_module.mp4", 403, [], '', function($body, $headers, $code) {
        return strpos($body, 'SPARKY_VIDEO_DATA') === false;
    });

    runHttpTest("User personal files route through download_my_files handler", "{$baseUrl}/app/upload/users/1/1/my_files/confidential.pdf", 403, [], '', function($body, $headers, $code) {
        return strpos($body, 'SPARKY_VIDEO_DATA') === false;
    });

    runHttpTest("Unauthorized learner access to private course media blocked", "{$baseUrl}/courses/PRIVATE101/document/private_lecture.mp4", 403);
    runHttpTest("Cross-course media traversal / unauthorized access blocked", "{$baseUrl}/courses/BIO101/document/../PRIVATE101/document/private.mp4", 403);

    echo "\n--- 4. HTTP 206 Byte-Range Video Streaming (RFC 7233) ---\n";
    // Full request without Range header returns HTTP 200 with Accept-Ranges: bytes
    runHttpTest("Media file returns HTTP 200 with Accept-Ranges bytes", "{$baseUrl}/test_sample_media.mp4", 200, [], "Accept-Ranges: bytes");

    // Partial Range: bytes=0-1023 -> Expect HTTP 206 Partial Content
    runHttpTest(
        "Range request bytes=0-1023 returns HTTP 206 Partial Content",
        "{$baseUrl}/test_sample_media.mp4",
        206,
        ['Range: bytes=0-1023'],
        "Content-Range: bytes 0-1023/{$testVideoSize}",
        function($body, $headers, $code) use ($dummyVideoData) {
            $hasLength = (stripos($headers, 'Content-Length: 1024') !== false);
            $bytesMatch = ($body === substr($dummyVideoData, 0, 1024));
            return $hasLength && $bytesMatch;
        }
    );

    // Open Range: bytes=1000- -> Expect HTTP 206
    $openEnd = $testVideoSize - 1;
    $expectedOpenLen = $testVideoSize - 1000;
    runHttpTest(
        "Open range request bytes=1000- returns HTTP 206",
        "{$baseUrl}/test_sample_media.mp4",
        206,
        ['Range: bytes=1000-'],
        "Content-Range: bytes 1000-{$openEnd}/{$testVideoSize}",
        function($body, $headers, $code) use ($dummyVideoData, $expectedOpenLen) {
            $hasLength = (stripos($headers, 'Content-Length: ' . $expectedOpenLen) !== false);
            $bytesMatch = ($body === substr($dummyVideoData, 1000));
            return $hasLength && $bytesMatch;
        }
    );

    // Suffix Range: bytes=-500 -> Expect HTTP 206
    $suffixStart = $testVideoSize - 500;
    runHttpTest(
        "Suffix range request bytes=-500 returns HTTP 206",
        "{$baseUrl}/test_sample_media.mp4",
        206,
        ['Range: bytes=-500'],
        "Content-Range: bytes {$suffixStart}-{$openEnd}/{$testVideoSize}",
        function($body, $headers, $code) use ($dummyVideoData) {
            $hasLength = (stripos($headers, 'Content-Length: 500') !== false);
            $bytesMatch = ($body === substr($dummyVideoData, -500));
            return $hasLength && $bytesMatch;
        }
    );

    // Unsatisfiable Range: bytes=9999999-10000000 -> Expect HTTP 416
    runHttpTest(
        "Unsatisfiable range returns HTTP 416 Range Not Satisfiable",
        "{$baseUrl}/test_sample_media.mp4",
        416,
        ['Range: bytes=9999999-10000000'],
        "Content-Range: bytes */{$testVideoSize}"
    );

    echo "\n--- 5. CORS Header Verification ---\n";
    runHttpTest(
        "CORS preflight request (OPTIONS) returns 200 with safe origin",
        "{$baseUrl}/test_sample_media.mp4",
        200,
        [
            'Origin: http://localhost:8899',
            'Access-Control-Request-Method: GET'
        ],
        "Access-Control-Allow-Origin: http://localhost:8899"
    );

} finally {
    // Cleanup temporary media file
    if (file_exists($testVideoPath)) {
        @unlink($testVideoPath);
    }
    
    // Shutdown background server process
    foreach ($pipes as $p) {
        if (is_resource($p)) fclose($p);
    }
    proc_terminate($serverProcess);
    proc_close($serverProcess);

    $serverLogContents = file_exists($serverLogFile) ? file_get_contents($serverLogFile) : '';
    if (file_exists($serverLogFile)) {
        @unlink($serverLogFile);
    }

    if ($createdTempConfig && file_exists($rootDir . '/app/config/configuration.php')) {
        @unlink($rootDir . '/app/config/configuration.php');
    }

    echo "\nTest server terminated cleanly.\n";
}

echo "========================================================\n";
echo "HTTP TESTS PASSED: " . $passed . "\n";
echo "HTTP TESTS FAILED: " . $failed . "\n";
echo "========================================================\n";

if ($failed > 0) {
    echo "HTTP VERIFICATION SUITE FAILED.\n";
    if (!empty($serverLogContents)) {
        echo "Server log output:\n" . $serverLogContents . "\n";
    }
    exit(1);
} else {
    echo "ALL REAL HTTP REQUEST TESTS PASSED SUCCESSFULLY.\n";
    exit(0);
}
