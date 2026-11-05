<?php

/* For licensing terms, see /license.txt */

/**
 * Homepage for the MySpace directory.
 */

// resetting the course id
$cidReset = true;

require_once __DIR__.'/../inc/global.inc.php';

api_block_anonymous_users();

$htmlHeadXtra[] = api_get_jqgrid_js();
$htmlHeadXtra[] = '<script src="'.api_get_path(WEB_PUBLIC_PATH).'assets/jquery.easy-pie-chart/dist/jquery.easypiechart.js"></script>';

$this_section = SECTION_TRACKING;

ob_start();
$nameTools = get_lang('MySpace');
$export_csv = isset($_GET['export']) && 'csv' === $_GET['export'] ? true : false;
$display = isset($_GET['display']) ? Security::remove_XSS($_GET['display']) : null;
$csv_content = [];
$user_id = api_get_user_id();
$session_id = isset($_GET['session_id']) ? (int) $_GET['session_id'] : 0;
$is_coach = api_is_coach($session_id);
$is_platform_admin = api_is_platform_admin();
$is_drh = api_is_drh();
$is_session_admin = api_is_session_admin();
$skipData = api_get_configuration_value('tracking_skip_generic_data');

$logInfo = [
    'tool' => SECTION_TRACKING,
];
Event::registerLog($logInfo);

$allowToTrack = api_is_platform_admin(true, true) || api_is_teacher();

if (!$allowToTrack) {
    api_not_allowed(true);
}

if ($is_session_admin) {
    header('location:session.php');
    exit;
}

// Get views
$views = ['admin', 'teacher', 'coach', 'drh'];
$view = 'teacher';
if (isset($_GET['view']) && in_array($_GET['view'], $views)) {
    $view = $_GET['view'];
}

$menu_items = [];
$pluginCalendar = api_get_plugin_setting('learning_calendar', 'enabled') === 'true';
$calendarMenuAdded = false;

// Modernized Reporting Navigation (Cleaned toolbar, removed session_filter, trainer view, certificate list)
$actionsLeft = '
<div class="btn-group" role="group" style="display:inline-flex; flex-wrap:wrap; gap:6px;">
    <a href="'.api_get_path(WEB_CODE_PATH).'mySpace/index.php" class="btn btn-default btn-sm active" style="font-weight:600; border-radius:8px; border:1px solid #CBD5E1; background:#EEF2FF; color:#4F46E5;">
        <i class="fa fa-th-large" style="margin-right:4px;"></i> '.get_lang('Overview').'
    </a>
    <a href="'.api_get_path(WEB_CODE_PATH).'mySpace/student.php" class="btn btn-default btn-sm" style="font-weight:600; border-radius:8px; border:1px solid #E2E8F0; background:#fff; color:#334155;">
        <i class="fa fa-graduation-cap text-info" style="margin-right:4px;"></i> '.get_lang('Students').'
    </a>
    <a href="'.api_get_path(WEB_CODE_PATH).'mySpace/course.php" class="btn btn-default btn-sm" style="font-weight:600; border-radius:8px; border:1px solid #E2E8F0; background:#fff; color:#334155;">
        <i class="fa fa-book text-danger" style="margin-right:4px;"></i> '.get_lang('Courses').'
    </a>
    <a href="'.api_get_path(WEB_CODE_PATH).'tracking/exams.php" class="btn btn-default btn-sm" style="font-weight:600; border-radius:8px; border:1px solid #E2E8F0; background:#fff; color:#334155;">
        <i class="fa fa-check-square-o text-warning" style="margin-right:4px;"></i> '.get_lang('ExamTracking').'
    </a>
    <a href="'.api_get_path(WEB_CODE_PATH).'auth/my_progress.php" class="btn btn-default btn-sm" style="font-weight:600; border-radius:8px; border:1px solid #E2E8F0; background:#fff; color:#334155;">
        <i class="fa fa-line-chart text-success" style="margin-right:4px;"></i> '.get_lang('MyStats').'
    </a>';

if ($is_platform_admin) {
    $actionsLeft .= '
    <a href="'.api_get_path(WEB_CODE_PATH).'mySpace/admin_view.php" class="btn btn-default btn-sm" style="font-weight:600; border-radius:8px; border:1px solid #E2E8F0; background:#fff; color:#64748B;">
        <i class="fa fa-sliders" style="margin-right:4px;"></i> '.get_lang('AdminInterface').'
    </a>';
}
$actionsLeft .= '</div>';

$actionsRight = '';
if ($display === 'useroverview' || $display === 'sessionoverview' || $display === 'courseoverview') {
    $actionsRight .= Display::url(
        '<i class="fa fa-file-excel-o text-success" style="margin-right:4px;"></i> '.get_lang('ExportAsCSV'),
        api_get_self().'?display='.$display.'&export=csv&view='.$view,
        ['class' => 'btn btn-default btn-sm', 'style' => 'font-weight:600; border-radius:8px; margin-right: 6px;']
    );
}

$actionsRight .= '
<a href="javascript:void(0);" onclick="window.print()" class="btn btn-default btn-sm" style="font-weight:600; border-radius:8px; border:1px solid #E2E8F0; background:#fff; color:#475569;">
    <i class="fa fa-print text-muted" style="margin-right:4px;"></i> '.get_lang('Print').'
</a>';

$userId = api_get_user_id();
$stats = Tracking::getStats($userId, true);

$numberStudents = $stats['student_count'];
$students = $stats['student_list'];
$numberStudentBosses = $stats['student_bosses'];
$numberTeachers = $stats['teachers'];
$countHumanResourcesUsers = $stats['drh'];
$countAssignedCourses = $stats['assigned_courses'];
$countCourses = $stats['courses'];
$sessions = $stats['session_list'];

$sessionIdList = [];
if (!empty($sessions)) {
    foreach ($sessions as $session) {
        $sessionIdList[] = $session['id'];
    }
}

// Sessions for the user
$countSessions = count($sessions);
$total_time_spent = 0;
$total_courses = 0;
$avgTotalProgress = 0;
$nb_inactive_students = 0;
$numberAssignments = 0;
$inactiveTime = time() - (3600 * 24 * 7);
$daysAgo = 7;
$studentIds = [];
$avg_courses_per_student = 0;

$view = new Template($nameTools);
$view->assign('students', $numberStudents);
$view->assign('studentbosses', $numberStudentBosses);
$view->assign('numberTeachers', $numberTeachers);
$view->assign('humanresources', $countHumanResourcesUsers);
$view->assign(
    'total_user',
    $numberStudents + $numberStudentBosses + $numberTeachers + $countHumanResourcesUsers
);
$view->assign('studentboss', STUDENT_BOSS);
$view->assign('drh', DRH);
$view->assign('stats', $stats);

$form = new FormValidator(
    'search_user',
    'get',
    api_get_path(WEB_CODE_PATH).'mySpace/student.php'
);
$form = Tracking::setUserSearchForm($form);

$totalTimeSpent = null;
$averageScore = null;
$posts = null;

if ($skipData === false) {
    $averageTimeSpentPerStudent = '00:00:00';

    if (!empty($students)) {
        // Students
        $studentIds = array_values($students);
        $progress = Tracking::get_avg_student_progress($studentIds);
        $countAssignments = Tracking::count_student_assignments($studentIds);
        // average progress
        $avgTotalProgress = $progress / $numberStudents;
        // average assignments
        $numberAssignments = $countAssignments / $numberStudents;
        $avg_courses_per_student = $countCourses / $numberStudents;
        $totalTimeSpent = Tracking::get_time_spent_on_the_platform($studentIds, 'ever');
        $averageTimeSpentPerStudent = api_time_to_hms($totalTimeSpent / $numberStudents);
        $posts = Tracking::count_student_messages($studentIds);
        $averageScore = Tracking::getAverageStudentScore($studentIds);
    }

    if ($export_csv) {
        //csv part
        $csv_content[] = [get_lang('Students')];
        $csv_content[] = [get_lang('InactivesStudents'), $nb_inactive_students];
        $csv_content[] = [get_lang('AverageTimeSpentOnThePlatform'), $averageTimeSpentPerStudent];
        $csv_content[] = [get_lang('AverageCoursePerStudent'), round($avg_courses_per_student, 3)];
        $csv_content[] = [
            get_lang('AverageProgressInLearnpath'),
            is_null($avgTotalProgress)
                ? null
                : round($avgTotalProgress, 2).'%',
        ];
        $csv_content[] = [
            get_lang('AverageResultsToTheExercices'),
            is_null($averageScore)
                ? null
                : round($averageScore, 2).'%',
        ];
        $csv_content[] = [get_lang('AveragePostsInForum'), $posts];
        $csv_content[] = [get_lang('AverageAssignments'), $numberAssignments];
        $csv_content[] = [];
    } else {
        $lastConnectionDate = api_get_utc_datetime(strtotime('15 days ago'));
        $countActiveUsers = SessionManager::getCountUserTracking(
            null,
            1,
            null,
            [],
            []
        );
        $countSleepingTeachers = SessionManager::getTeacherTracking(
            api_get_user_id(),
            1,
            $lastConnectionDate,
            true,
            $sessionIdList
        );

        $countSleepingStudents = SessionManager::getCountUserTracking(
            null,
            1,
            $lastConnectionDate,
            $sessionIdList,
            $studentIds
        );
        $report['AverageCoursePerStudent'] = is_null($avg_courses_per_student)
            ? ''
            : round($avg_courses_per_student, 3);
        $report['InactivesStudents'] = $nb_inactive_students;
        $report['AverageTimeSpentOnThePlatform'] = $averageTimeSpentPerStudent;
        $report['AverageProgressInLearnpath'] = is_null($avgTotalProgress)
            ? ''
            : round($avgTotalProgress, 2).'%';
        $report['AvgCourseScore'] = is_null($averageScore) ? '0' : round($averageScore, 2).'%';
        $report['AveragePostsInForum'] = is_null($posts) ? '0' : round($posts, 2);
        $report['AverageAssignments'] = is_null($numberAssignments) ? '' : round($numberAssignments, 2);
        $view->assign('report', $report);
    }
}

$view->assign('header', $nameTools);
$view->assign('form', $form->returnForm());
$view->assign('actions', Display::toolbarAction('toolbar', [$actionsLeft, $actionsRight]));
$view->assign('title', get_lang('Students').' ('.$numberStudents.')');

require_once __DIR__.'/../inc/lib/certificate_blueprint.lib.php';

// Actual platform metrics (connected with real modified database records)
$totalStudents = (int) Database::count_rows(Database::get_main_table(TABLE_MAIN_USER), "status = 5");
$totalCourses = (int) Database::count_rows(Database::get_main_table(TABLE_MAIN_COURSE));
$totalEnrollments = (int) Database::count_rows(Database::get_main_table(TABLE_MAIN_COURSE_USER), "status = 5");
$totalTrainers = (int) Database::count_rows(Database::get_main_table(TABLE_MAIN_USER), "status = 1");
$totalBlueprints = (int) Database::count_rows('c_certificate_blueprint');

// Active Courses Breakdown with real student enrollments
$allCourses = Database::select('id, code, title, visual_code', Database::get_main_table(TABLE_MAIN_COURSE));
$coursesSummary = [];
if (!empty($allCourses)) {
    foreach ($allCourses as $c) {
        $enrolledCount = (int) Database::count_rows(Database::get_main_table(TABLE_MAIN_COURSE_USER), "c_id = {$c['id']} AND status = 5");
        $hasBp = CertificateBlueprint::hasCertificateForCourse($c['code']);
        $coursesSummary[] = [
            'id' => $c['id'],
            'code' => $c['code'],
            'title' => $c['title'],
            'enrolled' => $enrolledCount,
            'has_certificate' => $hasBp,
        ];
    }
}

// Active Learners List with real course counts & activity
$allStudents = Database::select('id, username, firstname, lastname, email, registration_date', Database::get_main_table(TABLE_MAIN_USER), ['where' => ['status = ?' => 5], 'order' => 'id DESC']);
$studentsSummary = [];
if (!empty($allStudents)) {
    foreach ($allStudents as $st) {
        $enrolledCount = (int) Database::count_rows(Database::get_main_table(TABLE_MAIN_COURSE_USER), "user_id = {$st['id']} AND status = 5");
        $lastLogin = Tracking::get_last_connection_date($st['id'], true);
        $studentsSummary[] = [
            'id' => $st['id'],
            'username' => $st['username'],
            'name' => trim($st['firstname'].' '.$st['lastname']) ?: $st['username'],
            'email' => $st['email'],
            'courses_count' => $enrolledCount,
            'last_login' => $lastLogin ?: 'Never',
            'reg_date' => substr($st['registration_date'], 0, 10),
        ];
    }
}

$view->assign('total_students', $totalStudents);
$view->assign('total_courses', $totalCourses);
$view->assign('total_enrollments', $totalEnrollments);
$view->assign('total_trainers', $totalTrainers);
$view->assign('total_blueprints', $totalBlueprints);
$view->assign('courses_summary', $coursesSummary);
$view->assign('students_summary', $studentsSummary);

$template = $view->get_template('my_space/index.tpl');
$content = $view->fetch($template);
$view->assign('content', $content);
$view->display_one_col_template();

// Send the csv file if asked
if ($export_csv) {
    ob_end_clean();
    Export::arrayToCsv($csv_content, 'reporting_index');
    exit;
}
