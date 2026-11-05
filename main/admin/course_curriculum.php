<?php
/* For licensing terms, see /license.txt */

/**
 * Sparky-Style Unified Course Curriculum & Settings Editor
 * Seamlessly manage curriculum chapters, lessons, and course settings in one unified workspace
 */

// Allow large video uploads
@ini_set('upload_max_filesize', '512M');
@ini_set('post_max_size', '512M');
@ini_set('max_execution_time', '0');
@ini_set('max_input_time', '-1');
@ini_set('memory_limit', '512M');

// Catch payload exceeding POST size limit before anything else
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Uploaded file exceeds maximum server POST limit (512 MB).'
    ]);
    exit;
}

$cidReset = true;
require_once __DIR__.'/../inc/global.inc.php';
require_once __DIR__.'/../inc/lib/course_curriculum.lib.php';
require_once __DIR__.'/../inc/lib/certificate_blueprint.lib.php';

// Resolve course identifier from code, id, or cidReq
$courseParam = isset($_REQUEST['code']) ? trim($_REQUEST['code']) : (isset($_REQUEST['id']) ? trim($_REQUEST['id']) : (isset($_REQUEST['c_id']) ? trim($_REQUEST['c_id']) : (isset($_REQUEST['cidReq']) ? trim($_REQUEST['cidReq']) : '')));

$courseInfo = api_get_course_info($courseParam);
if (empty($courseInfo) && is_numeric($courseParam)) {
    $courseInfo = api_get_course_info_by_id((int)$courseParam);
}

if (empty($courseInfo) && empty($courseParam)) {
    $firstCourse = CourseManager::get_courses_list();
    if (!empty($firstCourse)) {
        $courseInfo = api_get_course_info($firstCourse[0]['code']);
    }
}

if (empty($courseInfo)) {
    api_not_allowed(true);
}

$courseCode = $courseInfo['code'];

// Access restrictions - Allow platform admins, course admins, and course teachers
$isAllowed = api_is_platform_admin() || api_is_platform_admin_by_id() || api_is_course_admin() || CourseManager::is_course_teacher(api_get_user_id(), $courseCode);
if (!$isAllowed) {
    if (isset($_REQUEST['action']) && strpos($_REQUEST['action'], 'ajax_') === 0) {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Authentication expired or unauthorized. Please refresh the page and log in.']);
        exit;
    }
    api_not_allowed(true);
}

$this_section = SECTION_PLATFORM_ADMIN;
$currentUserId = api_get_user_id();
$courseTitle = !empty($courseInfo) ? $courseInfo['title'] : 'Course Curriculum';

CourseCurriculum::initTables();
CertificateBlueprint::initTables();

$activeTab = isset($_REQUEST['tab']) && $_REQUEST['tab'] === 'settings' ? 'settings' : 'curriculum';

// Handle AJAX Endpoints
if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'ajax_upload_lesson_media') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    $lessonId = isset($_REQUEST['lesson_id']) ? (int)$_REQUEST['lesson_id'] : 0;
    
    $uploadedFile = null;
    foreach (['file', 'media_file', 'video_file', 'pdf_file', 'doc_file', 'audio_file'] as $k) {
        if (isset($_FILES[$k]) && $_FILES[$k]['error'] === UPLOAD_ERR_OK) {
            $uploadedFile = $_FILES[$k];
            break;
        }
    }

    if (!$uploadedFile) {
        $errCode = isset($_FILES['file']) ? $_FILES['file']['error'] : (isset($_FILES['video_file']) ? $_FILES['video_file']['error'] : 'none');
        echo json_encode(['success' => false, 'error' => 'No file received or upload error code: ' . $errCode]);
        exit;
    }

    $lesson = CourseCurriculum::getLesson($lessonId);
    $lTitle = $lesson ? $lesson['title'] : $uploadedFile['name'];

    $uploadRes = CourseCurriculum::handleUploadedFile($uploadedFile, $courseCode, $lTitle);
    if (!$uploadRes) {
        echo json_encode(['success' => false, 'error' => 'Failed to save uploaded file on server. Check folder permissions.']);
        exit;
    }

    if ($lessonId > 0) {
        CourseCurriculum::saveLesson($lessonId, [
            'content_url' => $uploadRes['web_url'],
            'file_path' => $uploadRes['rel_path'],
            'file_size' => $uploadRes['file_size'],
            'mime_type' => $uploadRes['mime_type'],
            'lesson_type' => $uploadRes['lesson_type']
        ]);
    }

    echo json_encode([
        'success' => true,
        'url' => $uploadRes['web_url'],
        'file_name' => $uploadRes['original_name'],
        'file_size' => $uploadRes['file_size'],
        'mime_type' => $uploadRes['mime_type'],
        'lesson_type' => $uploadRes['lesson_type']
    ]);
    exit;
}
if (isset($_GET['action']) && $_GET['action'] === 'ajax_get_lesson') {
    header('Content-Type: application/json');
    $lessonId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $lesson = CourseCurriculum::getLesson($lessonId);
    if (!$lesson) {
        echo json_encode(['error' => 'Lesson not found']);
        exit;
    }
    echo json_encode($lesson);
    exit;
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $postAction = trim($_POST['action']);

    // 0. Delete Course
    if ($postAction === 'delete_course') {
        if (api_is_platform_admin() || api_is_platform_admin_by_id() || api_is_course_admin()) {
            CourseManager::delete_course($courseCode);
            header("Location: course_list.php?msg=course_deleted&c=".urlencode($courseCode));
            exit;
        }
    }

    // 1. Save Course Settings
    if ($postAction === 'save_course_settings') {
        $newTitle = isset($_POST['title']) ? trim($_POST['title']) : '';
        $newCategory = isset($_POST['category_code']) ? trim($_POST['category_code']) : '';
        $newLanguage = isset($_POST['course_language']) ? trim($_POST['course_language']) : 'english';
        $newDepartment = isset($_POST['department_name']) ? trim($_POST['department_name']) : '';
        $newDepartmentUrl = isset($_POST['department_url']) ? trim($_POST['department_url']) : '';
        $newVisibility = isset($_POST['visibility']) ? (int)$_POST['visibility'] : 1;
        $newTeacherId = isset($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : 0;
        $blueprintId = isset($_POST['blueprint_id']) ? (int)$_POST['blueprint_id'] : 0;
        $newDescription = isset($_POST['description']) ? trim($_POST['description']) : '';
        $newTags = isset($_POST['extra_tags']) ? trim($_POST['extra_tags']) : '';

        // Handle Thumbnail Upload via Chamilo Course Picture API
        if (isset($_FILES['course_thumbnail']) && $_FILES['course_thumbnail']['error'] === UPLOAD_ERR_OK) {
            CourseManager::update_course_picture(
                $courseInfo,
                $_FILES['course_thumbnail']['name'],
                $_FILES['course_thumbnail']['tmp_name']
            );
        } elseif (!empty($_POST['delete_thumbnail']) && $_POST['delete_thumbnail'] == '1') {
            CourseManager::deleteCoursePicture($courseCode);
        }

        $courseTable = Database::get_main_table(TABLE_MAIN_COURSE);
        $newRegCode = isset($_POST['registration_code']) ? trim($_POST['registration_code']) : '';
        $newSubscribe = isset($_POST['subscribe']) ? (int)$_POST['subscribe'] : 1;
        $sqlUp = "UPDATE $courseTable SET 
                    title = '".Database::escape_string($newTitle)."',
                    category_code = '".Database::escape_string($newCategory)."',
                    course_language = '".Database::escape_string($newLanguage)."',
                    department_name = '".Database::escape_string($newDepartment)."',
                    department_url = '".Database::escape_string($newDepartmentUrl)."',
                    description = '".Database::escape_string($newDescription)."',
                    visibility = $newVisibility,
                    registration_code = '".Database::escape_string($newRegCode)."',
                    subscribe = $newSubscribe
                  WHERE code = '".Database::escape_string($courseCode)."'";
        Database::query($sqlUp);

        // Update Extra Fields (e.g. tags)
        if (!empty($courseInfo['real_id'])) {
            $extraFieldValue = new ExtraFieldValue('course');
            $extraFieldValue->saveFieldValues([
                'item_id' => (int)$courseInfo['real_id'],
                'extra_tags' => $newTags
            ]);
        }

        // Update primary teacher
        if ($newTeacherId > 0 && !empty($courseInfo['real_id'])) {
            $cId = (int)$courseInfo['real_id'];
            $courseUserTable = Database::get_main_table(TABLE_MAIN_COURSE_USER);
            Database::query("DELETE FROM $courseUserTable WHERE c_id = $cId AND status = ".COURSEMANAGER);
            Database::query("INSERT INTO $courseUserTable (c_id, user_id, status) VALUES ($cId, $newTeacherId, ".COURSEMANAGER.")");
        }

        // Assign Certificate Blueprint
        CertificateBlueprint::setCourseBlueprint($courseCode, $blueprintId);

        header("Location: course_curriculum.php?code=$courseCode&tab=settings&msg=settings_saved");
        exit;
    }

    // 2. Curriculum Chapter/Lesson Actions
    if ($postAction === 'create_chapter') {
        $chTitle = isset($_POST['chapter_title']) ? trim($_POST['chapter_title']) : 'New Chapter';
        if (!empty($chTitle)) {
            $newChId = CourseCurriculum::createChapter($courseCode, $chTitle);
            header("Location: course_curriculum.php?code=$courseCode&tab=curriculum&msg=chapter_created");
            exit;
        }
    }

    if ($postAction === 'create_lesson') {
        $chId = isset($_POST['chapter_id']) ? (int)$_POST['chapter_id'] : 0;
        $lTitle = isset($_POST['lesson_title']) ? trim($_POST['lesson_title']) : 'New Lesson';
        $lType = isset($_POST['lesson_type']) ? trim($_POST['lesson_type']) : 'video';
        if ($chId > 0 && !empty($lTitle)) {
            $newLessonId = CourseCurriculum::createLesson($chId, $courseCode, $lTitle, $lType);
            header("Location: course_curriculum.php?code=$courseCode&tab=curriculum&lesson_id=$newLessonId&msg=lesson_created");
            exit;
        }
    }

    if ($postAction === 'save_lesson') {
        $lessonId = isset($_POST['lesson_id']) ? (int)$_POST['lesson_id'] : 0;
        $mediaUrl = isset($_POST['content_url']) ? trim($_POST['content_url']) : '';
        $lTitle = isset($_POST['title']) ? trim($_POST['title']) : 'Lesson';
        $lType = isset($_POST['lesson_type']) ? trim($_POST['lesson_type']) : 'video';
        $filePath = '';
        $fileSize = 0;
        $mimeType = '';

        // Multi-format file upload integration with Chamilo Document Engine
        $uploaded = false;
        foreach (['media_file', 'video_file', 'pdf_file', 'doc_file', 'audio_file'] as $inputKey) {
            if (isset($_FILES[$inputKey]) && $_FILES[$inputKey]['error'] === UPLOAD_ERR_OK) {
                $uploadRes = CourseCurriculum::handleUploadedFile($_FILES[$inputKey], $courseCode, $lTitle);
                if ($uploadRes) {
                    $mediaUrl = $uploadRes['web_url'];
                    $filePath = $uploadRes['rel_path'];
                    $fileSize = $uploadRes['file_size'];
                    $mimeType = $uploadRes['mime_type'];
                    // Automatically adjust lesson type if generic
                    if (empty($lType) || $lType === 'video') {
                        $lType = $uploadRes['lesson_type'];
                    }
                    $uploaded = true;
                    break;
                }
            }
        }

        $lessonData = [
            'title' => $lTitle,
            'lesson_type' => $lType,
            'content_url' => $mediaUrl,
            'content_text' => isset($_POST['content_text']) ? $_POST['content_text'] : '',
            'duration' => isset($_POST['duration']) ? trim($_POST['duration']) : '10:00',
            'file_path' => $filePath,
            'file_size' => $fileSize,
            'mime_type' => $mimeType,
            'is_draft' => isset($_POST['is_draft']) ? 1 : 0,
            'is_free_preview' => isset($_POST['is_free_preview']) ? 1 : 0,
            'is_prerequisite' => isset($_POST['is_prerequisite']) ? 1 : 0,
            'downloadable_web' => isset($_POST['downloadable_web']) ? 1 : 0,
            'downloadable_app' => isset($_POST['downloadable_app']) ? 1 : 0,
            'enable_discussions' => isset($_POST['enable_discussions']) ? 1 : 0,
            'completion_required' => isset($_POST['completion_required']) ? 1 : 0,
            'block_forward_seeking' => isset($_POST['block_forward_seeking']) ? 1 : 0,
            'autoplay' => isset($_POST['autoplay']) ? 1 : 0,
            'enable_analytics' => isset($_POST['enable_analytics']) ? 1 : 0,
            'motivational_popup' => isset($_POST['motivational_popup']) ? trim($_POST['motivational_popup']) : ''
        ];

        CourseCurriculum::saveLesson($lessonId, $lessonData);
        header("Location: course_curriculum.php?code=$courseCode&tab=curriculum&lesson_id=$lessonId&msg=saved");
        exit;
    }

    if ($postAction === 'delete_lesson') {
        $lessonId = isset($_POST['lesson_id']) ? (int)$_POST['lesson_id'] : 0;
        CourseCurriculum::deleteLesson($lessonId);
        header("Location: course_curriculum.php?code=$courseCode&tab=curriculum&msg=lesson_deleted");
        exit;
    }

    if ($postAction === 'delete_chapter') {
        $chId = isset($_POST['chapter_id']) ? (int)$_POST['chapter_id'] : 0;
        CourseCurriculum::deleteChapter($chId);
        header("Location: course_curriculum.php?code=$courseCode&tab=curriculum&msg=chapter_deleted");
        exit;
    }
}

// Fetch Curriculum data
$chapters = CourseCurriculum::getCurriculum($courseCode);

// Determine active lesson to display in Curriculum tab
$activeLessonId = isset($_GET['lesson_id']) ? (int)$_GET['lesson_id'] : 0;
$activeLesson = null;

if ($activeLessonId > 0) {
    $activeLesson = CourseCurriculum::getLesson($activeLessonId);
}
if (!$activeLesson && !empty($chapters)) {
    foreach ($chapters as $c) {
        if (!empty($c['lessons'])) {
            $activeLesson = $c['lessons'][0];
            $activeLessonId = $activeLesson['id'];
            break;
        }
    }
}

// Fetch Settings Data
$categoryTable = Database::get_main_table(TABLE_MAIN_CATEGORY);
$resCats = Database::query("SELECT code, name FROM $categoryTable ORDER BY name ASC");
$allCategories = [];
while ($cRow = Database::fetch_assoc($resCats)) {
    $allCategories[] = $cRow;
}

$userTable = Database::get_main_table(TABLE_MAIN_USER);
$adminTable = Database::get_main_table(TABLE_MAIN_ADMIN);
$resTeachers = Database::query("SELECT u.id, u.firstname, u.lastname, u.email FROM $userTable u WHERE u.status = 1 OR u.id IN (SELECT user_id FROM $adminTable) ORDER BY u.firstname ASC");
$allTeachers = [];
while ($tRow = Database::fetch_assoc($resTeachers)) {
    $allTeachers[] = $tRow;
}

$allLanguages = api_get_languages_to_array();
$currentLanguage = !empty($courseInfo['course_language']) ? $courseInfo['course_language'] : api_get_setting('platformLanguage');
$currentDepartment = !empty($courseInfo['department_name']) ? $courseInfo['department_name'] : '';
$currentDepartmentUrl = !empty($courseInfo['department_url']) ? $courseInfo['department_url'] : '';

$allBlueprints = CertificateBlueprint::getAllBlueprints();
$assignedBlueprint = CertificateBlueprint::getBlueprintForCourse($courseCode);
$assignedBlueprintId = $assignedBlueprint ? (int)$assignedBlueprint['id'] : 0;

$cRealId = !empty($courseInfo['real_id']) ? (int)$courseInfo['real_id'] : 0;
$currentTeacherId = 0;
if ($cRealId > 0) {
    $courseUserTable = Database::get_main_table(TABLE_MAIN_COURSE_USER);
    $currentTeacherId = (int)Database::result(Database::query("SELECT user_id FROM $courseUserTable WHERE c_id = $cRealId AND status = ".COURSEMANAGER." LIMIT 1"), 0, 0);
}
if ($currentTeacherId === 0) {
    $currentTeacherId = $currentUserId;
}

// Fetch course tags
$extraFieldValue = new ExtraFieldValue('course');
$currentTags = '';
if ($cRealId > 0) {
    $tagData = $extraFieldValue->get_values_by_handler_and_field_variable($cRealId, 'tags');
    if (!empty($tagData) && !empty($tagData['value'])) {
        $currentTags = $tagData['value'];
    }
}

// Thumbnail info
$courseSysPath = api_get_path(SYS_COURSE_PATH).$courseInfo['path'];
$courseWebPath = api_get_path(WEB_COURSE_PATH).$courseInfo['path'];
$hasCustomThumbnail = file_exists($courseSysPath.'/course-pic.png') || file_exists($courseSysPath.'/course-pic85x85.png');
$currentThumbnailUrl = $hasCustomThumbnail ? ($courseWebPath.'/course-pic.png?t='.time()) : '';

Display::display_header($activeTab === 'settings' ? 'Course Settings' : 'Course Curriculum');
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
:root {
    --gf-primary: #6366F1;
    --gf-primary-hover: #4F46E5;
    --gf-navy-bar: #0B1727;
    --gf-bg: #F8FAFC;
    --gf-border: #E2E8F0;
    --gf-text-dark: #0F172A;
    --gf-text-muted: #64748B;
}

body {
    background-color: #F1F5F9;
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    color: #1E293B;
}

/* Full screen override */
#cm-content, #cm-content > .container {
    width: 100% !important;
    max-width: 100% !important;
    padding: 0 !important;
    margin: 0 !important;
    margin-bottom: 0 !important;
    padding-bottom: 0 !important;
}

/* Hide platform footer in course editor */
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


/* Top Dark Header */
.gf-curriculum-navbar {
    background: var(--gf-navy-bar);
    padding: 12px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #1E293B;
}

.gf-navbar-left {
    display: flex;
    align-items: center;
    gap: 20px;
}

.gf-back-nav {
    color: #94A3B8;
    font-size: 14px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none !important;
    padding-right: 14px;
    border-right: 1px solid #334155;
}

.gf-back-nav:hover {
    color: #FFFFFF;
}

.gf-nav-tabs-bar {
    display: flex;
    gap: 24px;
}

.gf-nav-tab-item {
    color: #94A3B8;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none !important;
    padding: 6px 0;
    position: relative;
    transition: color 0.15s;
}

.gf-nav-tab-item:hover {
    color: #FFFFFF;
}

.gf-nav-tab-item.active {
    color: #FFFFFF;
    font-weight: 700;
}

.gf-nav-tab-item.active::after {
    content: '';
    position: absolute;
    bottom: -13px;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--gf-primary);
    border-radius: 3px 3px 0 0;
}

.gf-navbar-right {
    display: flex;
    gap: 12px;
    align-items: center;
}

.btn-nav-courses {
    background: rgba(255, 255, 255, 0.08);
    color: #FFFFFF !important;
    border: 1px solid #334155;
    border-radius: 6px;
    padding: 7px 14px;
    font-size: 13px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none !important;
    transition: all 0.15s;
}

.btn-nav-courses:hover {
    background: rgba(255, 255, 255, 0.15);
}

.btn-nav-preview {
    background: var(--gf-primary);
    color: #FFFFFF !important;
    border: none;
    border-radius: 6px;
    padding: 7px 16px;
    font-size: 13px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none !important;
    box-shadow: 0 2px 4px rgba(99, 102, 241, 0.3);
    transition: all 0.15s;
}

.btn-nav-preview:hover {
    background: var(--gf-primary-hover);
    transform: translateY(-1px);
}

/* ================== SETTINGS TAB STYLES ================== */
.gf-settings-workspace {
    padding: 24px 32px;
    max-width: 1400px;
    margin: 0 auto;
    width: 100%;
    box-sizing: border-box;
}

.gf-settings-header-card {
    background: #FFFFFF;
    border: 1px solid var(--gf-border);
    border-radius: 12px;
    padding: 20px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}

.gf-settings-header-left h2 {
    font-size: 20px;
    font-weight: 800;
    color: var(--gf-text-dark);
    margin: 0 0 4px 0;
}

.gf-settings-header-left p {
    font-size: 13.5px;
    color: var(--gf-text-muted);
    margin: 0;
}

.gf-settings-grid {
    display: grid;
    grid-template-columns: 1.3fr 1fr;
    gap: 24px;
}

@media (max-width: 992px) {
    .gf-settings-grid {
        grid-template-columns: 1fr;
    }
}

.gf-card {
    background: #FFFFFF;
    border: 1px solid var(--gf-border);
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}

.gf-card-title {
    font-size: 16px;
    font-weight: 700;
    color: #0F172A;
    margin: 0 0 16px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.gf-label {
    font-size: 13px;
    font-weight: 600;
    color: #334155;
    margin-bottom: 6px;
    display: block;
}

.gf-input {
    border: 1px solid #CBD5E1;
    border-radius: 8px;
    padding: 9px 12px;
    font-size: 13.5px;
    color: #0F172A;
    width: 100%;
}

.gf-input:focus {
    border-color: var(--gf-primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
}

/* ================== CURRICULUM TAB STYLES ================== */
.gf-curriculum-workspace {
    display: flex;
    width: 100%;
    min-height: calc(100vh - 120px);
    background: #F8FAFC;
}

.gf-curriculum-sidebar {
    width: 320px;
    min-width: 320px;
    background: #FFFFFF;
    border-right: 1px solid var(--gf-border);
    padding: 20px 16px;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.gf-sidebar-header h2 {
    font-size: 18px;
    font-weight: 800;
    color: #0F172A;
    margin: 0 0 4px 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.gf-sidebar-header span {
    font-size: 12px;
    color: #64748B;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    display: block;
    margin-bottom: 16px;
}

.gf-chapter-list {
    overflow-y: auto;
    flex: 1;
    margin-bottom: 16px;
}

.gf-chapter-card {
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    margin-bottom: 10px;
    overflow: hidden;
    background: #FFFFFF;
    box-shadow: 0 1px 2px rgba(0,0,0,0.02);
}

.gf-chapter-card.active {
    border-color: #CBD5E1;
}

.btn-del-chapter {
    background: transparent;
    border: none;
    color: #94A3B8;
    padding: 4px 6px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.15s;
}
.btn-del-chapter:hover {
    color: #DC2626;
    background: #FEE2E2;
}

.gf-dropzone {
    border: 2px dashed #CBD5E1;
    border-radius: 12px;
    background: #F8FAFC;
    padding: 24px 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    margin-bottom: 16px;
}
.gf-dropzone:hover, .gf-dropzone.dragover {
    border-color: #6366F1;
    background: #EEF2FF;
}
.gf-dropzone-icon {
    font-size: 32px;
    color: #6366F1;
    margin-bottom: 8px;
}
.gf-upload-progress {
    margin-top: 14px;
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    padding: 12px 16px;
}
.gf-progress-track {
    width: 100%;
    height: 8px;
    background: #E2E8F0;
    border-radius: 4px;
    overflow: hidden;
    margin-top: 6px;
}
.gf-progress-bar-inner {
    height: 100%;
    background: linear-gradient(90deg, #6366F1 0%, #10B981 100%);
    width: 0%;
    transition: width 0.15s ease;
}

.gf-chapter-header {
    background: #F8FAFC;
    padding: 10px 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 13.5px;
    font-weight: 700;
    color: #1E293B;
    cursor: pointer;
    border-bottom: 1px solid #E2E8F0;
}

.gf-chapter-header:hover {
    background: #F1F5F9;
}

.gf-chapter-lessons {
    padding: 6px 0;
}

.gf-lesson-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 14px;
    text-decoration: none !important;
    color: #475569;
    font-size: 13px;
    border-left: 3px solid transparent;
    transition: all 0.15s;
}

.gf-lesson-item:hover {
    background: #F8FAFC;
    color: #0F172A;
}

.gf-lesson-item.active {
    background: #EEF2FF;
    border-left-color: var(--gf-primary);
    color: #4F46E5;
    font-weight: 600;
}

.gf-lesson-title {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 175px;
}

.gf-lesson-badge {
    font-size: 10.5px;
    padding: 2px 6px;
    border-radius: 4px;
    font-weight: 700;
    text-transform: uppercase;
}

.badge-video {
    background: #E0E7FF;
    color: #4338CA;
}

.badge-pdf {
    background: #FEE2E2;
    color: #B91C1C;
}

.badge-document {
    background: #FEF3C7;
    color: #B45309;
}

.btn-add-chapter {
    width: 100%;
    padding: 10px;
    background: #FFFFFF;
    border: 1px dashed #CBD5E1;
    border-radius: 8px;
    color: var(--gf-primary);
    font-weight: 600;
    font-size: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    cursor: pointer;
    transition: all 0.15s;
}

.btn-add-chapter:hover {
    background: #EEF2FF;
    border-color: var(--gf-primary);
}

.btn-add-lesson-mini {
    width: calc(100% - 24px);
    margin: 4px 12px 6px 12px;
    padding: 6px;
    background: #F8FAFC;
    border: 1px dashed #CBD5E1;
    border-radius: 6px;
    color: #64748B;
    font-weight: 600;
    font-size: 12px;
    text-align: center;
    cursor: pointer;
}

.btn-add-lesson-mini:hover {
    color: var(--gf-primary);
    background: #EEF2FF;
    border-color: var(--gf-primary);
}

.gf-curriculum-main {
    flex: 1;
    padding: 24px 32px;
    overflow-y: auto;
    background: #F8FAFC;
    border-right: 1px solid var(--gf-border);
}

.gf-lesson-header-row {
    margin-bottom: 20px;
}

.gf-lesson-name-input {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #CBD5E1;
    border-radius: 8px;
    font-size: 14.5px;
    font-weight: 600;
    color: #0F172A;
    outline: none;
    transition: border-color 0.15s;
}

.gf-lesson-name-input:focus {
    border-color: var(--gf-primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
}

.gf-type-pills {
    display: flex;
    gap: 10px;
    margin-bottom: 18px;
}

.gf-type-pill-btn {
    padding: 8px 16px;
    border: 1px solid #E2E8F0;
    background: #FFFFFF;
    color: #475569;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.15s;
}

.gf-type-pill-btn.active {
    border-color: var(--gf-primary);
    background: #EEF2FF;
    color: #4F46E5;
}

.gf-media-card {
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    padding: 12px 16px;
    background: #FFFFFF;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}

.gf-media-card-left {
    display: flex;
    align-items: center;
    gap: 12px;
}

.gf-media-thumb {
    width: 54px;
    height: 38px;
    border-radius: 4px;
    background: #0B1727;
    color: #FFFFFF;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    overflow: hidden;
}

.btn-view-details {
    border: 1px solid #D1D5DB;
    background: #FFFFFF;
    color: #374151;
    font-size: 12.5px;
    font-weight: 600;
    padding: 6px 14px;
    border-radius: 6px;
    cursor: pointer;
}

.gf-player-wrap {
    border-radius: 10px;
    overflow: hidden;
    background: #000000;
    box-shadow: 0 4px 14px rgba(0,0,0,0.1);
    margin-top: 14px;
}

.gf-player-wrap video {
    width: 100%;
    max-height: 420px;
    display: block;
}

.gf-pdf-frame {
    width: 100%;
    height: 500px;
    border: 1px solid #CBD5E1;
    border-radius: 8px;
    background: #FFFFFF;
}

.gf-doc-editor {
    width: 100%;
    height: 380px;
    padding: 16px;
    border: 1px solid #CBD5E1;
    border-radius: 8px;
    font-family: inherit;
    font-size: 14px;
    line-height: 1.6;
    color: #1E293B;
    outline: none;
    box-sizing: border-box;
}

.gf-curriculum-settings {
    width: 320px;
    min-width: 320px;
    background: #FFFFFF;
    padding: 24px 20px;
    box-sizing: border-box;
    overflow-y: auto;
}

.gf-settings-title {
    font-size: 17px;
    font-weight: 800;
    color: #0F172A;
    margin: 0 0 18px 0;
    letter-spacing: -0.01em;
}

.gf-setting-item {
    margin-bottom: 16px;
}

.gf-checkbox-label {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    font-size: 13.5px;
    font-weight: 600;
    color: #1E293B;
    cursor: pointer;
    margin-bottom: 2px;
}

.gf-checkbox-label input[type="checkbox"] {
    margin-top: 3px;
    width: 15px;
    height: 15px;
    cursor: pointer;
}

.gf-setting-subtext {
    font-size: 11.5px;
    color: #64748B;
    padding-left: 23px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.btn-save-lesson {
    background: var(--gf-primary);
    color: #FFFFFF !important;
    border: none;
    border-radius: 6px;
    padding: 8px 24px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 2px 4px rgba(99, 102, 241, 0.25);
    transition: all 0.15s;
}

.btn-save-lesson:hover {
    background: var(--gf-primary-hover);
    transform: translateY(-1px);
}
</style>

<!-- Top Dark Navy Navigation Bar -->
<div class="gf-curriculum-navbar">
    <div class="gf-navbar-left">
        <a href="course_list.php" class="gf-back-nav">
            <i class="fa fa-arrow-left"></i> Back
        </a>
        <div class="gf-nav-tabs-bar">
            <a href="course_curriculum.php?code=<?php echo $courseCode; ?>&tab=curriculum" class="gf-nav-tab-item <?php echo ($activeTab === 'curriculum') ? 'active' : ''; ?>"><i class="fa fa-book"></i> Curriculum</a>
            <a href="course_curriculum.php?code=<?php echo $courseCode; ?>&tab=settings" class="gf-nav-tab-item <?php echo ($activeTab === 'settings') ? 'active' : ''; ?>"><i class="fa fa-cog"></i> Settings</a>
            <a href="<?php echo api_get_path(WEB_CODE_PATH); ?>calendar/agenda_js.php?cidReq=<?php echo $courseCode; ?>" class="gf-nav-tab-item" target="_blank"><i class="fa fa-calendar-check-o" style="color: #34D399;"></i> Course Schedule</a>
            <?php if ($assignedBlueprint): ?>
            <a href="javascript:void(0)" onclick="openCertificateModal()" class="gf-nav-tab-item"><i class="fa fa-certificate" style="color: #F59E0B;"></i> Certificate</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="gf-navbar-right">
        <?php if ($assignedBlueprint): ?>
        <button type="button" onclick="openCertificateModal()" class="btn-nav-preview" style="background: rgba(245, 158, 11, 0.15); border-color: rgba(245, 158, 11, 0.4); color: #F59E0B; font-weight: 600; cursor: pointer;">
            <i class="fa fa-certificate"></i> View Certificate
        </button>
        <?php endif; ?>
        <a href="course_list.php" class="btn-nav-courses">
            <i class="fa fa-arrow-left"></i> Back to Courses
        </a>
        <a href="<?php echo api_get_path(WEB_PATH); ?>courses/<?php echo $courseCode; ?>/" target="_blank" class="btn-nav-preview">
            <i class="fa fa-eye"></i> Course preview
        </a>
        <button type="button" class="btn btn-danger btn-sm" onclick="openDeleteCourseModal('<?php echo htmlspecialchars(addslashes($courseCode)); ?>', '<?php echo htmlspecialchars(addslashes($courseTitle)); ?>')" style="background: #EF4444; border-color: #DC2626; color: #FFF; font-weight: 600; padding: 6px 14px; border-radius: 6px; margin-left: 6px;" title="Delete this Course">
            <i class="fa fa-trash"></i> Delete Course
        </button>
    </div>
</div>

<?php if ($activeTab === 'settings'): ?>

    <!-- ================== SETTINGS TAB VIEW ================== -->
    <div class="gf-settings-workspace">
        <div class="gf-settings-header-card">
            <div class="gf-settings-header-left">
                <h2>Course Settings: <?php echo htmlspecialchars($courseTitle); ?></h2>
                <p>Configure course details, category, primary instructor, visibility, and certificate template.</p>
            </div>
            <div class="gf-settings-header-actions">
                <button type="button" class="btn btn-danger" onclick="openDeleteCourseModal('<?php echo htmlspecialchars(addslashes($courseCode)); ?>', '<?php echo htmlspecialchars(addslashes($courseTitle)); ?>')" style="background: #FEF2F2; border-color: #FCA5A5; color: #DC2626; font-weight: 600; margin-right: 8px; border-radius: 6px; padding: 7px 16px;">
                    <i class="fa fa-trash"></i> Delete Course
                </button>
                <a href="course_curriculum.php?code=<?php echo $courseCode; ?>&tab=curriculum" class="btn btn-default" style="font-weight: 600; margin-right: 8px; border-radius: 6px;">
                    <i class="fa fa-list-alt"></i> Manage Curriculum
                </a>
                <button type="submit" form="courseSettingsForm" class="btn btn-primary" style="background: var(--gf-primary); border-color: var(--gf-primary); font-weight: 700; box-shadow: 0 2px 6px rgba(99,102,241,0.3); border-radius: 6px;">
                    <i class="fa fa-check"></i> Save Settings
                </button>
            </div>
        </div>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'settings_saved'): ?>
            <div class="alert alert-success" style="border-radius: 8px; font-weight: 600; margin-bottom: 24px;">
                <i class="fa fa-check-circle"></i> Course settings updated successfully!
            </div>
        <?php endif; ?>

        <form method="post" action="course_curriculum.php?code=<?php echo $courseCode; ?>" id="courseSettingsForm" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save_course_settings">
            <input type="hidden" name="tab" value="settings">

            <div class="gf-settings-grid">
                <!-- Left Column -->
                <div class="gf-settings-col">
                    <div class="gf-card">
                        <h3 class="gf-card-title"><i class="fa fa-info-circle" style="color: var(--gf-primary);"></i> Basic Course Information</h3>
                        
                        <div class="form-group">
                            <label class="gf-label">Course Title *</label>
                            <input type="text" name="title" class="form-control gf-input" required value="<?php echo htmlspecialchars($courseTitle); ?>" placeholder="e.g. Masterclass on Investment Strategies">
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="gf-label">Course Code (ID)</label>
                                    <input type="text" class="form-control gf-input" readonly value="<?php echo htmlspecialchars($courseCode); ?>" style="background: #F1F5F9; color: #64748B;">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="gf-label">Category</label>
                                    <select name="category_code" class="form-control gf-input">
                                        <option value="">-- No Category --</option>
                                        <?php foreach ($allCategories as $cat): ?>
                                            <option value="<?php echo htmlspecialchars($cat['code']); ?>" <?php echo (!empty($courseInfo['category_code']) && $courseInfo['category_code'] === $cat['code']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="gf-label">Language</label>
                                    <select name="course_language" class="form-control gf-input">
                                        <?php foreach ($allLanguages as $langCode => $langName): ?>
                                            <option value="<?php echo htmlspecialchars($langCode); ?>" <?php echo ($currentLanguage === $langCode) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($langName); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="gf-label">Course Visibility</label>
                                    <select name="visibility" class="form-control gf-input">
                                        <option value="1" <?php echo ($courseInfo['visibility'] == 1) ? 'selected' : ''; ?>>Published / Open to Students</option>
                                        <option value="2" <?php echo ($courseInfo['visibility'] == 2) ? 'selected' : ''; ?>>Open to Registered Members</option>
                                        <option value="3" <?php echo ($courseInfo['visibility'] == 3) ? 'selected' : ''; ?>>Private (By Invitation Only)</option>
                                        <option value="0" <?php echo ($courseInfo['visibility'] == 0) ? 'selected' : ''; ?>>Draft / Hidden</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="gf-label">Primary Instructor</label>
                                    <select name="teacher_id" class="form-control gf-input">
                                        <?php foreach ($allTeachers as $t): ?>
                                            <option value="<?php echo $t['id']; ?>" <?php echo ($currentTeacherId == $t['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($t['firstname'].' '.$t['lastname'].' ('.$t['email'].')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="gf-label">Department</label>
                                    <input type="text" name="department_name" class="form-control gf-input" value="<?php echo htmlspecialchars($currentDepartment); ?>" placeholder="e.g. Executive Education">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="gf-label">Department URL</label>
                                    <input type="text" name="department_url" class="form-control gf-input" value="<?php echo htmlspecialchars($currentDepartmentUrl); ?>" placeholder="https://...">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="gf-label">Tags / Keywords</label>
                                    <input type="text" name="extra_tags" class="form-control gf-input" value="<?php echo htmlspecialchars($currentTags); ?>" placeholder="e.g. trading, finance, algorithmic">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="gf-label">Course Description & Overview</label>
                            <textarea name="description" class="form-control gf-input" rows="5" placeholder="Enter an overview of what students will master in this course..."><?php echo htmlspecialchars(!empty($courseInfo['description']) ? $courseInfo['description'] : ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="gf-settings-col">
                    <!-- Course Thumbnail Card -->
                    <div class="gf-card">
                        <h3 class="gf-card-title"><i class="fa fa-image" style="color: #3B82F6;"></i> Course Thumbnail Image</h3>
                        <p style="font-size: 13px; color: #64748B; margin-bottom: 14px;">Upload a high-definition banner image for course cards and catalog preview.</p>

                        <div style="margin-bottom: 16px;">
                            <div id="thumbnailContainer" style="width: 100%; height: 180px; border-radius: 10px; border: 2px dashed #CBD5E1; background: #F8FAFC; overflow: hidden; display: flex; align-items: center; justify-content: center; position: relative;">
                                <img id="thumbnailPreviewImg" src="<?php echo $currentThumbnailUrl; ?>" style="<?php echo $hasCustomThumbnail ? '' : 'display: none;'; ?> width: 100%; height: 100%; object-fit: cover;" alt="Course Thumbnail Preview">
                                <div id="thumbnailPlaceholder" style="<?php echo $hasCustomThumbnail ? 'display: none;' : ''; ?> text-align: center; color: #94A3B8; padding: 20px;">
                                    <i class="fa fa-picture-o" style="font-size: 38px; margin-bottom: 8px; opacity: 0.5;"></i>
                                    <p style="font-size: 12.5px; margin: 0; font-weight: 500;">No custom thumbnail uploaded yet.<br>Default theme banner is in use.</p>
                                </div>
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 10px;">
                            <label class="gf-label" style="display: flex; justify-content: space-between; align-items: center;">
                                <span>Choose New Image:</span>
                                <span style="font-size: 11px; color: #64748B; font-weight: normal;">JPG, PNG, WebP (4:3 recommended)</span>
                            </label>
                            <input type="file" name="course_thumbnail" id="courseThumbnailInput" class="form-control" accept="image/*" onchange="previewThumbnail(this)">
                        </div>

                        <?php if ($hasCustomThumbnail): ?>
                            <div style="background: #FEF2F2; border: 1px solid #FEE2E2; border-radius: 6px; padding: 8px 12px; margin-top: 10px;">
                                <label style="color: #DC2626; font-size: 12px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; margin: 0;">
                                    <input type="checkbox" name="delete_thumbnail" value="1"> <i class="fa fa-trash"></i> Delete current thumbnail and reset to default
                                </label>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Certificate Blueprint Card -->
                    <div class="gf-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                            <h3 class="gf-card-title" style="margin: 0;"><i class="fa fa-certificate" style="color: #F59E0B;"></i> Certificate Blueprint</h3>
                            <a href="certificate_blueprints.php" target="_blank" style="font-size: 12.5px; color: var(--gf-primary); font-weight: 600;">
                                <i class="fa fa-external-link"></i> Blueprints Hub
                            </a>
                        </div>
                        <p style="font-size: 13px; color: #64748B; margin-bottom: 14px;">Enable or assign a certificate blueprint issued to learners upon completing this specific course.</p>

                        <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 12px 14px; margin-bottom: 14px; display: flex; align-items: center; justify-content: space-between;">
                            <div>
                                <strong style="font-size: 13px; color: #0F172A; display: block;">Enable Course Certificate</strong>
                                <span style="font-size: 11.5px; color: #64748B;">Only courses with an assigned blueprint will award certificates to students.</span>
                            </div>
                            <div>
                                <?php if ($assignedBlueprint): ?>
                                    <span class="label label-success" style="font-size: 11px; padding: 4px 8px; border-radius: 4px;"><i class="fa fa-check-circle"></i> Enabled</span>
                                <?php else: ?>
                                    <span class="label label-default" style="font-size: 11px; padding: 4px 8px; border-radius: 4px; background: #E2E8F0; color: #64748B;"><i class="fa fa-ban"></i> Disabled</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="gf-label">Assigned Blueprint:</label>
                            <select name="blueprint_id" id="blueprint_id_select" class="form-control gf-input" style="font-weight: 600;">
                                <option value="0" <?php echo ($assignedBlueprintId == 0) ? 'selected' : ''; ?>>-- No Certificate (Disabled) --</option>
                                <?php foreach ($allBlueprints as $bp): ?>
                                    <option value="<?php echo $bp['id']; ?>" <?php echo ($assignedBlueprintId == $bp['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($bp['name'].' ('.$bp['title'].')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <?php if ($assignedBlueprint): ?>
                            <div style="background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 8px; padding: 14px; margin-top: 12px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                                <div>
                                    <strong style="font-size: 13.5px; color: #166534;"><i class="fa fa-certificate text-warning"></i> <?php echo htmlspecialchars($assignedBlueprint['name']); ?></strong>
                                    <span style="display: block; font-size: 12px; color: #475569;">Template: <?php echo htmlspecialchars($assignedBlueprint['template_key']); ?> • Issuer: <?php echo htmlspecialchars($assignedBlueprint['issuer_name']); ?></span>
                                </div>
                                <div style="display: flex; gap: 8px;">
                                    <button type="button" onclick="openCertificateModal()" class="btn btn-xs btn-success" style="font-weight: 600; padding: 6px 12px; border-radius: 6px; cursor: pointer;">
                                        <i class="fa fa-eye"></i> View Certificate
                                    </button>
                                    <a href="certificate_editor.php?id=<?php echo $assignedBlueprint['id']; ?>&course=<?php echo $courseCode; ?>" target="_blank" class="btn btn-xs btn-default" style="font-weight: 600; padding: 6px 12px; border-radius: 6px; border-color: #86EFAC;">
                                        <i class="fa fa-pencil"></i> Edit Blueprint
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Save Action Card -->
                    <div class="gf-card" style="background: #F8FAFC; border-color: #CBD5E1; text-align: center; padding: 24px;">
                        <h4 style="font-weight: 700; color: #0F172A; margin-top: 0;">Ready to apply changes?</h4>
                        <p style="color: #64748B; font-size: 13px; margin-bottom: 16px;">Updates to settings and certificates will take effect immediately across all learner dashboards.</p>
                        <button type="submit" class="btn btn-primary btn-lg" style="background: var(--gf-primary); border-color: var(--gf-primary); font-weight: 700; padding: 10px 32px; border-radius: 8px; box-shadow: 0 4px 12px rgba(99,102,241,0.3); width: 100%;">
                            <i class="fa fa-check"></i> Save Course Settings
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <!-- Danger Zone Card -->
        <div class="gf-card" style="border: 1px solid #FECACA; background: #FFF5F5; margin-top: 24px; padding: 24px; border-radius: 12px;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div style="max-width: 750px;">
                    <h4 style="font-weight: 700; color: #DC2626; margin: 0 0 6px 0; display: flex; align-items: center; gap: 8px; font-size: 16px;">
                        <i class="fa fa-exclamation-triangle"></i> Danger Zone &mdash; Delete Course
                    </h4>
                    <p style="color: #7F1D1D; font-size: 13px; margin: 0; line-height: 1.5;">
                        Permanently delete <strong><?php echo htmlspecialchars($courseTitle); ?> (<?php echo htmlspecialchars($courseCode); ?>)</strong>. All curriculum chapters, lessons, attached files, student enrollment records, and grade progress will be permanently erased. This action cannot be undone.
                    </p>
                </div>
                <div>
                    <button type="button" class="btn btn-danger" onclick="openDeleteCourseModal('<?php echo htmlspecialchars(addslashes($courseCode)); ?>', '<?php echo htmlspecialchars(addslashes($courseTitle)); ?>')" style="background: #DC2626; border-color: #DC2626; font-weight: 700; padding: 10px 24px; border-radius: 8px; box-shadow: 0 2px 8px rgba(220,38,38,0.3); font-size: 14px;">
                        <i class="fa fa-trash"></i> Delete Course
                    </button>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>

    <!-- ================== CURRICULUM TAB VIEW ================== -->
    <div class="gf-curriculum-workspace">

        <!-- LEFT: Chapters & Lessons List Explorer -->
        <aside class="gf-curriculum-sidebar">
            <div>
                <div class="gf-sidebar-header">
                    <h2 title="<?php echo htmlspecialchars($courseTitle); ?>"><?php echo htmlspecialchars($courseTitle); ?></h2>
                    <span>Curriculum</span>
                </div>

                <div class="gf-chapter-list">
                    <?php if (empty($chapters)): ?>
                        <p class="text-muted text-center" style="font-size: 13px; padding: 20px;">No chapters found. Click below to add your first chapter.</p>
                    <?php else: ?>
                        <?php foreach ($chapters as $cIdx => $chap): ?>
                            <div class="gf-chapter-card <?php echo ($activeLesson && $activeLesson['chapter_id'] == $chap['id']) ? 'active' : ''; ?>">
                                <div class="gf-chapter-header">
                                    <div onclick="toggleChapterAccordion('chap_<?php echo $chap['id']; ?>')" style="flex: 1; cursor: pointer; display: flex; align-items: center; gap: 8px; overflow: hidden;">
                                        <i class="fa fa-folder-open-o" style="color: #6366F1; font-size: 14px;"></i>
                                        <strong style="font-size: 13px; color: #0F172A; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <?php echo ($cIdx + 1); ?>. <?php echo htmlspecialchars($chap['title']); ?>
                                        </strong>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <button type="button" class="btn-del-chapter" onclick="event.stopPropagation(); openDeleteChapterModal(<?php echo $chap['id']; ?>, '<?php echo htmlspecialchars(addslashes($chap['title'])); ?>')" title="Delete Chapter & All its Lessons">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                        <i class="fa fa-chevron-down" onclick="toggleChapterAccordion('chap_<?php echo $chap['id']; ?>')" style="font-size: 11px; color: #94A3B8; cursor: pointer; padding: 4px;"></i>
                                    </div>
                                </div>
                                <div class="gf-chapter-lessons" id="chap_<?php echo $chap['id']; ?>">
                                    <?php if (!empty($chap['lessons'])): ?>
                                        <?php foreach ($chap['lessons'] as $lIdx => $les): 
                                            $isLesActive = ($activeLesson && $activeLesson['id'] == $les['id']);
                                            $badgeClass = 'badge-video';
                                            $badgeIcon = 'fa-play-circle-o';
                                            if ($les['lesson_type'] === 'pdf') {
                                                $badgeClass = 'badge-pdf';
                                                $badgeIcon = 'fa-file-pdf-o';
                                            } elseif ($les['lesson_type'] === 'document') {
                                                $badgeClass = 'badge-document';
                                                $badgeIcon = 'fa-file-text-o';
                                            }
                                        ?>
                                            <a href="course_curriculum.php?code=<?php echo $courseCode; ?>&tab=curriculum&lesson_id=<?php echo $les['id']; ?>" class="gf-lesson-item <?php echo $isLesActive ? 'active' : ''; ?>">
                                                <div>
                                                    <i class="fa fa-plus" style="font-size: 11px; color: #94A3B8; margin-right: 6px;"></i>
                                                    <span class="gf-lesson-title"><?php echo ($lIdx + 1); ?>. <?php echo htmlspecialchars($les['title']); ?></span>
                                                </div>
                                                <span class="gf-lesson-badge <?php echo $badgeClass; ?>">
                                                    <i class="fa <?php echo $badgeIcon; ?>"></i> <?php echo strtoupper($les['lesson_type']); ?>
                                                </span>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>

                                    <button type="button" class="btn-add-lesson-mini" onclick="openAddLessonModal(<?php echo $chap['id']; ?>, '<?php echo htmlspecialchars(addslashes($chap['title'])); ?>')">
                                        <i class="fa fa-plus"></i> Add Lesson
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <button type="button" class="btn-add-chapter" onclick="openCreateChapterModal()">
                    <i class="fa fa-plus"></i> Add Chapter
                </button>
            </div>
        </aside>

        <!-- CENTER & RIGHT: Lesson Builder Workspace -->
        <main class="gf-curriculum-main">
            <?php if ($activeLesson): ?>
                <form method="post" action="course_curriculum.php?code=<?php echo $courseCode; ?>" enctype="multipart/form-data" id="formLessonEditor">
                    <input type="hidden" name="action" value="save_lesson">
                    <input type="hidden" name="tab" value="curriculum">
                    <input type="hidden" name="lesson_id" value="<?php echo $activeLesson['id']; ?>">
                    <input type="hidden" name="lesson_type" id="inputLessonType" value="<?php echo htmlspecialchars($activeLesson['lesson_type']); ?>">

                    <div style="display: flex; gap: 24px; align-items: flex-start;">
                        <!-- Middle Stage Area -->
                        <div style="flex: 1;">
                            <!-- Lesson Title Header Input -->
                            <div class="gf-lesson-header-row" style="display: flex; gap: 12px; align-items: center;">
                                <input type="text" name="title" class="gf-lesson-name-input" style="flex: 1;" value="<?php echo htmlspecialchars($activeLesson['title']); ?>" required placeholder="Enter lesson name...">
                                <button type="submit" class="btn btn-primary" style="background: var(--gf-primary); border-color: var(--gf-primary); font-weight: 700; padding: 10px 20px; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 8px rgba(99,102,241,0.3);">
                                    <i class="fa fa-check"></i> Save Lesson
                                </button>
                            </div>

                            <!-- Format Selector (Video, PDF, Study Material, Audio) -->
                            <div class="gf-type-pills">
                                <button type="button" class="gf-type-pill-btn <?php echo ($activeLesson['lesson_type'] === 'video') ? 'active' : ''; ?>" onclick="switchLessonFormat('video', this)">
                                    <i class="fa fa-play-circle-o"></i> Video
                                </button>
                                <button type="button" class="gf-type-pill-btn <?php echo ($activeLesson['lesson_type'] === 'pdf') ? 'active' : ''; ?>" onclick="switchLessonFormat('pdf', this)">
                                    <i class="fa fa-file-pdf-o"></i> PDF Document
                                </button>
                                <button type="button" class="gf-type-pill-btn <?php echo ($activeLesson['lesson_type'] === 'document') ? 'active' : ''; ?>" onclick="switchLessonFormat('document', this)">
                                    <i class="fa fa-file-text-o"></i> Study Material
                                </button>
                                <button type="button" class="gf-type-pill-btn <?php echo ($activeLesson['lesson_type'] === 'audio') ? 'active' : ''; ?>" onclick="switchLessonFormat('audio', this)">
                                    <i class="fa fa-volume-up"></i> Audio Track
                                </button>
                            </div>

                            <!-- 1. VIDEO FORMAT SECTION -->
                            <div id="sectionVideo" style="display: <?php echo ($activeLesson['lesson_type'] === 'video') ? 'block' : 'none'; ?>;">
                                <!-- Upload Dropzone -->
                                <div class="gf-dropzone" id="videoDropzone" onclick="document.getElementById('videoFilePicker').click()">
                                    <div class="gf-dropzone-icon"><i class="fa fa-cloud-upload"></i></div>
                                    <strong style="font-size: 14.5px; color: #0F172A; display: block; margin-bottom: 4px;">Drag & Drop Video File Here or Click to Browse</strong>
                                    <span style="font-size: 12px; color: #64748B;">Supports MP4, WebM, MKV, MOV, AVI up to 128 MB • Fast direct server sync</span>
                                    <input type="file" id="videoFilePicker" name="video_file" accept="video/mp4,video/webm,video/mkv,video/quicktime,video/*" style="display: none;" onchange="uploadFileViaAjax(this, 'video')">
                                </div>

                                <!-- AJAX Progress Bar Container -->
                                <div id="videoUploadProgress" class="gf-upload-progress" style="display: none;">
                                    <div style="display: flex; justify-content: space-between; font-size: 12.5px; font-weight: 600; color: #1E293B;">
                                        <span id="videoUploadStatusText"><i class="fa fa-spinner fa-spin"></i> Uploading video...</span>
                                        <span id="videoUploadPercentText">0%</span>
                                    </div>
                                    <div class="gf-progress-track">
                                        <div class="gf-progress-bar-inner" id="videoProgressBarFill"></div>
                                    </div>
                                </div>

                                <div class="form-group" style="margin: 14px 0;">
                                    <label style="font-size: 12.5px; font-weight: 600; color: #475569;">Video URL or Online Stream (MP4, YouTube, Vimeo):</label>
                                    <input type="text" name="content_url" id="adminVideoUrlInput" class="form-control" value="<?php echo htmlspecialchars($activeLesson['content_url']); ?>" placeholder="https://example.com/video.mp4 or YouTube / Vimeo URL" onchange="updateVideoPlayerSrc(this.value)">
                                </div>

                                <?php
                                    $currRawVideoUrl = !empty($activeLesson['content_url']) ? CourseCurriculum::resolveMediaUrl($activeLesson['content_url']) : 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4';
                                    $currEmbedVideoUrl = CourseCurriculum::formatVideoEmbedUrl($currRawVideoUrl);
                                    $currIsVideoEmbed = (strpos($currEmbedVideoUrl, 'youtube-nocookie.com') !== false || strpos($currEmbedVideoUrl, 'player.vimeo.com') !== false);
                                ?>
                                <div class="gf-player-wrap" style="border-radius: 10px; overflow: hidden; background: #000; box-shadow: 0 4px 15px rgba(0,0,0,0.2); position: relative; min-height: 260px;">
                                    <iframe id="mainVideoIframe" src="<?php echo $currIsVideoEmbed ? htmlspecialchars($currEmbedVideoUrl) : ''; ?>" style="width: 100%; height: 380px; border: none; display: <?php echo $currIsVideoEmbed ? 'block' : 'none'; ?>;" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                                    <div id="mainVideoWrap" style="width: 100%; display: <?php echo $currIsVideoEmbed ? 'none' : 'block'; ?>;">
                                        <video id="mainVideoPlayer" class="skip" controls playsinline preload="metadata" style="width: 100%; max-height: 420px; background: #000; display: block;" <?php if (!$currIsVideoEmbed && !empty($currRawVideoUrl)): ?>src="<?php echo htmlspecialchars($currRawVideoUrl); ?>"<?php endif; ?>>
                                            <?php if (!$currIsVideoEmbed && !empty($currRawVideoUrl)): ?>
                                            <source id="mainVideoSource" src="<?php echo htmlspecialchars($currRawVideoUrl); ?>" type="video/mp4">
                                            <?php endif; ?>
                                            Your browser does not support HTML video.
                                        </video>
                                    </div>
                                </div>
                            </div>

                            <!-- 2. PDF FORMAT SECTION -->
                            <div id="sectionPdf" style="display: <?php echo ($activeLesson['lesson_type'] === 'pdf') ? 'block' : 'none'; ?>;">
                                <!-- PDF Dropzone -->
                                <div class="gf-dropzone" id="pdfDropzone" onclick="document.getElementById('pdfFilePicker').click()">
                                    <div class="gf-dropzone-icon" style="color: #EF4444;"><i class="fa fa-file-pdf-o"></i></div>
                                    <strong style="font-size: 14.5px; color: #0F172A; display: block; margin-bottom: 4px;">Drag & Drop PDF Document Here or Click to Browse</strong>
                                    <span style="font-size: 12px; color: #64748B;">Upload lecture slides, course handbook, or study guide</span>
                                    <input type="file" id="pdfFilePicker" name="pdf_file" accept="application/pdf" style="display: none;" onchange="uploadFileViaAjax(this, 'pdf')">
                                </div>

                                <div id="pdfUploadProgress" class="gf-upload-progress" style="display: none;">
                                    <div style="display: flex; justify-content: space-between; font-size: 12.5px; font-weight: 600; color: #1E293B;">
                                        <span id="pdfUploadStatusText"><i class="fa fa-spinner fa-spin"></i> Uploading PDF...</span>
                                        <span id="pdfUploadPercentText">0%</span>
                                    </div>
                                    <div class="gf-progress-track">
                                        <div class="gf-progress-bar-inner" id="pdfProgressBarFill"></div>
                                    </div>
                                </div>

                                <div class="form-group" style="margin: 14px 0;">
                                    <label style="font-size: 12.5px; font-weight: 600; color: #475569;">PDF Document URL:</label>
                                    <input type="text" id="adminPdfUrlInput" class="form-control" value="<?php echo ($activeLesson['lesson_type'] === 'pdf') ? htmlspecialchars($activeLesson['content_url']) : ''; ?>" placeholder="https://example.com/document.pdf" onchange="updatePdfFrameSrc(this.value)">
                                </div>

                                <?php $actualPdfUrl = ($activeLesson['lesson_type'] === 'pdf' && !empty($activeLesson['content_url'])) ? CourseCurriculum::resolveMediaUrl($activeLesson['content_url']) : ''; ?>
                                <iframe id="adminPdfFrame" src="<?php echo !empty($actualPdfUrl) ? htmlspecialchars($actualPdfUrl) : 'about:blank'; ?>" class="gf-pdf-frame"></iframe>
                            </div>

                            <!-- 3. STUDY MATERIAL / DOCUMENT FORMAT SECTION -->
                            <div id="sectionDocument" style="display: <?php echo ($activeLesson['lesson_type'] === 'document') ? 'block' : 'none'; ?>;">
                                <!-- Document Dropzone -->
                                <div class="gf-dropzone" id="docDropzone" onclick="document.getElementById('docFilePicker').click()">
                                    <div class="gf-dropzone-icon" style="color: #3B82F6;"><i class="fa fa-file-text-o"></i></div>
                                    <strong style="font-size: 14.5px; color: #0F172A; display: block; margin-bottom: 4px;">Drag & Drop Study Material (DOCX, PPTX, XLSX, TXT, EPUB, ZIP)</strong>
                                    <span style="font-size: 12px; color: #64748B;">Upload supplementary files or write article notes below</span>
                                    <input type="file" id="docFilePicker" name="doc_file" accept=".doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.epub,.rtf,.pdf,.zip" style="display: none;" onchange="uploadFileViaAjax(this, 'document')">
                                </div>

                                <div id="docUploadProgress" class="gf-upload-progress" style="display: none;">
                                    <div style="display: flex; justify-content: space-between; font-size: 12.5px; font-weight: 600; color: #1E293B;">
                                        <span id="docUploadStatusText"><i class="fa fa-spinner fa-spin"></i> Uploading study material...</span>
                                        <span id="docUploadPercentText">0%</span>
                                    </div>
                                    <div class="gf-progress-track">
                                        <div class="gf-progress-bar-inner" id="docProgressBarFill"></div>
                                    </div>
                                </div>

                                <div class="form-group" style="margin-top: 14px;">
                                    <label style="font-size: 12.5px; font-weight: 600; color: #475569;">Lecture Notes & Article Content (HTML / Rich Text):</label>
                                    <textarea name="content_text" class="gf-doc-editor" placeholder="Write rich lesson materials, lecture notes, or key summaries for students..."><?php echo htmlspecialchars($activeLesson['content_text']); ?></textarea>
                                </div>
                            </div>

                            <!-- 4. AUDIO FORMAT SECTION -->
                            <div id="sectionAudio" style="display: <?php echo ($activeLesson['lesson_type'] === 'audio') ? 'block' : 'none'; ?>;">
                                <!-- Audio Dropzone -->
                                <div class="gf-dropzone" id="audioDropzone" onclick="document.getElementById('audioFilePicker').click()">
                                    <div class="gf-dropzone-icon" style="color: #10B981;"><i class="fa fa-volume-up"></i></div>
                                    <strong style="font-size: 14.5px; color: #0F172A; display: block; margin-bottom: 4px;">Drag & Drop Audio Track Here or Click to Browse</strong>
                                    <span style="font-size: 12px; color: #64748B;">Upload MP3, WAV, AAC, M4A audio podcast or lecture</span>
                                    <input type="file" id="audioFilePicker" name="audio_file" accept="audio/*,.mp3,.wav,.m4a,.ogg" style="display: none;" onchange="uploadFileViaAjax(this, 'audio')">
                                </div>

                                <div id="audioUploadProgress" class="gf-upload-progress" style="display: none;">
                                    <div style="display: flex; justify-content: space-between; font-size: 12.5px; font-weight: 600; color: #1E293B;">
                                        <span id="audioUploadStatusText"><i class="fa fa-spinner fa-spin"></i> Uploading audio...</span>
                                        <span id="audioUploadPercentText">0%</span>
                                    </div>
                                    <div class="gf-progress-track">
                                        <div class="gf-progress-bar-inner" id="audioProgressBarFill"></div>
                                    </div>
                                </div>

                                <?php $actualAudioUrl = ($activeLesson['lesson_type'] === 'audio' && !empty($activeLesson['content_url'])) ? CourseCurriculum::resolveMediaUrl($activeLesson['content_url']) : ''; ?>
                                <div style="margin-top: 20px; background: #0F172A; padding: 24px; border-radius: 12px; text-align: center;">
                                    <audio id="mainAudioPlayer" class="skip" controls style="width: 100%; border-radius: 30px;" <?php if (!empty($actualAudioUrl)): ?>src="<?php echo htmlspecialchars($actualAudioUrl); ?>"<?php endif; ?>>
                                        <?php if (!empty($actualAudioUrl)): ?>
                                        <source src="<?php echo htmlspecialchars($actualAudioUrl); ?>" type="audio/mpeg">
                                        <?php endif; ?>
                                        Your browser does not support audio playback.
                                    </audio>
                                </div>
                            </div>
                        </div>

                                                <!-- RIGHT: Lesson Settings Sidebar -->
                        <aside class="gf-curriculum-settings">
                            <h3 class="gf-settings-title">Lesson Settings</h3>

                            <div class="gf-setting-item">
                                <label class="gf-checkbox-label">
                                    <input type="checkbox" name="is_draft" value="1" <?php echo $activeLesson['is_draft'] ? 'checked' : ''; ?>>
                                    Draft
                                </label>
                                <span class="gf-setting-subtext"><i class="fa fa-info-circle"></i> Hide lesson from students</span>
                            </div>

                            <div class="gf-setting-item">
                                <label class="gf-checkbox-label">
                                    <input type="checkbox" name="is_free_preview" value="1" <?php echo $activeLesson['is_free_preview'] ? 'checked' : ''; ?>>
                                    Free preview
                                </label>
                                <span class="gf-setting-subtext"><i class="fa fa-info-circle"></i> Allow unregistered guests to preview</span>
                            </div>

                            <div class="gf-setting-item">
                                <label class="gf-checkbox-label">
                                    <input type="checkbox" name="is_prerequisite" value="1" <?php echo $activeLesson['is_prerequisite'] ? 'checked' : ''; ?>>
                                    Prerequisite
                                </label>
                                <span class="gf-setting-subtext"><i class="fa fa-info-circle"></i> Must be completed before next</span>
                            </div>

                            <div class="gf-setting-item">
                                <label class="gf-checkbox-label">
                                    <input type="checkbox" name="downloadable_web" value="1" <?php echo $activeLesson['downloadable_web'] ? 'checked' : ''; ?>>
                                    Make downloadable on web
                                </label>
                                <span class="gf-setting-subtext"><i class="fa fa-info-circle"></i> Enable direct download on desktop</span>
                            </div>

                            <div class="gf-setting-item">
                                <label class="gf-checkbox-label">
                                    <input type="checkbox" name="downloadable_app" value="1" <?php echo $activeLesson['downloadable_app'] ? 'checked' : ''; ?>>
                                    Make downloadable on app
                                </label>
                                <span class="gf-setting-subtext"><i class="fa fa-info-circle"></i> Enable offline saving in mobile apps</span>
                            </div>

                            <div class="gf-setting-item">
                                <label class="gf-checkbox-label">
                                    <input type="checkbox" name="enable_discussions" value="1" <?php echo $activeLesson['enable_discussions'] ? 'checked' : ''; ?>>
                                    Enable discussions
                                </label>
                                <span class="gf-setting-subtext"><i class="fa fa-info-circle"></i> Allow student comments</span>
                            </div>

                            <div class="gf-setting-item">
                                <label class="gf-checkbox-label">
                                    <input type="checkbox" name="completion_required" value="1" <?php echo $activeLesson['completion_required'] ? 'checked' : ''; ?>>
                                    Set completion requirement
                                </label>
                                <span class="gf-setting-subtext"><i class="fa fa-info-circle"></i> Required for certificate unlock</span>
                            </div>

                            <div class="gf-setting-item">
                                <label class="gf-checkbox-label">
                                    <input type="checkbox" name="block_forward_seeking" value="1" <?php echo $activeLesson['block_forward_seeking'] ? 'checked' : ''; ?>>
                                    Block forward seeking
                                </label>
                                <span class="gf-setting-subtext"><i class="fa fa-info-circle"></i> Prevent skipping video ahead</span>
                            </div>

                            <div class="gf-setting-item">
                                <label class="gf-checkbox-label">
                                    <input type="checkbox" name="autoplay" value="1" <?php echo $activeLesson['autoplay'] ? 'checked' : ''; ?>>
                                    Autoplay
                                </label>
                                <span class="gf-setting-subtext"><i class="fa fa-info-circle"></i> Auto start video on load</span>
                            </div>

                            <div class="gf-setting-item">
                                <label class="gf-checkbox-label">
                                    <input type="checkbox" name="enable_analytics" value="1" <?php echo $activeLesson['enable_analytics'] ? 'checked' : ''; ?>>
                                    Enable Video Analytics
                                </label>
                                <span class="gf-setting-subtext"><i class="fa fa-info-circle"></i> Track watch-time & heatmaps</span>
                            </div>

                            <div style="margin-top: 24px; display: flex; flex-direction: column; gap: 10px;">
                                <button type="submit" class="btn-save-lesson">
                                    <i class="fa fa-check"></i> Save Lesson
                                </button>
                                
                                <button type="submit" name="action" value="delete_lesson" class="btn btn-sm btn-default" style="color: #EF4444; border-color: #FCA5A5;" onclick="return confirm('Permanently delete this lesson?');">
                                    <i class="fa fa-trash-o"></i> Delete Lesson
                                </button>
                            </div>
                        </aside>
                    </div>
                </form>
            <?php else: ?>
                <div style="padding: 60px; text-align: center; color: #94A3B8;">
                    <i class="fa fa-book" style="font-size: 48px; opacity: 0.4; margin-bottom: 16px;"></i>
                    <h2>No lessons in this course yet.</h2>
                    <p>Click "Add Chapter" on the left to begin constructing your curriculum.</p>
                </div>
            <?php endif; ?>
        </main>

    </div>

    <!-- MODAL: Create New Chapter -->
    <div class="modal fade" id="modalCreateChapter" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content" style="border-radius: 12px; overflow: hidden;">
                <form method="post" action="course_curriculum.php?code=<?php echo $courseCode; ?>">
                    <input type="hidden" name="action" value="create_chapter">
                    <input type="hidden" name="tab" value="curriculum">
                    
                    <div class="modal-header" style="background: #0B1727; color: #FFFFFF; padding: 14px 18px;">
                        <button type="button" class="close" data-dismiss="modal" style="color:#fff; opacity:0.8;"><span>&times;</span></button>
                        <h5 class="modal-title" style="font-weight: 700;"><i class="fa fa-folder-plus"></i> Create New Chapter</h5>
                    </div>
                    <div class="modal-body" style="padding: 18px;">
                        <div class="form-group">
                            <label style="font-size: 13px; font-weight: 600;">Chapter / Module Title *</label>
                            <input type="text" name="chapter_title" class="form-control" required placeholder="e.g. Chapter 3: Advanced Portfolio Construction">
                        </div>
                    </div>
                    <div class="modal-footer" style="padding: 10px 18px; background: #F8FAFC;">
                        <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm" style="background: var(--gf-primary); border-color: var(--gf-primary); font-weight: 600;">Create Chapter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: Add New Lesson -->
    <div class="modal fade" id="modalAddLesson" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content" style="border-radius: 12px; overflow: hidden;">
                <form method="post" action="course_curriculum.php?code=<?php echo $courseCode; ?>">
                    <input type="hidden" name="action" value="create_lesson">
                    <input type="hidden" name="tab" value="curriculum">
                    <input type="hidden" name="chapter_id" id="addLessonChapterId" value="">
                    
                    <div class="modal-header" style="background: #0B1727; color: #FFFFFF; padding: 16px 20px;">
                        <button type="button" class="close" data-dismiss="modal" style="color:#fff; opacity:0.8;"><span>&times;</span></button>
                        <h4 class="modal-title" style="font-weight: 700; font-size: 16px;" id="addLessonModalTitle"><i class="fa fa-plus-circle"></i> Add Lesson</h4>
                    </div>
                    <div class="modal-body" style="padding: 20px;">
                        <div class="form-group">
                            <label style="font-size: 13px; font-weight: 600;">Lesson Title *</label>
                            <input type="text" name="lesson_title" class="form-control" required placeholder="e.g. Asset Allocation Strategies">
                        </div>
                        <div class="form-group">
                            <label style="font-size: 13px; font-weight: 600;">Lesson Format:</label>
                            <select name="lesson_type" class="form-control" style="font-size: 13.5px;">
                                <option value="video">Video (MP4, YouTube, Vimeo)</option>
                                <option value="pdf">PDF Document</option>
                                <option value="document">Rich Article / Study Material</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer" style="padding: 12px 20px; background: #F8FAFC;">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="background: var(--gf-primary); border-color: var(--gf-primary); font-weight: 600;">Add Lesson</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php endif; ?>

    <!-- Modal: Delete Chapter Confirmation -->
    <div class="modal fade" id="modalDeleteChapter" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document" style="max-width: 440px;">
            <div class="modal-content" style="border-radius: 12px; overflow: hidden; border: none; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);">
                <form method="post" action="course_curriculum.php?code=<?php echo $courseCode; ?>" id="deleteChapterForm">
                    <input type="hidden" name="action" value="delete_chapter">
                    <input type="hidden" name="tab" value="curriculum">
                    <input type="hidden" name="chapter_id" id="deleteModalChapterId" value="">
                    
                    <div class="modal-header" style="background: #FEF2F2; border-bottom: 1px solid #FEE2E2; padding: 16px 20px;">
                        <button type="button" class="close" data-dismiss="modal" style="font-size: 22px; color: #991B1B;"><span>&times;</span></button>
                        <h4 class="modal-title" style="color: #991B1B; font-weight: 700; display: flex; align-items: center; gap: 8px; margin: 0; font-size: 16px;">
                            <i class="fa fa-trash" style="color: #DC2626;"></i> Delete Module / Chapter
                        </h4>
                    </div>
                    <div class="modal-body" style="padding: 20px;">
                        <p style="font-size: 13.5px; color: #374151; margin-bottom: 12px;">
                            Are you sure you want to permanently delete this module?
                        </p>
                        <div style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px; padding: 12px 14px; margin-bottom: 12px;">
                            <strong style="font-size: 14.5px; color: #111827;" id="deleteModalChapterTitle"></strong>
                        </div>
                        <div class="alert alert-warning" style="font-size: 12px; margin: 0; padding: 8px 12px; border-radius: 6px;">
                            <i class="fa fa-exclamation-triangle"></i> All lessons, uploaded videos, and study materials inside this chapter will be permanently erased.
                        </div>
                    </div>
                    <div class="modal-footer" style="background: #F9FAFB; border-top: 1px solid #E5E7EB; padding: 12px 20px; display: flex; justify-content: flex-end; gap: 10px;">
                        <button type="button" class="btn btn-default" data-dismiss="modal" style="font-weight: 600; padding: 7px 16px; border-radius: 6px;">Cancel</button>
                        <button type="submit" class="btn btn-danger" style="background: #DC2626; border-color: #DC2626; font-weight: 700; padding: 7px 18px; border-radius: 6px;">
                            <i class="fa fa-trash"></i> Delete Chapter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Delete Course Confirmation -->
    
    

    <div class="modal fade" id="modalDeleteCourse" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document" style="max-width: 480px;">
            <div class="modal-content" style="border-radius: 12px; overflow: hidden; border: none; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);">
                <form method="post" action="course_curriculum.php?code=<?php echo $courseCode; ?>" id="deleteCourseForm">
                    <input type="hidden" name="action" value="delete_course">
                    <input type="hidden" name="course_code" id="deleteModalCourseCode" value="<?php echo htmlspecialchars($courseCode); ?>">
                    
                    <div class="modal-header" style="background: #FEF2F2; border-bottom: 1px solid #FEE2E2; padding: 18px 24px;">
                        <button type="button" class="close" data-dismiss="modal" style="font-size: 24px; color: #991B1B;"><span>&times;</span></button>
                        <h4 class="modal-title" style="color: #991B1B; font-weight: 700; display: flex; align-items: center; gap: 10px; margin: 0; font-size: 17px;">
                            <i class="fa fa-exclamation-triangle" style="color: #DC2626; font-size: 20px;"></i> Delete Course
                        </h4>
                    </div>
                    <div class="modal-body" style="padding: 24px;">
                        <p style="font-size: 14px; color: #374151; margin-bottom: 16px;">
                            Are you sure you want to permanently delete this course?
                        </p>
                        <div style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px; padding: 14px 16px; margin-bottom: 16px;">
                            <div style="font-weight: 700; color: #111827; font-size: 15px;" id="deleteModalCourseTitle"><?php echo htmlspecialchars($courseTitle); ?></div>
                            <div style="font-size: 12.5px; color: #6B7280; margin-top: 2px;">Code: <code id="deleteModalCourseCodeDisplay" style="color: #4F46E5; background: #EEF2FF;"><?php echo htmlspecialchars($courseCode); ?></code></div>
                        </div>
                        <div class="alert alert-danger" style="font-size: 12.5px; margin: 0; border-radius: 6px; padding: 10px 14px;">
                            <i class="fa fa-warning"></i> <strong>Warning:</strong> All lessons, student enrollments, quizzes, and files will be permanently erased.
                        </div>
                    </div>
                    <div class="modal-footer" style="background: #F9FAFB; border-top: 1px solid #E5E7EB; padding: 14px 24px; display: flex; justify-content: flex-end; gap: 10px;">
                        <button type="button" class="btn btn-default" data-dismiss="modal" style="font-weight: 600; padding: 8px 18px; border-radius: 6px;">Cancel</button>
                        <button type="submit" class="btn btn-danger" style="background: #DC2626; border-color: #DC2626; font-weight: 700; padding: 8px 20px; border-radius: 6px; box-shadow: 0 2px 6px rgba(220,38,38,0.3);">
                            <i class="fa fa-trash"></i> Yes, Delete Course
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<script>



function showModalSafe(modalId) {
    var $modal = $('#' + modalId);
    if ($modal.parent()[0] !== document.body) {
        $modal.appendTo('body');
    }
    $modal.modal('show');
}

function openDeleteChapterModal(chapId, chapTitle) {
    document.getElementById('deleteModalChapterId').value = chapId;
    document.getElementById('deleteModalChapterTitle').innerText = chapTitle;
    showModalSafe('modalDeleteChapter');
}

function openDeleteCourseModal(courseCode, courseTitle) {
    var codeInput = document.getElementById('deleteModalCourseCode');
    var titleDisplay = document.getElementById('deleteModalCourseTitle');
    var codeDisplay = document.getElementById('deleteModalCourseCodeDisplay');
    if (codeInput) codeInput.value = courseCode;
    if (titleDisplay) titleDisplay.innerText = courseTitle;
    if (codeDisplay) codeDisplay.innerText = courseCode;
    showModalSafe('modalDeleteCourse');
}

function toggleChapterAccordion(elemId) {
    var el = document.getElementById(elemId);
    if (el) {
        el.style.display = (el.style.display === 'none') ? 'block' : 'none';
    }
}

function openCreateChapterModal() {
    $('#modalCreateChapter').modal('show');
}

function openAddLessonModal(chapId, chapTitle) {
    document.getElementById('addLessonChapterId').value = chapId;
    document.getElementById('addLessonModalTitle').innerHTML = '<i class="fa fa-plus-circle"></i> Add Lesson to: ' + chapTitle;
    $('#modalAddLesson').modal('show');
}

function switchLessonFormat(formatType, btnElem) {
    document.getElementById('inputLessonType').value = formatType;
    document.querySelectorAll('.gf-type-pill-btn').forEach(function(b) { b.classList.remove('active'); });
    if (btnElem) btnElem.classList.add('active');

    // Pause any playing media immediately
    var vPlayer = document.getElementById('mainVideoPlayer');
    if (vPlayer) { try { vPlayer.pause(); } catch(e){} }
    var aPlayer = document.getElementById('mainAudioPlayer');
    if (aPlayer) { try { aPlayer.pause(); } catch(e){} }

    document.getElementById('sectionVideo').style.display = (formatType === 'video') ? 'block' : 'none';
    document.getElementById('sectionPdf').style.display = (formatType === 'pdf') ? 'block' : 'none';
    document.getElementById('sectionDocument').style.display = (formatType === 'document') ? 'block' : 'none';
    var sectionAudio = document.getElementById('sectionAudio');
    if (sectionAudio) sectionAudio.style.display = (formatType === 'audio') ? 'block' : 'none';
}


function uploadFileViaAjax(fileInput, type) {
    if (!fileInput.files || !fileInput.files[0]) return;
    var file = fileInput.files[0];
    var lessonIdInput = document.querySelector('input[name="lesson_id"]');
    var lessonId = lessonIdInput ? lessonIdInput.value : 0;
    var courseCode = "<?php echo htmlspecialchars($courseCode); ?>";

    var progressContainer = document.getElementById(type + 'UploadProgress');
    var progressBar = document.getElementById(type + 'ProgressBarFill');
    var statusText = document.getElementById(type + 'UploadStatusText');
    var percentText = document.getElementById(type + 'UploadPercentText');

    if (progressContainer) progressContainer.style.display = 'block';
    if (progressBar) progressBar.style.width = '0%';
    if (statusText) statusText.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Preparing to upload ' + file.name + '...';
    if (percentText) percentText.innerText = '0%';

    var formData = new FormData();
    formData.append('action', 'ajax_upload_lesson_media');
    formData.append('lesson_id', lessonId);
    formData.append('lesson_type', type);
    formData.append('file', file);

    var xhr = new XMLHttpRequest();
    var postUrl = '../inc/ajax/curriculum.ajax.php?code=' + encodeURIComponent(courseCode) + '&action=ajax_upload_lesson_media&lesson_id=' + encodeURIComponent(lessonId);
    xhr.open('POST', postUrl, true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

    xhr.upload.onprogress = function(e) {
        if (e.lengthComputable) {
            var percent = Math.round((e.loaded / e.total) * 100);
            if (progressBar) progressBar.style.width = percent + '%';
            if (percentText) percentText.innerText = percent + '%';
            var loadedMB = (e.loaded / (1024 * 1024)).toFixed(1);
            var totalMB = (e.total / (1024 * 1024)).toFixed(1);
            if (statusText) statusText.innerHTML = '<i class="fa fa-cloud-upload"></i> Uploading: ' + loadedMB + ' MB / ' + totalMB + ' MB (' + percent + '%)';
        }
    };

    xhr.onload = function() {
        if (xhr.status === 200) {
            var rawText = xhr.responseText.trim();
            // Check if response starts with HTML error
            if (rawText.charAt(0) === '<') {
                if (statusText) {
                    if (rawText.indexOf('not_allow') !== -1 || rawText.indexOf('formLogin') !== -1 || rawText.indexOf('not allowed') !== -1) {
                        statusText.innerHTML = '<span style="color: #DC2626;"><i class="fa fa-lock"></i> Session expired. Please <a href="' + window.location.href + '" style="color: #4F46E5; text-decoration: underline;">refresh page to log in</a>.</span>';
                    } else {
                        statusText.innerHTML = '<span style="color: #DC2626;"><i class="fa fa-exclamation-circle"></i> Server returned an unexpected response. Please try again.</span>';
                    }
                }
                return;
            }

            try {
                var res = JSON.parse(rawText);
                if (res.success) {
                    if (progressBar) progressBar.style.width = '100%';
                    if (statusText) statusText.innerHTML = '<strong style="color: #10B981;"><i class="fa fa-check-circle"></i> Upload Complete & Registered!</strong> (' + res.file_name + ')';
                    if (percentText) percentText.innerText = '100%';

                    // Update UI preview elements
                    var urlInput = document.getElementById('adminVideoUrlInput');
                    if (urlInput) urlInput.value = res.url;
                    
                    if (type === 'video') {
                        updateVideoPlayerSrc(res.url);
                    } else if (type === 'pdf') {
                        updatePdfFrameSrc(res.url);
                    } else if (type === 'audio') {
                        var aPlayer = document.getElementById('mainAudioPlayer');
                        if (aPlayer) { aPlayer.src = res.url; aPlayer.load(); }
                    }
                } else {
                    if (statusText) statusText.innerHTML = '<span style="color: #DC2626;"><i class="fa fa-exclamation-circle"></i> Upload Failed: ' + (res.error || 'Unknown error') + '</span>';
                }
            } catch (err) {
                if (statusText) statusText.innerHTML = '<span style="color: #DC2626;"><i class="fa fa-exclamation-circle"></i> Parse error: ' + err.message + '</span>';
            }
        } else {
            if (statusText) statusText.innerHTML = '<span style="color: #DC2626;"><i class="fa fa-exclamation-circle"></i> Server HTTP Error: ' + xhr.status + '</span>';
        }
    };

    xhr.onerror = function() {
        if (statusText) statusText.innerHTML = '<span style="color: #DC2626;"><i class="fa fa-exclamation-circle"></i> Network connection error during upload</span>';
    };

    xhr.send(formData);
}

function updatePdfFrameSrc(url) {
    var frame = document.getElementById('adminPdfFrame');
    if (frame && url.trim()) {
        frame.src = url.trim();
    }
}

// Setup drag and drop for dropzones
document.addEventListener('DOMContentLoaded', function() {
    ['video', 'pdf', 'doc', 'audio'].forEach(function(type) {
        var dz = document.getElementById(type + 'Dropzone');
        var picker = document.getElementById(type + 'FilePicker');
        if (dz && picker) {
            dz.addEventListener('dragover', function(e) {
                e.preventDefault();
                dz.classList.add('dragover');
            });
            dz.addEventListener('dragleave', function(e) {
                e.preventDefault();
                dz.classList.remove('dragover');
            });
            dz.addEventListener('drop', function(e) {
                e.preventDefault();
                dz.classList.remove('dragover');
                if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                    picker.files = e.dataTransfer.files;
                    uploadFileViaAjax(picker, type === 'doc' ? 'document' : type);
                }
            });
        }
    });
});

function updateVideoPlayerSrc(url) {
    url = (url || '').trim();
    if (!url) return;
    var player = document.getElementById('mainVideoPlayer');
    var videoWrap = document.getElementById('mainVideoWrap');
    var iframe = document.getElementById('mainVideoIframe');
    var source = document.getElementById('mainVideoSource');

    var isYt = /(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i.test(url);
    var isVimeo = /vimeo\.com\/(?:channels\/(?:\w+\/)?|groups\/([^\/]*)\/videos\/|album\/(\d+)\/video\/|video\/|)(\d+)/i.test(url);

    if (isYt) {
        var m = url.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i);
        var embedUrl = 'https://www.youtube-nocookie.com/embed/' + m[1] + '?rel=0&modestbranding=1&enablejsapi=1&autoplay=0';
        if (player) {
            try { player.pause(); } catch(e) {}
            player.removeAttribute('src');
            if (source) source.removeAttribute('src');
        }
        if (videoWrap) videoWrap.style.display = 'none';
        if (iframe) {
            iframe.src = embedUrl;
            iframe.style.display = 'block';
        }
    } else if (isVimeo) {
        var m = url.match(/vimeo\.com\/(?:channels\/(?:\w+\/)?|groups\/([^\/]*)\/videos\/|album\/(\d+)\/video\/|video\/|)(\d+)/i);
        var embedUrl = 'https://player.vimeo.com/video/' + m[3] + '?title=0&byline=0&portrait=0';
        if (player) {
            try { player.pause(); } catch(e) {}
            player.removeAttribute('src');
            if (source) source.removeAttribute('src');
        }
        if (videoWrap) videoWrap.style.display = 'none';
        if (iframe) {
            iframe.src = embedUrl;
            iframe.style.display = 'block';
        }
    } else {
        var resolved = url;
        var match = url.match(/(?:app\/)?upload\/curriculum\/([^/?#]+)\/([^/?#]+)/i);
        if (match) {
            resolved = '/app/upload/curriculum/' + match[1] + '/' + match[2];
        }
        if (iframe) {
            iframe.src = '';
            iframe.style.display = 'none';
        }
        if (videoWrap) videoWrap.style.display = 'block';
        if (player) {
            if (source) source.src = resolved;
            player.src = resolved;
            player.load();
        }
    }
}

function previewThumbnail(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var imgPreview = document.getElementById('thumbnailPreviewImg');
            var placeholder = document.getElementById('thumbnailPlaceholder');
            if (imgPreview) {
                imgPreview.src = e.target.result;
                imgPreview.style.display = 'block';
            }
            if (placeholder) {
                placeholder.style.display = 'none';
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
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
