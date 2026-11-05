<?php
/* For licensing terms, see /license.txt */

/**
 * Sparky-Style All Courses Management Hub
 * Reference: Sparky Course Control Center (/dashboard/courses/)
 */

$cidReset = true;
require_once __DIR__.'/../inc/global.inc.php';

// Access restrictions
api_protect_admin_script();

$this_section = SECTION_PLATFORM_ADMIN;
$currentUserId = api_get_user_id();

$courseTable = Database::get_main_table(TABLE_MAIN_COURSE);
$courseUserTable = Database::get_main_table(TABLE_MAIN_COURSE_USER);
$categoryTable = Database::get_main_table(TABLE_MAIN_CATEGORY);
$userTable = Database::get_main_table(TABLE_MAIN_USER);
$adminTable = Database::get_main_table(TABLE_MAIN_ADMIN);

// Handle AJAX Request: Get Course Enrollments & Teachers
if (isset($_GET['action']) && $_GET['action'] === 'ajax_get_enrollments') {
    header('Content-Type: application/json');
    $cCode = isset($_GET['course_code']) ? trim($_GET['course_code']) : '';
    if (empty($cCode)) {
        echo json_encode(['error' => 'Invalid course code']);
        exit;
    }
    $cId = (int)Database::result(Database::query("SELECT id FROM $courseTable WHERE code = '".Database::escape_string($cCode)."' LIMIT 1"), 0, 0);
    if ($cId <= 0) {
        echo json_encode(['error' => 'Course not found']);
        exit;
    }

    // Fetch enrolled students
    $sqlStudents = "SELECT u.id, u.firstname, u.lastname, u.email, u.username, cu.status
                    FROM $userTable u 
                    INNER JOIN $courseUserTable cu ON (cu.user_id = u.id AND cu.c_id = $cId)
                    WHERE cu.status = ".STUDENT." ORDER BY u.firstname ASC";
    $resStudents = Database::query($sqlStudents);
    $students = [];
    while ($row = Database::fetch_assoc($resStudents)) {
        $students[] = [
            'id' => $row['id'],
            'name' => $row['firstname'].' '.$row['lastname'],
            'email' => $row['email'],
            'username' => $row['username'],
        ];
    }

    // Fetch instructors
    $sqlTeachers = "SELECT u.id, u.firstname, u.lastname, u.email 
                    FROM $userTable u 
                    INNER JOIN $courseUserTable cu ON (cu.user_id = u.id AND cu.c_id = $cId)
                    WHERE cu.status = ".COURSEMANAGER." ORDER BY u.firstname ASC";
    $resTeachers = Database::query($sqlTeachers);
    $teachers = [];
    while ($row = Database::fetch_assoc($resTeachers)) {
        $teachers[] = [
            'id' => $row['id'],
            'name' => $row['firstname'].' '.$row['lastname'],
            'email' => $row['email'],
        ];
    }

    echo json_encode([
        'course_code' => $cCode,
        'course_id' => $cId,
        'students' => $students,
        'teachers' => $teachers,
    ]);
    exit;
}

// Handle POST / Form Actions
$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : '';
$message = '';
$messageType = 'success';

if (isset($_GET['msg']) && $_GET['msg'] === 'course_deleted') {
    $targetCode = isset($_GET['c']) ? htmlspecialchars($_GET['c']) : '';
    $message = "Course <strong>".$targetCode."</strong> was permanently deleted.";
    $messageType = "warning";
}

// Batch Publish / Draft Operations
if ($action === 'batch_publish_all') {
    Database::query("UPDATE ".Database::get_main_table(TABLE_MAIN_COURSE)." SET visibility = 1 WHERE visibility = 0");
    $message = "All draft courses have been published successfully!";
    $messageType = "success";
}
if ($action === 'batch_draft_all') {
    Database::query("UPDATE ".Database::get_main_table(TABLE_MAIN_COURSE)." SET visibility = 0 WHERE visibility > 0");
    $message = "All courses have been updated to draft mode.";
    $messageType = "warning";
}

// Delete Course
if ($action === 'delete_course' && isset($_REQUEST['course_code'])) {
    $targetCode = trim($_REQUEST['course_code']);
    if (!empty($targetCode)) {
        CourseManager::delete_course($targetCode);
        $message = "Course <strong>".htmlspecialchars($targetCode)."</strong> was permanently deleted.";
        $messageType = "warning";
    }
}

require_once __DIR__.'/../inc/lib/course_curriculum.lib.php';
require_once __DIR__.'/../inc/lib/certificate_blueprint.lib.php';

// Update Course Certificate Blueprint
if ($action === 'update_course_certificate' && isset($_POST['course_code'])) {
    $targetCode = trim($_POST['course_code']);
    $blueprintId = isset($_POST['blueprint_id']) ? (int)$_POST['blueprint_id'] : 0;
    CertificateBlueprint::setCourseBlueprint($targetCode, $blueprintId);
    if ($blueprintId > 0) {
        $message = "Certificate blueprint successfully updated for course <strong>".htmlspecialchars($targetCode)."</strong>.";
    } else {
        $message = "Certificate disabled for course <strong>".htmlspecialchars($targetCode)."</strong>.";
    }
    $messageType = "success";
}

// Quick Add Course
if ($action === 'quick_add_course' && isset($_POST['title'])) {
    $title = trim($_POST['title']);
    $code = !empty($_POST['code']) ? trim($_POST['code']) : CourseManager::generate_course_code(api_substr($title, 0, 10));
    $category = isset($_POST['category_code']) ? trim($_POST['category_code']) : '';
    $teacherId = isset($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : $currentUserId;
    $visibility = isset($_POST['visibility']) ? (int)$_POST['visibility'] : 1; // 1 = published/open
    $blueprintId = isset($_POST['blueprint_id']) ? (int)$_POST['blueprint_id'] : 0;

    if (!empty($title)) {
        $courseParams = [
            'title' => $title,
            'wanted_code' => $code,
            'category_code' => $category,
            'course_language' => api_get_setting('platformLanguage'),
            'visibility' => $visibility,
            'teachers' => [$teacherId],
            'exemplary_content' => true,
        ];
        $newCourse = CourseManager::create_course($courseParams, $teacherId);
        if ($newCourse) {
            $createdCode = is_array($newCourse) && isset($newCourse['code']) ? $newCourse['code'] : $code;
            if ($blueprintId > 0) {
                CertificateBlueprint::setCourseBlueprint($createdCode, $blueprintId);
            }
            CourseCurriculum::initTables();
            $chapId = CourseCurriculum::createChapter($createdCode, 'Module 1: Executive Foundation', 1);
            if ($chapId > 0) {
                CourseCurriculum::createLesson($chapId, $createdCode, 'Welcome & Overview', 'video', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4', '08:45', '', 1);
            }
            $message = "Course <strong>".htmlspecialchars($title)."</strong> created successfully and registered in LMS backend engine.";
            $messageType = "success";
        } else {
            $message = "Could not create course. Course title or code might already exist.";
            $messageType = "danger";
        }
    }
}


// Duplicate Course
if ($action === 'duplicate_course' && isset($_REQUEST['course_code'])) {
    $sourceCode = trim($_REQUEST['course_code']);
    $sourceInfo = api_get_course_info($sourceCode);
    if (!empty($sourceInfo)) {
        $dupParams = [
            'title' => $sourceInfo['title'].' (Copy)',
            'wanted_code' => $sourceInfo['code'].'_COPY_'.time(),
            'category_code' => $sourceInfo['category_code'],
            'course_language' => $sourceInfo['language'],
            'visibility' => $sourceInfo['visibility'],
            'teachers' => [$currentUserId],
        ];
        $dupCourse = CourseManager::create_course($dupParams, $currentUserId);
        if ($dupCourse) {
            $message = "Course duplicated successfully into <strong>".htmlspecialchars($dupParams['title'])."</strong>.";
            $messageType = "success";
        }
    }
}

// Enroll Student into Course
if ($action === 'enroll_student' && isset($_POST['course_code']) && isset($_POST['user_id'])) {
    $cCode = trim($_POST['course_code']);
    $uId = (int)$_POST['user_id'];
    if ($uId > 0 && !empty($cCode)) {
        CourseManager::subscribeUser($uId, $cCode, STUDENT, 0, 0, false, false);
        $message = "Student enrolled successfully.";
        $messageType = "success";
    }
}

// Unenroll Student from Course
if ($action === 'unenroll_student' && isset($_POST['course_code']) && isset($_POST['user_id'])) {
    $cCode = trim($_POST['course_code']);
    $uId = (int)$_POST['user_id'];
    if ($uId > 0 && !empty($cCode)) {
        CourseManager::unsubscribe_user($uId, $cCode);
        $message = "Student unenrollment completed.";
        $messageType = "success";
    }
}

// Update Instructors
if ($action === 'update_instructors' && isset($_POST['course_code']) && isset($_POST['teacher_id'])) {
    $cCode = trim($_POST['course_code']);
    $tId = (int)$_POST['teacher_id'];
    if ($tId > 0 && !empty($cCode)) {
        CourseManager::subscribeUser($tId, $cCode, COURSEMANAGER, 0, 0, false, false);
        $message = "Instructor assigned to course successfully.";
        $messageType = "success";
    }
}

// Update Category
if ($action === 'update_category' && isset($_POST['course_code']) && isset($_POST['category_code'])) {
    $cCode = trim($_POST['course_code']);
    $catCode = trim($_POST['category_code']);
    if (!empty($cCode)) {
        Database::query("UPDATE $courseTable SET category_code = '".Database::escape_string($catCode)."' WHERE code = '".Database::escape_string($cCode)."'");
        $message = "Course category updated successfully.";
        $messageType = "success";
    }
}

// Filters & Query Parameters
$searchKeyword = isset($_GET['search']) ? trim($_GET['search']) : '';
$teacherFilter = isset($_GET['teacher']) ? (int)$_GET['teacher'] : 0;
$categoryFilter = isset($_GET['category']) ? trim($_GET['category']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : 'all'; // all, published, draft, closed
$sortFilter = isset($_GET['sort']) ? trim($_GET['sort']) : 'newest'; // newest, oldest, title

// Fetch all categories for filter
$allCategories = [];
$resCats = Database::query("SELECT code, name FROM $categoryTable ORDER BY name ASC");
while ($cRow = Database::fetch_assoc($resCats)) {
    $allCategories[] = $cRow;
}

// Fetch all teachers for filter
$allTeachers = [];
$resTeachers = Database::query("SELECT id, firstname, lastname FROM $userTable WHERE status = ".COURSEMANAGER." ORDER BY firstname ASC");
while ($tRow = Database::fetch_assoc($resTeachers)) {
    $allTeachers[] = $tRow;
}

// Fetch all students for enrollment dropdown
$allStudents = [];
$resStuds = Database::query("SELECT id, firstname, lastname, email FROM $userTable WHERE status = ".STUDENT." ORDER BY firstname ASC LIMIT 200");
while ($sRow = Database::fetch_assoc($resStuds)) {
    $allStudents[] = $sRow;
}

// Total Courses Count
$totalCoursesCount = (int)Database::result(Database::query("SELECT COUNT(*) FROM $courseTable"), 0, 0);

// Fetch Certificate Blueprints and Course Blueprint Map
CertificateBlueprint::initTables();
$allBlueprints = CertificateBlueprint::getAllBlueprints();
$certMap = CertificateBlueprint::getCourseBlueprintMap();

// Build SQL Query
$where = ["1=1"];

if (!empty($searchKeyword)) {
    $kw = Database::escape_string($searchKeyword);
    $where[] = "(c.title LIKE '%$kw%' OR c.code LIKE '%$kw%' OR c.visual_code LIKE '%$kw%')";
}

if (!empty($categoryFilter)) {
    $where[] = "c.category_code = '".Database::escape_string($categoryFilter)."'";
}

if ($teacherFilter > 0) {
    $where[] = "c.id IN (SELECT c_id FROM $courseUserTable WHERE user_id = $teacherFilter AND status = ".COURSEMANAGER.")";
}

if ($statusFilter === 'published') {
    $where[] = "c.visibility IN (1, 2, 3)";
} elseif ($statusFilter === 'draft' || $statusFilter === 'closed') {
    $where[] = "c.visibility = 0";
}

$whereClause = implode(" AND ", $where);

// Sorting
$orderBy = "c.id DESC";
if ($sortFilter === 'oldest') {
    $orderBy = "c.id ASC";
} elseif ($sortFilter === 'title') {
    $orderBy = "c.title ASC";
}

$sqlCourses = "SELECT c.id, c.code, c.title, c.visual_code, c.directory, c.category_code, c.visibility, c.creation_date,
                      (SELECT COUNT(*) FROM $courseUserTable cu WHERE cu.c_id = c.id AND cu.status = ".STUDENT.") as student_count,
                      (SELECT COUNT(*) FROM $courseUserTable cu WHERE cu.c_id = c.id AND cu.status = ".COURSEMANAGER.") as teacher_count
               FROM $courseTable c
               WHERE $whereClause
               ORDER BY $orderBy";
$resCourses = Database::query($sqlCourses);
$courseList = [];
while ($row = Database::fetch_assoc($resCourses)) {
    $courseList[] = $row;
}

$tool_name = "All Courses";
$interbreadcrumb[] = ['url' => 'index.php', 'name' => get_lang('PlatformAdmin')];
$interbreadcrumb[] = ['url' => 'course_list.php', 'name' => 'All Courses'];

Display::display_header($tool_name);
?>

<style>
/* Sparky All Courses Theme */
:root {
    --gf-primary: #6366F1;
    --gf-primary-hover: #4F46E5;
    --gf-primary-light: #EEF2FF;
    --gf-sidebar-bg: #0B1727;
    --gf-sidebar-active: #182944;
    --gf-sidebar-line: #334155;
    --gf-sidebar-text: #94A3B8;
    --gf-sidebar-header: #FFFFFF;
    --color-border: #E5E7EB;
    --color-text-main: #111827;
    --color-text-muted: #6B7280;
}

/* Full screen container override */
#cm-content {
    width: 100% !important;
    max-width: 100% !important;
    padding: 0 16px !important;
    margin: 0 !important;
    box-sizing: border-box;
}

#cm-content > .container {
    width: 100% !important;
    max-width: 100% !important;
    padding: 0 !important;
    margin: 0 !important;
    box-sizing: border-box;
}

.gf-course-layout {
    display: flex;
    gap: 16px;
    align-items: flex-start;
    width: 100%;
    margin-top: 15px;
    margin-bottom: 30px;
    font-family: var(--font-family);
    box-sizing: border-box;
}

/* Dark Navy Tree Sidebar */
.gf-tree-sidebar {
    width: 220px;
    min-width: 220px;
    background: #0B1727;
    border-radius: 12px;
    padding: 16px 12px;
    box-shadow: 0 4px 18px rgba(11, 23, 39, 0.35);
    flex-shrink: 0;
}

.modal {
    z-index: 1060 !important;
}

.modal-backdrop {
    z-index: 1050 !important;
}

.gf-sidebar-top-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    color: #FFFFFF;
    font-size: 14.5px;
    font-weight: 700;
    text-decoration: none !important;
    border-radius: 6px;
    margin-bottom: 8px;
    transition: background-color 0.15s;
}

.gf-sidebar-top-item:hover {
    background: rgba(255, 255, 255, 0.08);
    color: #FFFFFF;
}

.gf-nav-group { margin-bottom: 4px; }

.gf-nav-group-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 10px;
    color: #FFFFFF;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
    border-radius: 6px;
    user-select: none;
    transition: background-color 0.15s;
}

.gf-nav-group-header:hover {
    background-color: rgba(255, 255, 255, 0.08);
}

.gf-header-left {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #FFFFFF;
}

.gf-header-left i {
    font-size: 15px;
    width: 18px;
    text-align: center;
}

.gf-group-chevron {
    font-size: 11px;
    color: #94A3B8;
    transition: transform 0.2s ease;
}

.gf-nav-group.collapsed .gf-group-chevron { transform: rotate(-90deg); }
.gf-nav-group.collapsed .gf-nav-tree-list { display: none; }

.gf-nav-tree-list {
    list-style: none;
    margin: 4px 0 0 14px;
    padding: 0 0 0 16px;
    position: relative;
}

.gf-nav-tree-list::before {
    content: '';
    position: absolute;
    top: 0;
    bottom: 14px;
    left: 4px;
    width: 2px;
    background-color: #334155;
}

.gf-nav-tree-item {
    position: relative;
    margin: 2px 0;
}

.gf-nav-tree-item::before {
    content: '';
    position: absolute;
    top: 15px;
    left: -12px;
    width: 12px;
    height: 12px;
    border-bottom: 2px solid #334155;
    border-left: 2px solid #334155;
    border-bottom-left-radius: 8px;
}

.gf-nav-tree-link {
    display: block;
    padding: 6px 12px;
    color: #94A3B8;
    font-size: 13px;
    font-weight: 500;
    border-radius: 6px;
    text-decoration: none !important;
    line-height: 1.35;
    transition: all 0.15s ease;
}

.gf-nav-tree-link:hover {
    color: #FFFFFF;
    background-color: rgba(255, 255, 255, 0.08);
}

.gf-nav-tree-link.active {
    background-color: #182944;
    color: #FFFFFF;
    font-weight: 700;
}

/* Right Main Content */
.gf-main-area {
    flex-grow: 1;
    min-width: 0;
}

/* Main Top Header matching screenshot */
.gf-courses-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 14px;
}

.gf-courses-title {
    font-size: 26px;
    font-weight: 800;
    color: #111827;
    margin: 0;
    letter-spacing: -0.5px;
}

.gf-top-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.btn-sort-courses {
    background: #FFFFFF;
    border: 1px solid #D1D5DB;
    color: #4F46E5;
    font-weight: 600;
    font-size: 13.5px;
    padding: 8px 16px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none !important;
}

.btn-sort-courses:hover {
    background: #F5F3FF;
    border-color: #6366F1;
}

.btn-add-course {
    background: #6366F1;
    border: 1px solid #6366F1;
    color: #FFFFFF !important;
    font-weight: 600;
    font-size: 13.5px;
    padding: 8px 16px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    box-shadow: 0 2px 6px rgba(99, 102, 241, 0.3);
    transition: all 0.2s;
    text-decoration: none !important;
}

.btn-add-course:hover {
    background: #4F46E5;
    transform: translateY(-1px);
}

.btn-create-ai {
    background: linear-gradient(135deg, #8B5CF6 0%, #6366F1 100%);
    border: none;
    color: #FFFFFF !important;
    font-weight: 600;
    font-size: 13.5px;
    padding: 8px 18px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(139, 92, 246, 0.35);
    transition: all 0.2s;
    text-decoration: none !important;
}

.btn-create-ai:hover {
    opacity: 0.95;
    transform: translateY(-1px);
}

/* Stat Card */
.gf-stat-box {
    background: #FFFFFF;
    border: 1px solid #E5E7EB;
    border-radius: 10px;
    padding: 16px 20px;
    width: 240px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.gf-stat-box span {
    font-size: 13px;
    color: #6B7280;
    font-weight: 500;
    display: block;
    margin-bottom: 4px;
}

.gf-stat-box h3 {
    font-size: 26px;
    font-weight: 800;
    color: #111827;
    margin: 0;
    line-height: 1;
}

.gf-stat-icon-wrap {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    background: #EEF2FF;
    color: #6366F1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

/* Single Row Filter Bar matching screenshot */
.gf-courses-filter-bar {
    background: #FFFFFF;
    border: 1px solid #E5E7EB;
    border-radius: 10px;
    padding: 14px 18px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.gf-search-input-wrap {
    display: inline-flex;
    align-items: center;
    background: #FFFFFF;
    border: 1px solid #D1D5DB;
    border-radius: 6px;
    padding: 0 12px;
    min-width: 220px;
    flex: 1 1 200px;
}

.gf-search-input-wrap i {
    color: #9CA3AF;
    font-size: 13px;
    margin-right: 8px;
}

.gf-search-input-wrap input {
    border: none;
    outline: none;
    padding: 8px 0;
    font-size: 13px;
    width: 100%;
}

.gf-filter-select {
    background: #FFFFFF;
    border: 1px solid #D1D5DB;
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 13px;
    color: #374151;
    outline: none;
    cursor: pointer;
    min-width: 130px;
}

/* Table Design matching screenshot */
.gf-courses-table-card {
    background: #FFFFFF;
    border: 1px solid #E5E7EB;
    border-radius: 10px;
    overflow-x: auto;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}

.gf-courses-table {
    width: 100%;
    margin: 0;
    border-collapse: collapse;
}

.gf-courses-table th {
    background: #FAFAFA;
    font-size: 12.5px;
    font-weight: 700;
    color: #374151;
    padding: 14px 16px;
    border-bottom: 1px solid #E5E7EB;
    text-align: left;
    white-space: nowrap;
}

.gf-courses-table td {
    padding: 14px 16px;
    font-size: 13.5px;
    border-bottom: 1px solid #F3F4F6;
    vertical-align: middle;
}

.gf-courses-table tbody tr:hover {
    background-color: #FBFBFE;
}

/* Thumbnail Column */
.gf-course-thumb {
    width: 38px;
    height: 38px;
    border-radius: 6px;
    object-fit: cover;
    background: #111827;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #818CF8;
    font-size: 16px;
    overflow: hidden;
}

.gf-course-id-link {
    color: #6366F1;
    font-weight: 600;
    text-decoration: none !important;
}

.gf-course-title-link {
    color: #4F46E5;
    font-weight: 600;
    text-decoration: none !important;
    display: block;
    max-width: 280px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.gf-course-title-link:hover {
    color: #3730A3;
    text-decoration: underline !important;
}

.btn-view-enrollments {
    background: #FFFFFF;
    border: 1px solid #D1D5DB;
    border-radius: 6px;
    padding: 5px 12px;
    font-size: 12.5px;
    font-weight: 500;
    color: #374151;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-view-enrollments:hover {
    background: #F9FAFB;
    border-color: #9CA3AF;
}

.gf-status-published {
    border: 1px solid #86EFAC;
    background: #F0FDF4;
    color: #16A34A;
    font-size: 11.5px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 4px;
}

.gf-status-draft {
    border: 1px solid #FDE68A;
    background: #FFFBEB;
    color: #D97706;
    font-size: 11.5px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 4px;
}

/* Action Buttons inside Table */
.gf-course-actions {
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
}

.btn-table-action {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #FFFFFF;
    border: 1px solid #D1D5DB;
    border-radius: 6px;
    padding: 5px 10px;
    font-size: 12.5px;
    font-weight: 500;
    color: #374151;
    text-decoration: none !important;
    cursor: pointer;
    transition: all 0.15s;
}

.btn-table-action:hover {
    background: #F9FAFB;
    color: #111827;
}

.btn-table-delete {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #FEF2F2;
    border: 1px solid #FCA5A5;
    border-radius: 6px;
    padding: 5px 10px;
    font-size: 12.5px;
    font-weight: 600;
    color: #DC2626 !important;
    text-decoration: none !important;
    cursor: pointer;
    transition: all 0.15s;
}

.btn-table-delete:hover {
    background: #DC2626;
    color: #FFFFFF !important;
    border-color: #DC2626;
}

.btn-table-ezy {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #FFFFFF;
    border: 1px solid #E5E7EB;
    border-radius: 6px;
    padding: 5px 10px;
    font-size: 12px;
    font-weight: 500;
    color: #9CA3AF;
    text-decoration: none !important;
}

.btn-table-ezy:hover {
    color: #4B5563;
    border-color: #D1D5DB;
}

.gf-dropdown-dots .dropdown-toggle {
    background: none;
    border: none;
    color: #6B7280;
    font-size: 15px;
    padding: 4px 8px;
    cursor: pointer;
}

.gf-dropdown-dots .dropdown-toggle:hover {
    color: #111827;
}

.gf-dots-menu {
    border-radius: 8px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.1);
    border: 1px solid #E5E7EB;
    padding: 6px 0;
    min-width: 170px;
}

.gf-dots-menu li a,
.gf-dots-menu li button {
    padding: 7px 14px;
    font-size: 13px;
    color: #374151;
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    text-align: left;
    background: none;
    border: none;
    text-decoration: none !important;
}

.gf-dots-menu li a:hover,
.gf-dots-menu li button:hover {
    background: #F3F4F6;
    color: #111827;
}
</style>

<div class="gf-course-layout">

    <!-- LEFT: Dark Navy Tree Sidebar matching screenshot -->
    <aside class="gf-tree-sidebar">
        <!-- Dashboard Top Level -->
        <a href="index.php" class="gf-sidebar-top-item">
            <i class="fa fa-th-large"></i>
            <span>Dashboard</span>
        </a>

        <!-- Group 1: Courses (Expanded with All sub-items) -->
        <div class="gf-nav-group" id="sideGroupCourses">
            <div class="gf-nav-group-header" onclick="toggleNav('sideGroupCourses')">
                <div class="gf-header-left">
                    <i class="fa fa-users"></i>
                    <span>Courses</span>
                </div>
                <i class="fa fa-chevron-down gf-group-chevron"></i>
            </div>
            <ul class="gf-nav-tree-list">
                <li class="gf-nav-tree-item">
                    <a href="course_list.php" class="gf-nav-tree-link active">
                        All Courses
                    </a>
                </li>
                <li class="gf-nav-tree-item">
                    <a href="course_add.php" class="gf-nav-tree-link">
                        + Create Course
                    </a>
                </li>
                <li class="gf-nav-tree-item">
                    <a href="course_category.php" class="gf-nav-tree-link">
                        Categories
                    </a>
                </li>
                <li class="gf-nav-tree-item">
                    <a href="<?php echo api_get_path(WEB_CODE_PATH); ?>gradebook/my_certificates.php" class="gf-nav-tree-link">
                        Awarded Certificates
                    </a>
                </li>
                <li class="gf-nav-tree-item">
                    <a href="certificate_blueprints.php" class="gf-nav-tree-link">
                        Certificate Blueprints
                    </a>
                </li>
                <li class="gf-nav-tree-item">
                    <a href="questions.php" class="gf-nav-tree-link">
                        Question Bank
                    </a>
                </li>
                <li class="gf-nav-tree-item">
                    <a href="<?php echo api_get_path(WEB_CODE_PATH); ?>exercise/question_import.php" class="gf-nav-tree-link">
                        Import Questions
                    </a>
                </li>
                <li class="gf-nav-tree-item">
                    <a href="<?php echo api_get_path(WEB_CODE_PATH); ?>mySpace/course.php" class="gf-nav-tree-link">
                        Quiz Submissions
                    </a>
                </li>
                <li class="gf-nav-tree-item">
                    <a href="<?php echo api_get_path(WEB_CODE_PATH); ?>work/work.php" class="gf-nav-tree-link">
                        Assignment Submissions
                    </a>
                </li>
                <li class="gf-nav-tree-item">
                    <a href="<?php echo api_get_path(WEB_CODE_PATH); ?>survey/survey_list.php" class="gf-nav-tree-link">
                        Survey Submissions
                    </a>
                </li>
                <li class="gf-nav-tree-item">
                    <a href="<?php echo api_get_path(WEB_CODE_PATH); ?>social/profile.php" class="gf-nav-tree-link">
                        Reviews
                    </a>
                </li>
            </ul>
        </div>

        <!-- Group 2: Coaching -->
        <div class="gf-nav-group collapsed" id="sideGroupCoaching">
            <div class="gf-nav-group-header" onclick="toggleNav('sideGroupCoaching')">
                <div class="gf-header-left">
                    <i class="fa fa-calendar-check-o"></i>
                    <span>Coaching</span>
                </div>
                <i class="fa fa-chevron-down gf-group-chevron"></i>
            </div>
            <ul class="gf-nav-tree-list">
                <li class="gf-nav-tree-item">
                    <a href="<?php echo api_get_path(WEB_CODE_PATH); ?>session/session_list.php" class="gf-nav-tree-link">
                        All Coaching Programs
                    </a>
                </li>
                <li class="gf-nav-tree-item">
                    <a href="<?php echo api_get_path(WEB_CODE_PATH); ?>session/session_category_list.php" class="gf-nav-tree-link">
                        Cohorts & Sessions
                    </a>
                </li>
            </ul>
        </div>

        <!-- Group 3: Products & Services -->
        <div class="gf-nav-group collapsed" id="sideGroupProducts">
            <div class="gf-nav-group-header" onclick="toggleNav('sideGroupProducts')">
                <div class="gf-header-left">
                    <i class="fa fa-shopping-bag"></i>
                    <span>Products & Services</span>
                </div>
                <i class="fa fa-chevron-down gf-group-chevron"></i>
            </div>
            <ul class="gf-nav-tree-list">
                <li class="gf-nav-tree-item">
                    <a href="course_list.php" class="gf-nav-tree-link">
                        Digital Products
                    </a>
                </li>
                <li class="gf-nav-tree-item">
                    <a href="<?php echo api_get_path(WEB_CODE_PATH); ?>session/session_list.php" class="gf-nav-tree-link">
                        Live Cohorts
                    </a>
                </li>
            </ul>
        </div>

        <!-- Group 4: Users -->
        <div class="gf-nav-group collapsed" id="sideGroupUsers">
            <div class="gf-nav-group-header" onclick="toggleNav('sideGroupUsers')">
                <div class="gf-header-left">
                    <i class="fa fa-users"></i>
                    <span>Users</span>
                </div>
                <i class="fa fa-chevron-down gf-group-chevron"></i>
            </div>
            <ul class="gf-nav-tree-list">
                <li class="gf-nav-tree-item">
                    <a href="user_dashboard.php?role=student" class="gf-nav-tree-link">
                        Students
                    </a>
                </li>
                <li class="gf-nav-tree-item">
                    <a href="user_dashboard.php?role=teacher" class="gf-nav-tree-link">
                        Teachers
                    </a>
                </li>
                <li class="gf-nav-tree-item">
                    <a href="user_dashboard.php?role=admin" class="gf-nav-tree-link">
                        Admin Users
                    </a>
                </li>
                <li class="gf-nav-tree-item">
                    <a href="user_add.php" class="gf-nav-tree-link" style="color: #A5B4FC;">
                        + Add User
                    </a>
                </li>
            </ul>
        </div>

        <!-- Group 5: Site Settings -->
        <div class="gf-nav-group collapsed" id="sideGroupSettings">
            <div class="gf-nav-group-header" onclick="toggleNav('sideGroupSettings')">
                <div class="gf-header-left">
                    <i class="fa fa-cog"></i>
                    <span>Site Settings</span>
                </div>
                <i class="fa fa-chevron-down gf-group-chevron"></i>
            </div>
            <ul class="gf-nav-tree-list">
                <li class="gf-nav-tree-item">
                    <a href="settings.php" class="gf-nav-tree-link">
                        Platform Settings
                    </a>
                </li>
                <li class="gf-nav-tree-item">
                    <a href="configure_homepage.php" class="gf-nav-tree-link">
                        Portal Homepage
                    </a>
                </li>
            </ul>
        </div>
    </aside>

    <!-- RIGHT: All Courses Management Content -->
    <main class="gf-main-area">

        <!-- Flash Message Alerts -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo htmlspecialchars($messageType); ?> alert-dismissible" role="alert" style="border-radius: 8px; font-weight: 500;">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Main Header & Action Buttons -->
        <div class="gf-courses-header">
            <h1 class="gf-courses-title">All Courses</h1>
            <div class="gf-top-actions">
                <a href="javascript:void(0)" class="btn-sort-courses" onclick="toggleSortMode()">
                    Sort Courses
                </a>
                <button type="button" class="btn-sort-courses" onclick="if(confirm('Publish all draft courses to make them visible to learners?')){ document.getElementById('batchPublishForm').submit(); }" style="background: #F0FDF4; border-color: #BBF7D0; color: #16A34A;" title="Batch Publish Courses">
                    <i class="fa fa-check-circle"></i> Batch Publish
                </button>
                <button type="button" class="btn-sort-courses" onclick="if(confirm('Set all published courses to Draft mode?')){ document.getElementById('batchDraftForm').submit(); }" style="background: #F8FAFC; border-color: #E2E8F0; color: #64748B;" title="Batch Set Draft">
                    <i class="fa fa-eye-slash"></i> Batch Draft
                </button>
                <button type="button" class="btn-add-course" onclick="openAddCourseModal()">
                    <i class="fa fa-plus-circle"></i> + Add Course
                </button>
            </div>
            
            <form id="batchPublishForm" method="post" action="course_list.php" style="display:none;">
                <input type="hidden" name="action" value="batch_publish_all">
            </form>
            <form id="batchDraftForm" method="post" action="course_list.php" style="display:none;">
                <input type="hidden" name="action" value="batch_draft_all">
            </form>
        </div>

        <!-- Total Courses Stat Box -->
        <div class="gf-stat-box">
            <div>
                <span>Total Courses</span>
                <h3><?php echo number_format($totalCoursesCount); ?></h3>
            </div>
            <div class="gf-stat-icon-wrap">
                <i class="fa fa-book"></i>
            </div>
        </div>

        <!-- Filter Bar matching screenshot -->
        <div class="gf-courses-filter-bar">
            <form id="gfCourseFilterForm" method="get" action="course_list.php" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; width: 100%;">
                
                <!-- Search by Title -->
                <div class="gf-search-input-wrap">
                    <i class="fa fa-search"></i>
                    <input type="text" name="search" placeholder="Search By Course Title" value="<?php echo htmlspecialchars($searchKeyword); ?>" onkeypress="if(event.key==='Enter'){ this.form.submit(); }">
                </div>

                <!-- All Teachers Filter -->
                <select name="teacher" class="gf-filter-select" onchange="this.form.submit()">
                    <option value="0">All Teachers</option>
                    <?php foreach ($allTeachers as $t): ?>
                        <option value="<?php echo $t['id']; ?>" <?php echo $teacherFilter === (int)$t['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($t['firstname'].' '.$t['lastname']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Course Type -->
                <select name="type" class="gf-filter-select" onchange="this.form.submit()">
                    <option value="">All Course Type</option>
                    <option value="self_paced">Self-Paced Masterclass</option>
                    <option value="live">Live Cohort</option>
                </select>

                <!-- All Status -->
                <select name="status" class="gf-filter-select" onchange="this.form.submit()">
                    <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="published" <?php echo $statusFilter === 'published' ? 'selected' : ''; ?>>Published</option>
                    <option value="draft" <?php echo $statusFilter === 'draft' ? 'selected' : ''; ?>>Draft / Closed</option>
                </select>

                <!-- All Categories -->
                <select name="category" class="gf-filter-select" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <?php foreach ($allCategories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['code']); ?>" <?php echo $categoryFilter === $cat['code'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Sort By -->
                <select name="sort" class="gf-filter-select" onchange="this.form.submit()">
                    <option value="newest" <?php echo $sortFilter === 'newest' ? 'selected' : ''; ?>>Sort By new</option>
                    <option value="oldest" <?php echo $sortFilter === 'oldest' ? 'selected' : ''; ?>>Sort By old</option>
                    <option value="title" <?php echo $sortFilter === 'title' ? 'selected' : ''; ?>>Sort By title</option>
                </select>

                <?php if (!empty($searchKeyword) || $teacherFilter > 0 || !empty($categoryFilter) || $statusFilter !== 'all'): ?>
                    <a href="course_list.php" class="btn btn-default btn-sm" style="border-radius: 6px;" title="Reset Filters">
                        <i class="fa fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Data Table matching screenshot -->
        <div class="gf-courses-table-card">
            <table class="gf-courses-table">
                <thead>
                    <tr>
                        <th style="width: 44px; text-align: center; padding: 12px 6px;">Thumb</th>
                        <th style="width: 50px; padding: 12px 6px;">ID</th>
                        <th style="padding: 12px 10px;">Course Title</th>
                        <th style="width: 75px; text-align: center; padding: 12px 6px;">Teachers</th>
                        <th style="width: 105px; text-align: center; padding: 12px 6px;">Students</th>
                        <th style="width: 80px; text-align: center; padding: 12px 6px;">Status</th>
                        <th style="width: 110px; padding: 12px 6px;">Created</th>
                        <th style="width: 250px; text-align: right; padding: 12px 10px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($courseList)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 48px; color: #9CA3AF;">
                                <i class="fa fa-book" style="font-size: 32px; display: block; margin-bottom: 8px; opacity: 0.4;"></i>
                                No courses found matching the search criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($courseList as $c): 
                            $createdDate = !empty($c['creation_date']) ? date('Y-m-d H:i', strtotime($c['creation_date'])) : date('Y-m-d H:i');
                            $courseUrl = api_get_path(WEB_COURSE_PATH).$c['directory'].'/';
                            $curriculumUrl = 'course_curriculum.php?code='.urlencode($c['code']).'&tab=curriculum';
                            $settingsUrl = 'course_curriculum.php?code='.urlencode($c['code']).'&tab=settings';
                        ?>
                            <tr>
                                <td style="padding: 10px 6px; text-align: center;">
                                    <div class="gf-course-thumb" style="width: 32px; height: 32px; margin: 0 auto; font-size: 14px;">
                                        <i class="fa fa-graduation-cap"></i>
                                    </div>
                                </td>
                                <td style="padding: 10px 6px;">
                                    <a href="<?php echo $settingsUrl; ?>" class="gf-course-id-link" title="Edit Course Settings">
                                        <?php echo $c['id']; ?>
                                    </a>
                                </td>
                                <td style="padding: 10px 10px;">
                                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                        <a href="<?php echo $curriculumUrl; ?>" class="gf-course-title-link" title="<?php echo htmlspecialchars($c['title']); ?>" style="max-width: 240px;">
                                            <?php echo htmlspecialchars($c['title']); ?>
                                        </a>
                                        <?php if (isset($certMap[$c['code']])): ?>
                                            <span class="label" style="background: #FEF3C7; color: #92400E; border: 1px solid #FCD34D; font-size: 10.5px; padding: 1px 5px; border-radius: 4px; font-weight: 600;" title="Certificate Enabled: <?php echo htmlspecialchars($certMap[$c['code']]['name']); ?>">
                                                <i class="fa fa-certificate" style="color: #D97706;"></i> Cert
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="text-align: center; padding: 10px 6px;">
                                    <span style="font-weight: 600; color: #374151; cursor: pointer;" onclick="openInstructorsModal('<?php echo htmlspecialchars(addslashes($c['code'])); ?>', '<?php echo htmlspecialchars(addslashes($c['title'])); ?>')">
                                        <?php echo max(1, (int)$c['teacher_count']); ?>
                                    </span>
                                </td>
                                <td style="text-align: center; padding: 10px 6px;">
                                    <button type="button" class="btn-view-enrollments" onclick="openEnrollmentsModal('<?php echo htmlspecialchars(addslashes($c['code'])); ?>', '<?php echo htmlspecialchars(addslashes($c['title'])); ?>')" style="padding: 3px 8px; font-size: 11.5px;">
                                        <?php echo (int)$c['student_count']; ?> Learners
                                    </button>
                                </td>
                                <td style="text-align: center; padding: 10px 6px;">
                                    <?php if ($c['visibility'] > 0): ?>
                                        <span class="gf-status-published" style="font-size: 11px; padding: 2px 6px;">Active</span>
                                    <?php else: ?>
                                        <span class="gf-status-draft" style="font-size: 11px; padding: 2px 6px;">Draft</span>
                                    <?php endif; ?>
                                </td>
                                <td style="color: #6B7280; font-size: 12px; padding: 10px 6px; white-space: nowrap;">
                                    <?php echo $createdDate; ?>
                                </td>
                                <td style="text-align: right; padding: 10px 10px; white-space: nowrap;">
                                    <div class="gf-course-actions" style="justify-content: flex-end; gap: 4px;">
                                        <a href="<?php echo $curriculumUrl; ?>" class="btn-table-action" style="background: #EEF2FF; color: #4F46E5; border-color: #C7D2FE; font-weight: 600; padding: 4px 8px; font-size: 11.5px;" title="Edit Curriculum & Lessons">
                                            <i class="fa fa-list-alt"></i> Curriculum
                                        </a>
                                        <a href="<?php echo $settingsUrl; ?>" class="btn-table-action" style="padding: 4px 8px; font-size: 11.5px;" title="Edit Course Settings">
                                            <i class="fa fa-cog"></i> Settings
                                        </a>
                                        <a href="<?php echo $courseUrl; ?>" class="btn-table-action" style="padding: 4px 7px; font-size: 11.5px;" title="View Course Page" target="_blank">
                                            <i class="fa fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn-table-delete" onclick="openDeleteCourseModal('<?php echo htmlspecialchars(addslashes($c['code'])); ?>', '<?php echo htmlspecialchars(addslashes($c['title'])); ?>')" title="Permanently Delete Course" style="padding: 4px 8px; font-size: 11.5px;">
                                            <i class="fa fa-trash"></i> Delete
                                        </button>
                                        
                                        <!-- 3-Dots More Dropdown -->
                                        <div class="dropdown gf-dropdown-dots" style="display: inline-block;">
                                            <button type="button" class="dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="More options">
                                                <i class="fa fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-right gf-dots-menu">
                                                <li>
                                                    <a href="<?php echo $curriculumUrl; ?>">
                                                        <i class="fa fa-list-alt"></i> Manage Curriculum
                                                    </a>
                                                </li>
                                                <li>
                                                    <a href="<?php echo $settingsUrl; ?>">
                                                        <i class="fa fa-sliders"></i> Course Settings
                                                    </a>
                                                </li>
                                                <li>
                                                    <a href="course_list.php?action=duplicate_course&course_code=<?php echo urlencode($c['code']); ?>" onclick="return confirm('Duplicate this course?');">
                                                        <i class="fa fa-copy"></i> Duplicate Course
                                                    </a>
                                                </li>
                                                <li>
                                                    <a href="javascript:void(0)" onclick="openInstructorsModal('<?php echo htmlspecialchars(addslashes($c['code'])); ?>', '<?php echo htmlspecialchars(addslashes($c['title'])); ?>')">
                                                        <i class="fa fa-user-plus"></i> Update Instructors
                                                    </a>
                                                </li>
                                                <li>
                                                    <a href="javascript:void(0)" onclick="openCategoryModal('<?php echo htmlspecialchars(addslashes($c['code'])); ?>', '<?php echo htmlspecialchars(addslashes($c['category_code'])); ?>')">
                                                        <i class="fa fa-folder-o"></i> Update Category
                                                    </a>
                                                </li>
                                                <li>
                                                    <a href="javascript:void(0)" onclick="openCertificateModal('<?php echo htmlspecialchars(addslashes($c['code'])); ?>', '<?php echo htmlspecialchars(addslashes($c['title'])); ?>', '<?php echo isset($certMap[$c['code']]) ? $certMap[$c['code']]['id'] : 0; ?>')">
                                                        <i class="fa fa-certificate" style="color: #F59E0B;"></i> Certificate Settings
                                                    </a>
                                                </li>
                                                <li>
                                                    <a href="<?php echo $courseUrl; ?>" target="_blank">
                                                        <i class="fa fa-external-link"></i> Preview Course Player
                                                    </a>
                                                </li>
                                                <li role="separator" class="divider" style="margin: 4px 0;"></li>
                                                <li>
                                                    <a href="javascript:void(0)" onclick="openDeleteCourseModal('<?php echo htmlspecialchars(addslashes($c['code'])); ?>', '<?php echo htmlspecialchars(addslashes($c['title'])); ?>')" style="color: #DC2626; font-weight: 600;">
                                                        <i class="fa fa-trash"></i> Delete Course
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>
</div>

<!-- MODAL: View & Manage Enrollments -->
<div class="modal fade" id="modalCourseEnrollments" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header" style="background: #6366F1; color: #FFFFFF; padding: 16px 20px;">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff; opacity:0.8;"><span>&times;</span></button>
                <h4 class="modal-title" id="enrollModalHeading" style="font-weight: 700;"><i class="fa fa-users"></i> Course Enrollments</h4>
            </div>
            <div class="modal-body" style="padding: 24px;">
                <!-- Section 1: Enrolled Students List -->
                <h5 style="font-weight: 700; color: #111827; margin-top: 0;">Enrolled Students</h5>
                <div id="enrolledStudentsContainer" style="max-height: 250px; overflow-y: auto; margin-bottom: 20px; border: 1px solid #E5E7EB; border-radius: 8px;">
                    <p class="text-muted text-center" style="padding: 20px;"><i class="fa fa-spinner fa-spin"></i> Loading students...</p>
                </div>

                <hr style="margin: 20px 0;">

                <!-- Section 2: Quick Enroll New Student Form -->
                <h5 style="font-weight: 700; color: #111827;">+ Enroll Student into this Course</h5>
                <form method="post" action="course_list.php" class="form-inline" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <input type="hidden" name="action" value="enroll_student">
                    <input type="hidden" name="course_code" id="enrollModalCourseCode" value="">
                    
                    <select name="user_id" class="form-control" required style="min-width: 280px; font-size: 13.5px;">
                        <option value="">-- Select Student to Enroll --</option>
                        <?php foreach ($allStudents as $s): ?>
                            <option value="<?php echo $s['id']; ?>">
                                <?php echo htmlspecialchars($s['firstname'].' '.$s['lastname'].' ('.$s['email'].')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="btn btn-primary" style="background: #6366F1; border-color: #6366F1; border-radius: 6px; font-weight: 600;">
                        <i class="fa fa-plus"></i> Enroll Student
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Update Instructors -->
<div class="modal fade" id="modalUpdateInstructors" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden;">
            <form method="post" action="course_list.php">
                <input type="hidden" name="action" value="update_instructors">
                <input type="hidden" name="course_code" id="instructorModalCourseCode" value="">

                <div class="modal-header" style="background: #111827; color: #FFFFFF; padding: 14px 18px;">
                    <button type="button" class="close" data-dismiss="modal" style="color:#fff; opacity:0.8;"><span>&times;</span></button>
                    <h5 class="modal-title" style="font-weight: 700;"><i class="fa fa-user-plus"></i> Assign Instructor</h5>
                </div>
                <div class="modal-body" style="padding: 18px;">
                    <div class="form-group">
                        <label style="font-size: 12.5px; font-weight: 600;">Select Teacher / Instructor:</label>
                        <select name="teacher_id" class="form-control" required>
                            <?php foreach ($allTeachers as $t): ?>
                                <option value="<?php echo $t['id']; ?>">
                                    <?php echo htmlspecialchars($t['firstname'].' '.$t['lastname']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer" style="padding: 10px 18px;">
                    <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" style="background: #6366F1; border-color: #6366F1;">Assign</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: Update Certificate Blueprint -->
<div class="modal fade" id="modalCourseCertificate" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden; border: 1px solid #E2E8F0;">
            <form method="post" action="course_list.php">
                <input type="hidden" name="action" value="update_course_certificate">
                <input type="hidden" name="course_code" id="certModalCourseCode" value="">

                <div class="modal-header" style="background: #4F46E5; color: #FFFFFF; padding: 16px 20px;">
                    <button type="button" class="close" data-dismiss="modal" style="color:#fff; opacity:0.8;"><span>&times;</span></button>
                    <h4 class="modal-title" id="certModalHeading" style="font-weight: 700;"><i class="fa fa-certificate"></i> Course Certificate Settings</h4>
                </div>
                <div class="modal-body" style="padding: 20px;">
                    <p style="font-size: 13.5px; color: #475569; margin-bottom: 16px;">
                        Select which certificate blueprint to issue to learners when they reach 100% completion in this specific course. Select <strong>-- No Certificate (Disabled) --</strong> if this course should not award certificates.
                    </p>
                    <div class="form-group">
                        <label style="font-size: 13px; font-weight: 600; color: #1E293B;">Certificate Blueprint</label>
                        <select name="blueprint_id" id="certModalBlueprintSelect" class="form-control" style="font-weight: 600; height: 42px; border-radius: 8px;">
                            <option value="0">-- No Certificate (Disabled) --</option>
                            <?php foreach ($allBlueprints as $bp): ?>
                                <option value="<?php echo $bp['id']; ?>">
                                    <?php echo htmlspecialchars($bp['name'].' ('.$bp['title'].')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer" style="padding: 12px 20px; background: #F8FAFC;">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: #4F46E5; border-color: #4F46E5; font-weight: 600;">Save Certificate Setting</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: Update Category -->
<div class="modal fade" id="modalUpdateCategory" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden;">
            <form method="post" action="course_list.php">
                <input type="hidden" name="action" value="update_category">
                <input type="hidden" name="course_code" id="categoryModalCourseCode" value="">

                <div class="modal-header" style="background: #111827; color: #FFFFFF; padding: 14px 18px;">
                    <button type="button" class="close" data-dismiss="modal" style="color:#fff; opacity:0.8;"><span>&times;</span></button>
                    <h5 class="modal-title" style="font-weight: 700;"><i class="fa fa-folder-open"></i> Course Category</h5>
                </div>
                <div class="modal-body" style="padding: 18px;">
                    <div class="form-group">
                        <label style="font-size: 12.5px; font-weight: 600;">Choose Category:</label>
                        <select name="category_code" id="categoryModalSelect" class="form-control">
                            <option value="">-- No Category --</option>
                            <?php foreach ($allCategories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['code']); ?>">
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer" style="padding: 10px 18px;">
                    <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" style="background: #6366F1; border-color: #6366F1;">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: Add Course -->
<div class="modal fade" id="modalAddCourse" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden;">
            <form method="post" action="course_list.php">
                <input type="hidden" name="action" value="quick_add_course">

                <div class="modal-header" style="background: #6366F1; color: #FFFFFF; padding: 16px 20px;">
                    <button type="button" class="close" data-dismiss="modal" style="color:#fff; opacity:0.8;"><span>&times;</span></button>
                    <h4 class="modal-title" style="font-weight: 700;"><i class="fa fa-plus-circle"></i> Create New Course</h4>
                </div>
                <div class="modal-body" style="padding: 20px;">
                    <div class="form-group">
                        <label style="font-size: 13px; font-weight: 600;">Course Title *</label>
                        <input type="text" name="title" class="form-control" required placeholder="e.g. Masterclass in Executive Leadership">
                    </div>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label style="font-size: 13px; font-weight: 600;">Course Code (Optional)</label>
                                <input type="text" name="code" class="form-control" placeholder="e.g. LEAD101">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label style="font-size: 13px; font-weight: 600;">Category</label>
                                <select name="category_code" class="form-control">
                                    <option value="">-- Select Category --</option>
                                    <?php foreach ($allCategories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat['code']); ?>">
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label style="font-size: 13px; font-weight: 600;">Instructor</label>
                                <select name="teacher_id" class="form-control">
                                    <?php foreach ($allTeachers as $t): ?>
                                        <option value="<?php echo $t['id']; ?>">
                                            <?php echo htmlspecialchars($t['firstname'].' '.$t['lastname']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label style="font-size: 13px; font-weight: 600;">Status</label>
                                <select name="visibility" class="form-control">
                                    <option value="1">Published (Open to Learners)</option>
                                    <option value="0">Draft (Admin Only)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label style="font-size: 13px; font-weight: 600;"><i class="fa fa-certificate text-warning"></i> Certificate Blueprint</label>
                        <select name="blueprint_id" class="form-control">
                            <option value="0">-- No Certificate (Disabled) --</option>
                            <?php foreach ($allBlueprints as $bp): ?>
                                <option value="<?php echo $bp['id']; ?>">
                                    <?php echo htmlspecialchars($bp['name'].' ('.$bp['title'].')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="help-block" style="font-size: 12px; color: #6B7280; margin-bottom: 0;">Leave as "No Certificate" if this course does not grant a certificate upon completion.</span>
                    </div>
                </div>
                <div class="modal-footer" style="background: #F9FAFB;">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: #6366F1; border-color: #6366F1; font-weight: 600;">
                        <i class="fa fa-check"></i> Create Course
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
            <form method="post" action="course_list.php" id="deleteCourseListForm">
                <input type="hidden" name="action" value="delete_course">
                <input type="hidden" name="course_code" id="deleteModalCourseCode" value="">
                
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
                        <div style="font-weight: 700; color: #111827; font-size: 15px;" id="deleteModalCourseTitle"></div>
                        <div style="font-size: 12.5px; color: #6B7280; margin-top: 2px;">Code: <code id="deleteModalCourseCodeDisplay" style="color: #4F46E5; background: #EEF2FF;"></code></div>
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

function openDeleteCourseModal(courseCode, courseTitle) {
    var codeInput = document.getElementById('deleteModalCourseCode');
    var titleDisplay = document.getElementById('deleteModalCourseTitle');
    var codeDisplay = document.getElementById('deleteModalCourseCodeDisplay');
    if (codeInput) codeInput.value = courseCode;
    if (titleDisplay) titleDisplay.innerText = courseTitle;
    if (codeDisplay) codeDisplay.innerText = courseCode;
    showModalSafe('modalDeleteCourse');
}

function toggleNav(groupId) {
    var g = document.getElementById(groupId);
    if (g) g.classList.toggle('collapsed');
}

function openAddCourseModal() {
    showModalSafe('modalAddCourse');
}

function openInstructorsModal(courseCode, courseTitle) {
    document.getElementById('instructorModalCourseCode').value = courseCode;
    showModalSafe('modalUpdateInstructors');
}

function openCategoryModal(courseCode, currentCategory) {
    document.getElementById('categoryModalCourseCode').value = courseCode;
    document.getElementById('categoryModalSelect').value = currentCategory || '';
    showModalSafe('modalUpdateCategory');
}

function openCertificateModal(courseCode, courseTitle, currentBlueprintId) {
    document.getElementById('certModalCourseCode').value = courseCode;
    document.getElementById('certModalHeading').innerHTML = '<i class="fa fa-certificate"></i> Certificate Settings: ' + courseTitle;
    document.getElementById('certModalBlueprintSelect').value = currentBlueprintId || '0';
    showModalSafe('modalCourseCertificate');
}

function openEnrollmentsModal(courseCode, courseTitle) {
    document.getElementById('enrollModalCourseCode').value = courseCode;
    document.getElementById('enrollModalHeading').innerHTML = '<i class="fa fa-graduation-cap"></i> Enrollments: ' + courseTitle;
    var container = document.getElementById('enrolledStudentsContainer');
    container.innerHTML = '<p class="text-muted text-center" style="padding: 20px;"><i class="fa fa-spinner fa-spin"></i> Loading enrolled students...</p>';
    showModalSafe('modalCourseEnrollments');

    fetch('course_list.php?action=ajax_get_enrollments&course_code=' + encodeURIComponent(courseCode))
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.error) {
                container.innerHTML = '<div class="alert alert-danger" style="margin: 10px;">' + data.error + '</div>';
                return;
            }
            if (!data.students || data.students.length === 0) {
                container.innerHTML = '<p class="text-muted text-center" style="padding: 24px;">No students are currently enrolled in this course.</p>';
                return;
            }
            var html = '<table class="table table-hover" style="margin: 0;">' +
                       '<thead><tr><th>Student Name</th><th>Email</th><th style="width: 100px; text-align: right;">Action</th></tr></thead><tbody>';
            data.students.forEach(function(s) {
                html += '<tr>' +
                            '<td><strong>' + s.name + '</strong></td>' +
                            '<td class="text-muted">' + s.email + '</td>' +
                            '<td style="text-align: right;">' +
                                '<form method="post" action="course_list.php" style="margin:0;" onsubmit="return confirm(\'Unenroll ' + s.name + ' from course?\');">' +
                                    '<input type="hidden" name="action" value="unenroll_student">' +
                                    '<input type="hidden" name="course_code" value="' + courseCode + '">' +
                                    '<input type="hidden" name="user_id" value="' + s.id + '">' +
                                    '<button type="submit" class="btn btn-xs btn-danger" style="border-radius: 4px;">' +
                                        '<i class="fa fa-times"></i> Remove' +
                                    '</button>' +
                                '</form>' +
                            '</td>' +
                        '</tr>';
            });
            html += '</tbody></table>';
            container.innerHTML = html;
        });
}

function toggleSortMode() {
    var sortSelect = document.querySelector('select[name="sort"]');
    if (sortSelect) {
        sortSelect.value = sortSelect.value === 'newest' ? 'title' : 'newest';
        document.getElementById('gfCourseFilterForm').submit();
    }
}
</script>

<?php
Display::display_footer();
