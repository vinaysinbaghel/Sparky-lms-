<?php
/* For licensing terms, see /license.txt */

/**
 * Sparky-Style Create Course Hub
 * Reference: Sparky Course Creation Hub (/dashboard/courses/add/)
 */

use Chamilo\CoreBundle\Entity\CourseCategory;
use Chamilo\CoreBundle\Entity\Repository\CourseCategoryRepository;

$cidReset = true;
require_once __DIR__.'/../inc/global.inc.php';
require_once __DIR__.'/../inc/lib/course_curriculum.lib.php';
require_once __DIR__.'/../inc/lib/certificate_blueprint.lib.php';

$this_section = SECTION_PLATFORM_ADMIN;
api_protect_admin_script();

$currentUserId = api_get_user_id();
$tool_name = "Create New Course";
$interbreadcrumb[] = ['url' => 'index.php', 'name' => get_lang('PlatformAdmin')];
$interbreadcrumb[] = ['url' => 'course_list.php', 'name' => 'All Courses'];
$interbreadcrumb[] = ['url' => 'course_add.php', 'name' => 'Create Course'];

$em = Database::getManager();
/** @var CourseCategoryRepository $courseCategoriesRepo */
$courseCategoriesRepo = $em->getRepository('ChamiloCoreBundle:CourseCategory');
$accessUrlId = api_get_current_access_url_id();

$userTable = Database::get_main_table(TABLE_MAIN_USER);
$courseTable = Database::get_main_table(TABLE_MAIN_COURSE);
$categoryTable = Database::get_main_table(TABLE_MAIN_CATEGORY);

CertificateBlueprint::initTables();
$allBlueprints = CertificateBlueprint::getAllBlueprints();

// Fetch all instructors / teachers
$sqlTeachers = "SELECT id, firstname, lastname, email, username FROM $userTable WHERE status IN (".COURSEMANAGER.", 1) AND active = 1 ORDER BY firstname ASC";
$resTeachers = Database::query($sqlTeachers);
$allTeachers = [];
while ($row = Database::fetch_assoc($resTeachers)) {
    $allTeachers[] = $row;
}

// Fetch all course categories
$allCategories = CourseCategory::get_categories();
if (empty($allCategories)) {
    $sqlCats = "SELECT id, name, code FROM $categoryTable ORDER BY name ASC";
    $resCats = Database::query($sqlCats);
    $allCategories = [];
    while ($row = Database::fetch_assoc($resCats)) {
        $allCategories[] = $row;
    }
}

// Handle Form POST Submission
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_new_course') {
    $title = trim($_POST['title'] ?? '');
    $wantedCode = trim($_POST['visual_code'] ?? '');
    if (empty($wantedCode)) {
        $wantedCode = CourseManager::generate_course_code(api_substr($title, 0, 10));
    }
    $categoryCode = trim($_POST['category_code'] ?? '');
    $teacherId = isset($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : $currentUserId;
    $visibility = isset($_POST['visibility']) ? (int)$_POST['visibility'] : COURSE_VISIBILITY_OPEN_PLATFORM;
    $language = trim($_POST['course_language'] ?? api_get_setting('platformLanguage'));
    $diskQuota = isset($_POST['disk_quota']) ? (int)$_POST['disk_quota'] * 1024 * 1024 : 100 * 1024 * 1024;
    $autoCurriculum = !empty($_POST['auto_curriculum']);

    if (empty($title)) {
        $message = "Please enter a valid Course Title.";
        $messageType = "danger";
    } else {
        $courseParams = [
            'title' => $title,
            'wanted_code' => $wantedCode,
            'category_code' => $categoryCode,
            'course_language' => $language,
            'visibility' => $visibility,
            'teachers' => [$teacherId],
            'disk_quota' => $diskQuota,
            'exemplary_content' => $autoCurriculum,
            'subscribe' => 1,
            'unsubscribe' => 0,
        ];

        $newCourseInfo = CourseManager::create_course($courseParams, $teacherId);

        if ($newCourseInfo && !empty($newCourseInfo['code'])) {
            $createdCode = $newCourseInfo['code'];
            
            // Assign Certificate Blueprint if chosen
            $blueprintId = isset($_POST['blueprint_id']) ? (int)$_POST['blueprint_id'] : 0;
            if ($blueprintId > 0) {
                CertificateBlueprint::setCourseBlueprint($createdCode, $blueprintId);
            }

            // Auto-initialize Modern Curriculum Structure if requested
            CourseCurriculum::initTables();
            $existingCurriculum = CourseCurriculum::getCurriculum($createdCode);
            if (empty($existingCurriculum) && $autoCurriculum) {
                $chapId = CourseCurriculum::createChapter($createdCode, 'Module 1: Executive Foundation', 1);
                if ($chapId > 0) {
                    CourseCurriculum::createLesson($chapId, $createdCode, 'Welcome & Executive Course Overview', 'video', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4', '08:45', '', 1);
                    CourseCurriculum::createLesson($chapId, $createdCode, 'Core Frameworks & Principles', 'document', '', '15 mins', 'Welcome to the core curriculum. This module outlines strategic principles, case studies, and actionable execution methodologies for leaders.', 2);
                }
            }

            Display::addFlash(
                Display::return_message(
                    "Course <strong>".htmlspecialchars($title)."</strong> created successfully!",
                    'confirmation',
                    false
                )
            );

            header("Location: course_list.php");
            exit;
        } else {
            $message = "Could not create course. The course code or title may already exist on the platform.";
            $messageType = "danger";
        }
    }
}

Display::display_header($tool_name);
?>

<style>
/* Sparky Theme & Tree Sidebar Navigation */
:root {
    --gf-sidebar-bg: #0F172A;
    --gf-sidebar-active: #4F46E5;
    --gf-sidebar-line: #1E293B;
    --gf-sidebar-text: #94A3B8;
    --gf-sidebar-header: #FFFFFF;
    
    --color-primary: #4F46E5;
    --color-primary-hover: #4338CA;
    --color-border: #E2E8F0;
    --color-text-main: #0F172A;
    --color-text-muted: #64748B;
    --font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

#cm-content, #cm-content > .container {
    width: 100% !important;
    max-width: 100% !important;
    padding-left: 20px !important;
    padding-right: 20px !important;
    margin: 0 !important;
}

.gf-course-layout {
    display: flex;
    gap: 24px;
    align-items: flex-start;
    width: 100%;
    margin-top: 15px;
    margin-bottom: 30px;
    font-family: var(--font-family);
}

/* Sidebar Styling */
.gf-tree-sidebar {
    width: 260px;
    min-width: 260px;
    background: linear-gradient(180deg, #0B1727 0%, #070D18 100%);
    border: 1px solid #1E293B;
    border-radius: 12px;
    padding: 16px 12px;
    box-shadow: 0 4px 18px rgba(11, 23, 39, 0.45);
    flex-shrink: 0;
}

.gf-sidebar-top-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    color: #FFFFFF;
    font-weight: 700;
    font-size: 14.5px;
    text-decoration: none !important;
    border-radius: 8px;
    margin-bottom: 12px;
    background: rgba(255, 255, 255, 0.05);
    transition: all 0.2s ease;
}

.gf-sidebar-top-item:hover {
    background: rgba(227, 18, 43, 0.2);
    color: #FFFFFF;
}

.gf-nav-group { margin-bottom: 6px; }

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
    font-size: 14px;
    width: 18px;
    text-align: center;
    color: #94A3B8;
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
    padding: 0 0 0 10px;
    border-left: 1px solid var(--gf-sidebar-line);
}

.gf-nav-tree-item { position: relative; }

.gf-nav-tree-link {
    display: block;
    padding: 6px 10px;
    color: var(--gf-sidebar-text);
    font-size: 13px;
    font-weight: 500;
    text-decoration: none !important;
    border-radius: 6px;
    transition: all 0.15s ease;
    margin-bottom: 2px;
}

.gf-nav-tree-link:hover {
    color: #FFFFFF;
    background-color: rgba(255, 255, 255, 0.06);
}

.gf-nav-tree-link.active {
    color: #FFFFFF !important;
    background-color: var(--gf-sidebar-active) !important;
    font-weight: 600;
}

/* Right Main Content Area */
.gf-main-area {
    flex: 1;
    min-width: 0;
}

.gf-card-box {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
    margin-bottom: 24px;
    overflow: hidden;
}

.gf-card-header {
    padding: 18px 24px;
    border-bottom: 1px solid #F1F5F9;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.gf-card-header h3 {
    margin: 0;
    font-size: 17px;
    font-weight: 700;
    color: #0F172A;
    display: flex;
    align-items: center;
    gap: 8px;
}

.gf-card-body {
    padding: 24px;
}

.form-label {
    font-size: 13.5px;
    font-weight: 600;
    color: #334155;
    margin-bottom: 6px;
    display: block;
}

.form-help {
    font-size: 12px;
    color: #64748B;
    margin-top: 4px;
    display: block;
}

.form-control-modern {
    height: 42px;
    border-radius: 8px;
    border: 1px solid #CBD5E1;
    padding: 8px 14px;
    font-size: 14px;
    color: #0F172A;
    width: 100%;
    transition: all 0.15s ease;
    background: #FFFFFF;
}

.form-control-modern:focus {
    border-color: #4F46E5;
    outline: none;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
}

.btn-et-submit {
    background: #4F46E5;
    border: 1px solid #4F46E5;
    color: #FFFFFF !important;
    font-weight: 700;
    font-size: 14px;
    padding: 10px 24px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none !important;
}

.btn-et-submit:hover {
    background: #4338CA;
    border-color: #4338CA;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
}

.btn-et-cancel {
    background: #F8FAFC;
    border: 1px solid #CBD5E1;
    color: #475569 !important;
    font-weight: 600;
    font-size: 14px;
    padding: 10px 20px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none !important;
}

.btn-et-cancel:hover {
    background: #F1F5F9;
    color: #0F172A !important;
}
</style>

<div class="gf-course-layout">

    <!-- LEFT: Dark Navy Tree Sidebar Navigation -->
    <aside class="gf-tree-sidebar">
        <a href="index.php" class="gf-sidebar-top-item">
            <i class="fa fa-th-large"></i>
            <span>Dashboard</span>
        </a>

        <!-- Courses Group -->
        <div class="gf-nav-group" id="sideGroupCourses">
            <div class="gf-nav-group-header" onclick="toggleNav('sideGroupCourses')">
                <div class="gf-header-left">
                    <i class="fa fa-book"></i>
                    <span>Courses</span>
                </div>
                <i class="fa fa-chevron-down gf-group-chevron"></i>
            </div>
            <ul class="gf-nav-tree-list">
                <li class="gf-nav-tree-item">
                    <a href="course_list.php" class="gf-nav-tree-link">
                        All Courses
                    </a>
                </li>
                <li class="gf-nav-tree-item">
                    <a href="course_add.php" class="gf-nav-tree-link active">
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
            </ul>
        </div>

        <!-- Users Group -->
        <div class="gf-nav-group" id="sideGroupUsers">
            <div class="gf-nav-group-header" onclick="toggleNav('sideGroupUsers')">
                <div class="gf-header-left">
                    <i class="fa fa-users"></i>
                    <span>Users & Faculty</span>
                </div>
                <i class="fa fa-chevron-down gf-group-chevron"></i>
            </div>
            <ul class="gf-nav-tree-list">
                <li class="gf-nav-tree-item">
                    <a href="user_dashboard.php" class="gf-nav-tree-link">
                        All Students
                    </a>
                </li>
                <li class="gf-nav-tree-item">
                    <a href="user_dashboard.php?role=teacher" class="gf-nav-tree-link">
                        All Teachers
                    </a>
                </li>
                <li class="gf-nav-tree-item">
                    <a href="user_add.php" class="gf-nav-tree-link">
                        Add New User
                    </a>
                </li>
            </ul>
        </div>
    </aside>

    <!-- RIGHT: Course Creation Form -->
    <main class="gf-main-area">

        <!-- Header -->
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
            <div>
                <h1 style="font-size: 24px; font-weight: 800; color: #0F172A; margin: 0 0 6px 0; letter-spacing: -0.4px;">
                    <i class="fa fa-plus-circle" style="color: #4F46E5;"></i> Create New Course
                </h1>
                <p style="font-size: 13.5px; color: #64748B; margin: 0;">
                    Add an executive masterclass, live cohort, or self-paced course to the LMS backend engine.
                </p>
            </div>
            <a href="course_list.php" class="btn-et-cancel">
                <i class="fa fa-arrow-left"></i> Back to Courses
            </a>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo htmlspecialchars($messageType); ?>" style="border-radius: 8px; font-weight: 600;">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="course_add.php" id="formCreateCourse">
            <input type="hidden" name="action" value="save_new_course">

            <!-- Card 1: Essential Details -->
            <div class="gf-card-box">
                <div class="gf-card-header">
                    <h3><i class="fa fa-info-circle text-primary"></i> 1. Course Information</h3>
                </div>
                <div class="gf-card-body">
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label class="form-label" for="course_title">Course Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="course_title" class="form-control-modern" placeholder="e.g. Masterclass in Executive Leadership & Strategy" required autofocus />
                        <span class="form-help">Enter the full display title of the course or masterclass.</span>
                    </div>

                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label class="form-label" for="visual_code">Course Code (Optional)</label>
                                <input type="text" name="visual_code" id="visual_code" class="form-control-modern" placeholder="e.g. LEAD101 (Auto-generated if blank)" />
                                <span class="form-help">Unique identifier for this course on the platform.</span>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group" style="margin-bottom: 20px;">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <label class="form-label" for="category_code" style="margin-bottom: 0;">Category / Faculty</label>
                                    <button type="button" class="btn btn-link btn-xs" id="btn-quick-add-cat" style="padding: 0; color: #4F46E5; font-weight: 600; text-decoration: none;">
                                        <i class="fa fa-plus-circle"></i> + New Category
                                    </button>
                                </div>
                                <select name="category_code" id="category_code" class="form-control-modern" style="margin-top: 6px;">
                                    <option value="">-- No Category / General --</option>
                                    <?php foreach ($allCategories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat['code']); ?>">
                                            <?php echo htmlspecialchars($cat['name']); ?> (<?php echo htmlspecialchars($cat['code']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 2: Instructor & Access Permissions -->
            <div class="gf-card-box">
                <div class="gf-card-header">
                    <h3><i class="fa fa-user-circle text-primary"></i> 2. Instructor & Access Settings</h3>
                </div>
                <div class="gf-card-body">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label class="form-label" for="teacher_id">Lead Instructor / Teacher</label>
                                <select name="teacher_id" id="teacher_id" class="form-control-modern">
                                    <?php foreach ($allTeachers as $t): ?>
                                        <option value="<?php echo $t['id']; ?>" <?php echo (int)$t['id'] === $currentUserId ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($t['firstname'].' '.$t['lastname']); ?> (<?php echo htmlspecialchars($t['email']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="form-help">The primary educator managing this course curriculum and students.</span>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label class="form-label" for="visibility">Course Access & Visibility</label>
                                <select name="visibility" id="visibility" class="form-control-modern">
                                    <option value="<?php echo COURSE_VISIBILITY_OPEN_WORLD; ?>">Public (Open to the World)</option>
                                    <option value="<?php echo COURSE_VISIBILITY_OPEN_PLATFORM; ?>" selected>Platform Members (Registered Learners)</option>
                                    <option value="<?php echo COURSE_VISIBILITY_REGISTERED; ?>">Private (Invitation Only)</option>
                                    <option value="<?php echo COURSE_VISIBILITY_CLOSED; ?>">Draft / Closed (Admin & Teacher Only)</option>
                                </select>
                                <span class="form-help">Control who can discover and enroll into this course.</span>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label class="form-label" for="course_language">Course Language</label>
                                <select name="course_language" id="course_language" class="form-control-modern">
                                    <option value="english" selected>English</option>
                                    <option value="spanish">Spanish</option>
                                    <option value="french">French</option>
                                    <option value="german">German</option>
                                    <option value="hindi">Hindi</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label class="form-label" for="disk_quota">Storage Quota (MB)</label>
                                <input type="number" name="disk_quota" id="disk_quota" class="form-control-modern" value="500" min="10" max="10000" />
                                <span class="form-help">Allocated document and media storage for this course.</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label class="form-label" for="blueprint_id"><i class="fa fa-certificate text-warning"></i> Course Certificate Blueprint</label>
                        <select name="blueprint_id" id="blueprint_id" class="form-control-modern" style="font-weight: 600;">
                            <option value="0">-- No Certificate (Disabled) --</option>
                            <?php foreach ($allBlueprints as $bp): ?>
                                <option value="<?php echo $bp['id']; ?>">
                                    <?php echo htmlspecialchars($bp['name'].' ('.$bp['title'].')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="form-help">Select a certificate blueprint if you want learners to be eligible for a certificate upon completing this specific course.</span>
                    </div>

                    <!-- Auto Curriculum Checkbox -->
                    <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 14px 16px; margin-top: 10px; display: flex; align-items: flex-start; gap: 12px;">
                        <input type="checkbox" name="auto_curriculum" id="auto_curriculum" value="1" checked style="margin-top: 3px; width: 18px; height: 18px; cursor: pointer;" />
                        <label for="auto_curriculum" style="margin: 0; cursor: pointer;">
                            <strong style="color: #0F172A; font-size: 13.5px; display: block;">Initialize Modern Executive Curriculum Template</strong>
                            <span style="color: #64748B; font-size: 12px;">Automatically creates Chapter 1, sample executive video lesson, and study guide modules ready for the Learner Course Player.</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div style="display: flex; align-items: center; justify-content: flex-end; gap: 12px; margin-top: 24px;">
                <a href="course_list.php" class="btn-et-cancel">Cancel</a>
                <button type="submit" class="btn-et-submit">
                    <i class="fa fa-check-circle"></i> Create Course &amp; Open
                </button>
            </div>
        </form>

    </main>
</div>

<!-- Modal: Quick Add Category -->
<div class="modal fade" id="modalQuickAddCategory" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden; border: 1px solid #E2E8F0;">
            <div class="modal-header" style="background: #4F46E5; color: #FFFFFF; padding: 14px 18px;">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff; opacity:0.8;">&times;</button>
                <h4 class="modal-title" style="font-weight: 700; font-size: 15px;"><i class="fa fa-folder-open"></i> Add Course Category</h4>
            </div>
            <div class="modal-body" style="padding: 18px;">
                <div id="quickCatMsg" class="alert" style="display: none;"></div>
                <div class="form-group" style="margin-bottom: 14px;">
                    <label style="font-weight: 600; font-size: 13px;">Category Name *</label>
                    <input type="text" id="quickCatName" class="form-control" placeholder="e.g. Leadership & Strategy" />
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-weight: 600; font-size: 13px;">Category Code (Optional)</label>
                    <input type="text" id="quickCatCode" class="form-control" placeholder="e.g. LEAD" />
                </div>
            </div>
            <div class="modal-footer" style="background: #F8FAFC; padding: 12px 18px;">
                <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm" id="btnSaveQuickCat" style="font-weight: 600;">Save Category</button>
            </div>
        </div>
    </div>
</div>

<script>
function toggleNav(groupId) {
    var g = document.getElementById(groupId);
    if (g) g.classList.toggle('collapsed');
}

$(function() {
    $('#btn-quick-add-cat').on('click', function(e) {
        e.preventDefault();
        $('#quickCatName').val('');
        $('#quickCatCode').val('');
        $('#quickCatMsg').hide();
        $('#modalQuickAddCategory').modal('show');
    });

    $('#btnSaveQuickCat').on('click', function() {
        var name = $.trim($('#quickCatName').val());
        var code = $.trim($('#quickCatCode').val());
        if (!name) {
            $('#quickCatMsg').removeClass('alert-success').addClass('alert alert-danger').text('Please enter a category name.').show();
            return;
        }

        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: '<?php echo api_get_path(WEB_AJAX_PATH); ?>course_category.ajax.php?a=add_category',
            type: 'POST',
            data: { name: name, code: code },
            dataType: 'json',
            success: function(resp) {
                btn.prop('disabled', false).html('Save Category');
                if (resp && resp.status === 'success') {
                    $('#modalQuickAddCategory').modal('hide');
                    var newOption = new Option(resp.name + ' (' + resp.code + ')', resp.code, true, true);
                    $('#category_code').append(newOption).val(resp.code);
                } else {
                    $('#quickCatMsg').removeClass('alert-success').addClass('alert alert-danger').text(resp.message || 'Error saving category').show();
                }
            },
            error: function() {
                btn.prop('disabled', false).html('Save Category');
                $('#quickCatMsg').removeClass('alert-success').addClass('alert alert-danger').text('Server communication error.').show();
            }
        });
    });
});
</script>

<?php
Display::display_footer();
