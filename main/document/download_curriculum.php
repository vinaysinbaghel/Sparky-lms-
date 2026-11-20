<?php
/* For licensing terms, see /license.txt */

/**
 * Sparky LMS — Curriculum Media Download & Streaming Handler
 * Enforces course enrollment and access permissions before streaming or downloading curriculum assets.
 */

session_cache_limiter('none');
require_once __DIR__ . '/../inc/global-min.inc.php';

$courseCode = isset($_GET['course']) ? trim($_GET['course']) : (isset($_GET['cDir']) ? trim($_GET['cDir']) : '');
$fileName = isset($_GET['file']) ? trim($_GET['file']) : '';

// 1. Sanitize input parameters against traversal & injection
if (empty($courseCode) || empty($fileName) || strpos($fileName, '..') !== false || strpos($courseCode, '..') !== false) {
    http_response_code(403);
    echo "403 Forbidden: Invalid parameters.";
    exit;
}

$safeFileName = basename($fileName);
$courseInfo = api_get_course_info($courseCode);

if (empty($courseInfo)) {
    http_response_code(404);
    echo "404 Not Found: Course does not exist.";
    exit;
}

// 2. Authorization check: Platform Admin, Course Teacher, or Enrolled Student
$userId = api_get_user_id();
$isAllowed = false;

if (api_is_platform_admin()) {
    $isAllowed = true;
} elseif ($userId > 0) {
    // Check if user is subscribed to course or is course teacher
    if (CourseManager::is_user_subscribed_in_course($userId, $courseInfo['code']) ||
        CourseManager::is_course_teacher($userId, $courseInfo['code'])) {
        $isAllowed = true;
    }
}

if (!$isAllowed) {
    http_response_code(403);
    echo "403 Forbidden: Active course enrollment is required to access this curriculum asset.";
    exit;
}

// 3. Resolve file and verify canonical path containment
$sysAppPath = api_get_path(SYS_APP_PATH);
$curriculumDir = realpath($sysAppPath . 'upload/curriculum/' . $courseInfo['code']);
$filePath = $curriculumDir ? realpath($curriculumDir . DIRECTORY_SEPARATOR . $safeFileName) : false;

if (!$filePath || !is_file($filePath) || strpos($filePath, $curriculumDir) !== 0) {
    http_response_code(404);
    echo "404 Not Found: Curriculum asset not found.";
    exit;
}

// 4. Stream or download with HTTP 206 Partial Content support for videos/audio
$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$mediaMimes = [
    'mp4'  => 'video/mp4',
    'webm' => 'video/webm',
    'mkv'  => 'video/x-matroska',
    'mov'  => 'video/quicktime',
    'ogg'  => 'video/ogg',
    'mp3'  => 'audio/mpeg',
    'wav'  => 'audio/wav',
    'm4a'  => 'audio/mp4',
    'pdf'  => 'application/pdf',
];

if (isset($mediaMimes[$ext])) {
    $mime = $mediaMimes[$ext];
    $size = filesize($filePath);
    $fp = @fopen($filePath, 'rb');
    if (!$fp) {
        http_response_code(500);
        exit;
    }

    $start = 0;
    $end = $size - 1;

    header("Content-Type: $mime");
    header("Accept-Ranges: bytes");
    header("Cache-Control: private, max-age=3600");
    header("X-Content-Type-Options: nosniff");

    if (isset($_SERVER['HTTP_RANGE'])) {
        $range = trim($_SERVER['HTTP_RANGE']);
        if (preg_match('/^bytes=\s*(\d*)-(\d*)$/i', $range, $matches)) {
            $rangeStart = $matches[1];
            $rangeEnd = $matches[2];

            if ($rangeStart === '' && $rangeEnd !== '') {
                $suffix = (int)$rangeEnd;
                $start = max(0, $size - $suffix);
                $end = $size - 1;
            } elseif ($rangeStart !== '' && $rangeEnd === '') {
                $start = (int)$rangeStart;
                $end = $size - 1;
            } elseif ($rangeStart !== '' && $rangeEnd !== '') {
                $start = (int)$rangeStart;
                $end = min((int)$rangeEnd, $size - 1);
            }

            if ($start > $end || $start >= $size) {
                http_response_code(416);
                header("Content-Range: bytes */$size");
                fclose($fp);
                exit;
            }

            http_response_code(206);
            $length = ($end - $start) + 1;
            header("Content-Range: bytes $start-$end/$size");
            header("Content-Length: $length");

            fseek($fp, $start);
            $buffer = 1024 * 64;
            $remaining = $length;
            while (!feof($fp) && $remaining > 0 && connection_status() == 0) {
                $read = min($buffer, $remaining);
                echo fread($fp, $read);
                flush();
                $remaining -= $read;
            }
            fclose($fp);
            exit;
        }
    }

    header("Content-Length: $size");
    fpassthru($fp);
    fclose($fp);
    exit;
}

// Fallback to standard DocumentManager download
DocumentManager::file_send_for_download($filePath, false, $safeFileName);
exit;
