<?php
/* For licensing terms, see /license.txt */

require_once __DIR__.'/../inc/global.inc.php';
$this_section = SECTION_COURSES;

// notice for unauthorized people.
api_protect_course_script(true);

if (api_get_setting('allow_user_course_subscription_by_course_admin') == 'false') {
    if (!api_is_platform_admin()) {
        api_not_allowed(true);
    }
}

// Sample CSV Download handler
if (isset($_GET['action']) && $_GET['action'] === 'download_sample') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sample_students_import.csv"');
    echo "username\r\n";
    echo "learner_01\r\n";
    echo "learner_02\r\n";
    exit;
}

$userType = STUDENT;
if (!empty($_REQUEST['type']) && $_REQUEST['type'] == COURSEMANAGER) {
    $userType = COURSEMANAGER;
}

$tool_name = get_lang('ImportUsersToACourse');

$interbreadcrumb[] = ["url" => "user.php?".api_get_cidreq(), "name" => get_lang("Users")];
$interbreadcrumb[] = ["url" => "#", "name" => get_lang("ImportUsersToACourse")];

$form = new FormValidator('user_import', 'post', 'user_import.php?'.api_get_cidreq().'&type='.$userType);
$form->addElement('file', 'import_file', get_lang('ImportCSVFileLocation'));
$form->addElement('checkbox', 'unsubscribe_users', null, get_lang('UnsubscribeUsersAlreadyAddedInCourse'));
$form->addElement('hidden', 'type', $userType);
$form->addButtonImport(get_lang('Import'));

$course_code = api_get_course_id();

if (empty($course_code)) {
    api_not_allowed(true);
}

$session_id = api_get_session_id();
$message = '';
$user_to_show = [];
$type = '';

if ($form->validate()) {
    if (isset($_FILES['import_file']['size']) && $_FILES['import_file']['size'] !== 0) {
        $unsubscribe_users = isset($_POST['unsubscribe_users']);
        //@todo : csvToArray deprecated
        $users = Import::csvToArray($_FILES['import_file']['tmp_name']);
        $invalid_users = [];
        $clean_users = [];

        if (!empty($users)) {
            $empty_line = 0;
            foreach ($users as $user_data) {
                $user_id = null;
                $user_data = array_change_key_case($user_data, CASE_LOWER);

                // Checking "username" field
                if (!empty($user_data['username'])) {
                    $user_id = UserManager::get_user_id_from_username($user_data['username']);
                }

                // Checking "id" field
                if (!empty($user_data['id'])) {
                    $user_id = $user_data['id'];
                }

                if (UserManager::is_user_id_valid($user_id)) {
                    $clean_users[] = $user_id;
                } else {
                    $invalid_users[] = $user_data;
                }
            }

            if (empty($invalid_users)) {
                $type = 'confirmation';
                $message = get_lang('ListOfUsersSubscribedToCourse');

                if ($unsubscribe_users) {
                    $current_user_list = CourseManager::get_user_list_from_course_code(
                        $course_code,
                        $session_id,
                        null,
                        null,
                        $userType
                    );
                    if (!empty($current_user_list)) {
                        $user_ids = [];
                        foreach ($current_user_list as $user) {
                            if ($userType == COURSEMANAGER) {
                                if (CourseManager::is_course_teacher($user['user_id'], $course_code)) {
                                    $user_ids[] = $user['user_id'];
                                }
                            } else {
                                if (!CourseManager::is_course_teacher($user['user_id'], $course_code)) {
                                    $user_ids[] = $user['user_id'];
                                }
                            }
                        }
                        CourseManager::unsubscribe_user($user_ids, $course_code, $session_id);
                    }
                }

                foreach ($clean_users as $userId) {
                    $userInfo = api_get_user_info($userId);
                    CourseManager::subscribeUser($userId, $course_code, $userType, $session_id);
                    if (empty($session_id)) {
                        //just to make sure
                        if (CourseManager::is_user_subscribed_in_course($userId, $course_code)) {
                            $user_to_show[] = $userInfo['complete_name'];
                        }
                    } else {
                        //just to make sure
                        if (CourseManager::is_user_subscribed_in_course($userId, $course_code, true, $session_id)) {
                            $user_to_show[] = $userInfo['complete_name'];
                        }
                    }
                }
            } else {
                $message = get_lang('CheckUsersWithId');
                $type = 'warning';
                foreach ($invalid_users as $invalid_user) {
                    $user_to_show[] = $invalid_user;
                }
            }
        }
    }
}

Display::display_header();

echo '<div class="et-import-container" style="max-width:880px; margin:0 auto; padding:10px 0 40px 0;">';

// Header & Back link
echo '<div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:24px;">';
echo '<div>';
echo '<h2 style="font-size:22px; font-weight:800; color:#0F172A; margin:0 0 4px 0;"><em class="fa fa-file-excel-o" style="color:#10B981;"></em> '.get_lang('ImportUsersToACourse').'</h2>';
echo '<p style="color:#64748B; font-size:13px; margin:0;">Bulk register learners directly into this cohort using a CSV spreadsheet.</p>';
echo '</div>';
echo '<div>';
echo '<a href="user.php?'.api_get_cidreq().'" class="btn btn-default" style="font-weight:600; border-radius:8px;"><em class="fa fa-arrow-left"></em> '.get_lang('Back').' to Roster</a>';
echo '</div>';
echo '</div>';

if (!empty($message)) {
    if (!empty($user_to_show)) {
        $userMessage = null;
        foreach ($user_to_show as $user) {
            if (!is_array($user)) {
                $user = [$user];
            }
            $user = array_filter($user);
            $userMessage .= implode(', ', $user)."<br />";
        }
        if ($type == 'confirmation') {
            echo Display::return_message($message.': <br />'.$userMessage, 'confirm', false);
        } else {
            echo Display::return_message($message.':  <br />'.$userMessage, 'warning', false);
        }
    } else {
        $empty_line_msg = ($empty_line == 0) ? get_lang('ErrorsWhenImportingFile') : get_lang('ErrorsWhenImportingFile').': '.get_lang('EmptyHeaderLine');
        echo Display::return_message($empty_line_msg, 'error');
    }
}

// Main Card with Form
echo '<div style="background:#FFFFFF; border:1px solid #E2E8F0; border-radius:14px; padding:28px; box-shadow:0 4px 16px rgba(0,0,0,0.04); margin-bottom:24px;">';
$form->display();
echo '</div>';

// Instructions & Sample Download Card
echo '<div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:14px; padding:24px;">';
echo '<div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:16px;">';
echo '<h4 style="font-size:15px; font-weight:700; color:#0F172A; margin:0;"><em class="fa fa-info-circle text-primary"></em> CSV File Format & Guidelines</h4>';
echo '<a href="user_import.php?'.api_get_cidreq().'&action=download_sample" class="btn btn-default btn-sm" style="background:#FFFFFF; border:1px solid #CBD5E1; font-weight:600; color:#0F172A; border-radius:6px;">';
echo '<em class="fa fa-download" style="color:#10B981;"></em> Download Sample CSV';
echo '</a>';
echo '</div>';

echo '<p style="font-size:13px; color:#475569; line-height:1.5; margin-bottom:14px;">';
echo 'Your CSV file must have a single column with the header <code>username</code> (or <code>id</code>) followed by the usernames of registered learners you wish to enroll:';
echo '</p>';

echo '<div class="row">';
echo '<div class="col-xs-12 col-md-6">';
echo '<div style="background:#FFFFFF; border:1px solid #E2E8F0; border-radius:8px; padding:12px 16px;">';
echo '<div style="font-size:12px; font-weight:700; color:#64748B; text-transform:uppercase; margin-bottom:6px;">Format 1: By Username (Recommended)</div>';
echo '<pre style="background:#F1F5F9; border:none; padding:10px; font-size:12px; margin:0; border-radius:6px;">username
learner_01
learner_02
robert.smith</pre>';
echo '</div>';
echo '</div>';

echo '<div class="col-xs-12 col-md-6">';
echo '<div style="background:#FFFFFF; border:1px solid #E2E8F0; border-radius:8px; padding:12px 16px;">';
echo '<div style="font-size:12px; font-weight:700; color:#64748B; text-transform:uppercase; margin-bottom:6px;">Format 2: By User ID</div>';
echo '<pre style="background:#F1F5F9; border:none; padding:10px; font-size:12px; margin:0; border-radius:6px;">id
12
18
45</pre>';
echo '</div>';
echo '</div>';
echo '</div>';

echo '</div>'; // end et-import-container
echo '</div>';

Display::display_footer();
