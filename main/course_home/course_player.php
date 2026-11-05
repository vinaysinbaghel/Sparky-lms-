<?php
/* For licensing terms, see /license.txt */

/**
 * Sparky Modern Learner Course Player & Cinema Experience
 * Seamless learning interface for students & learners with multi-format study material support
 */

require_once __DIR__.'/../inc/global.inc.php';
require_once __DIR__.'/../inc/lib/course_curriculum.lib.php';
require_once __DIR__.'/../inc/lib/certificate_blueprint.lib.php';

$currentUserId = api_get_user_id();

// Robust course lookup without redirections
$courseCode = api_get_course_id();
if (empty($courseCode)) {
    $courseCode = isset($_GET['cidReq']) ? trim($_GET['cidReq']) : (isset($_GET['cDir']) ? trim($_GET['cDir']) : '');
}
if (empty($courseCode) && !empty($_SERVER['REQUEST_URI'])) {
    if (preg_match('#/courses/([^/?]+)#', $_SERVER['REQUEST_URI'], $m)) {
        $courseCode = $m[1];
    }
}

$courseInfo = !empty($courseCode) ? api_get_course_info($courseCode) : [];
if (empty($courseInfo) && !empty($courseCode)) {
    $courseInfo = api_get_course_info_by_id($courseCode);
}
if (empty($courseInfo) && !empty($courseCode)) {
    $cCodeByDir = CourseManager::getCourseCodeFromDirectory($courseCode);
    if (!empty($cCodeByDir)) {
        $courseCode = $cCodeByDir;
        $courseInfo = api_get_course_info($courseCode);
    }
}
if (empty($courseInfo)) {
    $firstCourse = CourseManager::get_courses_list();
    if (!empty($firstCourse)) {
        $courseCode = $firstCourse[0]['code'];
        $courseInfo = api_get_course_info($courseCode);
    }
}

$courseTitle = !empty($courseInfo['title']) ? $courseInfo['title'] : 'Course Player';
$canEditCourse = api_is_platform_admin() || api_is_course_admin() || CourseManager::is_course_teacher($currentUserId, $courseCode);

CourseCurriculum::initTables();
CertificateBlueprint::initTables();

$chapters = CourseCurriculum::getCurriculum($courseCode);

// Determine active lesson
$activeLessonId = isset($_GET['lesson_id']) ? (int)$_GET['lesson_id'] : 0;
$activeLesson = null;
$allLessons = [];

foreach ($chapters as $chap) {
    if (!empty($chap['lessons'])) {
        foreach ($chap['lessons'] as $les) {
            $allLessons[] = $les;
            if ($activeLessonId > 0 && (int)$les['id'] === $activeLessonId) {
                $activeLesson = $les;
            }
        }
    }
}

if (!$activeLesson && !empty($allLessons)) {
    $activeLesson = $allLessons[0];
    $activeLessonId = (int)$activeLesson['id'];
}

// Find previous and next lessons
$prevLesson = null;
$nextLesson = null;
$currentLessonIndex = 0;

if ($activeLesson) {
    foreach ($allLessons as $idx => $les) {
        if ($les['id'] == $activeLesson['id']) {
            $currentLessonIndex = $idx;
            if ($idx > 0) {
                $prevLesson = $allLessons[$idx - 1];
            }
            if ($idx < count($allLessons) - 1) {
                $nextLesson = $allLessons[$idx + 1];
            }
            break;
        }
    }
}

// Check Certificate Blueprint
$blueprint = CertificateBlueprint::getBlueprintForCourse($courseCode);

// Initialize & sync user completed lessons from DB
$dbCompletedLessonIds = CourseCurriculum::getUserCompletedLessons($currentUserId, $courseCode);
if (!isset($_SESSION['completed_lessons_'.$courseCode])) {
    $_SESSION['completed_lessons_'.$courseCode] = $dbCompletedLessonIds;
} else {
    $_SESSION['completed_lessons_'.$courseCode] = array_unique(array_merge($dbCompletedLessonIds, $_SESSION['completed_lessons_'.$courseCode]));
}

// Handle AJAX lesson completion toggle
if (isset($_POST['action']) && $_POST['action'] === 'toggle_complete') {
    header('Content-Type: application/json');
    $lId = isset($_POST['lesson_id']) ? (int)$_POST['lesson_id'] : 0;
    if ($lId > 0) {
        if (!isset($_SESSION['completed_lessons_'.$courseCode])) {
            $_SESSION['completed_lessons_'.$courseCode] = CourseCurriculum::getUserCompletedLessons($currentUserId, $courseCode);
        }
        if (in_array($lId, $_SESSION['completed_lessons_'.$courseCode])) {
            $_SESSION['completed_lessons_'.$courseCode] = array_values(array_diff($_SESSION['completed_lessons_'.$courseCode], [$lId]));
            $completed = false;
        } else {
            $_SESSION['completed_lessons_'.$courseCode][] = $lId;
            $completed = true;
        }
        CourseCurriculum::saveUserLessonCompletion($currentUserId, $courseCode, $lId, $completed ? 1 : 0);
        $completedCount = count($_SESSION['completed_lessons_'.$courseCode]);
        $totalCount = max(1, count($allLessons));
        $percent = round(($completedCount / $totalCount) * 100);
        echo json_encode([
            'success' => true,
            'completed' => $completed,
            'completed_count' => $completedCount,
            'total_count' => $totalCount,
            'percent' => $percent
        ]);
        exit;
    }
}

$completedLessonIds = isset($_SESSION['completed_lessons_'.$courseCode]) ? $_SESSION['completed_lessons_'.$courseCode] : [];
$completedCount = count($completedLessonIds);
$totalLessonsCount = max(1, count($allLessons));
$progressPercent = round(($completedCount / $totalLessonsCount) * 100);

Display::display_header($courseTitle);
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Alex+Brush&family=Allura&family=Cinzel:wght@500;700;900&family=Cinzel+Decorative:wght@700&family=Cormorant+Garamond:ital,wght@0,600;0,700;1,400&family=Dancing+Script:wght@600;700&family=Great+Vibes&family=Inter:wght@400;500;600;700;800&family=Merriweather:ital,wght@0,400;0,700;1,400&family=Montserrat:wght@400;600;700;800&family=Outfit:wght@400;600;700&family=Parisienne&family=Pinyon+Script&family=Playfair+Display:ital,wght@0,600;0,700;1,400&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

<style>
:root {
    --gf-primary: #6366F1;
    --gf-primary-hover: #4F46E5;
    --gf-navy: #0B1727;
    --gf-navy-dark: #070D18;
    --gf-bg: #F8FAFC;
    --gf-border: #E2E8F0;
    --gf-text-dark: #0F172A;
    --gf-text-muted: #64748B;
    --gf-green: #10B981;
}

body {
    background-color: #0B132B !important;
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    color: #F1F5F9;
    margin: 0;
    padding: 0;
    overflow-x: hidden;
}

/* Hide platform footer in course player */
footer, #footer, .footer-section, #cm-footer, .site-footer, #push, .sub-footer, .bottom-footer, #wrapper > footer, .copyright-section {
    display: none !important;
    margin: 0 !important;
    padding: 0 !important;
    height: 0 !important;
    border: none !important;
    visibility: hidden !important;
    opacity: 0 !important;
    pointer-events: none !important;
}

#cm-content, #cm-content > .container {
    width: 100% !important;
    max-width: 100% !important;
    padding: 0 !important;
    margin: 0 !important;
}

/* Top Dark Header */
.gf-player-topbar {
    background: #0B1727;
    border-bottom: 1px solid #1E293B;
    padding: 12px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 64px;
    box-sizing: border-box;
}

.gf-header-left {
    display: flex;
    align-items: center;
    gap: 16px;
}

.gf-back-btn {
    background: rgba(255, 255, 255, 0.08);
    color: #CBD5E1 !important;
    font-size: 13px;
    font-weight: 600;
    padding: 7px 14px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none !important;
    border: 1px solid #334155;
    transition: all 0.15s;
}

.gf-back-btn:hover {
    color: #FFFFFF !important;
    background: rgba(255, 255, 255, 0.14);
}

.gf-header-title h1 {
    font-size: 15.5px;
    font-weight: 700;
    color: #FFFFFF;
    margin: 0;
    line-height: 1.2;
}

.gf-header-title span {
    font-size: 11.5px;
    color: #94A3B8;
    font-weight: 500;
}

.gf-header-center {
    flex: 1;
    max-width: 360px;
    margin: 0 24px;
}

.gf-progress-box {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid #334155;
    border-radius: 8px;
    padding: 6px 14px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.gf-progress-label {
    display: flex;
    justify-content: space-between;
    font-size: 11.5px;
    font-weight: 600;
    color: #CBD5E1;
}

.gf-progress-bar-wrap {
    width: 100%;
    height: 6px;
    background: #334155;
    border-radius: 6px;
    overflow: hidden;
}

.gf-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #10B981 0%, #059669 100%);
    border-radius: 6px;
    transition: width 0.3s ease;
}

.gf-header-right {
    display: flex;
    align-items: center;
    gap: 12px;
}

.btn-admin-edit {
    background: rgba(99, 102, 241, 0.15);
    color: #818CF8 !important;
    border: 1px solid rgba(99, 102, 241, 0.4);
    font-size: 13px;
    font-weight: 600;
    padding: 6px 14px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none !important;
    transition: all 0.15s;
}

.btn-admin-edit:hover {
    background: var(--gf-primary);
    color: #FFFFFF !important;
    border-color: var(--gf-primary);
}

.btn-cert-preview {
    background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
    color: #FFFFFF !important;
    border: none;
    font-size: 13px;
    font-weight: 600;
    padding: 6px 14px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none !important;
    box-shadow: 0 2px 6px rgba(217, 119, 6, 0.3);
    transition: all 0.15s;
    cursor: pointer;
}

.btn-cert-preview:hover {
    opacity: 0.95;
    transform: translateY(-1px);
}

/* Main Layout */
.gf-player-workspace {
    display: flex;
    width: 100%;
    height: calc(100vh - 64px);
    background: #0B132B;
    overflow: hidden;
}

/* Left: Sidebar Playlist */
.gf-playlist-sidebar {
    width: 380px;
    min-width: 380px;
    background: #111C33;
    border-right: 1px solid #1E293B;
    display: flex;
    flex-direction: column;
    height: 100%;
}

.gf-playlist-header {
    padding: 16px 20px;
    border-bottom: 1px solid #1E293B;
    background: #0B1727;
}

.gf-playlist-header h3 {
    font-size: 14.5px;
    font-weight: 700;
    color: #F8FAFC;
    margin: 0 0 4px 0;
}

.gf-playlist-header span {
    font-size: 12px;
    color: #94A3B8;
}

.gf-playlist-content {
    flex: 1;
    overflow-y: auto;
    padding: 12px;
}

.gf-playlist-chapter {
    margin-bottom: 10px;
    border: 1px solid #1E293B;
    border-radius: 8px;
    background: #0A1124;
    overflow: hidden;
}

.gf-playlist-chapter-header {
    padding: 12px 14px;
    background: rgba(255, 255, 255, 0.03);
    font-size: 13px;
    font-weight: 700;
    color: #CBD5E1;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    user-select: none;
}

.gf-playlist-chapter-header:hover {
    background: rgba(255, 255, 255, 0.06);
}

.gf-playlist-lessons {
    padding: 4px 0;
}

.gf-playlist-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 14px;
    text-decoration: none !important;
    color: #94A3B8;
    font-size: 13px;
    border-left: 3px solid transparent;
    transition: all 0.15s;
}

.gf-playlist-item:hover {
    background: rgba(255, 255, 255, 0.05);
    color: #F1F5F9;
}

.gf-playlist-item.active {
    background: rgba(99, 102, 241, 0.18);
    border-left-color: var(--gf-primary);
    color: #FFFFFF;
    font-weight: 600;
}

.gf-playlist-item-left {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
    overflow: hidden;
}

.gf-check-icon {
    font-size: 14px;
    color: #475569;
    transition: all 0.2s;
}

.gf-check-icon.completed {
    color: #10B981;
}

.gf-playlist-item-title {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.gf-playlist-item-meta {
    font-size: 11px;
    color: #64748B;
    display: flex;
    align-items: center;
    gap: 6px;
    margin-left: 8px;
}

/* Center / Right: Main Stage */
.gf-player-stage {
    flex: 1;
    background: #070D1A;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
}

/* =======================================================
   CINEMA VIDEO PLAYER COMPONENT
   ======================================================= */
.gf-cinema-container {
    position: relative;
    width: 100%;
    max-width: 100%;
    background: #000000;
    aspect-ratio: 16 / 9;
    max-height: calc(100vh - 190px);
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.8);
    user-select: none;
}

.gf-cinema-video {
    width: 100%;
    height: 100%;
    object-fit: contain;
    background: #000000;
    cursor: pointer;
}

/* Big Center Ripple Play/Pause Button */
.gf-cinema-big-play {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background: rgba(15, 23, 42, 0.75);
    border: 2px solid rgba(255, 255, 255, 0.3);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #FFFFFF;
    font-size: 26px;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 10;
    opacity: 0.9;
}

.gf-cinema-big-play:hover {
    transform: translate(-50%, -50%) scale(1.1);
    background: var(--gf-primary);
    border-color: #FFFFFF;
    box-shadow: 0 0 30px rgba(99, 102, 241, 0.6);
}

.gf-cinema-container.playing .gf-cinema-big-play {
    opacity: 0;
    pointer-events: none;
    transform: translate(-50%, -50%) scale(0.8);
}

/* Custom Bottom Controls Bar */
.gf-cinema-controls {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(to top, rgba(0, 0, 0, 0.92) 0%, rgba(0, 0, 0, 0.6) 65%, transparent 100%);
    padding: 24px 20px 14px 20px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    transition: opacity 0.3s ease, transform 0.3s ease;
    z-index: 20;
}

.gf-cinema-container.playing.hide-controls .gf-cinema-controls {
    opacity: 0;
    transform: translateY(10px);
    pointer-events: none;
}

/* Scrubber Timeline */
.gf-scrubber-wrap {
    position: relative;
    width: 100%;
    height: 6px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 6px;
    cursor: pointer;
    transition: height 0.15s ease;
}

.gf-scrubber-wrap:hover {
    height: 10px;
}

.gf-scrubber-buffered {
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    background: rgba(255, 255, 255, 0.4);
    border-radius: 6px;
    width: 0%;
    pointer-events: none;
}

.gf-scrubber-progress {
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    background: linear-gradient(90deg, #6366F1 0%, #818CF8 100%);
    border-radius: 6px;
    width: 0%;
    pointer-events: none;
}

.gf-scrubber-thumb {
    position: absolute;
    top: 50%;
    right: -6px;
    transform: translateY(-50%) scale(0);
    width: 14px;
    height: 14px;
    background: #FFFFFF;
    border-radius: 50%;
    box-shadow: 0 0 6px rgba(0,0,0,0.5);
    transition: transform 0.15s ease;
    pointer-events: none;
}

.gf-scrubber-wrap:hover .gf-scrubber-thumb {
    transform: translateY(-50%) scale(1);
}

/* Control Buttons Row */
.gf-controls-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.gf-controls-left, .gf-controls-right {
    display: flex;
    align-items: center;
    gap: 14px;
}

.gf-ctrl-btn {
    background: transparent;
    border: none;
    color: #F8FAFC;
    font-size: 16px;
    cursor: pointer;
    padding: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    transition: color 0.15s, transform 0.1s;
}

.gf-ctrl-btn:hover {
    color: #818CF8;
    transform: scale(1.1);
}

.gf-ctrl-skip {
    font-size: 13.5px;
    font-weight: 700;
    color: #CBD5E1;
    display: inline-flex;
    align-items: center;
    gap: 3px;
}

.gf-ctrl-time {
    font-size: 12.5px;
    font-weight: 600;
    color: #E2E8F0;
    margin-left: 6px;
    font-variant-numeric: tabular-nums;
}

/* Volume Slider Group */
.gf-volume-group {
    display: flex;
    align-items: center;
    gap: 8px;
    position: relative;
}

.gf-volume-slider {
    width: 70px;
    height: 4px;
    -webkit-appearance: none;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 4px;
    outline: none;
    cursor: pointer;
}

.gf-volume-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #FFFFFF;
    cursor: pointer;
}

/* Speed Dropdown Menu */
.gf-speed-selector {
    background: rgba(255, 255, 255, 0.1);
    color: #FFFFFF;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
    padding: 4px 8px;
    cursor: pointer;
    outline: none;
}

.gf-speed-selector option {
    background: #0F172A;
    color: #FFFFFF;
}

/* =======================================================
   STUDY MATERIAL / DOCUMENT / AUDIO VIEWER COMPONENTS
   ======================================================= */
.gf-study-doc-container {
    background: #FFFFFF;
    border-radius: 12px;
    margin: 20px 30px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
    overflow: hidden;
    color: #1E293B;
}

.gf-study-doc-header {
    background: #F8FAFC;
    border-bottom: 1px solid #E2E8F0;
    padding: 16px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.gf-study-doc-info {
    display: flex;
    align-items: center;
    gap: 14px;
}

.gf-study-doc-icon {
    width: 44px;
    height: 44px;
    border-radius: 8px;
    background: #EEF2FF;
    color: #4F46E5;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.gf-study-doc-icon.pdf {
    background: #FEF2F2;
    color: #EF4444;
}

.gf-study-doc-icon.audio {
    background: #ECFDF5;
    color: #10B981;
}

.gf-study-doc-body {
    padding: 36px 48px;
    font-size: 15.5px;
    line-height: 1.8;
    color: #334155;
    min-height: 420px;
}

.gf-study-doc-body h1, .gf-study-doc-body h2 {
    color: #0F172A;
    font-weight: 800;
    margin-top: 0;
}

.gf-pdf-frame-modern {
    width: 100%;
    height: 720px;
    border: none;
    background: #525659;
}

/* Audio Player Card */
.gf-audio-card {
    background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%);
    border: 1px solid #334155;
    border-radius: 16px;
    padding: 32px 40px;
    margin: 40px auto;
    max-width: 680px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    display: flex;
    flex-direction: column;
    gap: 20px;
    align-items: center;
    text-align: center;
}

.gf-audio-disc {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366F1 0%, #A855F7 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
    color: #FFFFFF;
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
    animation: spinSlow 12s linear infinite;
    animation-play-state: paused;
}

.gf-audio-card.playing .gf-audio-disc {
    animation-play-state: running;
}

@keyframes spinSlow {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.gf-audio-player-element {
    width: 100%;
    border-radius: 30px;
    outline: none;
}

/* Bottom Lesson Controller & Info Bar */
.gf-player-controls-bar {
    background: #111C33;
    padding: 16px 30px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-top: 1px solid #1E293B;
    border-bottom: 1px solid #1E293B;
}

.gf-lesson-header-info h2 {
    font-size: 17.5px;
    font-weight: 700;
    color: #FFFFFF;
    margin: 0 0 4px 0;
}

.gf-lesson-header-info span {
    font-size: 12.5px;
    color: #94A3B8;
}

.gf-lesson-nav-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

.btn-lesson-nav {
    background: rgba(255, 255, 255, 0.08);
    color: #FFFFFF !important;
    border: 1px solid #334155;
    font-size: 13px;
    font-weight: 600;
    padding: 8px 16px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none !important;
    transition: all 0.15s;
}

.btn-lesson-nav:hover {
    background: rgba(255, 255, 255, 0.15);
}

.btn-lesson-nav.disabled {
    opacity: 0.35;
    pointer-events: none;
}

.btn-mark-complete {
    background: #10B981;
    color: #FFFFFF !important;
    border: none;
    font-size: 13px;
    font-weight: 700;
    padding: 8px 20px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    box-shadow: 0 2px 6px rgba(16, 185, 129, 0.3);
    transition: all 0.15s;
}

.btn-mark-complete:hover {
    background: #059669;
}

.btn-mark-complete.is-completed {
    background: #334155;
    color: #A7F3D0 !important;
    box-shadow: none;
}

.btn-download-mat {
    background: rgba(99, 102, 241, 0.15);
    color: #818CF8 !important;
    border: 1px solid rgba(99, 102, 241, 0.4);
    font-size: 12.5px;
    font-weight: 600;
    padding: 6px 14px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none !important;
    transition: all 0.15s;
}

.btn-download-mat:hover {
    background: var(--gf-primary);
    color: #FFFFFF !important;
}

/* Discussion / Additional Info Tabs */
.gf-player-extra-section {
    padding: 24px 30px;
    background: #070D1A;
    flex: 1;
}

.gf-tab-nav {
    display: flex;
    gap: 20px;
    border-bottom: 1px solid #1E293B;
    padding-bottom: 10px;
    margin-bottom: 20px;
}

.gf-tab-btn {
    background: none;
    border: none;
    color: #94A3B8;
    font-size: 13.5px;
    font-weight: 600;
    padding: 6px 12px;
    cursor: pointer;
    border-radius: 6px;
    transition: all 0.15s;
}

.gf-tab-btn.active {
    color: #FFFFFF;
    background: rgba(255, 255, 255, 0.08);
}
</style>

<!-- Top Dark Navigation Bar -->
<div class="gf-player-topbar">
    <div class="gf-header-left">
        <a href="<?php echo api_get_path(WEB_PATH); ?>user_portal.php" class="gf-back-btn">
            <i class="fa fa-arrow-left"></i> Dashboard
        </a>
        <div class="gf-header-title">
            <h1><?php echo htmlspecialchars($courseTitle); ?></h1>
            <span><?php echo !empty($courseInfo['category_code']) ? htmlspecialchars($courseInfo['category_code']) : 'Program Curriculum'; ?></span>
        </div>
    </div>

    <div class="gf-header-center">
        <div class="gf-progress-box">
            <div class="gf-progress-label">
                <span>Course Progress</span>
                <span id="progressText"><?php echo $progressPercent; ?>% (<?php echo $completedCount; ?>/<?php echo $totalLessonsCount; ?> Complete)</span>
            </div>
            <div class="gf-progress-bar-wrap">
                <div class="gf-progress-fill" id="progressBarFill" style="width: <?php echo $progressPercent; ?>%;"></div>
            </div>
        </div>
    </div>

    <div class="gf-header-right">
        <?php if ($blueprint): ?>
            <button type="button" class="btn-cert-preview" onclick="openCertificateModal()">
                <i class="fa fa-certificate"></i> View Certificate
            </button>
        <?php endif; ?>

        <?php if ($canEditCourse): ?>
            <a href="<?php echo api_get_path(WEB_PATH); ?>main/admin/course_curriculum.php?code=<?php echo $courseCode; ?>" class="btn-admin-edit">
                <i class="fa fa-sliders"></i> Curriculum Editor
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Player Layout -->
<div class="gf-player-workspace">

    <!-- LEFT: Playlist & Modules -->
    <aside class="gf-playlist-sidebar">
        <div class="gf-playlist-header">
            <h3><i class="fa fa-list-ul"></i> Course Content</h3>
            <span><?php echo count($chapters); ?> Modules • <?php echo count($allLessons); ?> Study Items</span>
        </div>

        <div class="gf-playlist-content">
            <?php foreach ($chapters as $cIdx => $chap): ?>
                <div class="gf-playlist-chapter">
                    <div class="gf-playlist-chapter-header" onclick="togglePlayerAccordion('chap_ply_<?php echo $chap['id']; ?>')">
                        <span>Module <?php echo ($cIdx + 1); ?>: <?php echo htmlspecialchars($chap['title']); ?></span>
                        <i class="fa fa-chevron-down" style="font-size: 11px;"></i>
                    </div>
                    <div class="gf-playlist-lessons" id="chap_ply_<?php echo $chap['id']; ?>">
                        <?php if (!empty($chap['lessons'])): ?>
                            <?php foreach ($chap['lessons'] as $lIdx => $les): 
                                $isActive = ($activeLesson && $activeLesson['id'] == $les['id']);
                                $isCompleted = in_array($les['id'], $completedLessonIds);
                                $typeIcon = 'fa-play-circle-o';
                                if ($les['lesson_type'] === 'pdf') $typeIcon = 'fa-file-pdf-o';
                                elseif ($les['lesson_type'] === 'audio') $typeIcon = 'fa-volume-up';
                                elseif ($les['lesson_type'] === 'document') $typeIcon = 'fa-file-text-o';
                            ?>
                                <a href="<?php echo api_get_path(WEB_PATH); ?>courses/<?php echo $courseCode; ?>/?lesson_id=<?php echo $les['id']; ?>" class="gf-playlist-item <?php echo $isActive ? 'active' : ''; ?>" id="playlist_item_<?php echo $les['id']; ?>">
                                    <div class="gf-playlist-item-left">
                                        <i class="fa fa-check-circle gf-check-icon <?php echo $isCompleted ? 'completed' : ''; ?>" id="check_icon_<?php echo $les['id']; ?>"></i>
                                        <span class="gf-playlist-item-title"><?php echo ($lIdx + 1); ?>. <?php echo htmlspecialchars($les['title']); ?></span>
                                    </div>
                                    <div class="gf-playlist-item-meta">
                                        <i class="fa <?php echo $typeIcon; ?>"></i>
                                        <?php if (!empty($les['duration'])): ?>
                                            <span><?php echo htmlspecialchars($les['duration']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted" style="padding: 10px 14px; font-size: 12px; margin: 0;">No study materials in this module.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </aside>

    <!-- RIGHT: Main Learning Stage -->
    <main class="gf-player-stage">
        <?php if ($activeLesson): ?>
            <!-- Media Stage (Cinema Video / PDF / Document / Audio) -->
            <?php if ($activeLesson['lesson_type'] === 'video'): ?>
                <?php 
                    $rawUrl = !empty($activeLesson['content_url']) ? CourseCurriculum::resolveMediaUrl($activeLesson['content_url']) : 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4';
                    $embedUrl = CourseCurriculum::formatVideoEmbedUrl($rawUrl);
                    $isEmbed = (strpos($embedUrl, 'youtube-nocookie.com') !== false || strpos($embedUrl, 'player.vimeo.com') !== false);
                ?>
                <?php if ($isEmbed): ?>
                    <div class="gf-cinema-container">
                        <iframe src="<?php echo htmlspecialchars($embedUrl); ?>" style="width: 100%; height: 100%; border: none;" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    </div>
                <?php else: ?>
                    <div class="gf-cinema-container" id="cinemaContainer">
                        <video class="gf-cinema-video skip" id="cinemaVideo" preload="metadata" playsinline>
                            <source src="<?php echo htmlspecialchars($rawUrl); ?>" type="video/mp4">
                            Your browser does not support the HTML5 cinema video player.
                        </video>

                        <!-- Big Center Play Button Overlay -->
                        <div class="gf-cinema-big-play" id="bigPlayBtn">
                            <i class="fa fa-play" id="bigPlayIcon" style="margin-left: 4px;"></i>
                        </div>

                        <!-- Sleek Bottom Video Controls -->
                        <div class="gf-cinema-controls" id="cinemaControls">
                            <!-- Scrubber Timeline -->
                            <div class="gf-scrubber-wrap" id="scrubberWrap">
                                <div class="gf-scrubber-buffered" id="scrubberBuffered"></div>
                                <div class="gf-scrubber-progress" id="scrubberProgress">
                                    <div class="gf-scrubber-thumb"></div>
                                </div>
                            </div>

                            <!-- Buttons Row -->
                            <div class="gf-controls-row">
                                <div class="gf-controls-left">
                                    <button type="button" class="gf-ctrl-btn" id="btnPlayPause" title="Play / Pause (Space)">
                                        <i class="fa fa-play" id="playPauseIcon"></i>
                                    </button>
                                    <button type="button" class="gf-ctrl-btn gf-ctrl-skip" id="btnSkipBack" title="Rewind 10 seconds (J or Left)">
                                        <i class="fa fa-rotate-left"></i> 10s
                                    </button>
                                    <button type="button" class="gf-ctrl-btn gf-ctrl-skip" id="btnSkipForward" title="Forward 10 seconds (L or Right)">
                                        10s <i class="fa fa-rotate-right"></i>
                                    </button>

                                    <div class="gf-volume-group">
                                        <button type="button" class="gf-ctrl-btn" id="btnMute" title="Mute / Unmute (M)">
                                            <i class="fa fa-volume-up" id="volumeIcon"></i>
                                        </button>
                                        <input type="range" class="gf-volume-slider" id="volumeSlider" min="0" max="1" step="0.05" value="1">
                                    </div>

                                    <span class="gf-ctrl-time" id="timeDisplay">00:00 / 00:00</span>
                                </div>

                                <div class="gf-controls-right">
                                    <select class="gf-speed-selector" id="speedSelector" title="Playback Speed">
                                        <option value="0.5">0.5x</option>
                                        <option value="0.75">0.75x</option>
                                        <option value="1" selected>1.0x (Normal)</option>
                                        <option value="1.25">1.25x</option>
                                        <option value="1.5">1.5x</option>
                                        <option value="1.75">1.75x</option>
                                        <option value="2">2.0x</option>
                                    </select>

                                    <button type="button" class="gf-ctrl-btn" id="btnPip" title="Picture in Picture">
                                        <i class="fa fa-external-link"></i>
                                    </button>
                                    <button type="button" class="gf-ctrl-btn" id="btnFullscreen" title="Fullscreen (F)">
                                        <i class="fa fa-arrows-alt" id="fullscreenIcon"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            <?php elseif ($activeLesson['lesson_type'] === 'pdf'): ?>
                <?php $pdfUrl = !empty($activeLesson['content_url']) ? CourseCurriculum::resolveMediaUrl($activeLesson['content_url']) : 'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf'; ?>
                <div class="gf-study-doc-container" style="margin: 0; border-radius: 0;">
                    <div class="gf-study-doc-header">
                        <div class="gf-study-doc-info">
                            <div class="gf-study-doc-icon pdf"><i class="fa fa-file-pdf-o"></i></div>
                            <div>
                                <strong style="font-size: 14.5px; color: #0F172A; display: block;"><?php echo htmlspecialchars($activeLesson['title']); ?></strong>
                                <span style="font-size: 12px; color: #64748B;">Interactive PDF Document</span>
                            </div>
                        </div>
                        <div>
                            <a href="<?php echo htmlspecialchars($pdfUrl); ?>" target="_blank" download class="btn-download-mat">
                                <i class="fa fa-download"></i> Download PDF
                            </a>
                        </div>
                    </div>
                    <iframe src="<?php echo htmlspecialchars($pdfUrl); ?>" class="gf-pdf-frame-modern"></iframe>
                </div>

            <?php elseif ($activeLesson['lesson_type'] === 'audio'): ?>
                <?php $audioUrl = !empty($activeLesson['content_url']) ? CourseCurriculum::resolveMediaUrl($activeLesson['content_url']) : ''; ?>
                <div class="gf-audio-card" id="audioCard">
                    <div class="gf-audio-disc"><i class="fa fa-music"></i></div>
                    <div>
                        <h2 style="font-size: 20px; font-weight: 700; color: #FFFFFF; margin: 0 0 6px 0;"><?php echo htmlspecialchars($activeLesson['title']); ?></h2>
                        <span style="font-size: 13px; color: #94A3B8;">Audio Lecture & Study Recording</span>
                    </div>
                    <audio controls class="gf-audio-player-element skip" id="audioElement">
                        <source src="<?php echo htmlspecialchars($audioUrl); ?>" type="audio/mpeg">
                        Your browser does not support audio playback.
                    </audio>
                    <?php if (!empty($audioUrl)): ?>
                        <a href="<?php echo htmlspecialchars($audioUrl); ?>" target="_blank" download class="btn-download-mat">
                            <i class="fa fa-download"></i> Download Audio Track
                        </a>
                    <?php endif; ?>
                </div>

            <?php elseif ($activeLesson['lesson_type'] === 'document'): ?>
                <div class="gf-study-doc-container">
                    <div class="gf-study-doc-header">
                        <div class="gf-study-doc-info">
                            <div class="gf-study-doc-icon"><i class="fa fa-file-text-o"></i></div>
                            <div>
                                <strong style="font-size: 14.5px; color: #0F172A; display: block;"><?php echo htmlspecialchars($activeLesson['title']); ?></strong>
                                <span style="font-size: 12px; color: #64748B;">Executive Reading & Lecture Notes</span>
                            </div>
                        </div>
                        <?php if (!empty($activeLesson['content_url']) && $activeLesson['content_url'] !== '#'): ?>
                            <a href="<?php echo htmlspecialchars(CourseCurriculum::resolveMediaUrl($activeLesson['content_url'])); ?>" target="_blank" download class="btn-download-mat">
                                <i class="fa fa-download"></i> Download Attachment
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="gf-study-doc-body">
                        <?php 
                        if (!empty($activeLesson['content_text'])) {
                            echo nl2br($activeLesson['content_text']);
                        } else {
                            echo '<h2>' . htmlspecialchars($activeLesson['title']) . '</h2>';
                            echo '<p>Welcome to this comprehensive study guide. Review the concepts below and proceed to the next module upon completion.</p>';
                            echo '<p>In this lesson, you will master practical implementation frameworks, key formulas, and proven decision-making strategies designed for executive clarity and efficiency.</p>';
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Controller & Navigation Bar -->
            <div class="gf-player-controls-bar">
                <div class="gf-lesson-header-info">
                    <h2><?php echo htmlspecialchars($activeLesson['title']); ?></h2>
                    <span>Module Item <?php echo ($currentLessonIndex + 1); ?> of <?php echo count($allLessons); ?> • <?php echo ucfirst($activeLesson['lesson_type']); ?></span>
                </div>

                <div class="gf-lesson-nav-actions">
                    <?php if ($prevLesson): ?>
                        <a href="<?php echo api_get_path(WEB_PATH); ?>courses/<?php echo $courseCode; ?>/?lesson_id=<?php echo $prevLesson['id']; ?>" class="btn-lesson-nav">
                            <i class="fa fa-chevron-left"></i> Previous
                        </a>
                    <?php else: ?>
                        <span class="btn-lesson-nav disabled"><i class="fa fa-chevron-left"></i> Previous</span>
                    <?php endif; ?>

                    <?php 
                        $isCurrentCompleted = in_array($activeLesson['id'], $completedLessonIds); 
                    ?>
                    <button type="button" class="btn-mark-complete <?php echo $isCurrentCompleted ? 'is-completed' : ''; ?>" id="btnToggleComplete" onclick="toggleLessonCompletion(<?php echo $activeLesson['id']; ?>)">
                        <i class="fa fa-check"></i> 
                        <span id="completeBtnText"><?php echo $isCurrentCompleted ? 'Completed' : 'Mark as Complete'; ?></span>
                    </button>

                    <?php if ($nextLesson): ?>
                        <a href="<?php echo api_get_path(WEB_PATH); ?>courses/<?php echo $courseCode; ?>/?lesson_id=<?php echo $nextLesson['id']; ?>" class="btn-lesson-nav" style="background: var(--gf-primary); border-color: var(--gf-primary);">
                            Next Module <i class="fa fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Notes & Extra Info -->
            <div class="gf-player-extra-section">
                <div class="gf-tab-nav">
                    <button type="button" class="gf-tab-btn active" onclick="switchPlayerTab('overview', this)">Overview & Key Notes</button>
                    <button type="button" class="gf-tab-btn" onclick="switchPlayerTab('discussion', this)">Class Discussions</button>
                </div>

                <div id="tabOverview" style="color: #CBD5E1; font-size: 14px; line-height: 1.6;">
                    <p>Welcome to <strong><?php echo htmlspecialchars($activeLesson['title']); ?></strong>. Study the material carefully. Once completed, click <strong>Mark as Complete</strong> to update your course progress and work towards your verified certificate.</p>
                </div>

                <div id="tabDiscussion" style="display: none; color: #CBD5E1; font-size: 14px;">
                    <p class="text-muted"><i class="fa fa-comments"></i> Discussion forum is active for enrolled students. Share questions or takeaways with peers.</p>
                </div>
            </div>
        <?php else: ?>
            <div style="padding: 60px; text-align: center; color: #94A3B8;">
                <i class="fa fa-book" style="font-size: 48px; color: #64748B; margin-bottom: 16px;"></i>
                <h2>No Study Material Available</h2>
                <p>This course curriculum is currently being assembled. Please check back shortly.</p>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- =======================================================
     CINEMA VIDEO PLAYER & INTERACTIVE SCRIPTS
     ======================================================= -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const video = document.getElementById('cinemaVideo');
    const container = document.getElementById('cinemaContainer');
    const bigPlayBtn = document.getElementById('bigPlayBtn');
    const bigPlayIcon = document.getElementById('bigPlayIcon');
    const btnPlayPause = document.getElementById('btnPlayPause');
    const playPauseIcon = document.getElementById('playPauseIcon');
    const scrubberWrap = document.getElementById('scrubberWrap');
    const scrubberProgress = document.getElementById('scrubberProgress');
    const scrubberBuffered = document.getElementById('scrubberBuffered');
    const timeDisplay = document.getElementById('timeDisplay');
    const btnSkipBack = document.getElementById('btnSkipBack');
    const btnSkipForward = document.getElementById('btnSkipForward');
    const btnMute = document.getElementById('btnMute');
    const volumeIcon = document.getElementById('volumeIcon');
    const volumeSlider = document.getElementById('volumeSlider');
    const speedSelector = document.getElementById('speedSelector');
    const btnPip = document.getElementById('btnPip');
    const btnFullscreen = document.getElementById('btnFullscreen');
    const fullscreenIcon = document.getElementById('fullscreenIcon');

    if (video && container) {
        // Format Seconds helper
        function formatTime(seconds) {
            if (isNaN(seconds) || seconds < 0) return '00:00';
            const m = Math.floor(seconds / 60);
            const s = Math.floor(seconds % 60);
            return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
        }

        // Toggle Play / Pause
        function togglePlay() {
            if (video.paused || video.ended) {
                const p = video.play();
                if (p !== undefined) {
                    p.catch(err => {
                        console.log('Play prevented or pending:', err);
                    });
                }
            } else {
                video.pause();
            }
        }

        if (bigPlayBtn) bigPlayBtn.addEventListener('click', (e) => { e.stopPropagation(); togglePlay(); });
        if (btnPlayPause) btnPlayPause.addEventListener('click', (e) => { e.stopPropagation(); togglePlay(); });
        video.addEventListener('click', (e) => {
            if (e.target === video) {
                togglePlay();
            }
        });
        video.addEventListener('dblclick', (e) => {
            if (e.target === video && btnFullscreen) {
                btnFullscreen.click();
            }
        });

        // Don't let controls bar clicks bubble up to video
        if (cinemaControls) {
            cinemaControls.addEventListener('click', (e) => {
                e.stopPropagation();
            });
        }

        video.addEventListener('play', () => {
            container.classList.add('playing');
            if (playPauseIcon) playPauseIcon.className = 'fa fa-pause';
        });

        video.addEventListener('pause', () => {
            container.classList.remove('playing');
            container.classList.remove('hide-controls');
            if (playPauseIcon) playPauseIcon.className = 'fa fa-play';
        });

        video.addEventListener('ended', () => {
            container.classList.remove('playing');
            container.classList.remove('hide-controls');
            if (playPauseIcon) playPauseIcon.className = 'fa fa-repeat';
        });

        video.addEventListener('loadedmetadata', () => {
            if (video.duration && !isNaN(video.duration)) {
                if (timeDisplay) timeDisplay.textContent = formatTime(video.currentTime) + ' / ' + formatTime(video.duration);
            }
        });

        // Time Update
        video.addEventListener('timeupdate', () => {
            if (video.duration && !isNaN(video.duration)) {
                const percent = (video.currentTime / video.duration) * 100;
                if (scrubberProgress && !isScrubbing) scrubberProgress.style.width = percent + '%';
                if (timeDisplay) timeDisplay.textContent = formatTime(video.currentTime) + ' / ' + formatTime(video.duration);
            }
        });

        // Buffer Progress
        video.addEventListener('progress', () => {
            if (video.duration && video.buffered.length > 0) {
                try {
                    const bufferedEnd = video.buffered.end(video.buffered.length - 1);
                    const percent = (bufferedEnd / video.duration) * 100;
                    if (scrubberBuffered) scrubberBuffered.style.width = percent + '%';
                } catch(e) {}
            }
        });

        // Update Progress UI helper
        function updateProgressUI() {
            if (video.duration && !isNaN(video.duration)) {
                const percent = (video.currentTime / video.duration) * 100;
                if (scrubberProgress) scrubberProgress.style.width = percent + '%';
                if (timeDisplay) timeDisplay.textContent = formatTime(video.currentTime) + ' / ' + formatTime(video.duration);
            }
        }

        // Scrubber Click & Drag (with touch support)
        let isScrubbing = false;
        function scrub(e) {
            const clientX = e.clientX !== undefined ? e.clientX : (e.touches && e.touches[0] ? e.touches[0].clientX : null);
            if (clientX === null) return;
            if (!video.duration || isNaN(video.duration)) return;
            const rect = scrubberWrap.getBoundingClientRect();
            const pos = Math.max(0, Math.min(1, (clientX - rect.left) / rect.width));
            video.currentTime = pos * video.duration;
            if (scrubberProgress) scrubberProgress.style.width = (pos * 100) + '%';
            if (timeDisplay) timeDisplay.textContent = formatTime(video.currentTime) + ' / ' + formatTime(video.duration);
        }

        if (scrubberWrap) {
            scrubberWrap.addEventListener('mousedown', (e) => {
                e.stopPropagation();
                isScrubbing = true;
                scrub(e);
            });
            window.addEventListener('mousemove', (e) => {
                if (isScrubbing) scrub(e);
            });
            window.addEventListener('mouseup', () => {
                isScrubbing = false;
            });

            // Touch support for mobile/touchpads
            scrubberWrap.addEventListener('touchstart', (e) => {
                e.stopPropagation();
                isScrubbing = true;
                scrub(e);
            }, { passive: true });
            window.addEventListener('touchmove', (e) => {
                if (isScrubbing) scrub(e);
            }, { passive: true });
            window.addEventListener('touchend', () => {
                isScrubbing = false;
            });
        }

        // 10s Skips
        if (btnSkipBack) btnSkipBack.addEventListener('click', (e) => {
            e.stopPropagation();
            video.currentTime = Math.max(0, (video.currentTime || 0) - 10);
            updateProgressUI();
        });
        if (btnSkipForward) btnSkipForward.addEventListener('click', (e) => {
            e.stopPropagation();
            const maxTime = (Number.isFinite(video.duration) && video.duration > 0) ? video.duration : Infinity;
            video.currentTime = Math.min(maxTime, (video.currentTime || 0) + 10);
            updateProgressUI();
        });

        // Volume & Mute
        if (btnMute) {
            btnMute.addEventListener('click', (e) => {
                e.stopPropagation();
                video.muted = !video.muted;
                updateVolumeUI();
            });
        }

        if (volumeSlider) {
            volumeSlider.addEventListener('input', (e) => {
                video.volume = parseFloat(e.target.value);
                video.muted = (video.volume === 0);
                updateVolumeUI();
            });
        }

        function updateVolumeUI() {
            if (video.muted || video.volume === 0) {
                if (volumeIcon) volumeIcon.className = 'fa fa-volume-off';
                if (volumeSlider) volumeSlider.value = 0;
            } else if (video.volume < 0.5) {
                if (volumeIcon) volumeIcon.className = 'fa fa-volume-down';
                if (volumeSlider) volumeSlider.value = video.volume;
            } else {
                if (volumeIcon) volumeIcon.className = 'fa fa-volume-up';
                if (volumeSlider) volumeSlider.value = video.volume;
            }
        }

        // Speed Selector
        if (speedSelector) {
            speedSelector.addEventListener('change', (e) => {
                video.playbackRate = parseFloat(e.target.value);
            });
        }

        // Picture-in-Picture
        if (btnPip) {
            btnPip.addEventListener('click', async (e) => {
                e.stopPropagation();
                try {
                    if (document.pictureInPictureElement) {
                        await document.exitPictureInPicture();
                    } else if (document.pictureInPictureEnabled) {
                        await video.requestPictureInPicture();
                    }
                } catch (err) {
                    console.error('PiP Error:', err);
                }
            });
        }

        // Fullscreen
        if (btnFullscreen) {
            btnFullscreen.addEventListener('click', (e) => {
                e.stopPropagation();
                if (!document.fullscreenElement) {
                    container.requestFullscreen().catch(err => console.error(err));
                    if (fullscreenIcon) fullscreenIcon.className = 'fa fa-compress';
                } else {
                    document.exitFullscreen();
                    if (fullscreenIcon) fullscreenIcon.className = 'fa fa-arrows-alt';
                }
            });
        }

        // Controls Inactivity Auto-hide with hover protection
        let controlsTimeout;
        container.addEventListener('mousemove', () => {
            container.classList.remove('hide-controls');
            clearTimeout(controlsTimeout);
            if (!video.paused) {
                controlsTimeout = setTimeout(() => {
                    container.classList.add('hide-controls');
                }, 3000);
            }
        });

        if (cinemaControls) {
            cinemaControls.addEventListener('mouseenter', () => {
                clearTimeout(controlsTimeout);
                container.classList.remove('hide-controls');
            });
        }

        // Keyboard Controls
        window.addEventListener('keydown', (e) => {
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) return;

            if (e.key === ' ' || e.key === 'k' || e.key === 'K') {
                e.preventDefault();
                togglePlay();
            } else if (e.key === 'ArrowLeft' || e.key === 'j' || e.key === 'J') {
                e.preventDefault();
                video.currentTime = Math.max(0, (video.currentTime || 0) - 10);
                updateProgressUI();
            } else if (e.key === 'ArrowRight' || e.key === 'l' || e.key === 'L') {
                e.preventDefault();
                const maxTime = (Number.isFinite(video.duration) && video.duration > 0) ? video.duration : Infinity;
                video.currentTime = Math.min(maxTime, (video.currentTime || 0) + 10);
                updateProgressUI();
            } else if (e.key === 'm' || e.key === 'M') {
                e.preventDefault();
                video.muted = !video.muted;
                updateVolumeUI();
            } else if (e.key === 'f' || e.key === 'F') {
                e.preventDefault();
                if (btnFullscreen) btnFullscreen.click();
            }
        });
    }

    // Audio Disc Animation toggle
    const audioEl = document.getElementById('audioElement');
    const audioCard = document.getElementById('audioCard');
    if (audioEl && audioCard) {
        audioEl.addEventListener('play', () => audioCard.classList.add('playing'));
        audioEl.addEventListener('pause', () => audioCard.classList.remove('playing'));
        audioEl.addEventListener('ended', () => audioCard.classList.remove('playing'));
    }
});

// Accordion toggle
function togglePlayerAccordion(id) {
    const el = document.getElementById(id);
    if (el) {
        el.style.display = (el.style.display === 'none') ? 'block' : 'none';
    }
}

// Tab switcher
function switchPlayerTab(tabName, btn) {
    document.querySelectorAll('.gf-tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    const tabOver = document.getElementById('tabOverview');
    const tabDisc = document.getElementById('tabDiscussion');
    if (tabName === 'overview') {
        if (tabOver) tabOver.style.display = 'block';
        if (tabDisc) tabDisc.style.display = 'none';
    } else {
        if (tabOver) tabOver.style.display = 'none';
        if (tabDisc) tabDisc.style.display = 'block';
    }
}

// AJAX Lesson Completion Toggle
function toggleLessonCompletion(lessonId) {
    const btn = document.getElementById('btnToggleComplete');
    const btnText = document.getElementById('completeBtnText');
    const checkIcon = document.getElementById('check_icon_' + lessonId);
    const progressFill = document.getElementById('progressBarFill');
    const progressText = document.getElementById('progressText');

    const formData = new FormData();
    formData.append('action', 'toggle_complete');
    formData.append('lesson_id', lessonId);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (data.completed) {
                btn.classList.add('is-completed');
                btnText.textContent = 'Completed';
                if (checkIcon) checkIcon.classList.add('completed');
            } else {
                btn.classList.remove('is-completed');
                btnText.textContent = 'Mark as Complete';
                if (checkIcon) checkIcon.classList.remove('completed');
            }
            if (progressFill) progressFill.style.width = data.percent + '%';
            if (progressText) progressText.textContent = data.percent + '% (' + data.completed_count + '/' + data.total_count + ' Complete)';
        }
    })
    .catch(err => console.error('Error toggling completion:', err));
}

</script>

<?php
?>

<!-- Self-Contained In-Page Certificate Preview Modal -->
<div id="gfCertificateModal" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); z-index: 999999; justify-content: center; align-items: center; padding: 20px; box-sizing: border-box;">
    <div style="background: #1E293B; border: 1px solid #334155; border-radius: 14px; width: 100%; max-width: 1100px; height: 90vh; max-height: 850px; display: flex; flex-direction: column; box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.85); overflow: hidden; position: relative;">
        <!-- Modal Header -->
        <div style="background: #0F172A; border-bottom: 1px solid #334155; padding: 14px 22px; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <span style="display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 10px; background: rgba(245, 158, 11, 0.2); color: #F59E0B; font-size: 16px;">
                    <i class="fa fa-certificate"></i>
                </span>
                <div>
                    <h3 style="margin: 0; color: #FFFFFF; font-size: 16px; font-weight: 700; line-height: 1.3;"><?php echo htmlspecialchars($courseTitle); ?> - Certificate Preview</h3>
                    <span style="color: #94A3B8; font-size: 12px;">Official verified completion certificate</span>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <button type="button" onclick="printCertificateModal()" style="background: #E3122B; color: #FFFFFF; border: none; font-weight: 600; padding: 7px 16px; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; font-size: 13px; box-shadow: 0 4px 12px rgba(227,18,43,0.35);">
                    <i class="fa fa-print"></i> Print / PDF
                </button>
                <button type="button" onclick="closeCertificateModal()" style="background: rgba(255, 255, 255, 0.1); color: #94A3B8; border: 1px solid #475569; width: 34px; height: 34px; border-radius: 8px; font-size: 22px; line-height: 1; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s;">
                    &times;
                </button>
            </div>
        </div>
        <!-- Modal Body (Iframe) -->
        <div style="flex: 1; position: relative; background: #0B0F19; overflow: hidden;">
            <iframe id="gfCertIframe" src="" style="width: 100%; height: 100%; border: none; display: block;" allowfullscreen></iframe>
        </div>
    </div>
</div>

<script>
function openCertificateModal() {
    var modal = document.getElementById("gfCertificateModal");
    var iframe = document.getElementById("gfCertIframe");
    if (!modal) {
        alert("Certificate modal container not found.");
        return;
    }
    var certUrl = "<?php echo api_get_path(WEB_PATH); ?>main/gradebook/view_blueprint_certificate.php?course_code=<?php echo urlencode($courseCode); ?>&embed=1";
    if (iframe && (!iframe.src || iframe.src === "about:blank" || iframe.src.indexOf("view_blueprint_certificate") === -1)) {
        iframe.src = certUrl;
    }
    modal.style.display = "flex";
    document.body.style.overflow = "hidden";
}

function closeCertificateModal() {
    var modal = document.getElementById("gfCertificateModal");
    if (modal) {
        modal.style.display = "none";
        document.body.style.overflow = "";
    }
}

function printCertificateModal() {
    var iframe = document.getElementById("gfCertIframe");
    if (iframe && iframe.contentWindow) {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
    }
}

function openCertificatePreview() {
    openCertificateModal();
}

document.addEventListener("keydown", function(e) {
    if (e.key === "Escape" || e.keyCode === 27) {
        closeCertificateModal();
    }
});

document.addEventListener("click", function(e) {
    var modal = document.getElementById("gfCertificateModal");
    if (modal && e.target === modal) {
        closeCertificateModal();
    }
});
</script>

<?php
Display::display_footer();
