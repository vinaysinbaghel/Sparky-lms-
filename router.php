<?php
/**
 * Chamilo Development Server Router
 * Handles HTTP 206 Partial Content (Byte-Range requests) for Video, Audio, and PDF streaming.
 * Required for media timeline seeking/scrubbing and HTML5 video player controls.
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rawurldecode($uri);

// Security: Block directory traversal and sensitive directories from direct web access
if (
    strpos($uri, '..') !== false ||
    strpos($uri, '/.git') === 0 ||
    strpos($uri, '/scratch/') === 0 ||
    strpos($uri, '/app/config/') === 0
) {
    http_response_code(403);
    echo "403 Forbidden";
    exit;
}

// Handle preflight CORS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
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

// Media types that require HTTP Range support
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

function serveMediaWithRangeSupport($file, $mimeType) {
    $fileSize = filesize($file);
    $rangeHeader = isset($_SERVER['HTTP_RANGE']) ? trim($_SERVER['HTTP_RANGE']) : '';

    // Standard CORS and Cache headers
    header("Access-Control-Allow-Origin: *");
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