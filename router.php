<?php
/**
 * Sparky Development Server Router
 * Handles URL rewrites matching .htaccess and nginx rules for Courses, SCORM, Certificates, and
 * HTTP 206 Partial Content (Byte-Range requests) for Video, Audio, and PDF streaming.
 */

$rawUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Iterative URL decoding to defeat multiple URL-encoding attacks (%252e%252e -> %2e%2e -> ..)
$decodedUri = $rawUri;
$maxDecodes = 5;
while ($maxDecodes-- > 0 && strpos($decodedUri, '%') !== false) {
    $next = rawurldecode($decodedUri);
    if ($next === $decodedUri) break;
    $decodedUri = $next;
}
$uri = $decodedUri;

// Reject any null bytes or carriage returns in URI
if (strpos($rawUri, chr(0)) !== false || strpos($uri, chr(0)) !== false) {
    http_response_code(403);
    echo "403 Forbidden";
    exit;
}

// Security: Comprehensive protection against directory traversal, sensitive folders, and config leaks
$cleanUri = '/' . ltrim(str_replace('\\', '/', $uri), '/');
$uriExt = strtolower(pathinfo($cleanUri, PATHINFO_EXTENSION));
$uriBase = basename($cleanUri);

$blockedPrefixes = [
    '/.git', '/.github', '/database', '/scratch', '/bin', '/tests',
    '/app/config', '/app/logs', '/app/cache', '/app/courses'
];

$blockedExtensions = [
    'sql', 'bat', 'sh', 'yml', 'yaml', 'dist', 'lock', 'env', 'log', 'ini', 'md'
];

$blockedFiles = [
    'composer.json', 'composer.lock', 'bower.json', 'web.config',
    '.gitignore', '.gitattributes', '.editorconfig', '.htaccess',
    '.travis.yml', '.codeclimate.yml', '.scrutinizer.yml',
    'push-to-github.bat', 'start-server.bat'
];

$isForbidden = false;

// Directory traversal check (both on decoded URI and raw URI)
if (strpos($cleanUri, '..') !== false || strpos($rawUri, '..') !== false || preg_match('#(^|/)\.\.(/|$)#', $cleanUri)) {
    $isForbidden = true;
}

// Sensitive directory access
foreach ($blockedPrefixes as $prefix) {
    if (strpos($cleanUri, $prefix . '/') === 0 || $cleanUri === $prefix) {
        $isForbidden = true;
        break;
    }
}

// Sensitive extensions & files
if (in_array($uriExt, $blockedExtensions, true) || in_array(strtolower($uriBase), $blockedFiles, true)) {
    $isForbidden = true;
}

// Prevent direct script execution from upload & cache directories
if (preg_match('#^/app/(upload|cache|logs)/.*\.ph(p[34578]?|t|tml|ar)$#i', $cleanUri)) {
    $isForbidden = true;
}

if ($isForbidden) {
    http_response_code(403);
    echo "403 Forbidden";
    exit;
}

// Security: Validate CORS Origin (Avoid wildcard * on protected resources)
function applyCorsHeaders() {
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
    if (!empty($origin)) {
        $allowedHosts = ['localhost', '127.0.0.1'];
        if (!empty($_SERVER['HTTP_HOST'])) {
            $allowedHosts[] = strtolower(explode(':', $_SERVER['HTTP_HOST'])[0]);
        }
        $appHost = getenv('APP_HOST');
        if (!empty($appHost)) {
            $allowedHosts[] = strtolower(explode(':', $appHost)[0]);
        }
        $originHost = strtolower(parse_url($origin, PHP_URL_HOST));
        if (in_array($originHost, $allowedHosts, true)) {
            header("Access-Control-Allow-Origin: " . $origin);
            header("Access-Control-Allow-Credentials: true");
            header("Vary: Origin");
        }
    }
}

// Handle preflight CORS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    applyCorsHeaders();
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Range, Content-Type, Authorization, X-Requested-With, Origin, Accept");
    header("Access-Control-Max-Age: 86400");
    http_response_code(200);
    exit;
}

$docRoot = rtrim(str_replace('\\', '/', __DIR__), '/');
$filePath = $docRoot . $uri;

if (!is_file($filePath) && !empty($_SERVER['DOCUMENT_ROOT'])) {
    $altRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
    if (is_file($altRoot . $uri)) {
        $filePath = $altRoot . $uri;
    }
}

// ==========================================
// Sparky URL Rewrites (Matching .htaccess)
// ==========================================

// Course home redirection: /courses/<cDir>/ or /courses/<cDir>/index.php
if (preg_match('#^/courses/([^/]+)(?:/index\.php|/)?$#', $uri, $matches)) {
    $cDir = $matches[1];
    $_GET['cDir'] = $cDir;
    $_REQUEST['cDir'] = $cDir;
    $_SERVER['SCRIPT_NAME'] = '/main/course_home/course_home.php';
    $_SERVER['PHP_SELF'] = '/main/course_home/course_home.php';
    $_SERVER['SCRIPT_FILENAME'] = $docRoot . '/main/course_home/course_home.php';
    chdir($docRoot . '/main/course_home');
    require $docRoot . '/main/course_home/course_home.php';
    exit;
}

// Course images: /courses/<cDir>/course-pic...
if (preg_match('#^/courses/([^/]+)/course-pic(85x85|-email-pic-cropped|-email-pic)?\.png$#', $uri, $matches)) {
    $code = $matches[1];
    $variant = isset($matches[2]) ? $matches[2] : '';
    $imageType = 'course_image_large_source';
    if ($variant === '85x85') {
        $imageType = 'course_image_source';
    } elseif ($variant === '-email-pic-cropped') {
        $imageType = 'course_email_image_source';
    } elseif ($variant === '-email-pic') {
        $imageType = 'course_email_image_large_source';
    }
    $_GET['a'] = 'get_course_image';
    $_GET['code'] = $code;
    $_GET['image'] = $imageType;
    $_REQUEST['a'] = 'get_course_image';
    $_REQUEST['code'] = $code;
    $_REQUEST['image'] = $imageType;
    $_SERVER['SCRIPT_NAME'] = '/main/inc/ajax/course.ajax.php';
    $_SERVER['PHP_SELF'] = '/main/inc/ajax/course.ajax.php';
    $_SERVER['SCRIPT_FILENAME'] = $docRoot . '/main/inc/ajax/course.ajax.php';
    chdir($docRoot . '/main/inc/ajax');
    require $docRoot . '/main/inc/ajax/course.ajax.php';
    exit;
}

// SCORM content & assets (Strictly routed through download_scorm.php to verify enrollment & permissions)
if (preg_match('#^/courses/([^/]+)/scorm/(.*)$#', $uri, $matches)) {
    $_GET['doc_url'] = '/' . $matches[2];
    $_GET['cDir'] = $matches[1];
    $_REQUEST['doc_url'] = '/' . $matches[2];
    $_REQUEST['cDir'] = $matches[1];
    $_SERVER['SCRIPT_NAME'] = '/main/document/download_scorm.php';
    $_SERVER['PHP_SELF'] = '/main/document/download_scorm.php';
    $_SERVER['SCRIPT_FILENAME'] = $docRoot . '/main/document/download_scorm.php';
    chdir($docRoot . '/main/document');
    require $docRoot . '/main/document/download_scorm.php';
    exit;
}

// Course document downloads & certificates (Strictly routed through download.php)
if (preg_match('#^/courses/([^/]+)/document/(.*)$#', $uri, $matches)) {
    $_GET['doc_url'] = '/' . $matches[2];
    $_GET['cDir'] = $matches[1];
    $_REQUEST['doc_url'] = '/' . $matches[2];
    $_REQUEST['cDir'] = $matches[1];
    $_SERVER['SCRIPT_NAME'] = '/main/document/download.php';
    $_SERVER['PHP_SELF'] = '/main/document/download.php';
    $_SERVER['SCRIPT_FILENAME'] = $docRoot . '/main/document/download.php';
    chdir($docRoot . '/main/document');
    require $docRoot . '/main/document/download.php';
    exit;
}

// Course upload files
if (preg_match('#^/courses/([^/]+)/upload/([^/]+)/(.*)$#', $uri, $matches)) {
    $_GET['code'] = $matches[1];
    $_GET['type'] = $matches[2];
    $_GET['file'] = $matches[3];
    $_REQUEST['code'] = $matches[1];
    $_REQUEST['type'] = $matches[2];
    $_REQUEST['file'] = $matches[3];
    $_SERVER['SCRIPT_NAME'] = '/main/document/download_uploaded_files.php';
    $_SERVER['PHP_SELF'] = '/main/document/download_uploaded_files.php';
    $_SERVER['SCRIPT_FILENAME'] = $docRoot . '/main/document/download_uploaded_files.php';
    chdir($docRoot . '/main/document');
    require $docRoot . '/main/document/download_uploaded_files.php';
    exit;
}

// Course work folder
if (preg_match('#^/courses/([^/]+)/work/(.*)$#', $uri, $matches)) {
    $_GET['file'] = 'work/' . $matches[2];
    $_GET['cDir'] = $matches[1];
    $_REQUEST['file'] = 'work/' . $matches[2];
    $_REQUEST['cDir'] = $matches[1];
    $_SERVER['SCRIPT_NAME'] = '/main/work/download.php';
    $_SERVER['PHP_SELF'] = '/main/work/download.php';
    $_SERVER['SCRIPT_FILENAME'] = $docRoot . '/main/work/download.php';
    chdir($docRoot . '/main/work');
    require $docRoot . '/main/work/download.php';
    exit;
}

// Course protected files: Route through document download handler to enforce LMS authorization
if (preg_match('#^/courses/([^/]+)/(.*)$#', $uri, $matches)) {
    $cDir = $matches[1];
    $relFile = $matches[2];

    // All course files MUST be protected through download handler
    $_GET['doc_url'] = '/' . ltrim($relFile, '/');
    $_GET['cDir'] = $cDir;
    $_REQUEST['doc_url'] = '/' . ltrim($relFile, '/');
    $_REQUEST['cDir'] = $cDir;
    $_SERVER['SCRIPT_NAME'] = '/main/document/download.php';
    $_SERVER['PHP_SELF'] = '/main/document/download.php';
    $_SERVER['SCRIPT_FILENAME'] = $docRoot . '/main/document/download.php';
    chdir($docRoot . '/main/document');
    require $docRoot . '/main/document/download.php';
    exit;
}

// Certificates clean URL: /certificates/?id=123
if (preg_match('#^/certificates/?$#', $uri)) {
    $_SERVER['SCRIPT_NAME'] = '/certificates/index.php';
    $_SERVER['PHP_SELF'] = '/certificates/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $docRoot . '/certificates/index.php';
    chdir($docRoot . '/certificates');
    require $docRoot . '/certificates/index.php';
    exit;
}

// Session about: /session/<id>/about
if (preg_match('#^/session/(\d+)/about/?$#', $uri, $matches)) {
    $_GET['session_id'] = $matches[1];
    $_REQUEST['session_id'] = $matches[1];
    $_SERVER['SCRIPT_NAME'] = '/main/session/about.php';
    $_SERVER['PHP_SELF'] = '/main/session/about.php';
    $_SERVER['SCRIPT_FILENAME'] = $docRoot . '/main/session/about.php';
    chdir($docRoot . '/main/session');
    require $docRoot . '/main/session/about.php';
    exit;
}

// Course about: /course/<id>/about
if (preg_match('#^/course/(\d+)/about/?$#', $uri, $matches)) {
    $_GET['course_id'] = $matches[1];
    $_REQUEST['course_id'] = $matches[1];
    $_SERVER['SCRIPT_NAME'] = '/main/course_info/about.php';
    $_SERVER['PHP_SELF'] = '/main/course_info/about.php';
    $_SERVER['SCRIPT_FILENAME'] = $docRoot . '/main/course_info/about.php';
    chdir($docRoot . '/main/course_info');
    require $docRoot . '/main/course_info/about.php';
    exit;
}

// Media types that require HTTP Range support (Only evaluated AFTER protected course routes)
$mediaMimes = [
    'mp4'  => 'video/mp4',
    'webm' => 'video/webm',
    'mkv'  => 'video/x-matroska',
    'mov'  => 'video/quicktime',
    'ogg'  => 'video/ogg',
    'ogv'  => 'video/ogg',
    'mp3'  => 'audio/mpeg',
    'wav'  => 'audio/wav',
    'm4a'  => 'audio/mp4',
    'aac'  => 'audio/aac',
    'flac' => 'audio/flac',
    'pdf'  => 'application/pdf',
];

$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

if (is_file($filePath) && isset($mediaMimes[$ext])) {
    serveMediaWithRangeSupport($filePath, $mediaMimes[$ext]);
    exit;
}

// For all other requests (PHP scripts, CSS, JS, images, directories), let PHP built-in server handle normally
return false;

/**
 * Validates that a file's canonical realpath is strictly within document root and not in blocked directories.
 */
function assertSafePath($file) {
    $realFile = realpath($file);
    $realDocRoot = realpath(__DIR__);
    if ($realFile === false || $realDocRoot === false) {
        http_response_code(404);
        echo "404 Not Found";
        exit;
    }
    $normalizedFile = str_replace('\\', '/', $realFile);
    $normalizedRoot = str_replace('\\', '/', $realDocRoot);
    if (strpos($normalizedFile, $normalizedRoot . '/') !== 0 && $normalizedFile !== $normalizedRoot) {
        http_response_code(403);
        echo "403 Forbidden";
        exit;
    }
    $relPath = substr($normalizedFile, strlen($normalizedRoot));
    $blocked = ['/app/config', '/app/courses', '/app/logs', '/app/cache', '/.git', '/.github', '/database', '/tests', '/scratch', '/bin'];
    foreach ($blocked as $b) {
        if (strpos($relPath, $b . '/') === 0 || $relPath === $b) {
            http_response_code(403);
            echo "403 Forbidden";
            exit;
        }
    }
}

function serveStaticFile($file) {
    assertSafePath($file);
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $contentTypes = [
        'js'    => 'application/javascript',
        'css'   => 'text/css',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'svg'   => 'image/svg+xml',
        'ico'   => 'image/x-icon',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
        'eot'   => 'application/vnd.ms-fontobject',
        'html'  => 'text/html',
        'htm'   => 'text/html',
        'json'  => 'application/json',
        'xml'   => 'application/xml',
    ];
    $contentType = isset($contentTypes[$ext]) ? $contentTypes[$ext] : (mime_content_type($file) ?: 'application/octet-stream');
    header('Content-Type: ' . $contentType);
    header('Content-Length: ' . filesize($file));
    header('Cache-Control: public, max-age=86400');
    readfile($file);
    exit;
}

function serveMediaWithRangeSupport($file, $mimeType) {
    assertSafePath($file);
    $fileSize = filesize($file);
    $rangeHeader = isset($_SERVER['HTTP_RANGE']) ? trim($_SERVER['HTTP_RANGE']) : '';

    // Standard CORS and Cache headers
    applyCorsHeaders();
    header("Access-Control-Allow-Methods: GET, HEAD, OPTIONS");
    header("Access-Control-Allow-Headers: Range, Content-Type, Accept");
    header("Access-Control-Expose-Headers: Content-Range, Content-Length, Accept-Ranges");
    header("Accept-Ranges: bytes");
    header("Content-Type: " . $mimeType);
    header("Cache-Control: public, max-age=3600");

    if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
        header("Content-Length: " . $fileSize);
        exit;
    }

    $start = 0;
    $end = $fileSize - 1;
    $isRange = false;

    if (!empty($rangeHeader) && preg_match('/^bytes=\s*(\d*)\s*-\s*(\d*)$/i', $rangeHeader, $matches)) {
        $rawStart = $matches[1];
        $rawEnd = $matches[2];

        if ($rawStart === '' && $rawEnd !== '') {
            // Suffix range: bytes=-500
            $start = $fileSize - (int)$rawEnd;
            $end = $fileSize - 1;
        } elseif ($rawStart !== '' && $rawEnd === '') {
            // Open range: bytes=100-
            $start = (int)$rawStart;
            $end = $fileSize - 1;
        } elseif ($rawStart !== '' && $rawEnd !== '') {
            // Explicit range: bytes=100-200
            $start = (int)$rawStart;
            $end = (int)$rawEnd;
        }

        $start = max(0, $start);
        $end = min($fileSize - 1, $end);

        if ($start > $end || $start >= $fileSize) {
            header("HTTP/1.1 416 Range Not Satisfiable");
            header("Content-Range: bytes */" . $fileSize);
            exit;
        }

        $isRange = true;
    }

    $length = $end - $start + 1;

    if ($isRange) {
        http_response_code(206);
        header("HTTP/1.1 206 Partial Content");
        header("Content-Range: bytes " . $start . "-" . $end . "/" . $fileSize);
        header("Content-Length: " . $length);
    } else {
        http_response_code(200);
        header("HTTP/1.1 200 OK");
        header("Content-Length: " . $fileSize);
    }

    @ini_set('zlib.output_compression', 'Off');

    $fp = @fopen($file, 'rb');
    if (!$fp) {
        http_response_code(500);
        exit;
    }

    if ($start > 0) {
        fseek($fp, $start);
    }

    // Clean any prior output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }

    $chunkSize = 64 * 1024; // 64 KB buffer
    $bytesRemaining = $length;

    while ($bytesRemaining > 0 && !feof($fp)) {
        if (connection_aborted()) {
            break;
        }
        $readBytes = min($bytesRemaining, $chunkSize);
        $buffer = fread($fp, $readBytes);
        if ($buffer === false || $buffer === '') {
            break;
        }
        echo $buffer;
        flush();
        $bytesRemaining -= strlen($buffer);
    }

    fclose($fp);
    exit;
}
