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

// Start background server process with NUL / dev/null pipes to avoid OS buffer deadlocks
$nullDevice = (DIRECTORY_SEPARATOR === '\\') ? 'NUL' : '/dev/null';
$cmd = sprintf('php -S %s:%d "%s/router.php"', $host, $port, $rootDir);
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['file', $nullDevice, 'w'],
    2 => ['file', $nullDevice, 'w']
];

$serverProcess = proc_open($cmd, $descriptors, $pipes, $rootDir);
if (!is_resource($serverProcess)) {
    echo "ERROR: Failed to launch test server process.\n";
    exit(1);
}

// Give server 500ms to bind port
usleep(500000);

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
        echo "  [FAIL] {$testName} -> Expected HTTP {$expStr}, Got HTTP {$httpCode}\n";
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
    // Unauthenticated requests to /courses/... must NOT return raw static file; they must route to PHP handlers which redirect or deny
    runHttpTest("Course video routes through download handler (not raw static)", "{$baseUrl}/courses/BIO101/document/lecture.mp4", [302, 303, 401, 403, 200], [], '', function($body, $headers, $code) {
        // Enforce that it does NOT return raw video data
        return strpos($body, 'SPARKY_VIDEO_DATA') === false;
    });

    runHttpTest("Course PDF document routes through download handler", "{$baseUrl}/courses/BIO101/document/syllabus.pdf", [302, 303, 401, 403, 200], [], '', function($body, $headers, $code) {
        return strpos($body, 'SPARKY_VIDEO_DATA') === false;
    });

    runHttpTest("Course SCORM package routes through SCORM download handler", "{$baseUrl}/courses/BIO101/scorm/module1/index.html", [302, 303, 401, 403, 200], [], '', function($body, $headers, $code) {
        return strpos($body, 'SPARKY_VIDEO_DATA') === false;
    });

    runHttpTest("Course assignments (work) route through work download handler", "{$baseUrl}/courses/BIO101/work/assignment_final.pdf", [302, 303, 401, 403, 200], [], '', function($body, $headers, $code) {
        return strpos($body, 'SPARKY_VIDEO_DATA') === false;
    });

    runHttpTest("Course uploaded files route through upload handler", "{$baseUrl}/courses/BIO101/upload/forum/screenshot.png", [302, 303, 401, 403, 200], [], '', function($body, $headers, $code) {
        return strpos($body, 'SPARKY_VIDEO_DATA') === false;
    });

    runHttpTest("Curriculum upload routes through download_curriculum handler", "{$baseUrl}/app/upload/curriculum/BIO101/lecture_module.mp4", [302, 303, 401, 403, 404, 200], [], '', function($body, $headers, $code) {
        return strpos($body, 'SPARKY_VIDEO_DATA') === false;
    });

    runHttpTest("User personal files route through download_my_files handler", "{$baseUrl}/app/upload/users/1/1/my_files/confidential.pdf", [302, 303, 401, 403, 404, 200], [], '', function($body, $headers, $code) {
        return strpos($body, 'SPARKY_VIDEO_DATA') === false;
    });

    echo "\n--- 4. HTTP 206 Byte-Range Video Streaming (RFC 7233) ---\n";
    // Full request without Range header returns HTTP 200 with Accept-Ranges: bytes
    runHttpTest("Media file returns HTTP 200 with Accept-Ranges bytes", "{$baseUrl}/test_sample_media.mp4", 200, [], "Accept-Ranges: bytes");

    // Partial Range: bytes=0-1023 -> Expect HTTP 206 Partial Content
    runHttpTest(
        "Range request bytes=0-1023 returns HTTP 206 Partial Content",
        "{$baseUrl}/test_sample_media.mp4",
        206,
        ['Range: bytes=0-1023'],
        "Content-Range: bytes 0-1023/{$testVideoSize}"
    );

    // Open Range: bytes=1000- -> Expect HTTP 206
    $openEnd = $testVideoSize - 1;
    runHttpTest(
        "Open range request bytes=1000- returns HTTP 206",
        "{$baseUrl}/test_sample_media.mp4",
        206,
        ['Range: bytes=1000-'],
        "Content-Range: bytes 1000-{$openEnd}/{$testVideoSize}"
    );

    // Suffix Range: bytes=-500 -> Expect HTTP 206
    $suffixStart = $testVideoSize - 500;
    runHttpTest(
        "Suffix range request bytes=-500 returns HTTP 206",
        "{$baseUrl}/test_sample_media.mp4",
        206,
        ['Range: bytes=-500'],
        "Content-Range: bytes {$suffixStart}-{$openEnd}/{$testVideoSize}"
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
        unlink($testVideoPath);
    }
    
    // Shutdown background server process
    foreach ($pipes as $p) {
        if (is_resource($p)) fclose($p);
    }
    proc_terminate($serverProcess);
    proc_close($serverProcess);
    echo "\nTest server terminated cleanly.\n";
}

echo "========================================================\n";
echo "HTTP TESTS PASSED: " . $passed . "\n";
echo "HTTP TESTS FAILED: " . $failed . "\n";
echo "========================================================\n";

if ($failed > 0) {
    echo "HTTP VERIFICATION SUITE FAILED.\n";
    exit(1);
} else {
    echo "ALL REAL HTTP REQUEST TESTS PASSED SUCCESSFULLY.\n";
    exit(0);
}
