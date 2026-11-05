<?php

/* For licensing terms, see /license.txt */

/**
 * List of achieved certificates by the current user.
 *
 * @author Angel Fernando Quiroz Campos <angel.quiroz@beeznest.com>
 */
$cidReset = true;

require_once __DIR__.'/../inc/global.inc.php';
require_once __DIR__.'/../inc/lib/certificate_blueprint.lib.php';

api_block_inactive_user();

$logInfo = [
    'tool' => 'MyCertificates',
];
Event::registerLog($logInfo);

if (api_is_anonymous()) {
    api_not_allowed(true);
}
$userId = api_get_user_id();

$courseList = GradebookUtils::getUserCertificatesInCourses($userId);
$sessionList = GradebookUtils::getUserCertificatesInSessions($userId);

// Fetch Certificate Blueprints for user enrolled courses
$userCourses = CourseManager::get_courses_list_by_user_id($userId);
if (!empty($userCourses)) {
    foreach ($userCourses as $uc) {
        $cCode = $uc['code'];
        $hasBp = CertificateBlueprint::hasCertificateForCourse($cCode);
        if ($hasBp) {
            // Check if already in courseList
            $alreadyPresent = false;
            foreach ($courseList as $cItem) {
                if (isset($cItem['code']) && $cItem['code'] === $cCode) {
                    $alreadyPresent = true;
                    break;
                }
            }

            if (!$alreadyPresent) {
                $cTitle = !empty($uc['title']) ? $uc['title'] : $cCode;
                $courseList[] = [
                    'code' => $cCode,
                    'course' => $cTitle,
                    'score' => '100%',
                    'date' => date('d/m/Y'),
                    'link' => api_get_path(WEB_CODE_PATH)."gradebook/view_blueprint_certificate.php?course_code={$cCode}&user_id={$userId}",
                    'pdf' => api_get_path(WEB_CODE_PATH)."gradebook/view_blueprint_certificate.php?course_code={$cCode}&user_id={$userId}&action=print",
                ];
            }
        }
    }
}

// If admin and no courses found, list all active blueprint courses so admin can test & preview
if (empty($courseList) && empty($sessionList) && api_is_platform_admin()) {
    $bpCourses = Database::query("SELECT DISTINCT course_code FROM c_certificate_blueprint_course");
    while ($bpc = Database::fetch_array($bpCourses, 'ASSOC')) {
        $cCode = $bpc['course_code'];
        $cInfo = api_get_course_info($cCode);
        $cTitle = !empty($cInfo['title']) ? $cInfo['title'] : $cCode;
        $courseList[] = [
            'code' => $cCode,
            'course' => $cTitle,
            'score' => '100%',
            'date' => date('d/m/Y'),
            'link' => api_get_path(WEB_CODE_PATH)."gradebook/view_blueprint_certificate.php?course_code={$cCode}&user_id={$userId}",
            'pdf' => api_get_path(WEB_CODE_PATH)."gradebook/view_blueprint_certificate.php?course_code={$cCode}&user_id={$userId}&action=print",
        ];
    }
}

if (empty($courseList) && empty($sessionList)) {
    Display::addFlash(
        Display::return_message(get_lang('YouNotYetAchievedCertificates'), 'warning')
    );
}

$hideExportLink = api_get_setting('hide_certificate_export_link');
$hideExportLinkStudent = api_get_setting('hide_certificate_export_link_students');
$allowExport = true;
if ($hideExportLink === 'true' ||
    (api_is_student() && $hideExportLinkStudent === 'true')
) {
    $allowExport = false;
}

$template = new Template(get_lang('MyCertificates'));
$template->assign('course_list', $courseList);
$template->assign('session_list', $sessionList);
$template->assign('allow_export', $allowExport);
$templateName = $template->get_template('gradebook/my_certificates.tpl');
$content = $template->fetch($templateName);

if ('true' === api_get_setting('allow_public_certificates')) {
    $template->assign(
        'actions',
        Display::toolbarButton(
            get_lang('SearchCertificates'),
            api_get_path(WEB_CODE_PATH).'gradebook/search.php',
            'search',
            'info'
        )
    );
}

$template->assign('content', $content);
$template->display_one_col_template();
