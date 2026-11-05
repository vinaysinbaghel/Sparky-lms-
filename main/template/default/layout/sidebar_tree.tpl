{# ========================================================================= #}
{# ET Collapsible Offcanvas Admin Drawer                                    #}
{# Rendered ONLY when an admin is in Admin Mode. Learner mode is 100% clean. #}
{# ========================================================================= #}
{% if _u.is_admin == 1 and et_view_mode == 'admin' %}
<div id="et-admin-drawer" class="et-admin-drawer">
    <div class="et-admin-drawer-header">
        <div style="display: flex; align-items: center; gap: 8px;">
            <span class="gf-brand-dot"></span>
            <span class="et-drawer-title">Sparky Admin</span>
        </div>
        <button type="button" class="et-drawer-close" onclick="closeEtAdminDrawer()" title="Close Drawer">&times;</button>
    </div>

    <!-- Quick Relocated Admin Course Manager CTA -->
    <div style="margin-bottom: 16px;">
        <a href="{{ _p.web_main }}admin/course_list.php" class="btn btn-sm btn-block" style="background: #4F46E5; color: #FFFFFF; font-weight: 700; border-radius: 8px; padding: 9px 14px; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 2px 10px rgba(79, 70, 229, 0.4);">
            <em class="fa fa-sliders"></em> Admin Course Manager
        </a>
    </div>

    <nav class="gf-sidebar-nav">
        <!-- Admin Group 1: Users & Learners -->
        <div class="gf-nav-group active-group" id="homeGroupUsers">
            <div class="gf-nav-group-header" onclick="toggleGfNav('homeGroupUsers')">
                <div class="gf-header-left">
                    <i class="fa fa-users"></i>
                    <span>Users & Learners</span>
                </div>
                <i class="fa fa-chevron-down gf-group-chevron"></i>
            </div>
            <ul class="gf-nav-tree-list">
                <li class="gf-nav-tree-item">
                    <a href="{{ _p.web_main }}admin/user_dashboard.php?role=student" class="gf-nav-tree-link">
                        Students
                    </a>
                </li>
                <li class="gf-nav-tree-item">
                    <a href="{{ _p.web_main }}admin/user_dashboard.php?role=teacher" class="gf-nav-tree-link">
                        Teachers & Instructors
                    </a>
                </li>
                <li class="gf-nav-tree-item">
                    <a href="{{ _p.web_main }}admin/user_dashboard.php?role=admin" class="gf-nav-tree-link">
                        Admin Accounts
                    </a>
                </li>
                <li class="gf-nav-tree-item">
                    <a href="{{ _p.web_main }}admin/user_add.php" class="gf-nav-tree-link gf-add-link">
                        + Add User
                    </a>
                </li>
            </ul>
        </div>

        <!-- Admin Group 2: Courses & Content -->
        <div class="gf-nav-group active-group" id="homeGroupCourses">
            <div class="gf-nav-group-header" onclick="toggleGfNav('homeGroupCourses')">
                <div class="gf-header-left">
                    <i class="fa fa-laptop"></i>
                    <span>Courses & Content</span>
                </div>
                <i class="fa fa-chevron-down gf-group-chevron"></i>
            </div>
            <ul class="gf-nav-tree-list">
                <li class="gf-nav-tree-item">
                    <a href="{{ _p.web_main }}admin/course_list.php" class="gf-nav-tree-link">
                        All Courses
                    </a>
                </li>
                <li class="gf-nav-tree-item">
                    <a href="{{ _p.web_main }}admin/course_category.php" class="gf-nav-tree-link">
                        Categories
                    </a>
                </li>
                <li class="gf-nav-tree-item">
                    <a href="{{ _p.web_main }}admin/awarded_certificates.php" class="gf-nav-tree-link">
                        Awarded Certificates
                    </a>
                </li>
                <li class="gf-nav-tree-item">
                    <a href="{{ _p.web_main }}admin/certificate_blueprints.php" class="gf-nav-tree-link">
                        Certificate Blueprints
                    </a>
                </li>
                <li class="gf-nav-tree-item">
                    <a href="{{ _p.web_main }}admin/questions.php" class="gf-nav-tree-link">
                        Question Bank
                    </a>
                </li>
            </ul>
        </div>

        <!-- Admin Group 3: Coaching & Cohorts -->
        <div class="gf-nav-group active-group" id="homeGroupCoaching">
            <div class="gf-nav-group-header" onclick="toggleGfNav('homeGroupCoaching')">
                <div class="gf-header-left">
                    <i class="fa fa-calendar-check-o"></i>
                    <span>Coaching & Cohorts</span>
                </div>
                <i class="fa fa-chevron-down gf-group-chevron"></i>
            </div>
            <ul class="gf-nav-tree-list">
                <li class="gf-nav-tree-item">
                    <a href="{{ _p.web_main }}session/session_list.php" class="gf-nav-tree-link">
                        All Coaching Programs
                    </a>
                </li>
                <li class="gf-nav-tree-item">
                    <a href="{{ _p.web_main }}session/session_category_list.php" class="gf-nav-tree-link">
                        Cohorts & Sessions
                    </a>
                </li>
                <li class="gf-nav-tree-item">
                    <a href="{{ _p.web_main }}session/session_add.php" class="gf-nav-tree-link gf-add-link">
                        + Create Program
                    </a>
                </li>
            </ul>
        </div>

        <!-- Admin Group 4: System Administration -->
        <div class="gf-nav-group active-group" id="homeGroupSystem">
            <div class="gf-nav-group-header" onclick="toggleGfNav('homeGroupSystem')">
                <div class="gf-header-left">
                    <i class="fa fa-cog"></i>
                    <span>System Settings</span>
                </div>
                <i class="fa fa-chevron-down gf-group-chevron"></i>
            </div>
            <ul class="gf-nav-tree-list">
                <li class="gf-nav-tree-item">
                    <a href="{{ _p.web_main }}admin/index.php" class="gf-nav-tree-link">
                        Platform Overview
                    </a>
                </li>
                <li class="gf-nav-tree-item">
                    <a href="{{ _p.web_main }}admin/settings.php" class="gf-nav-tree-link">
                        Platform Settings
                    </a>
                </li>
            </ul>
        </div>

        <!-- Quick Switcher to Learner View -->
        <div style="margin-top: 18px; padding-top: 14px; border-top: 1px solid rgba(79, 70, 229, 0.25);">
            <a href="{{ _p.web }}user_portal.php?action=set_view_mode&mode=learner" class="btn btn-sm btn-block" style="background: rgba(255, 255, 255, 0.08); color: #CBD5E1; border: 1px solid rgba(255, 255, 255, 0.15); font-weight: 600; border-radius: 6px; padding: 7px 12px; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px;">
                <i class="fa fa-graduation-cap"></i> Switch to Learner View
            </a>
        </div>
    </nav>
</div>
<div id="et-admin-drawer-backdrop" class="et-admin-drawer-backdrop" onclick="closeEtAdminDrawer()"></div>

<script>
function openEtAdminDrawer() {
    var drawer = document.getElementById('et-admin-drawer');
    var backdrop = document.getElementById('et-admin-drawer-backdrop');
    if (drawer) drawer.classList.add('open');
    if (backdrop) backdrop.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeEtAdminDrawer() {
    var drawer = document.getElementById('et-admin-drawer');
    var backdrop = document.getElementById('et-admin-drawer-backdrop');
    if (drawer) drawer.classList.remove('open');
    if (backdrop) backdrop.classList.remove('open');
    document.body.style.overflow = '';
}

function toggleEtAdminDrawer() {
    var drawer = document.getElementById('et-admin-drawer');
    if (drawer && drawer.classList.contains('open')) {
        closeEtAdminDrawer();
    } else {
        openEtAdminDrawer();
    }
}

function toggleGfNav(groupId) {
    var group = document.getElementById(groupId);
    if (group) {
        group.classList.toggle('collapsed');
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEtAdminDrawer();
    }
});
</script>
{% endif %}