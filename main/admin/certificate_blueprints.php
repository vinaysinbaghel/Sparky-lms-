<?php
/* For licensing terms, see /license.txt */

/**
 * Certificate Blueprints Management Panel
 * Sparky Learning Platform
 */

$cidReset = true;
require_once __DIR__.'/../inc/global.inc.php';
require_once __DIR__.'/../inc/lib/certificate_blueprint.lib.php';

// Access restrictions - Allow platform admins, course admins, and course teachers
$courseParam = isset($_REQUEST['course']) ? Security::remove_XSS($_REQUEST['course']) : (isset($_REQUEST['course_code']) ? Security::remove_XSS($_REQUEST['course_code']) : (isset($_REQUEST['cidReq']) ? Security::remove_XSS($_REQUEST['cidReq']) : ''));
$isAllowed = api_is_platform_admin() || api_is_platform_admin_by_id() || api_is_course_admin() || (!empty($courseParam) && CourseManager::is_course_teacher(api_get_user_id(), $courseParam));
if (!$isAllowed) {
    api_not_allowed(true);
}

$this_section = SECTION_PLATFORM_ADMIN;
$currentUserId = api_get_user_id();

CertificateBlueprint::initTables();

$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : '';
$message = '';
$messageType = 'success';

// Handle Actions
if ($action === 'delete' && isset($_REQUEST['id'])) {
    $delId = (int)$_REQUEST['id'];
    if ($delId > 0) {
        CertificateBlueprint::deleteBlueprint($delId);
        $message = "Certificate blueprint #$delId has been deleted.";
        $messageType = "warning";
    }
}

if ($action === 'duplicate' && isset($_REQUEST['id'])) {
    $dupId = (int)$_REQUEST['id'];
    if ($dupId > 0) {
        $newId = CertificateBlueprint::duplicateBlueprint($dupId);
        if ($newId) {
            $message = "Certificate blueprint duplicated as #$newId.";
            $messageType = "success";
        }
    }
}

if ($action === 'batch_delete' && isset($_POST['blueprint_ids']) && is_array($_POST['blueprint_ids'])) {
    $count = 0;
    foreach ($_POST['blueprint_ids'] as $bpId) {
        if (CertificateBlueprint::deleteBlueprint((int)$bpId)) {
            $count++;
        }
    }
    $message = "Successfully deleted $count certificate blueprint(s).";
    $messageType = "warning";
}

if ($action === 'assign_courses' && isset($_POST['blueprint_id'])) {
    $bpId = (int)$_POST['blueprint_id'];
    $assignedCourses = isset($_POST['courses']) && is_array($_POST['courses']) ? $_POST['courses'] : [];
    $bp = CertificateBlueprint::getBlueprint($bpId);
    if ($bp) {
        $bp['courses'] = $assignedCourses;
        CertificateBlueprint::saveBlueprint($bp, $bpId);
        $message = "Course assignments updated for <strong>".htmlspecialchars($bp['name'])."</strong>.";
        $messageType = "success";
    }
}

// Fetch Search & Data
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$blueprints = CertificateBlueprint::getAllBlueprints($search);
$allCourses = CourseManager::get_courses_list();

// Load page header
Display::display_header('Certificate Blueprints');
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Alex+Brush&family=Allura&family=Cinzel:wght@500;700;900&family=Cinzel+Decorative:wght@700&family=Cormorant+Garamond:ital,wght@0,600;0,700;1,400&family=Dancing+Script:wght@600;700&family=Great+Vibes&family=Inter:wght@400;500;600;700&family=Merriweather:ital,wght@0,400;0,700;1,400&family=Montserrat:wght@400;600;700;800&family=Outfit:wght@400;600;700&family=Parisienne&family=Pinyon+Script&family=Playfair+Display:ital,wght@0,600;0,700;1,400&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

<style>
:root {
    --color-primary: #4F46E5;
    --color-primary-hover: #4338CA;
    --color-sidebar-bg: #0F172A;
    --color-tree-line: #334155;
    --color-tree-active: #4F46E5;
    --color-purple-btn: #4F46E5;
    --color-purple-hover: #4338CA;
}

body {
    background-color: #F8FAFC;
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    color: #1E293B;
}

/* Full screen container override */
#cm-content, #cm-content > .container {
    width: 100% !important;
    max-width: 100% !important;
    padding-left: 20px !important;
    padding-right: 20px !important;
    margin: 0 !important;
}

.gf-layout-container {
    display: flex;
    min-height: calc(100vh - 120px);
    width: 100%;
    margin: 0;
}

/* LEFT: Tree Sidebar */
.gf-tree-sidebar {
    width: 250px;
    background: var(--color-sidebar-bg);
    padding: 24px 16px;
    flex-shrink: 0;
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.2);
}

.gf-nav-group {
    margin-bottom: 24px;
}

.gf-nav-group-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    color: #9CA3AF;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    padding: 6px 12px;
    cursor: pointer;
    user-select: none;
}

.gf-nav-group-header:hover {
    color: #FFFFFF;
}

.gf-nav-group-header .gf-header-left {
    display: flex;
    align-items: center;
    gap: 8px;
}

.gf-nav-group.collapsed .gf-nav-tree-list {
    display: none;
}

.gf-nav-group.collapsed .gf-group-chevron {
    transform: rotate(-90deg);
}

.gf-group-chevron {
    font-size: 10px;
    transition: transform 0.2s;
}

.gf-nav-tree-list {
    list-style: none;
    padding-left: 20px;
    margin: 8px 0 0 0;
    position: relative;
}

.gf-nav-tree-list::before {
    content: '';
    position: absolute;
    top: 0;
    bottom: 12px;
    left: 8px;
    width: 2px;
    background-color: var(--color-tree-line);
}

.gf-nav-tree-item {
    position: relative;
    margin-bottom: 2px;
}

.gf-nav-tree-item::before {
    content: '';
    position: absolute;
    top: 14px;
    left: -12px;
    width: 12px;
    height: 2px;
    background-color: var(--color-tree-line);
}

.gf-nav-tree-link {
    display: block;
    padding: 7px 12px;
    color: #CBD5E1;
    font-size: 13px;
    font-weight: 500;
    text-decoration: none !important;
    border-radius: 6px;
    transition: all 0.15s ease;
}

.gf-nav-tree-link:hover {
    color: #FFFFFF;
    background-color: rgba(79, 70, 229, 0.2);
}

.gf-nav-tree-link.active {
    color: #FFFFFF;
    background-color: var(--color-tree-active);
    font-weight: 600;
}

/* RIGHT: Main Content */
.gf-main-content {
    flex: 1;
    padding: 32px 40px;
    background: #F8FAFC;
    overflow-x: auto;
}

.gf-page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.gf-page-title {
    font-size: 24px;
    font-weight: 700;
    color: #0F172A;
    margin: 0;
    letter-spacing: -0.02em;
}

.gf-header-actions {
    display: flex;
    gap: 12px;
    align-items: center;
}

.btn-create-blueprint {
    background: var(--color-purple-btn);
    color: #FFFFFF !important;
    border: none;
    border-radius: 8px;
    padding: 9px 20px;
    font-size: 14px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 2px 4px rgba(99, 102, 241, 0.2);
    transition: all 0.2s;
    text-decoration: none !important;
}

.btn-create-blueprint:hover {
    background: var(--color-purple-hover);
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
}

.btn-batch-delete {
    border: 1px solid #E2E8F0;
    background: #FFFFFF;
    color: #94A3B8;
    border-radius: 8px;
    padding: 8px 16px;
    font-size: 13.5px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    cursor: not-allowed;
    transition: all 0.2s;
}

.btn-batch-delete.active {
    border-color: #FCA5A5;
    background: #FEF2F2;
    color: #DC2626;
    cursor: pointer;
    box-shadow: 0 1px 3px rgba(220, 38, 38, 0.15);
}

.btn-batch-delete.active:hover {
    background: #FEE2E2;
    border-color: #EF4444;
}

/* Search bar */
.gf-search-bar {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    padding: 10px 16px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.gf-search-input {
    border: none;
    outline: none;
    width: 100%;
    font-size: 14px;
    color: #1E293B;
}

/* Table */
.gf-table-wrapper {
    background: #FFFFFF;
    border-radius: 10px;
    border: 1px solid #E2E8F0;
    overflow: visible !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    position: relative;
}

.gf-blueprint-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
}

.gf-blueprint-table th {
    background: #FFFFFF;
    color: #475569;
    font-size: 13px;
    font-weight: 600;
    padding: 14px 20px;
    border-bottom: 1px solid #E2E8F0;
}

.gf-blueprint-table td {
    padding: 14px 20px;
    font-size: 14px;
    color: #1E293B;
    border-bottom: 1px solid #F1F5F9;
    vertical-align: middle;
}

.gf-blueprint-table tbody tr:hover {
    background: #F8FAFC;
}

.gf-blueprint-table tbody tr.row-selected {
    background-color: #F5F3FF !important;
}

.gf-blueprint-table input[type="checkbox"] {
    width: 17px;
    height: 17px;
    border-radius: 4px;
    cursor: pointer;
}

.blueprint-name-link {
    font-weight: 600;
    color: #0F172A;
    text-decoration: none;
    transition: color 0.15s;
}

.blueprint-name-link:hover {
    color: var(--color-purple-btn);
}

.badge-template {
    font-size: 11px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 4px;
    background: #F1F5F9;
    color: #475569;
    margin-left: 8px;
}

/* Action Cell with Direct Buttons & Dropdown */
.gf-actions-cell {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    position: relative;
}

.gf-btn-icon {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #E2E8F0;
    background: #FFFFFF;
    color: #64748B;
    font-size: 13.5px;
    text-decoration: none !important;
    transition: all 0.15s ease;
    cursor: pointer;
}

.gf-btn-icon:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.06);
}

.gf-btn-icon.gf-btn-edit:hover {
    color: #4F46E5;
    border-color: #C7D2FE;
    background: #EEF2FF;
}

.gf-btn-icon.gf-btn-delete {
    color: #EF4444;
}

.gf-btn-icon.gf-btn-delete:hover {
    color: #FFFFFF;
    border-color: #EF4444;
    background: #EF4444;
}

/* Action 3-dots Menu */
.gf-action-dropdown {
    position: relative;
    display: inline-block;
}

.gf-dots-btn {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #E2E8F0;
    background: #FFFFFF;
    color: #64748B;
    font-size: 14px;
    cursor: pointer;
    line-height: 1;
    transition: all 0.15s ease;
}

.gf-dots-btn:hover, .gf-action-dropdown.open .gf-dots-btn {
    background: #F1F5F9;
    color: #0F172A;
    border-color: #CBD5E1;
}

.gf-action-dropdown .dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    left: auto;
    z-index: 9999 !important;
    min-width: 185px;
    margin-top: 4px;
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    padding: 6px 0;
    list-style: none;
    text-align: left;
    display: none;
}

.gf-action-dropdown.open .dropdown-menu {
    display: block !important;
}

.gf-action-dropdown .dropdown-menu li a {
    padding: 8px 16px;
    font-size: 13px;
    color: #334155;
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    transition: background 0.15s, color 0.15s;
}

.gf-action-dropdown .dropdown-menu li a:hover {
    background: #F8FAFC;
    color: var(--color-purple-btn);
}

.gf-action-dropdown .dropdown-menu li a.delete-item:hover {
    background: #FEF2F2;
    color: #DC2626 !important;
}

.gf-action-dropdown .dropdown-menu li.divider {
    height: 1px;
    margin: 6px 0;
    background-color: #E2E8F0;
}
</style>

<div class="gf-layout-container">

    <!-- LEFT: Tree Sidebar Navigation -->
    <aside class="gf-tree-sidebar">
        <!-- Group 1: Users -->
        <div class="gf-nav-group" id="groupUsers">
            <div class="gf-nav-group-header" onclick="toggleNavGroup('groupUsers')">
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
            </ul>
        </div>

        <!-- Group 2: Courses -->
        <div class="gf-nav-group" id="groupCourses">
            <div class="gf-nav-group-header" onclick="toggleNavGroup('groupCourses')">
                <div class="gf-header-left">
                    <i class="fa fa-laptop"></i>
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
                    <a href="course_category.php" class="gf-nav-tree-link">
                        Categories
                    </a>
                </li>
                <li class="gf-nav-tree-item">
                    <a href="certificate_blueprints.php" class="gf-nav-tree-link active">
                        Certificate Blueprints
                    </a>
                </li>
            </ul>
        </div>

        <!-- Group 3: Coaching -->
        <div class="gf-nav-group" id="groupCoaching">
            <div class="gf-nav-group-header" onclick="toggleNavGroup('groupCoaching')">
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
            </ul>
        </div>

        <!-- Group 4: System -->
        <div class="gf-nav-group" id="groupAdmin">
            <div class="gf-nav-group-header" onclick="toggleNavGroup('groupAdmin')">
                <div class="gf-header-left">
                    <i class="fa fa-cog"></i>
                    <span>System</span>
                </div>
                <i class="fa fa-chevron-down gf-group-chevron"></i>
            </div>
            <ul class="gf-nav-tree-list">
                <li class="gf-nav-tree-item">
                    <a href="index.php" class="gf-nav-tree-link">
                        Settings
                    </a>
                </li>
            </ul>
        </div>
    </aside>

    <!-- RIGHT: Main Content Area -->
    <main class="gf-main-content">

        <!-- Notification Banner -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible" style="border-radius: 8px; font-weight: 500;">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="gf-page-header">
            <h1 class="gf-page-title">Certificate Blueprints</h1>
            <div class="gf-header-actions">
                <button type="button" id="batchDeleteBtn" class="btn-batch-delete" onclick="triggerBatchDelete()">
                    <i class="fa fa-trash-o"></i> Delete
                </button>
                <a href="certificate_editor.php" class="btn-create-blueprint">
                    <i class="fa fa-plus-circle"></i> Create
                </a>
            </div>
        </div>

        <!-- Search Bar -->
        <form method="get" action="certificate_blueprints.php">
            <div class="gf-search-bar">
                <i class="fa fa-search" style="color: #94A3B8;"></i>
                <input type="text" name="q" class="gf-search-input" placeholder="Search blueprints by name or title..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
        </form>

        <!-- Blueprints Table Form -->
        <div class="gf-table-wrapper">
            <form id="blueprintTableForm" method="post" action="certificate_blueprints.php">
                <input type="hidden" name="action" id="blueprintActionInput" value="">
                
                <table class="gf-blueprint-table">
                    <thead>
                        <tr>
                            <th style="width: 40px; text-align: center;">
                                <input type="checkbox" id="masterCheckbox" onclick="toggleSelectAll(this)">
                            </th>
                            <th style="width: 100px;">ID</th>
                            <th>Name</th>
                            <th style="width: 140px;">Courses</th>
                            <th style="width: 200px;">Created At</th>
                            <th style="width: 140px; text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($blueprints)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 48px; color: #94A3B8;">
                                    <i class="fa fa-certificate" style="font-size: 36px; margin-bottom: 12px; display: block; opacity: 0.3;"></i>
                                    No certificate blueprints found. Click <strong>Create</strong> to design your first custom certificate.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($blueprints as $bp): 
                                $formattedDate = date('Y-m-d H:i', strtotime($bp['created_at']));
                                $templateLabels = [
                                    'et_masterclass' => 'Executive Masterclass',
                                    'classic_gold' => 'Classic Gold',
                                    'modern_blue' => 'Modern Blue',
                                    'blank_student_only' => 'Blank (Name Only)'
                                ];
                                $tLabel = isset($templateLabels[$bp['template_key']]) ? $templateLabels[$bp['template_key']] : 'Custom';
                            ?>
                                <tr id="bpRow_<?php echo $bp['id']; ?>">
                                    <td style="text-align: center;">
                                        <input type="checkbox" name="blueprint_ids[]" value="<?php echo $bp['id']; ?>" class="row-checkbox" onchange="updateBatchState()">
                                    </td>
                                    <td style="color: #64748B; font-weight: 500;">
                                        <?php echo $bp['id']; ?>
                                    </td>
                                    <td>
                                        <a href="certificate_editor.php?id=<?php echo $bp['id']; ?>" class="blueprint-name-link">
                                            <?php echo htmlspecialchars($bp['name']); ?>
                                        </a>
                                        <span class="badge-template"><?php echo $tLabel; ?></span>
                                    </td>
                                    <td style="font-weight: 600; color: #334155;">
                                        <?php echo (int)$bp['course_count']; ?>
                                    </td>
                                    <td style="color: #64748B; font-size: 13.5px;">
                                        <?php echo $formattedDate; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <div class="gf-actions-cell">
                                            <!-- Quick Edit Button -->
                                            <a href="certificate_editor.php?id=<?php echo $bp['id']; ?>" class="gf-btn-icon gf-btn-edit" title="Edit Blueprint">
                                                <i class="fa fa-pencil"></i>
                                            </a>
                                            <!-- Quick Delete Button -->
                                            <a href="certificate_blueprints.php?action=delete&id=<?php echo $bp['id']; ?>" class="gf-btn-icon gf-btn-delete" title="Delete Blueprint" onclick="return confirm('Permanently delete blueprint &quot;<?php echo htmlspecialchars(addslashes($bp['name'])); ?>&quot;?');">
                                                <i class="fa fa-trash-o"></i>
                                            </a>
                                            <!-- More Actions Dropdown -->
                                            <div class="dropdown gf-action-dropdown">
                                                <button class="gf-dots-btn" type="button" title="More Options">
                                                    <i class="fa fa-ellipsis-v"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-right">
                                                    <li>
                                                        <a href="certificate_editor.php?id=<?php echo $bp['id']; ?>">
                                                            <i class="fa fa-pencil" style="color: #4F46E5;"></i> Edit Blueprint
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a href="javascript:void(0)" onclick="openLivePreviewModal(<?php echo $bp['id']; ?>)">
                                                            <i class="fa fa-eye" style="color: #0284C7;"></i> Live Preview
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a href="javascript:void(0)" onclick="openAssignModal(<?php echo $bp['id']; ?>, '<?php echo htmlspecialchars(addslashes($bp['name'])); ?>')">
                                                            <i class="fa fa-book" style="color: #16A34A;"></i> Assign Courses
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a href="certificate_blueprints.php?action=duplicate&id=<?php echo $bp['id']; ?>">
                                                            <i class="fa fa-clone" style="color: #D97706;"></i> Duplicate
                                                        </a>
                                                    </li>
                                                    <li role="separator" class="divider"></li>
                                                    <li>
                                                        <a href="certificate_blueprints.php?action=delete&id=<?php echo $bp['id']; ?>" class="delete-item" style="color: #EF4444;" onclick="return confirm('Permanently delete blueprint &quot;<?php echo htmlspecialchars(addslashes($bp['name'])); ?>&quot;?');">
                                                            <i class="fa fa-trash"></i> Delete Blueprint
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
            </form>
        </div>

    </main>
</div>

<!-- MODAL: Assign Courses -->
<div class="modal fade" id="modalAssignCourses" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden;">
            <form method="post" action="certificate_blueprints.php">
                <input type="hidden" name="action" value="assign_courses">
                <input type="hidden" name="blueprint_id" id="assignModalBpId" value="">
                
                <div class="modal-header" style="background: #0F172A; color: #FFFFFF; padding: 16px 20px;">
                    <button type="button" class="close" data-dismiss="modal" style="color:#fff; opacity:0.8;"><span>&times;</span></button>
                    <h4 class="modal-title" id="assignModalTitle" style="font-weight: 700; font-size: 16px;">
                        <i class="fa fa-graduation-cap"></i> Assign to Courses
                    </h4>
                </div>
                <div class="modal-body" style="padding: 20px;">
                    <p style="font-size: 13px; color: #64748B; margin-bottom: 14px;">
                        Select the courses/masterclasses that will automatically issue this certificate upon completion:
                    </p>
                    <div class="form-group">
                        <select name="courses[]" id="assignCoursesSelect" class="form-control" multiple size="7" style="border-radius: 6px; font-size: 13.5px;">
                            <?php foreach ($allCourses as $c): ?>
                                <option value="<?php echo htmlspecialchars($c['code']); ?>">
                                    <?php echo htmlspecialchars($c['title'].' ('.$c['code'].')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #94A3B8; font-size: 12px; margin-top: 6px; display: block;">
                            Hold Ctrl (Windows) or Command (Mac) to select multiple courses.
                        </small>
                    </div>
                </div>
                <div class="modal-footer" style="background: #F8FAFC; padding: 12px 20px;">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: var(--color-purple-btn); border-color: var(--color-purple-btn); font-weight: 600;">
                        Save Assignments
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: Live Preview -->
<div class="modal fade" id="modalLivePreview" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document" style="width: 860px;">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header" style="background: #0F172A; color: #FFFFFF; padding: 14px 20px; display: flex; justify-content: space-between; align-items: center;">
                <h4 class="modal-title" id="previewModalTitle" style="font-weight: 700; font-size: 16px; margin: 0;">
                    <i class="fa fa-certificate"></i> Certificate Preview
                </h4>
                <button type="button" class="close" data-dismiss="modal" style="color:#fff; opacity:0.8; margin-top:-2px;"><span>&times;</span></button>
            </div>
            <div class="modal-body" id="previewModalBody" style="padding: 24px; background: #E2E8F0; text-align: center;">
                <div style="padding: 40px; color: #64748B;"><i class="fa fa-spinner fa-spin fa-2x"></i></div>
            </div>
            <div class="modal-footer" style="background: #F8FAFC; padding: 12px 20px;">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-info" onclick="printModalCertificate()"><i class="fa fa-print"></i> Print / Save PDF</button>
            </div>
        </div>
    </div>
</div>

<script>
function toggleNavGroup(groupId) {
    var g = document.getElementById(groupId);
    if (g) g.classList.toggle('collapsed');
}

function toggleSelectAll(master) {
    var cbs = document.querySelectorAll('.row-checkbox');
    cbs.forEach(function(cb) { cb.checked = master.checked; });
    updateBatchState();
}

function updateBatchState() {
    var checked = document.querySelectorAll('.row-checkbox:checked');
    var btn = document.getElementById('batchDeleteBtn');
    if (checked.length > 0) {
        btn.classList.add('active');
        btn.innerHTML = '<i class="fa fa-trash-o"></i> Delete (' + checked.length + ')';
    } else {
        btn.classList.remove('active');
        btn.innerHTML = '<i class="fa fa-trash-o"></i> Delete';
    }

    // Highlight selected rows
    document.querySelectorAll('.row-checkbox').forEach(function(cb) {
        var tr = cb.closest('tr');
        if (tr) {
            if (cb.checked) {
                tr.classList.add('row-selected');
            } else {
                tr.classList.remove('row-selected');
            }
        }
    });
}

function triggerBatchDelete() {
    var checked = document.querySelectorAll('.row-checkbox:checked');
    if (checked.length === 0) {
        alert('Please select at least one blueprint using the checkboxes to delete.');
        return;
    }
    if (confirm('Permanently delete ' + checked.length + ' selected certificate blueprint(s)?')) {
        document.getElementById('blueprintActionInput').value = 'batch_delete';
        document.getElementById('blueprintTableForm').submit();
    }
}

// Robust dropdown toggle with viewport detection
$(document).ready(function() {
    $(document).on('click', '.gf-dots-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var $dropdown = $(this).closest('.gf-action-dropdown');
        var $menu = $dropdown.find('.dropdown-menu');
        var wasOpen = $dropdown.hasClass('open') || $menu.is(':visible');
        
        // Close all other dropdowns
        $('.gf-action-dropdown .dropdown-menu').hide();
        $('.gf-action-dropdown').removeClass('open');
        
        if (!wasOpen) {
            $dropdown.addClass('open');
            $menu.show();
            
            // Check if menu goes below viewport bottom
            var rect = $menu[0].getBoundingClientRect();
            var viewportHeight = window.innerHeight || document.documentElement.clientHeight;
            if (rect.bottom > viewportHeight - 10) {
                $menu.css({ top: 'auto', bottom: '100%', marginBottom: '4px' });
            } else {
                $menu.css({ top: '100%', bottom: 'auto', marginTop: '4px' });
            }
        }
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('.gf-action-dropdown').length) {
            $('.gf-action-dropdown .dropdown-menu').hide();
            $('.gf-action-dropdown').removeClass('open');
        }
    });
});

function openAssignModal(bpId, bpName) {
    document.getElementById('assignModalBpId').value = bpId;
    document.getElementById('assignModalTitle').innerHTML = '<i class="fa fa-graduation-cap"></i> Assign: ' + bpName;
    
    // Reset selections
    var select = document.getElementById('assignCoursesSelect');
    for (var i = 0; i < select.options.length; i++) {
        select.options[i].selected = false;
    }

    // Fetch current blueprint courses
    fetch('certificate_editor.php?action=ajax_get_blueprint&id=' + bpId)
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data && data.course_codes) {
                for (var i = 0; i < select.options.length; i++) {
                    if (data.course_codes.indexOf(select.options[i].value) !== -1) {
                        select.options[i].selected = true;
                    }
                }
            }
        });

    $('#modalAssignCourses').modal('show');
}

function openLivePreviewModal(bpId) {
    var body = document.getElementById('previewModalBody');
    body.innerHTML = '<div style="padding: 40px; color: #64748B;"><i class="fa fa-spinner fa-spin fa-2x"></i> Loading certificate preview...</div>';
    $('#modalLivePreview').modal('show');

    fetch('certificate_editor.php?action=ajax_get_blueprint&id=' + bpId)
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.error) {
                body.innerHTML = '<div class="alert alert-danger">' + data.error + '</div>';
                return;
            }
            document.getElementById('previewModalTitle').innerText = data.name + ' - Preview';
            body.innerHTML = renderCertificateHtml(data, 'Johnathan Doe (Learner)', 'AI for Business Professionals');
        });
}

function renderCertificateHtml(d, studentName, courseName) {
    if (d.custom_html && d.custom_html.trim()) {
        var customRendered = d.custom_html
            .replace(/\{\{STUDENT_NAME\}\}/g, studentName)
            .replace(/\{\{COURSE_NAME\}\}/g, courseName)
            .replace(/\{\{DATE\}\}/g, d.date_text || 'October 2026')
            .replace(/\{\{CERTIFICATE_ID\}\}/g, 'SPARKY-CERT-' + (d.id || 'DEMO'))
            .replace(/\{\{ISSUER\}\}/g, d.issuer_name || 'Sparky Learning')
            .replace(/\{\{SIGNATORY_NAME\}\}/g, d.signatory_name || 'Academic Director')
            .replace(/\{\{SIGNATORY_TITLE\}\}/g, d.signatory_title || 'Sparky Learning Platform');
        return '<div id="certificatePrintArea" style="margin: 0 auto; display: flex; justify-content: center;">' + customRendered + '</div>';
    }

    var primary = d.primary_color || '#4F46E5';
    var font = d.font_family || 'Playfair Display';
    var tKey = d.template_key || 'classic_gold';

    var studentFont = d.student_font || font;
    var studentRot = parseInt(d.student_rotation) || 0;
    var studentFontSize = parseInt(d.student_font_size) || 34;
    var studentOffsetX = parseInt(d.student_offset_x) || 0;
    var studentOffsetY = parseInt(d.student_offset_y) || 0;
    var studentFontWeight = d.student_font_weight || '700';
    var studentSpacing = parseInt(d.student_letter_spacing) || 0;

    var studentTransformCss = 'transform: translate(' + studentOffsetX + 'px, ' + studentOffsetY + 'px) rotate(' + studentRot + 'deg); transform-origin: center center; display: inline-block;';
    var studentStyleCss = 'font-family: \'' + studentFont + '\', cursive, serif; font-size: ' + studentFontSize + 'px; font-weight: ' + studentFontWeight + '; letter-spacing: ' + studentSpacing + 'px; color: ' + primary + '; ' + studentTransformCss;

    if (tKey === 'blank_student_only') {
        var bg = d.background_image ? 'background: url(\'' + d.background_image + '\') no-repeat center center / cover;' : 'background: #FFFFFF; border: 2px dashed #CBD5E1;';
        return '<div id="certificatePrintArea" style="width: 780px; height: 550px; margin: 0 auto; ' + bg + ' box-shadow: 0 10px 25px rgba(0,0,0,0.15); box-sizing: border-box; display: flex; align-items: center; justify-content: center; text-align: center; position: relative;">' +
            '<div style="' + studentStyleCss + ' max-width: 90%;">' + studentName + '</div>' +
        '</div>';
    }

    var borderStyle = 'border: 12px double #D4AF37;';
    var bgGradient = 'background: #FFFFFF;';
    var headerEmblem = '<div style="color: ' + primary + '; font-size: 28px; margin-bottom: 8px;"><i class="fa fa-trophy"></i></div>';

    if (d.background_image) {
        borderStyle = 'border: 1px solid #CBD5E1;';
        bgGradient = 'background: url(\'' + d.background_image + '\') no-repeat center center / cover;';
        headerEmblem = '';
    } else if (tKey === 'et_masterclass') {
        borderStyle = 'border: 8px solid #4F46E5; outline: 3px solid #6366F1; outline-offset: -12px;';
        bgGradient = 'background: radial-gradient(circle, #FFFFFF 70%, #EEF2FF 100%);';
        headerEmblem = '<div style="font-weight: 900; font-size: 20px; letter-spacing: 2px; color: #111827; margin-bottom: 4px;">⚡ SPARKY</div><div style="font-weight: 800; font-size: 14px; letter-spacing: 3px; color: #4F46E5; margin-bottom: 12px;">CERTIFICATE OF EXCELLENCE</div>';
    } else if (tKey === 'modern_blue') {
        borderStyle = 'border: 8px solid #0284C7;';
        bgGradient = 'background: linear-gradient(135deg, #FFFFFF 0%, #F0F9FF 100%);';
        headerEmblem = '<div style="color: #0284C7; font-size: 30px; margin-bottom: 8px;"><i class="fa fa-graduation-cap"></i></div>';
    }

    return '<div id="certificatePrintArea" style="width: 780px; height: 550px; margin: 0 auto; ' + bgGradient + ' ' + borderStyle + ' padding: 36px 48px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); box-sizing: border-box; display: flex; flex-direction: column; justify-content: space-between; text-align: center; font-family: \'' + font + '\', serif; position: relative;">' +
        '<div>' +
            headerEmblem +
            '<div style="font-size: 24px; font-weight: 700; letter-spacing: 3px; color: #1E293B; text-transform: uppercase; margin-bottom: 12px;">' + (d.title || 'CERTIFICATE OF COMPLETION') + '</div>' +
            '<div style="font-size: 14px; color: #64748B; font-style: italic; margin-bottom: 14px;">' + (d.text_above_student || 'This is to certify that') + '</div>' +
            '<div style="margin-bottom: 14px;"><div style="' + studentStyleCss + ' border-bottom: 2px solid #E2E8F0; padding: 0 30px 4px 30px;">' + studentName + '</div></div>' +
            '<div style="font-size: 13.5px; color: #64748B; margin-bottom: 10px;">' + (d.text_above_course || 'Has pursued studies and completed all the requirements for') + '</div>' +
            '<div style="font-size: 20px; font-weight: 700; color: #0F172A; margin-bottom: 10px;">' + courseName + '</div>' +
            '<div style="font-size: 12.5px; color: #64748B;">' + (d.text_below_course || 'Successfully') + ' &bull; ' + (d.date_text || 'October 2026') + '</div>' +
        '</div>' +
        '<div style="display: flex; justify-content: space-between; align-items: flex-end; padding-top: 20px; border-top: 1px solid #F1F5F9;">' +
            '<div style="text-align: left;">' +
                '<div style="font-size: 11px; color: #94A3B8; text-transform: uppercase; letter-spacing: 1px;">Issuer</div>' +
                '<div style="font-size: 13px; font-weight: 600; color: #334155;">' + (d.issuer_name || 'Sparky Learning') + '</div>' +
            '</div>' +
            '<div>' +
                '<div style="width: 54px; height: 54px; border-radius: 50%; background: linear-gradient(135deg, #F59E0B, #D97706); color: #FFF; display: inline-flex; align-items: center; justify-content: center; font-size: 24px; box-shadow: 0 4px 8px rgba(217,119,6,0.3);"><i class="fa fa-shield"></i></div>' +
                '<div style="font-size: 10px; font-weight: 700; color: #D97706; text-transform: uppercase; margin-top: 4px; letter-spacing: 1px;">Official Seal</div>' +
            '</div>' +
            '<div style="text-align: right;">' +
                '<div style="font-family: \'Great Vibes\', cursive; font-size: 26px; color: #1E293B; line-height: 1;">' + (d.signatory_name || 'Academic Director') + '</div>' +
                '<div style="border-top: 1px solid #CBD5E1; padding-top: 2px; margin-top: 2px; font-size: 11px; font-weight: 600; color: #64748B;">' + (d.signatory_title || 'Sparky Learning Platform') + '</div>' +
            '</div>' +
        '</div>' +
    '</div>';
}

function printModalCertificate() {
    var content = document.getElementById('certificatePrintArea');
    if (!content) return;
    var printWin = window.open('', '', 'width=900,height=650');
    printWin.document.write('<html><head><title>Certificate</title><link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Inter:wght@400;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,400&display=swap" rel="stylesheet"><style>body{margin:0;padding:20px;display:flex;justify-content:center;align-items:center;background:#fff;}@page{size:landscape;margin:0;}</style></head><body>');
    printWin.document.write(content.outerHTML);
    printWin.document.write('</body></html>');
    printWin.document.close();
    printWin.focus();
    setTimeout(function() {
        printWin.print();
        printWin.close();
    }, 500);
}
</script>

<?php
Display::display_footer();
