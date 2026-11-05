<?php

/* For licensing terms, see /license.txt */

use ChamiloSession as Session;

$cidReset = true;
require_once __DIR__.'/../inc/global.inc.php';

$this_section = SECTION_PLATFORM_ADMIN;

api_protect_admin_script(true);

$user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : (int) $_POST['user_id'];
api_protect_super_admin($user_id, null, true !== api_get_configuration_value('disallow_session_admin_edit_users'));
$is_platform_admin = api_is_platform_admin() ? 1 : 0;
$userInfo = api_get_user_info($user_id);
$userEntity = api_get_user_entity($user_id);

$htmlHeadXtra[] = '
<script>
var is_platform_id = "'.$is_platform_admin.'";

function enable_expiration_date() {
    document.user_edit.radio_expiration_date[0].checked=false;
    document.user_edit.radio_expiration_date[1].checked=true;
}

function password_switch_radio_button(){
    var input_elements = document.getElementsByTagName("input");
    for (var i = 0; i < input_elements.length; i++) {
        if(input_elements.item(i).name == "reset_password" && input_elements.item(i).value == "2") {
            input_elements.item(i).checked = true;
        }
    }
}

function display_drh_list(){
    var $radios = $("input:radio[name=platform_admin]");
    if (document.getElementById("status_select").value=='.COURSEMANAGER.') {
        if (is_platform_id == 1)
            document.getElementById("id_platform_admin").style.display="block";
    } else if (document.getElementById("status_select").value=='.STUDENT.') {
        if (is_platform_id == 1)
            document.getElementById("id_platform_admin").style.display="none";
        $radios.filter("[value=0]").attr("checked", true);
    } else {
        if (is_platform_id == 1)
            document.getElementById("id_platform_admin").style.display="none";
        $radios.filter("[value=0]").attr("checked", true);
    }
}

function show_image(image,width,height) {
    width = parseInt(width) + 20;
    height = parseInt(height) + 20;
    window_x = window.open(image,\'windowX\',\'width=\'+ width + \', height=\'+ height + \' , resizable=0\');
}

function confirmation(name) {
    if (confirm("'.get_lang('AreYouSureToDeleteJS', '').' " + name + " ?")) {
            document.forms["profile"].submit();
    } else {
        return false;
    }
}
</script>';

$htmlHeadXtra[] = api_get_password_checker_js('#username', '#password');
$htmlHeadXtra[] = api_get_css_asset('cropper/dist/cropper.min.css');
$htmlHeadXtra[] = api_get_asset('cropper/dist/cropper.min.js');
$tool_name = get_lang('ModifyUserInfo');

$interbreadcrumb[] = ['url' => 'index.php', 'name' => get_lang('PlatformAdmin')];
$interbreadcrumb[] = ['url' => 'user_list.php', 'name' => get_lang('UserList')];

$table_user = Database::get_main_table(TABLE_MAIN_USER);
$table_admin = Database::get_main_table(TABLE_MAIN_ADMIN);
$sql = "SELECT u.*, a.user_id AS is_admin FROM $table_user u
        LEFT JOIN $table_admin a ON a.user_id = u.id
        WHERE u.id = '".$user_id."'";
$res = Database::query($sql);
if (Database::num_rows($res) != 1) {
    header('Location: user_list.php');
    exit;
}

$user_data = Database::fetch_array($res, 'ASSOC');
$user_data['platform_admin'] = is_null($user_data['is_admin']) ? 0 : 1;
$user_data['send_mail'] = 0;
$user_data['old_password'] = $user_data['password'];
//Convert the registration date of the user

$user_data['registration_date'] = api_get_local_time($user_data['registration_date']);
unset($user_data['password']);

// Create the form
$form = new FormValidator(
    'user_edit',
    'post',
    api_get_self().'?user_id='.$user_id,
    ''
);
$form->protect();
$form->addElement('header', $tool_name);
$form->addElement('hidden', 'user_id', $user_id);

if (api_is_western_name_order()) {
    // Firstname
    $form->addElement('text', 'firstname', get_lang('FirstName'), ['autocomplete' => 'off']);
    $form->applyFilter('firstname', 'html_filter');
    $form->applyFilter('firstname', 'trim');
    $form->addRule('firstname', get_lang('ThisFieldIsRequired'), 'required');
    // Lastname
    $form->addElement('text', 'lastname', get_lang('LastName'), ['autocomplete' => 'off']);
    $form->applyFilter('lastname', 'html_filter');
    $form->applyFilter('lastname', 'trim');
    $form->addRule('lastname', get_lang('ThisFieldIsRequired'), 'required');
} else {
    // Lastname
    $form->addElement('text', 'lastname', get_lang('LastName'), ['autocomplete' => 'off']);
    $form->applyFilter('lastname', 'html_filter');
    $form->applyFilter('lastname', 'trim');
    $form->addRule('lastname', get_lang('ThisFieldIsRequired'), 'required');
    // Firstname
    $form->addElement('text', 'firstname', get_lang('FirstName'), ['autocomplete' => 'off']);
    $form->applyFilter('firstname', 'html_filter');
    $form->applyFilter('firstname', 'trim');
    $form->addRule('firstname', get_lang('ThisFieldIsRequired'), 'required');
}

// Official code
$form->addElement('text', 'official_code', get_lang('OfficialCode'), ['size' => '40']);
$form->applyFilter('official_code', 'html_filter');
$form->applyFilter('official_code', 'trim');

// Email
$form->addElement('text', 'email', get_lang('Email'), ['autocomplete' => 'off']);
$form->addRule('email', get_lang('EmailWrong'), 'email');
if (api_get_setting('registration', 'email') == 'true') {
    $form->addRule('email', get_lang('EmailWrong'), 'required');
}

if (api_get_setting('login_is_email') == 'true') {
    $form->addRule('email', sprintf(get_lang('UsernameMaxXCharacters'), (string) USERNAME_MAX_LENGTH), 'maxlength', USERNAME_MAX_LENGTH);
    $form->addRule('email', get_lang('UserTaken'), 'username_available', $user_data['username']);
}

// OpenID
if (api_get_setting('openid_authentication') == 'true') {
    $form->addElement('text', 'openid', get_lang('OpenIDURL'));
}

// Phone
$form->addElement('text', 'phone', get_lang('PhoneNumber'), ['autocomplete' => 'off']);

// Picture
$form->addFile(
    'picture',
    get_lang('AddImage'),
    ['id' => 'picture', 'class' => 'picture-form', 'crop_image' => true, 'crop_ratio' => '1 / 1']
);
$allowed_picture_types = api_get_supported_image_extensions(false);

$form->addRule(
    'picture',
    get_lang('OnlyImagesAllowed').' ('.implode(',', $allowed_picture_types).')',
    'filetype',
    $allowed_picture_types
);
if (strlen($user_data['picture_uri']) > 0) {
    $form->addElement('checkbox', 'delete_picture', '', get_lang('DelImage'));
}

// Username
if (api_get_setting('login_is_email') != 'true') {
    $form->addElement(
        'text',
        'username',
        get_lang('LoginName'),
        ['autocomplete' => 'off', 'maxlength' => USERNAME_MAX_LENGTH]
    );
    $form->addRule('username', get_lang('ThisFieldIsRequired'), 'required');
    $form->addRule(
        'username',
        sprintf(get_lang('UsernameMaxXCharacters'), (string) USERNAME_MAX_LENGTH),
        'maxlength',
        USERNAME_MAX_LENGTH
    );
    $form->addRule('username', get_lang('OnlyLettersAndNumbersAllowed'), 'username');
    $form->addRule('username', get_lang('UserTaken'), 'username_available', $user_data['username']);
}

if (isset($extAuthSource) && !empty($extAuthSource) && count($extAuthSource) > 0) {
    $form->addLabel(
        get_lang('ExternalAuthentication'),
        $userInfo['auth_source']
    );
}

// Password
$form->addElement('radio', 'reset_password', get_lang('Password'), get_lang('DontResetPassword'), 0);
$nb_ext_auth_source_added = 0;
if (isset($extAuthSource) && !empty($extAuthSource) && count($extAuthSource) > 0) {
    $auth_sources = [];
    foreach ($extAuthSource as $key => $info) {
        // @todo : make uniform external authentication configuration (ex : cas and external_login ldap)
        // Special case for CAS. CAS is activated from Chamilo > Administration > Configuration > CAS
        // extAuthSource always on for CAS even if not activated
        // same action for file user_add.php
        if (($key == CAS_AUTH_SOURCE && api_get_setting('cas_activate') === 'true') || ($key != CAS_AUTH_SOURCE)) {
            $auth_sources[$key] = $key;
            $nb_ext_auth_source_added++;
        }
    }
    if ($nb_ext_auth_source_added > 0) {
        // @todo check the radio button for external authentification and select the external authentication in the menu
        $group[] = $form->createElement('radio', 'reset_password', null, get_lang('ExternalAuthentication').' ', 3);
        $group[] = $form->createElement('select', 'auth_source', null, $auth_sources);
        $group[] = $form->createElement('static', '', '', '<br />');
        $form->addGroup($group, 'password', null, null, false);
    }
}
$form->addElement('radio', 'reset_password', null, get_lang('AutoGeneratePassword'), 1);
$group = [];
$group[] = $form->createElement('radio', 'reset_password', null, get_lang('EnterPassword'), 2);
$group[] = $form->createElement(
    'password',
    'password',
    null,
    [
        'id' => 'password',
        'autocomplete' => 'new-password',
        'onkeydown' => 'javascript: password_switch_radio_button();',
        'show_hide' => true,
        //'required' => 'required'
    ]
);

$form->addGroup(
    $group,
    'password',
    '',
    null,
    false
);
$form->addPasswordRule('password', 'password');
$form->addNoSamePasswordRule('password', $userEntity);

// Status
$status = UserManager::getUserStatusList();

$hideSelectProfile = false;
// to hide the status list if it is not enabled with user_status_show_option for admin sessions.
if (api_is_session_admin()) {
    if (true === api_get_configuration_value('user_status_show_options_enabled')) {
        $userStatusConfig = api_get_configuration_value('user_status_show_option');
        if (!empty($userStatusConfig)) {
            $statusConfigHidden = [];
            foreach ($userStatusConfig as $role => $enabled) {
                $constStatus = constant($role);
                if (!$enabled) {
                    $statusConfigHidden[] = $constStatus;
                }
            }
            $hideSelectProfile = in_array($user_data['status'], $statusConfigHidden);
        }
    }
}

if (!$hideSelectProfile) {
    $form->addElement(
        'select',
        'status',
        get_lang('Profile'),
        $status,
        [
            'id' => 'status_select',
            'onchange' => 'javascript: display_drh_list();',
        ]
    );
} else {
    $form->addElement('hidden', 'status', $user_data['status']);
}

// Platform admin
if (api_is_platform_admin()) {
    $group = [];
    $group[] = $form->createElement('radio', 'platform_admin', null, get_lang('Yes'), 1);
    $group[] = $form->createElement('radio', 'platform_admin', null, get_lang('No'), 0);

    $form->addElement('html', '<div id="id_platform_admin">');
    $form->addGroup($group, 'admin', get_lang('PlatformAdmin'), null, false);
    $form->addElement('html', '</div>');
}

// Language
$form->addSelectLanguage('language', get_lang('Language'));

// Send email
$group = [];
$group[] = $form->createElement('radio', 'send_mail', null, get_lang('Yes'), 1);
$group[] = $form->createElement('radio', 'send_mail', null, get_lang('No'), 0);
$form->addGroup($group, 'mail', get_lang('SendMailToNewUser'), null, false);

// Registration User and Date
$creatorInfo = api_get_user_info($user_data['creator_id']);
$date = sprintf(
    get_lang('CreatedByXYOnZ'),
    'user_information.php?user_id='.$user_data['creator_id'],
    $creatorInfo['username'],
    $user_data['registration_date']
);
$form->addElement('label', get_lang('RegistrationDate'), $date);

$defaultExpiration = 0;
if (!$user_data['platform_admin']) {
    $hideNeverExpiresOpt = api_get_configuration_value('user_hide_never_expire_option');
    $hideExpirationDate = api_get_configuration_value('user_hide_expiration_date_for_session_admin');
    $lblExpiration = '';
    if (!$hideExpirationDate || api_is_platform_admin()) {
        if ($hideNeverExpiresOpt && !api_is_platform_admin()) {
            $lblExpiration = get_lang('ExpirationDate');
            $defaultExpiration = 1;
            $group = [];
            $group[] = $form->createElement('radio', 'radio_expiration_date', get_lang('ExpirationDate'), get_lang('Enabled'), 1);
            $group[] = $form->createElement(
                'DateTimePicker',
                'expiration_date',
                null
            );
        } else {
            $form->addElement('radio', 'radio_expiration_date', get_lang('ExpirationDate'), get_lang('NeverExpires'), 0);
            $group = [];
            $group[] = $form->createElement('radio', 'radio_expiration_date', null, get_lang('Enabled'), 1);
            $group[] = $form->createElement(
                'DateTimePicker',
                'expiration_date',
                null,
                [
                    'onchange' => 'javascript: enable_expiration_date();',
                ]
            );
        }
        $form->addGroup($group, 'max_member_group', $lblExpiration, null, false);
    }

    // Active account or inactive account
    $form->addElement('radio', 'active', get_lang('ActiveAccount'), get_lang('Active'), 1);
    $form->addElement('radio', 'active', '', get_lang('Inactive'), 0);
}

// EXTRA FIELDS
$extraField = new ExtraField('user');
$excludeExtraFields = [
    'legal_accept',
    'already_logged_in',
    'update_type',
    'rssfeeds',
    'dashboard',
    'user_chat_status',
    'google_calendar_url',
    'captcha_blocked_until_date',
    'skype',
    'mail_notify_invitation',
    'mail_notify_message',
    'mail_notify_group_message',
];
$returnParams = $extraField->addElements(
    $form,
    $user_data['user_id'],
    $excludeExtraFields,
    false,
    false,
    [],
    [],
    [],
    false,
    true
);
$jqueryReadyContent = $returnParams['jquery_ready_content'];

$allowEmailTemplate = api_get_configuration_value('mail_template_system');
if ($allowEmailTemplate) {
    $form->addEmailTemplate(['user_edit_content.tpl']);
}

// the $jqueryReadyContent variable collects all functions that will be load in the
$htmlHeadXtra[] = '<script>
$(function () {
    '.$jqueryReadyContent.'
});
</script>';

// Freeze user conditions, admin cannot updated them
$extraConditions = api_get_configuration_value('show_conditions_to_user');

if ($extraConditions && isset($extraConditions['conditions'])) {
    $extraConditions = $extraConditions['conditions'];
    foreach ($extraConditions as $condition) {
        /** @var HTML_QuickForm_group $element */
        $element = $form->getElement('extra_'.$condition['variable']);
        if ($element) {
            $element->freeze();
        }
    }
}

// Submit button
$form->addButtonSave(get_lang('Save'));

// Set default values
$user_data['reset_password'] = 0;
$expiration_date = $user_data['expiration_date'];

if (empty($expiration_date)) {
    $user_data['radio_expiration_date'] = 0;
    $days = api_get_setting('account_valid_duration');
    $user_data['expiration_date'] = api_get_local_time('+'.$days.' day');
} else {
    $user_data['radio_expiration_date'] = 1;
    $user_data['expiration_date'] = api_get_local_time($expiration_date);
}
$form->setDefaults($user_data);

$error_drh = false;
// Validate form
if ($form->validate()) {
    $user = $form->getSubmitValues(1);
    $reset_password = (int) $user['reset_password'];
    if ($reset_password == 2 && empty($user['password'])) {
        Display::addFlash(Display::return_message(get_lang('PasswordIsTooShort')));
        header('Location: '.api_get_self().'?user_id='.$user_id);
        exit();
    }

    $is_user_subscribed_in_course = CourseManager::is_user_subscribed_in_course($user['user_id']);

    $picture_element = $form->getElement('picture');
    $picture = $picture_element->getValue();

    $picture_uri = $user_data['picture_uri'];
    if (isset($user['delete_picture']) && $user['delete_picture']) {
        $picture_uri = UserManager::deleteUserPicture($user_id);
    } elseif (!empty($picture['name'])) {
        $picture_uri = UserManager::update_user_picture(
            $user_id,
            $_FILES['picture']['name'],
            $_FILES['picture']['tmp_name'],
            $user['picture_crop_result']
        );
    }

    $lastname = $user['lastname'];
    $firstname = $user['firstname'];
    $password = $user['password'];
    $auth_source = isset($user['auth_source']) ? $user['auth_source'] : $userInfo['auth_source'];
    $official_code = $user['official_code'];
    $email = $user['email'];
    $phone = $user['phone'];
    $username = isset($user['username']) ? $user['username'] : $userInfo['username'];
    $status = (int) $user['status'];
    $platform_admin = 0;
    // Only platform admin can change user status to admin.
    if (api_is_platform_admin()) {
        $platform_admin = (int) $user['platform_admin'];
    }

    $send_mail = (int) $user['send_mail'];
    $reset_password = (int) $user['reset_password'];
    $hr_dept_id = isset($user['hr_dept_id']) ? intval($user['hr_dept_id']) : null;
    $language = $user['language'];
    $address = isset($user['address']) ? $user['address'] : null;

    $expiration_date = null;
    if (!$user_data['platform_admin'] && $user['radio_expiration_date'] == '1') {
        if (empty($user['expiration_date'])) {
            Display::addFlash(Display::return_message(get_lang('EmptyExpirationDate')));
            header('Location: '.api_get_self().'?user_id='.$user_id);
            exit();
        }
        $expiration_date = $user['expiration_date'];
    }

    $active = $user_data['platform_admin'] ? 1 : intval($user['active']);

    //If the user is set to admin the status will be overwrite by COURSEMANAGER = 1
    if ($platform_admin == 1) {
        $status = COURSEMANAGER;
    }

    if (api_get_setting('login_is_email') === 'true') {
        $username = $email;
    }

    $template = isset($user['email_template_option']) ? $user['email_template_option'] : [];

    UserManager::update_user(
        $user_id,
        $firstname,
        $lastname,
        $username,
        $password,
        $auth_source,
        $email,
        $status,
        $official_code,
        $phone,
        $picture_uri,
        $expiration_date,
        $active,
        null,
        $hr_dept_id,
        null,
        $language,
        null,
        $send_mail,
        $reset_password,
        $address,
        $template
    );

    if ($reset_password != 0 && !empty($password)) {
        Database::query("UPDATE ".Database::get_main_table(TABLE_MAIN_USER)." SET plain_password = '".Database::escape_string($password)."' WHERE id = $user_id");
        UserManager::update_extra_field_value($user_id, 'plain_password', $password);
    }
    UserManager::subscribeUserToBossList(
        $user_id,
        $studentBossListSent,
        true
    );

    if (api_get_setting('openid_authentication') === 'true' && !empty($user['openid'])) {
        $up = UserManager::update_openid($user_id, $user['openid']);
    }

    $currentUserId = api_get_user_id();
    if ($user_id != $currentUserId) {
        $userObj = api_get_user_entity($user_id);
        if ($platform_admin == 1) {
            UserManager::addUserAsAdmin($userObj);
        } else {
            UserManager::removeUserAdmin($userObj);
        }
    }

    // It updates course relation type as EX-LEARNER if project name (extra field from user_edition_extra_field_to_check) is changed
    if (false !== api_get_configuration_value('user_edition_extra_field_to_check')) {
        $extraToCheck = api_get_configuration_value('user_edition_extra_field_to_check');
        if (isset($user['extra_'.$extraToCheck])) {
            $extraValueToCheck = $user['extra_'.$extraToCheck];
            UserManager::updateCourseRelationTypeExLearner($user_id, $extraValueToCheck);
        }
    }

    $extraFieldValue = new ExtraFieldValue('user');
    $extraFieldValue->saveFieldValues($user);
    $userInfo = api_get_user_info($user_id);
    $message = get_lang('UserUpdated').': '.Display::url(
        $userInfo['complete_name_with_username'],
        api_get_path(WEB_CODE_PATH).'admin/user_edit.php?user_id='.$user_id
    );

    Session::erase('system_timezone');

    Display::addFlash(Display::return_message($message, 'normal', false));
    header('Location: user_list.php');
    exit();
}

$actions = [];
$actions[] = '<a href="'.api_get_path(WEB_CODE_PATH).'admin/user_information.php?user_id='.$user_id.'" class="btn btn-default" style="font-weight:600;"><i class="fa fa-user"></i> '.get_lang('Information').'</a>';
if (api_can_login_as($user_id)) {
    $actions[] = '<a href="'.api_get_path(WEB_CODE_PATH).'admin/user_list.php?action=login_as&user_id='.$user_id.'&sec_token='.Security::getTokenFromSession().'" class="btn btn-default" style="font-weight:600;"><i class="fa fa-sign-in"></i> '.get_lang('LoginAs').'</a>';
}
$actions[] = '<a href="'.api_get_path(WEB_CODE_PATH).'admin/user_dashboard.php?role=student" class="btn btn-default" style="font-weight:600;"><i class="fa fa-users"></i> '.get_lang('UserDashboard').'</a>';
$actions[] = '<a href="'.api_get_path(WEB_CODE_PATH).'admin/user_list.php" class="btn btn-default" style="font-weight:600;"><i class="fa fa-list"></i> '.get_lang('UserList').'</a>';

$content = Display::toolbarAction('toolbar-user-edit', [implode(' ', $actions)]);

$bigImage = UserManager::getUserPicture($user_id, USER_IMAGE_SIZE_BIG);
$normalImage = UserManager::getUserPicture($user_id, USER_IMAGE_SIZE_ORIGINAL);

$roleBadge = '<span class="label" style="background:#4F46E5; color:#fff; font-size:11px; padding:3px 9px; border-radius:12px; font-weight:700;">Learner</span>';
if (!empty($user_data['is_admin'])) {
    $roleBadge = '<span class="label" style="background:#E3122B; color:#fff; font-size:11px; padding:3px 9px; border-radius:12px; font-weight:700;">Administrator</span>';
} elseif ($user_data['status'] == COURSEMANAGER) {
    $roleBadge = '<span class="label" style="background:#F59E0B; color:#fff; font-size:11px; padding:3px 9px; border-radius:12px; font-weight:700;">Instructor</span>';
}

$statusBadge = $user_data['active'] == 1 
    ? '<span class="label" style="background:#ECFDF5; color:#065F46; border:1px solid #A7F3D0; font-size:11px; padding:3px 8px; border-radius:12px; font-weight:600;">Active</span>'
    : '<span class="label" style="background:#FEF2F2; color:#991B1B; border:1px solid #FECACA; font-size:11px; padding:3px 8px; border-radius:12px; font-weight:600;">Inactive</span>';

$content .= '<div class="row" style="margin-top: 15px;">';

// Left Sidebar: Compact Profile Card
$content .= '<div class="col-md-3 col-sm-12" style="margin-bottom: 20px;">';
$content .= '<div class="panel panel-default" style="border-radius:12px; border:1px solid #E2E8F0; box-shadow:0 1px 4px rgba(0,0,0,0.05); background:#ffffff; padding:22px 18px; text-align:center;">';
$content .= '<div style="margin-bottom:14px; position:relative; display:inline-block;">';
$content .= '<img src="'.$normalImage.'" alt="'.api_htmlentities($user_data['firstname']).'" style="width:96px; height:96px; border-radius:50%; object-fit:cover; border:3px solid #E2E8F0; box-shadow:0 3px 10px rgba(0,0,0,0.08);" />';
$content .= '</div>';
$content .= '<h4 style="margin:0 0 4px 0; font-weight:700; color:#0F172A; font-size:16px;">'.api_htmlentities($user_data['firstname'].' '.$user_data['lastname']).'</h4>';
$content .= '<div style="color:#64748B; font-size:12.5px; font-weight:500; margin-bottom:12px;">@'.api_htmlentities($user_data['username']).'</div>';
$content .= '<div style="margin-bottom:16px; display:flex; justify-content:center; gap:6px; flex-wrap:wrap;">'.$roleBadge.' '.$statusBadge.'</div>';
$content .= '<hr style="border:0; border-top:1px solid #F1F5F9; margin:14px 0;" />';
$content .= '<div style="text-align:left; font-size:12.5px; color:#475569; line-height:1.8;">';
$content .= '<div><span style="color:#64748B; width:70px; display:inline-block;">User ID:</span> <strong style="color:#0F172A;">#'.$user_id.'</strong></div>';
$content .= '<div><span style="color:#64748B; width:70px; display:inline-block;">Email:</span> <span style="color:#0F172A; word-break:break-all;">'.api_htmlentities($user_data['email']).'</span></div>';
if (!empty($user_data['phone'])) {
    $content .= '<div><span style="color:#64748B; width:70px; display:inline-block;">Phone:</span> <span style="color:#0F172A;">'.api_htmlentities($user_data['phone']).'</span></div>';
}
$content .= '<div><span style="color:#64748B; width:70px; display:inline-block;">Joined:</span> <span style="color:#0F172A;">'.$user_data['registration_date'].'</span></div>';
$content .= '</div>';
$content .= '</div>';
$content .= '</div>';

// Right Column: Clean Form Container
$content .= '<div class="col-md-9 col-sm-12">';
$content .= '<div class="panel panel-default" style="border-radius:12px; border:1px solid #E2E8F0; box-shadow:0 1px 4px rgba(0,0,0,0.05); background:#ffffff; padding:24px 30px; margin-bottom:30px;">';
$content .= '<style>
.form-horizontal .form-group { margin-bottom: 16px; }
.form-horizontal .control-label { font-weight: 600; color: #1E293B; font-size: 13px; }
.form-control { border-radius: 6px; border: 1px solid #CBD5E1; box-shadow: none; font-size: 13.5px; height: 38px; }
.form-control:focus { border-color: #4F46E5; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
legend { font-size: 16px; font-weight: 700; color: #0F172A; border-bottom: 1px solid #E2E8F0; padding-bottom: 8px; margin-bottom: 20px; }
.btn-primary { background: #E3122B !important; border-color: #E3122B !important; font-weight: 600; border-radius: 6px; padding: 8px 24px; font-size: 14px; }
.btn-primary:hover { background: #C8102E !important; }
.form-horizontal .radio, .form-horizontal .checkbox { margin-top: 4px; margin-bottom: 4px; }
</style>';
$content .= $form->returnForm();
$content .= '</div>';
$content .= '</div>';
$content .= '</div>';

$tpl = new Template($tool_name);
$tpl->assign('content', $content);
$tpl->display_one_col_template();
