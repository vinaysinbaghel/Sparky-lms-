<?php
/* For licensing terms, see /license.txt */

/**
 * Sparky-Style User Creation Portal
 * Fast Onboarding for Students, Teachers, and Platform Administrators
 */

$cidReset = true;
require_once __DIR__.'/../inc/global.inc.php';

// Access restrictions
api_protect_admin_script(true);

$this_section = SECTION_PLATFORM_ADMIN;
$currentUserId = api_get_user_id();

$message = '';
$messageType = 'success';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (Security::check_token('post')) {
        $firstname = isset($_POST['firstname']) ? trim($_POST['firstname']) : '';
        $lastname = isset($_POST['lastname']) ? trim($_POST['lastname']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        $officialCode = isset($_POST['official_code']) ? trim($_POST['official_code']) : '';
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        $userType = isset($_POST['user_type']) ? trim($_POST['user_type']) : 'student';
        $sendMail = isset($_POST['send_mail']) ? 1 : 0;
        $assignCourses = isset($_POST['assign_courses']) && is_array($_POST['assign_courses']) ? $_POST['assign_courses'] : [];

        if (empty($firstname) || empty($lastname) || empty($email)) {
            $message = "First name, last name, and email address are required.";
            $messageType = "danger";
        } else {
            if (empty($username)) {
                $username = UserManager::create_unique_username($firstname, $lastname);
            }
            if (empty($password)) {
                $password = api_generate_password();
            }

            $status = STUDENT;
            $isPlatformAdmin = 0;
            if ($userType === 'teacher') {
                $status = COURSEMANAGER;
            } elseif ($userType === 'admin') {
                $status = COURSEMANAGER;
                $isPlatformAdmin = 1;
            }

            $newUserId = UserManager::create_user(
                $firstname,
                $lastname,
                $status,
                $email,
                $username,
                $password,
                $officialCode,
                api_get_setting('platformLanguage'),
                $phone,
                null,
                PLATFORM_AUTH_SOURCE,
                null,
                1,
                0,
                [],
                null,
                $sendMail,
                $isPlatformAdmin
            );

            if ($newUserId) {
                $extraFieldValue = new ExtraFieldValue('user');
                $extraFieldValue->save([
                    'item_id' => $newUserId,
                    'variable' => 'verification_status',
                    'value' => 'verified',
                ]);
                $extraFieldValue->save([
                    'item_id' => $newUserId,
                    'variable' => 'approval_status',
                    'value' => 'approved',
                ]);

                if (!empty($assignCourses)) {
                    foreach ($assignCourses as $cCode) {
                        CourseManager::subscribeUser($newUserId, $cCode, $status, 0, 0, false, false);
                    }
                }

                Security::clear_token();
                $targetUrl = api_get_path(WEB_CODE_PATH).'admin/user_dashboard.php?role='.$userType;
                Display::addFlash(Display::return_message("User <strong>".htmlspecialchars("$firstname $lastname ($username)")."</strong> created successfully with role <strong>".ucfirst($userType)."</strong>.", 'success', false));
                header("Location: $targetUrl");
                exit;
            } else {
                $message = "Could not create user. The username or email might already be registered.";
                $messageType = "danger";
            }
        }
        Security::clear_token();
    }
}

// Fetch all available courses & coaching programs
$allCourses = CourseManager::get_courses_list();
$token = Security::get_token();

$tool_name = "Add User";
$interbreadcrumb[] = ['url' => 'index.php', 'name' => get_lang('PlatformAdmin')];
$interbreadcrumb[] = ['url' => 'user_dashboard.php', 'name' => 'Students & Faculty'];
$interbreadcrumb[] = ['url' => 'user_add.php', 'name' => 'Add User'];

Display::display_header($tool_name);
?>

<style>
/* Sparky Theme & Tree Sidebar Navigation */
:root {
    --gf-sidebar-bg: #0F172A;
    --gf-sidebar-active: #4F46E5;
    --gf-sidebar-line: #312E81;
    --gf-sidebar-text: #CBD5E1;
    --gf-sidebar-header: #FFFFFF;
    
    --color-primary: #4F46E5;
    --color-primary-hover: #4338CA;
    --color-primary-light: #EEF2FF;
    --color-neutral-bg: #F5F6FA;
    --color-card-bg: #FFFFFF;
    --color-border: #E5E7EB;
    --color-text-main: #111827;
    --color-text-muted: #6B7280;
    --radius-card: 12px;
    --radius-button: 8px;
    --radius-pill: 999px;
    --font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

.gf-app-container {
    display: flex;
    gap: 24px;
    margin-bottom: 60px;
    font-family: var(--font-family);
}

/* Left Sidebar - Dark Navy Tree Menu */
.gf-tree-sidebar {
    width: 260px;
    flex-shrink: 0;
    background-color: var(--gf-sidebar-bg);
    border-radius: var(--radius-card);
    padding: 20px 14px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}

.gf-sidebar-brand {
    padding: 6px 12px 18px 12px;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.gf-sidebar-brand h3 {
    font-size: 15px;
    font-weight: 800;
    color: #ffffff;
    margin: 0;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

.gf-nav-group {
    margin-bottom: 14px;
}

.gf-nav-group-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 12px;
    color: var(--gf-sidebar-header);
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
    border-radius: 8px;
    user-select: none;
    transition: background-color 0.2s;
}

.gf-nav-group-header:hover {
    background-color: rgba(255, 255, 255, 0.06);
}

.gf-header-left {
    display: flex;
    align-items: center;
    gap: 10px;
}

.gf-header-left i {
    font-size: 16px;
    width: 20px;
    text-align: center;
}

.gf-group-chevron {
    font-size: 11px;
    color: #94A3B8;
    transition: transform 0.2s ease;
}

.gf-nav-group.collapsed .gf-group-chevron {
    transform: rotate(-90deg);
}

.gf-nav-group.collapsed .gf-nav-tree-list {
    display: none;
}

/* Curved Tree Line Structure */
.gf-nav-tree-list {
    list-style: none;
    margin: 4px 0 0 16px;
    padding: 0 0 0 18px;
    position: relative;
}

.gf-nav-tree-list::before {
    content: '';
    position: absolute;
    top: 0;
    bottom: 14px;
    left: 4px;
    width: 2px;
    background-color: var(--gf-sidebar-line);
}

.gf-nav-tree-item {
    position: relative;
    margin: 4px 0;
}

.gf-nav-tree-item::before {
    content: '';
    position: absolute;
    top: 15px;
    left: -14px;
    width: 14px;
    height: 12px;
    border-bottom: 2px solid var(--gf-sidebar-line);
    border-left: 2px solid var(--gf-sidebar-line);
    border-bottom-left-radius: 8px;
}

.gf-nav-tree-link {
    display: block;
    padding: 8px 14px;
    color: var(--gf-sidebar-text);
    font-size: 13px;
    font-weight: 500;
    border-radius: 6px;
    text-decoration: none !important;
    transition: all 0.2s;
}

.gf-nav-tree-link:hover {
    color: #ffffff;
    background-color: rgba(255, 255, 255, 0.08);
}

.gf-nav-tree-link.active {
    background-color: var(--gf-sidebar-active);
    color: #ffffff;
    font-weight: 700;
}

/* Right Main Content Area */
.gf-main-content {
    flex-grow: 1;
    min-width: 0;
}

.gf-header-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}

.gf-header-row h1 {
    font-size: 26px;
    font-weight: 800;
    margin: 0;
    letter-spacing: -0.5px;
    color: var(--color-text-main);
    display: flex;
    align-items: center;
    gap: 10px;
}

.gf-card {
    background: var(--color-card-bg);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-card);
    padding: 32px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

/* User Type Visual Selector */
.gf-role-cards-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 28px;
}

.gf-role-card-option {
    border: 2px solid var(--color-border);
    border-radius: var(--radius-card);
    padding: 20px 16px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    background: #ffffff;
}

.gf-role-card-option:hover {
    border-color: #DDD6FE;
    background: #FAF9FF;
}

.gf-role-card-option.active {
    border-color: var(--color-primary);
    background: var(--color-primary-light);
    box-shadow: 0 4px 12px rgba(108, 92, 231, 0.15);
}

.gf-role-icon {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    margin: 0 auto 10px auto;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.role-icon-student { background: #EDE9FE; color: var(--color-primary); }
.role-icon-teacher { background: #EBF3FF; color: #2563EB; }
.role-icon-admin { background: #FEE2E2; color: #DC2626; }

.gf-role-title {
    font-weight: 700;
    font-size: 14px;
    color: var(--color-text-main);
    margin-bottom: 4px;
}

.gf-role-desc {
    font-size: 12px;
    color: var(--color-text-muted);
    line-height: 1.3;
}

/* Form Styles */
.gf-form-section-title {
    font-size: 14px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--color-text-muted);
    margin: 24px 0 16px 0;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--color-border);
}

.gf-form-group {
    margin-bottom: 18px;
}

.gf-form-label {
    display: block;
    font-weight: 600;
    font-size: 13px;
    color: var(--color-text-main);
    margin-bottom: 6px;
}

.gf-form-input {
    width: 100%;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-button);
    padding: 10px 14px;
    font-size: 13px;
    color: var(--color-text-main);
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.gf-form-input:focus {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.12);
}

/* Buttons */
.btn-gf-primary {
    background-color: var(--color-primary);
    color: #ffffff !important;
    font-weight: 600;
    font-size: 14px;
    padding: 11px 24px;
    border-radius: var(--radius-button);
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    box-shadow: 0 2px 6px rgba(108, 92, 231, 0.25);
    transition: all 0.2s ease;
    text-decoration: none !important;
}

.btn-gf-primary:hover {
    background-color: var(--color-primary-hover);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(108, 92, 231, 0.35);
}

.btn-gf-outline {
    background-color: #ffffff;
    color: var(--color-text-main) !important;
    font-weight: 600;
    font-size: 13px;
    padding: 10px 20px;
    border-radius: var(--radius-button);
    border: 1px solid var(--color-border);
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    text-decoration: none !important;
}

.btn-gf-outline:hover {
    background: var(--color-neutral-bg);
}
</style>

<div class="gf-app-container">

    <!-- LEFT SIDEBAR: Dark Navy Tree Navigation -->
    <aside class="gf-tree-sidebar">
        <div class="gf-sidebar-brand">
            <i class="fa fa-th-large" style="color: var(--color-primary); font-size: 18px;"></i>
            <h3>Sparky</h3>
        </div>

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
                <li class="gf-nav-tree-item">
                    <a href="user_add.php" class="gf-nav-tree-link active">
                        + Add User
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
                <li class="gf-nav-tree-item">
                    <a href="<?php echo api_get_path(WEB_CODE_PATH); ?>session/session_category_list.php" class="gf-nav-tree-link">
                        Cohorts & Sessions
                    </a>
                </li>
                <li class="gf-nav-tree-item">
                    <a href="<?php echo api_get_path(WEB_CODE_PATH); ?>session/session_add.php" class="gf-nav-tree-link">
                        + Create Program
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
                        Dashboard Overview
                    </a>
                </li>
                <li class="gf-nav-tree-item">
                    <a href="settings.php" class="gf-nav-tree-link">
                        Platform Settings
                    </a>
                </li>
            </ul>
        </div>
    </aside>

    <!-- RIGHT MAIN: Add User Content -->
    <main class="gf-main-content">

        <!-- Flash Notifications -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo htmlspecialchars($messageType); ?> alert-dismissible" role="alert" style="border-radius: 8px; font-weight: 500;">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Header Row -->
        <div class="gf-header-row">
            <div>
                <h1><i class="fa fa-user-plus text-primary" style="color: var(--color-primary);"></i> Add New User</h1>
                <p style="margin: 4px 0 0 0; color: var(--color-text-muted); font-size: 13px;">Create and onboard Students, Teachers, and Administrators with instant course assignments.</p>
            </div>
            <div>
                <a href="user_dashboard.php" class="btn-gf-outline">
                    <i class="fa fa-arrow-left"></i> Back to Users
                </a>
            </div>
        </div>

        <!-- Main Creation Card -->
        <div class="gf-card">
            <form method="post" action="user_add.php">
                <input type="hidden" name="sec_token" value="<?php echo $token; ?>">
                <input type="hidden" name="user_type" id="inputUserType" value="student">

                <!-- Step 1: User Type Selector -->
                <label class="gf-form-label" style="font-size: 14px; margin-bottom: 12px;">1. Select Account Role:</label>
                <div class="gf-role-cards-grid">
                    <div class="gf-role-card-option active" id="cardStudent" onclick="selectRole('student')">
                        <div class="gf-role-icon role-icon-student">
                            <i class="fa fa-graduation-cap"></i>
                        </div>
                        <div class="gf-role-title">Student / Learner</div>
                        <div class="gf-role-desc">Enrolled in courses & coaching programs</div>
                    </div>

                    <div class="gf-role-card-option" id="cardTeacher" onclick="selectRole('teacher')">
                        <div class="gf-role-icon role-icon-teacher">
                            <i class="fa fa-briefcase"></i>
                        </div>
                        <div class="gf-role-title">Teacher / Instructor</div>
                        <div class="gf-role-desc">Course management & coaching tutor</div>
                    </div>

                    <div class="gf-role-card-option" id="cardAdmin" onclick="selectRole('admin')">
                        <div class="gf-role-icon role-icon-admin">
                            <i class="fa fa-shield"></i>
                        </div>
                        <div class="gf-role-title">Administrator</div>
                        <div class="gf-role-desc">Full LMS portal administration access</div>
                    </div>
                </div>

                <!-- Step 2: Personal Information -->
                <div class="gf-form-section-title">2. Personal & Account Details</div>

                <div class="row">
                    <div class="col-sm-6">
                        <div class="gf-form-group">
                            <label class="gf-form-label">First Name *</label>
                            <input type="text" name="firstname" class="gf-form-input" required placeholder="e.g. Rahul">
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="gf-form-group">
                            <label class="gf-form-label">Last Name *</label>
                            <input type="text" name="lastname" class="gf-form-input" required placeholder="e.g. Sharma">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-4">
                        <div class="gf-form-group">
                            <label class="gf-form-label">Email Address *</label>
                            <input type="email" name="email" class="gf-form-input" required placeholder="e.g. rahul.sharma@domain.com">
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="gf-form-group">
                            <label class="gf-form-label">Enrollment Code / Student ID</label>
                            <input type="text" name="official_code" class="gf-form-input" placeholder="e.g. STU-2026-001">
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="gf-form-group">
                            <label class="gf-form-label">Phone Number</label>
                            <input type="text" name="phone" class="gf-form-input" placeholder="e.g. +91 9876543210">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-6">
                        <div class="gf-form-group">
                            <label class="gf-form-label">Username (Optional)</label>
                            <input type="text" name="username" class="gf-form-input" placeholder="Leave blank to auto-generate">
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="gf-form-group">
                            <label class="gf-form-label">Password (Optional)</label>
                            <input type="text" name="password" class="gf-form-input" placeholder="Leave blank to auto-generate">
                        </div>
                    </div>
                </div>

                <div class="gf-form-group" style="margin-top: 6px;">
                    <label style="font-weight: 500; font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
                        <input type="checkbox" name="send_mail" value="1" checked> Send welcome email notification with credentials
                    </label>
                </div>

                <!-- Step 3: Immediate Course / Program Assignment -->
                <div class="gf-form-section-title">3. Assign to Courses / Coaching Programs (Optional)</div>

                <div class="gf-form-group">
                    <label class="gf-form-label">Select Course(s) to assign immediately:</label>
                    <select name="assign_courses[]" class="gf-form-input" multiple size="6" style="height: 150px;">
                        <?php foreach ($allCourses as $c): ?>
                            <option value="<?php echo htmlspecialchars($c['code']); ?>">
                                <?php echo htmlspecialchars($c['title'].' ('.$c['code'].')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span style="font-size: 12px; color: var(--color-text-muted); margin-top: 4px; display: block;">Hold Ctrl (or Cmd on Mac) to select multiple programs.</span>
                </div>

                <!-- Submit Buttons -->
                <div style="margin-top: 32px; display: flex; gap: 12px; align-items: center;">
                    <button type="submit" class="btn-gf-primary">
                        <i class="fa fa-check"></i> Create User Account
                    </button>
                    <a href="user_dashboard.php" class="btn-gf-outline">
                        Cancel
                    </a>
                </div>
            </form>
        </div>

    </main>
</div>

<script>
function toggleNavGroup(groupId) {
    const group = document.getElementById(groupId);
    if (group) {
        group.classList.toggle('collapsed');
    }
}

function selectRole(role) {
    document.getElementById('inputUserType').value = role;
    
    document.getElementById('cardStudent').classList.remove('active');
    document.getElementById('cardTeacher').classList.remove('active');
    document.getElementById('cardAdmin').classList.remove('active');

    if (role === 'student') {
        document.getElementById('cardStudent').classList.add('active');
    } else if (role === 'teacher') {
        document.getElementById('cardTeacher').classList.add('active');
    } else if (role === 'admin') {
        document.getElementById('cardAdmin').classList.add('active');
    }
}
</script>

<?php
Display::display_footer();
