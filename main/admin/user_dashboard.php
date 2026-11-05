<?php
/* For licensing terms, see /license.txt */

/**
 * Sparky-Style Students & User Administration Panel
 * Education & Learning Platform (/dashboard/students/)
 */

$cidReset = true;
require_once __DIR__.'/../inc/global.inc.php';
require_once api_get_path(SYS_CODE_PATH).'inc/lib/magic_login.lib.php';

// Access restrictions
api_protect_admin_script(true);

$this_section = SECTION_PLATFORM_ADMIN;
$currentUserId = api_get_user_id();

$extraFieldValue = new ExtraFieldValue('user');
$extraField = new ExtraField('user');

// Database tables
$userTable = Database::get_main_table(TABLE_MAIN_USER);
$adminTable = Database::get_main_table(TABLE_MAIN_ADMIN);
$courseUserTable = Database::get_main_table(TABLE_MAIN_COURSE_USER);
$courseTable = Database::get_main_table(TABLE_MAIN_COURSE);
$extraFieldTable = Database::get_main_table(TABLE_EXTRA_FIELD);
$extraFieldValueTable = Database::get_main_table(TABLE_EXTRA_FIELD_VALUES);

// Handle AJAX Request for User Info & Enrollments
if (isset($_GET['action']) && $_GET['action'] === 'ajax_get_user') {
    header('Content-Type: application/json');
    $uId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    if ($uId <= 0) {
        echo json_encode(['error' => 'Invalid user ID']);
        exit;
    }
    $uInfo = api_get_user_info($uId);
    if (!$uInfo) {
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    $uCourses = CourseManager::get_courses_list_by_user_id($uId);
    $verif = $extraFieldValue->get_values_by_handler_and_field_variable($uId, 'verification_status');
    $verifVal = isset($verif['value']) ? $verif['value'] : 'verified';
    $appr = $extraFieldValue->get_values_by_handler_and_field_variable($uId, 'approval_status');
    $apprVal = isset($appr['value']) ? $appr['value'] : 'approved';
    $tags = $extraFieldValue->get_values_by_handler_and_field_variable($uId, 'student_tags');
    $tagsVal = isset($tags['value']) ? $tags['value'] : '';

    // Fetch plain_password from user table or extra_field
    $plainPass = '';
    $uRow = Database::fetch_assoc(Database::query("SELECT plain_password FROM $userTable WHERE id = $uId"));
    if (!empty($uRow['plain_password'])) {
        $plainPass = $uRow['plain_password'];
    } else {
        $efPass = $extraFieldValue->get_values_by_handler_and_field_variable($uId, 'plain_password');
        if (!empty($efPass['value'])) {
            $plainPass = $efPass['value'];
        }
    }

    echo json_encode([
        'id' => $uInfo['id'],
        'name' => $uInfo['complete_name'],
        'firstname' => $uInfo['firstname'],
        'lastname' => $uInfo['lastname'],
        'username' => $uInfo['username'],
        'password' => $plainPass,
        'email' => $uInfo['email'],
        'phone' => $uInfo['phone'],
        'status' => $uInfo['status'],
        'active' => $uInfo['active'],
        'registered' => $uInfo['registration_date'],
        'verification' => $verifVal,
        'approval' => $apprVal,
        'tags' => $tagsVal,
        'courses' => array_values($uCourses),
    ]);
    exit;
}

// Handle AJAX Request to Set / Reset User Password
if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'ajax_set_password') {
    header('Content-Type: application/json');
    $uId = isset($_REQUEST['user_id']) ? (int)$_REQUEST['user_id'] : 0;
    $newPassword = isset($_REQUEST['new_password']) ? trim($_REQUEST['new_password']) : '';

    if ($uId <= 0 || empty($newPassword)) {
        echo json_encode(['success' => false, 'error' => 'User ID and new password are required.']);
        exit;
    }

    $uInfo = api_get_user_info($uId);
    if (!$uInfo) {
        echo json_encode(['success' => false, 'error' => 'User not found.']);
        exit;
    }

    UserManager::updatePassword($uId, $newPassword);
    Database::query("UPDATE $userTable SET plain_password = '" . Database::escape_string($newPassword) . "' WHERE id = $uId");
    $extraFieldValue->save([
        'item_id' => $uId,
        'variable' => 'plain_password',
        'value' => $newPassword,
    ]);

    echo json_encode([
        'success' => true,
        'password' => $newPassword,
        'message' => 'Password updated successfully.',
    ]);
    exit;
}


// Handle AJAX Request for Magic Login Link Generation
if (isset($_GET['action']) && $_GET['action'] === 'get_magic_link') {
    header('Content-Type: application/json');
    $uId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    $forceNew = !empty($_GET['force_new']);
    if ($uId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
        exit;
    }
    $uInfo = api_get_user_info($uId);
    if (!$uInfo) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }

    $magicUrl = MagicLoginManager::getMagicLoginUrl($uId, $forceNew);
    echo json_encode([
        'success' => true,
        'url' => $magicUrl,
        'user_id' => $uId,
        'name' => $uInfo['complete_name'],
        'email' => $uInfo['email'],
        'username' => $uInfo['username']
    ]);
    exit;
}
// 1-Click Magic Login
if (isset($_GET['action']) && $_GET['action'] === 'login_as') {
    $targetId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    if ($targetId > 0 && api_can_login_as($targetId)) {
        $loginRes = UserManager::loginAsUser($targetId);
        if ($loginRes) {
            header("Location: ".api_get_path(WEB_PATH)."user_portal.php");
            exit;
        }
    }
}

// Handle Single Actions from Query
$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : '';
$message = '';
$messageType = 'success';

if ($action === 'delete_user' && isset($_REQUEST['user_id'])) {
    $targetId = (int)$_REQUEST['user_id'];
    if ($targetId > 0 && $targetId !== $currentUserId) {
        if (!UserManager::canDeleteUser($targetId)) {
            $message = "Cannot delete this user because they are the sole instructor of an active course. Please reassign the course instructor first.";
            $messageType = "danger";
        } else {
            Database::query("DELETE FROM c_certificate_awarded WHERE user_id = $targetId");
            $deleted = UserManager::delete_user($targetId);
            if ($deleted) {
                $message = "Student / User has been permanently deleted.";
                $messageType = "success";
            } else {
                $message = "Could not delete user. Please check database permissions.";
                $messageType = "danger";
            }
        }
    }
}

if ($action === 'enroll_single' && isset($_POST['user_id']) && isset($_POST['course_code'])) {
    $targetId = (int)$_POST['user_id'];
    $cCode = trim($_POST['course_code']);
    $role = isset($_POST['role']) && (int)$_POST['role'] === COURSEMANAGER ? COURSEMANAGER : STUDENT;
    if ($targetId > 0 && !empty($cCode)) {
        CourseManager::subscribeUser($targetId, $cCode, $role, 0, 0, false, false);
        $message = "User successfully enrolled into <strong>".htmlspecialchars($cCode)."</strong>.";
        $messageType = "success";
    }
}

if ($action === 'unenroll_single' && isset($_POST['user_id']) && isset($_POST['course_code'])) {
    $targetId = (int)$_POST['user_id'];
    $cCode = trim($_POST['course_code']);
    if ($targetId > 0 && !empty($cCode)) {
        CourseManager::unsubscribe_user($targetId, $cCode);
        $message = "User unenrollment completed.";
        $messageType = "success";
    }
}

if ($action === 'toggle_verification' && isset($_POST['user_id'])) {
    $targetId = (int)$_POST['user_id'];
    $newStatus = isset($_POST['new_status']) && $_POST['new_status'] === 'verified' ? 'verified' : 'unverified';
    if ($targetId > 0) {
        $extraFieldValue->save([
            'item_id' => $targetId,
            'variable' => 'verification_status',
            'value' => $newStatus,
        ]);
        $message = "Verification status updated to <strong>".ucfirst($newStatus)."</strong>.";
        $messageType = "info";
    }
}

if ($action === 'toggle_approval' && isset($_POST['user_id'])) {
    $targetId = (int)$_POST['user_id'];
    $newStatus = isset($_POST['new_status']) && $_POST['new_status'] === 'approved' ? 'approved' : 'pending';
    if ($targetId > 0) {
        $extraFieldValue->save([
            'item_id' => $targetId,
            'variable' => 'approval_status',
            'value' => $newStatus,
        ]);
        $message = "Approval status updated to <strong>".ucfirst($newStatus)."</strong>.";
        $messageType = "info";
    }
}

if ($action === 'update_tags' && isset($_POST['user_id'])) {
    $targetId = (int)$_POST['user_id'];
    $tagStr = isset($_POST['tags']) ? trim($_POST['tags']) : '';
    if ($targetId > 0) {
        $extraFieldValue->save([
            'item_id' => $targetId,
            'variable' => 'student_tags',
            'value' => $tagStr,
        ]);
        $message = "Tags updated successfully.";
        $messageType = "success";
    }
}

if ($action === 'batch_delete' && isset($_POST['user_ids']) && is_array($_POST['user_ids'])) {
    $countDel = 0;
    $cannotDel = 0;
    foreach ($_POST['user_ids'] as $delId) {
        $delId = (int)$delId;
        if ($delId > 0 && $delId !== $currentUserId) {
            if (UserManager::canDeleteUser($delId)) {
                Database::query("DELETE FROM c_certificate_awarded WHERE user_id = $delId");
                if (UserManager::delete_user($delId)) {
                    $countDel++;
                }
            } else {
                $cannotDel++;
            }
        }
    }
    if ($countDel > 0) {
        $message = "Successfully deleted $countDel student(s)/user(s)." . ($cannotDel > 0 ? " ($cannotDel user(s) could not be deleted because they are sole instructors)." : "");
        $messageType = "warning";
    } else {
        $message = "No users could be deleted." . ($cannotDel > 0 ? " Selected users are assigned as sole course instructors." : " Please select at least one user.");
        $messageType = "danger";
    }
}

if ($action === 'quick_add_user' && isset($_POST['email'])) {
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $roleType = trim($_POST['role_type']);
    $verifyOpt = isset($_POST['verification_status']) ? $_POST['verification_status'] : 'verified';
    $assignCourses = isset($_POST['assign_courses']) && is_array($_POST['assign_courses']) ? $_POST['assign_courses'] : [];

    if (empty($firstname) || empty($lastname) || empty($email)) {
        $message = "First name, last name, and email are required.";
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
        if ($roleType === 'teacher') {
            $status = COURSEMANAGER;
        } elseif ($roleType === 'admin') {
            $status = COURSEMANAGER;
            $isPlatformAdmin = 1;
        }

        // Create user with send_mail = 0 so failure in local mail function does not break
        $newUserId = UserManager::create_user(
            $firstname,
            $lastname,
            $status,
            $email,
            $username,
            $password,
            '',
            api_get_setting('platformLanguage'),
            $phone,
            null,
            PLATFORM_AUTH_SOURCE,
            null,
            1,
            0,
            [],
            null,
            0,
            $isPlatformAdmin
        );

        if ($newUserId) {
            Database::query("UPDATE $userTable SET plain_password = '" . Database::escape_string($password) . "' WHERE id = $newUserId");
            $extraFieldValue->save([
                'item_id' => $newUserId,
                'variable' => 'plain_password',
                'value' => $password,
            ]);
            $extraFieldValue->save([
                'item_id' => $newUserId,
                'variable' => 'verification_status',
                'value' => $verifyOpt,
            ]);
            $extraFieldValue->save([
                'item_id' => $newUserId,
                'variable' => 'approval_status',
                'value' => 'approved',
            ]);

            // Enroll user directly in selected courses
            $enrolledCourseNames = [];
            if (!empty($assignCourses)) {
                foreach ($assignCourses as $cCode) {
                    $cCode = trim($cCode);
                    if (!empty($cCode)) {
                        $cInf = api_get_course_info($cCode);
                        if (!empty($cInf)) {
                            CourseManager::subscribeUser($newUserId, $cInf['code'], $status, 0, 0, false, false);
                            $enrolledCourseNames[] = $cInf['title'];
                        }
                    }
                }
            }

            $coursesBadgeHtml = !empty($enrolledCourseNames) 
                ? '<div style="margin-top: 10px; font-size: 13px; color: #065F46;"><i class="fa fa-check-circle" style="color: #10B981;"></i> <strong>Directly enrolled in ' . count($enrolledCourseNames) . ' course(s):</strong> ' . htmlspecialchars(implode(', ', $enrolledCourseNames)) . '</div>'
                : '<div style="margin-top: 10px; font-size: 12.5px; color: #6B7280;"><i class="fa fa-info-circle"></i> No courses were selected for initial enrollment.</div>';

            $message = '
            <div style="background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 10px; padding: 16px; margin: 4px 0;">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 12px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 50%; background: #10B981; color: #FFF; font-size: 16px;">
                            <i class="fa fa-check"></i>
                        </span>
                        <div>
                            <strong style="color: #166534; font-size: 15.5px;">Student Created & Enrolled Successfully!</strong>
                            <span style="display: block; color: #15803D; font-size: 12.5px;">' . htmlspecialchars("$firstname $lastname ($email)") . '</span>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-success" onclick="copyLoginCredentials(\''.htmlspecialchars(addslashes($username)).'\', \''.htmlspecialchars(addslashes($password)).'\', \''.htmlspecialchars(addslashes(implode(', ', $enrolledCourseNames))).'\')" style="background: #10B981; border-color: #059669; font-weight: 600; padding: 6px 14px; border-radius: 6px; box-shadow: 0 2px 6px rgba(16,185,129,0.3);">
                        <i class="fa fa-clipboard"></i> Copy Login Credentials
                    </button>
                </div>
                <div style="background: #FFFFFF; border: 1px solid #DCFCE7; border-radius: 8px; padding: 12px 16px; display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; font-size: 13px;">
                    <div>
                        <span style="color: #6B7280; font-size: 11px; text-transform: uppercase; font-weight: 700; display: block;">Username</span>
                        <code style="color: #0F172A; font-size: 13.5px; font-weight: 700; background: #F1F5F9; padding: 2px 6px; border-radius: 4px;">' . htmlspecialchars($username) . '</code>
                    </div>
                    <div>
                        <span style="color: #6B7280; font-size: 11px; text-transform: uppercase; font-weight: 700; display: block;">Password</span>
                        <code style="color: #DC2626; font-size: 13.5px; font-weight: 700; background: #FEF2F2; padding: 2px 6px; border-radius: 4px; border: 1px solid #FEE2E2;">' . htmlspecialchars($password) . '</code>
                    </div>
                    <div>
                        <span style="color: #6B7280; font-size: 11px; text-transform: uppercase; font-weight: 700; display: block;">Role</span>
                        <span style="color: #374151; font-weight: 600;">' . htmlspecialchars(ucfirst($roleType)) . '</span>
                    </div>
                </div>
                ' . $coursesBadgeHtml . '
            </div>';
            $messageType = "success";
        } else {
            $message = "Could not create user. Username or email already registered.";
            $messageType = "danger";
        }
    }
}

// Handle Sample CSV Download// Handle Sample CSV Download
if (isset($_GET['action']) && $_GET['action'] === 'download_sample_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sample_bulk_students_template.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['firstname', 'lastname', 'email', 'phone', 'username', 'password', 'course_codes']);
    fputcsv($out, ['Aarav', 'Sharma', 'aarav.sharma@example.com', '9876543210', 'aarav_sharma', 'Password@123', '']);
    fputcsv($out, ['Priya', 'Verma', 'priya.verma@example.com', '9811223344', 'priya_verma', 'Password@123', '']);
    fputcsv($out, ['Vikram', 'Malhotra', 'vikram.m@example.com', '9822334455', 'vikram_m', 'Password@123', '']);
    fclose($out);
    exit;
}

// Handle Bulk CSV Import
if ($action === 'bulk_import_students' && isset($_FILES['import_file']) && $_FILES['import_file']['error'] === UPLOAD_ERR_OK) {
    $fileTmp = $_FILES['import_file']['tmp_name'];
    $defaultCourses = isset($_POST['assign_courses']) && is_array($_POST['assign_courses']) ? $_POST['assign_courses'] : [];
    $markVerified = isset($_POST['mark_verified']) && $_POST['mark_verified'] == '1';
    
    $handle = fopen($fileTmp, 'r');
    if ($handle) {
        $header = fgetcsv($handle, 4096, ',');
        if (!$header || count($header) < 2) {
            rewind($handle);
            $header = fgetcsv($handle, 4096, ';');
        }
        
        $headerMap = [];
        if ($header) {
            foreach ($header as $idx => $col) {
                $colClean = strtolower(trim(str_replace([' ', '_', '-'], '', $col)));
                $headerMap[$colClean] = $idx;
            }
        }
        
        $importedCount = 0;
        $skippedCount = 0;
        $enrolledCount = 0;
        
        while (($row = fgetcsv($handle, 4096, ',')) !== false || ($row = fgetcsv($handle, 4096, ';')) !== false) {
            if (empty($row) || (count($row) == 1 && empty($row[0]))) continue;
            
            $fn = isset($headerMap['firstname']) && isset($row[$headerMap['firstname']]) ? trim($row[$headerMap['firstname']]) : (isset($row[0]) ? trim($row[0]) : '');
            $ln = isset($headerMap['lastname']) && isset($row[$headerMap['lastname']]) ? trim($row[$headerMap['lastname']]) : (isset($row[1]) ? trim($row[1]) : '');
            $em = isset($headerMap['email']) && isset($row[$headerMap['email']]) ? trim($row[$headerMap['email']]) : (isset($row[2]) ? trim($row[2]) : '');
            $ph = isset($headerMap['phone']) && isset($row[$headerMap['phone']]) ? trim($row[$headerMap['phone']]) : (isset($row[3]) ? trim($row[3]) : '');
            $un = isset($headerMap['username']) && isset($row[$headerMap['username']]) ? trim($row[$headerMap['username']]) : '';
            $pw = isset($headerMap['password']) && isset($row[$headerMap['password']]) ? trim($row[$headerMap['password']]) : '';
            $rowCoursesStr = isset($headerMap['coursecodes']) && isset($row[$headerMap['coursecodes']]) ? trim($row[$headerMap['coursecodes']]) : '';
            if (empty($rowCoursesStr) && isset($headerMap['courses']) && isset($row[$headerMap['courses']])) {
                $rowCoursesStr = trim($row[$headerMap['courses']]);
            }
            
            if (empty($em) || !filter_var($em, FILTER_VALIDATE_EMAIL)) {
                $skippedCount++;
                continue;
            }
            
            if (empty($fn)) $fn = explode('@', $em)[0];
            if (empty($ln)) $ln = 'Student';
            if (empty($un)) $un = UserManager::create_unique_username($fn, $ln);
            if (empty($pw)) $pw = api_generate_password();
            
            // Check existing user
            $existingUser = api_get_user_info_from_email($em);
            $targetUId = 0;
            if (!empty($existingUser)) {
                $targetUId = (int)$existingUser['id'];
                $skippedCount++;
            } else {
                $targetUId = UserManager::create_user(
                    $fn, $ln, STUDENT, $em, $un, $pw,
                    '', api_get_setting('platformLanguage'), $ph,
                    null, PLATFORM_AUTH_SOURCE, null, 1, 0, [], null, 1, 0
                );
                if ($targetUId) {
                    Database::query("UPDATE $userTable SET plain_password = '" . Database::escape_string($pw) . "' WHERE id = $targetUId");
                    $extraFieldValue->save(['item_id' => $targetUId, 'variable' => 'plain_password', 'value' => $pw]);
                    $importedCount++;
                    if ($markVerified) {
                        $extraFieldValue->save(['item_id' => $targetUId, 'variable' => 'verification_status', 'value' => 'verified']);
                        $extraFieldValue->save(['item_id' => $targetUId, 'variable' => 'approval_status', 'value' => 'approved']);
                    }
                }
            }
            
            // Enroll in selected courses and CSV courses
            $coursesToEnroll = $defaultCourses;
            if (!empty($rowCoursesStr)) {
                $splitCourses = array_map('trim', explode(',', $rowCoursesStr));
                $coursesToEnroll = array_unique(array_merge($coursesToEnroll, $splitCourses));
            }
            
            if ($targetUId && !empty($coursesToEnroll)) {
                foreach ($coursesToEnroll as $cCode) {
                    $cCode = trim($cCode);
                    if (!empty($cCode)) {
                        CourseManager::subscribeUser($targetUId, $cCode, STUDENT, 0, 0, false, false);
                        $enrolledCount++;
                    }
                }
            }
        }
        fclose($handle);
        
        $message = "<strong>Bulk Import Complete:</strong> Successfully created <strong>$importedCount</strong> student(s) and assigned <strong>$enrolledCount</strong> course enrollments. ($skippedCount duplicate/invalid rows skipped)";
        $messageType = "success";
    } else {
        $message = "Please upload a valid CSV file.";
        $messageType = "danger";
    }
}

// Handle CSV Export
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    $roleExp = isset($_GET['role']) && $_GET['role'] === 'teacher' ? COURSEMANAGER : STUDENT;
    $sql = "SELECT u.id, u.firstname, u.lastname, u.username, u.email, u.phone, u.registration_date, u.active
            FROM $userTable u WHERE u.status = $roleExp ORDER BY u.id DESC";
    $res = Database::query($sql);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="users_export_'.date('Ymd_His').'.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'First Name', 'Last Name', 'Username', 'Email', 'Phone', 'Registration Date', 'Active']);

    while ($row = Database::fetch_assoc($res)) {
        fputcsv($output, [
            $row['id'],
            $row['firstname'],
            $row['lastname'],
            $row['username'],
            $row['email'],
            $row['phone'],
            $row['registration_date'],
            $row['active'] == 1 ? 'Active' : 'Inactive'
        ]);
    }
    fclose($output);
    exit;
}

// Filters & Query Parameters
$roleFilter = isset($_GET['role']) ? trim($_GET['role']) : 'student';
$searchKeyword = isset($_GET['search']) ? trim($_GET['search']) : '';
$productFilter = isset($_GET['product']) ? trim($_GET['product']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : 'all'; // all, verified, unverified
$userActiveFilter = isset($_GET['user_active']) ? trim($_GET['user_active']) : 'active'; // active, inactive, all
$tagFilter = isset($_GET['tag']) ? trim($_GET['tag']) : '';
$dateStart = isset($_GET['date_start']) ? trim($_GET['date_start']) : '';
$dateEnd = isset($_GET['date_end']) ? trim($_GET['date_end']) : '';

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 25;
$offset = ($page - 1) * $limit;

// Fetch all courses for dropdowns
$allCourses = CourseManager::get_courses_list();

// Build Query
$where = ["1=1"];

if ($roleFilter === 'student') {
    $where[] = "u.status = ".STUDENT;
} elseif ($roleFilter === 'teacher') {
    $where[] = "u.status = ".COURSEMANAGER." AND u.id NOT IN (SELECT user_id FROM $adminTable)";
} elseif ($roleFilter === 'admin') {
    $where[] = "u.id IN (SELECT user_id FROM $adminTable)";
}

if ($userActiveFilter === 'active') {
    $where[] = "u.active = 1";
} elseif ($userActiveFilter === 'inactive') {
    $where[] = "u.active = 0";
}

if (!empty($searchKeyword)) {
    $kw = Database::escape_string($searchKeyword);
    $where[] = "(u.firstname LIKE '%$kw%' OR u.lastname LIKE '%$kw%' OR u.email LIKE '%$kw%' OR u.username LIKE '%$kw%')";
}

if (!empty($productFilter)) {
    $pCode = Database::escape_string($productFilter);
    $where[] = "u.id IN (SELECT user_id FROM $courseUserTable WHERE c_id = (SELECT id FROM $courseTable WHERE code = '$pCode' LIMIT 1))";
}

if (!empty($dateStart)) {
    $where[] = "u.registration_date >= '".Database::escape_string($dateStart)." 00:00:00'";
}
if (!empty($dateEnd)) {
    $where[] = "u.registration_date <= '".Database::escape_string($dateEnd)." 23:59:59'";
}

$whereClause = implode(" AND ", $where);

// Count total
$totalUsers = (int)Database::result(Database::query("SELECT COUNT(DISTINCT u.id) FROM $userTable u WHERE $whereClause"), 0, 0);
$totalPages = ceil($totalUsers / $limit);

// Fetch Users List
$sql = "SELECT u.id, u.firstname, u.lastname, u.username, u.email, u.phone, u.status, u.active, u.registration_date
        FROM $userTable u
        WHERE $whereClause
        ORDER BY u.id DESC
        LIMIT $offset, $limit";
$res = Database::query($sql);

$userList = [];
$verifiedFieldId = (int)Database::result(Database::query("SELECT id FROM $extraFieldTable WHERE variable = 'verification_status' AND extra_field_type = 1 LIMIT 1"), 0, 0);
$approvalFieldId = (int)Database::result(Database::query("SELECT id FROM $extraFieldTable WHERE variable = 'approval_status' AND extra_field_type = 1 LIMIT 1"), 0, 0);
$tagFieldId = (int)Database::result(Database::query("SELECT id FROM $extraFieldTable WHERE variable = 'student_tags' AND extra_field_type = 1 LIMIT 1"), 0, 0);

while ($row = Database::fetch_assoc($res)) {
    $uId = $row['id'];
    
    // Verification Status
    $vStatus = 'verified';
    if ($verifiedFieldId > 0) {
        $vVal = Database::result(Database::query("SELECT value FROM $extraFieldValueTable WHERE item_id = $uId AND field_id = $verifiedFieldId LIMIT 1"), 0, 0);
        if ($vVal !== false && $vVal !== null && $vVal !== '') {
            $vStatus = $vVal;
        }
    }

    // Approval Status
    $aStatus = 'approved';
    if ($approvalFieldId > 0) {
        $aVal = Database::result(Database::query("SELECT value FROM $extraFieldValueTable WHERE item_id = $uId AND field_id = $approvalFieldId LIMIT 1"), 0, 0);
        if ($aVal !== false && $aVal !== null && $aVal !== '') {
            $aStatus = $aVal;
        }
    }

    // Tags
    $tagsVal = '';
    if ($tagFieldId > 0) {
        $tVal = Database::result(Database::query("SELECT value FROM $extraFieldValueTable WHERE item_id = $uId AND field_id = $tagFieldId LIMIT 1"), 0, 0);
        if ($tVal !== false && $tVal !== null) {
            $tagsVal = $tVal;
        }
    }

    if ($statusFilter === 'verified' && $vStatus !== 'verified') continue;
    if ($statusFilter === 'unverified' && $vStatus === 'verified') continue;
    if (!empty($tagFilter) && stripos($tagsVal, $tagFilter) === false) continue;

    $row['verification_status'] = $vStatus;
    $row['approval_status'] = $aStatus;
    $row['tags'] = $tagsVal;
    $userList[] = $row;
}

$token = Security::get_token();

$tool_name = "Sparky Workspace";
$interbreadcrumb[] = ['url' => 'index.php', 'name' => get_lang('PlatformAdmin')];
$interbreadcrumb[] = ['url' => 'user_dashboard.php', 'name' => 'Students'];

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
    --color-border: #E5E7EB;
    --color-text-main: #111827;
    --color-text-muted: #6B7280;
    --font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
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
    background: linear-gradient(180deg, #0F172A 0%, #020617 100%);
    border: 1px solid #1E293B;
    border-radius: 12px;
    padding: 16px 12px;
    box-shadow: 0 4px 18px rgba(2, 6, 23, 0.45);
    flex-shrink: 0;
}

.gf-nav-group { margin-bottom: 6px; }

.gf-nav-group-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 10px;
    color: #FFFFFF;
    font-weight: 700;
    font-size: 14.5px;
    cursor: pointer;
    border-radius: 6px;
    user-select: none;
    transition: background-color 0.15s;
}

.gf-nav-group-header:hover {
    background-color: rgba(79, 70, 229, 0.15);
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
    color: #818CF8;
}

.gf-group-chevron {
    font-size: 11px;
    color: #A5B4FC;
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
    background-color: #312E81;
}

.gf-nav-tree-item {
    position: relative;
    margin: 3px 0;
}

.gf-nav-tree-item::before {
    content: '';
    position: absolute;
    top: 15px;
    left: -12px;
    width: 12px;
    height: 12px;
    border-bottom: 2px solid #312E81;
    border-left: 2px solid #312E81;
    border-bottom-left-radius: 8px;
}

.gf-nav-tree-link {
    display: block;
    padding: 7px 12px;
    color: #CBD5E1;
    font-size: 13.5px;
    font-weight: 500;
    border-radius: 6px;
    text-decoration: none !important;
    line-height: 1.35;
    transition: all 0.15s ease;
}

.gf-nav-tree-link:hover {
    color: #FFFFFF;
    background-color: rgba(79, 70, 229, 0.22);
}

.gf-nav-tree-link.active {
    background-color: #4F46E5;
    color: #FFFFFF;
    font-weight: 700;
    box-shadow: 0 2px 8px rgba(79, 70, 229, 0.45);
}

/* Right Main Content Area */
.gf-main-content {
    flex-grow: 1;
    min-width: 0;
}

/* Top Dual Filter Bars matching Screenshot */
.gf-filter-section {
    background: #FFFFFF;
    border: 1px solid #E5E7EB;
    border-radius: 10px;
    padding: 16px 20px;
    margin-bottom: 18px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}

.gf-filter-row {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}

.gf-filter-row:last-child {
    margin-bottom: 0;
}

/* Top Action Buttons */
.gf-btn-trash {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    background: #FFFFFF;
    border: 1px solid #D1D5DB;
    border-radius: 6px;
    color: #9CA3AF;
    font-size: 13.5px;
    font-weight: 500;
    cursor: not-allowed;
    transition: all 0.2s;
}

.gf-btn-trash.active {
    background: #FEE2E2;
    border-color: #EF4444;
    color: #DC2626;
    cursor: pointer;
}

.gf-search-box {
    display: inline-flex;
    align-items: center;
    background: #FFFFFF;
    border: 1px solid #D1D5DB;
    border-radius: 6px;
    padding: 0 12px;
    min-width: 240px;
    flex: 1 1 240px;
}

.gf-search-box i {
    color: #9CA3AF;
    font-size: 14px;
    margin-right: 8px;
}

.gf-search-box input {
    border: none;
    outline: none;
    padding: 8px 0;
    font-size: 13.5px;
    width: 100%;
    color: #111827;
}

.gf-select-control {
    background: #FFFFFF;
    border: 1px solid #D1D5DB;
    border-radius: 6px;
    padding: 8px 14px;
    font-size: 13.5px;
    color: #374151;
    outline: none;
    min-width: 140px;
    cursor: pointer;
}

.gf-tag-filter-input {
    border: 1px solid #D1D5DB;
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 13.5px;
    color: #374151;
    outline: none;
    min-width: 150px;
}

.gf-date-range-box {
    display: inline-flex;
    align-items: center;
    background: #FFFFFF;
    border: 1px solid #D1D5DB;
    border-radius: 6px;
    padding: 0 10px;
    gap: 8px;
}

.gf-date-range-box input {
    border: none;
    outline: none;
    padding: 8px 0;
    font-size: 13px;
    color: #374151;
    width: 120px;
}

.gf-date-range-box i {
    color: #9CA3AF;
    font-size: 14px;
}

/* Modern Data Table matching Screenshot */
.gf-table-wrapper {
    background: #FFFFFF;
    border: 1px solid #E5E7EB;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}

.gf-table {
    width: 100%;
    margin: 0;
    border-collapse: collapse;
}

.gf-table th {
    background: #FAFAFA;
    font-size: 13px;
    font-weight: 700;
    color: #374151;
    padding: 14px 18px;
    border-bottom: 1px solid #E5E7EB;
    text-align: left;
}

.gf-table td {
    padding: 16px 18px;
    font-size: 13.5px;
    border-bottom: 1px solid #F3F4F6;
    vertical-align: middle;
}

.gf-table tbody tr:hover {
    background-color: #FBFBFE;
}

/* Student Profile Column */
.gf-student-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}

.gf-avatar-circle {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #E5E7EB;
    color: #9CA3AF;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.gf-student-details strong {
    display: block;
    color: #111827;
    font-size: 14px;
    font-weight: 700;
    margin-bottom: 2px;
}

.gf-student-details span {
    color: #6B7280;
    font-size: 12.5px;
}

/* Status Badges matching Screenshot */
.gf-status-cell {
    display: flex;
    align-items: center;
    gap: 6px;
}

.gf-badge-verified,
.gf-badge-approved {
    border: 1px solid #86EFAC;
    background: #F0FDF4;
    color: #16A34A;
    font-size: 11.5px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 4px;
    display: inline-block;
}

.gf-badge-unverified {
    border: 1px solid #FECACA;
    background: #FEF2F2;
    color: #DC2626;
    font-size: 11.5px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 4px;
}

.gf-badge-pending {
    border: 1px solid #FDE68A;
    background: #FFFBEB;
    color: #D97706;
    font-size: 11.5px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 4px;
}

/* Tag Box */
.gf-tag-plus-box {
    width: 28px;
    height: 28px;
    border: 1px solid #E5E7EB;
    background: #F9FAFB;
    color: #9CA3AF;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 13px;
    transition: all 0.2s;
}

.gf-tag-plus-box:hover {
    border-color: #D1D5DB;
    background: #F3F4F6;
    color: #374151;
}

.gf-tag-pill {
    background: #F3F4F6;
    color: #374151;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 7px;
    border-radius: 4px;
    margin-right: 4px;
}

/* Activity Column */
.gf-activity-cell span {
    display: block;
}

.gf-activity-date {
    color: #374151;
    font-size: 13px;
    font-weight: 500;
}

.gf-activity-login {
    color: #9CA3AF;
    font-size: 12px;
}

/* Actions Column matching Screenshot */
.gf-actions-cell {
    display: flex;
    align-items: center;
    gap: 14px;
}

.gf-action-btn-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #4B5563;
    font-size: 13px;
    font-weight: 500;
    text-decoration: none !important;
    cursor: pointer;
    transition: color 0.15s;
    background: none;
    border: none;
    padding: 0;
}

.gf-action-btn-link:hover {
    color: var(--color-primary);
}

.gf-action-btn-link i {
    font-size: 13px;
}

.gf-more-dropdown .dropdown-toggle::after {
    display: none;
}

.gf-more-menu {
    border-radius: 8px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.1);
    border: 1px solid #E5E7EB;
    padding: 6px 0;
}

.gf-more-menu li a,
.gf-more-menu li button {
    padding: 8px 16px;
    font-size: 13px;
    color: #374151;
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    text-align: left;
    background: none;
    border: none;
}

.gf-more-menu li a:hover,
.gf-more-menu li button:hover {
    background: #F3F4F6;
    color: var(--color-primary);
}
</style>

<div class="gf-layout-container">

    <!-- LEFT: Tree Sidebar Navigation (ET Red Theme) -->
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
                    <a href="user_dashboard.php?role=student" class="gf-nav-tree-link <?php echo $roleFilter === 'student' ? 'active' : ''; ?>">
                        Students
                    </a>
                </li>
                <li class="gf-nav-tree-item">
                    <a href="user_dashboard.php?role=teacher" class="gf-nav-tree-link <?php echo $roleFilter === 'teacher' ? 'active' : ''; ?>">
                        Teachers
                    </a>
                </li>
                <li class="gf-nav-tree-item">
                    <a href="user_dashboard.php?role=admin" class="gf-nav-tree-link <?php echo $roleFilter === 'admin' ? 'active' : ''; ?>">
                        Admin Users
                    </a>
                </li>
                <li class="gf-nav-tree-item">
                    <a href="user_add.php" class="gf-nav-tree-link" style="color: #FCA5A5;">
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
                    <a href="<?php echo api_get_path(WEB_CODE_PATH); ?>session/session_add.php" class="gf-nav-tree-link" style="color: #FCA5A5;">
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

    <!-- RIGHT: Students / Users Management Content -->
    <main class="gf-main-content">

        <!-- Flash Alerts -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo htmlspecialchars($messageType); ?> alert-dismissible" role="alert" style="border-radius: 8px; font-weight: 500;">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Filter Bar matching user screenshot -->
        <div class="gf-filter-section">
            <form id="gfFilterForm" method="get" action="user_dashboard.php">
                <input type="hidden" name="role" value="<?php echo htmlspecialchars($roleFilter); ?>">

                <!-- Row 1: Delete, Search, All Products, All Students, Filter tags -->
                <div class="gf-filter-row">
                    <button type="button" id="topDeleteBtn" class="gf-btn-trash" onclick="triggerBatchDelete()">
                        <i class="fa fa-trash-o"></i> Delete
                    </button>

                    <div class="gf-search-box">
                        <i class="fa fa-search"></i>
                        <input type="text" name="search" placeholder="Student name or email" value="<?php echo htmlspecialchars($searchKeyword); ?>" onkeypress="if(event.key==='Enter'){ this.form.submit(); }">
                    </div>

                    <select name="product" class="gf-select-control" onchange="this.form.submit()">
                        <option value="">All Products</option>
                        <?php foreach ($allCourses as $c): ?>
                            <option value="<?php echo htmlspecialchars($c['code']); ?>" <?php echo $productFilter === $c['code'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="status" class="gf-select-control" onchange="this.form.submit()">
                        <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Students</option>
                        <option value="verified" <?php echo $statusFilter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                        <option value="unverified" <?php echo $statusFilter === 'unverified' ? 'selected' : ''; ?>>Unverified</option>
                    </select>

                    <input type="text" name="tag" class="gf-tag-filter-input" placeholder="Filter by tags" value="<?php echo htmlspecialchars($tagFilter); ?>" onkeypress="if(event.key==='Enter'){ this.form.submit(); }">
                </div>

                <!-- Row 2: Joining Date range, Active Users -->
                <div class="gf-filter-row">
                    <div class="gf-date-range-box">
                        <input type="text" placeholder="Joining start" name="date_start" value="<?php echo htmlspecialchars($dateStart); ?>" onfocus="(this.type='date')" onblur="if(!this.value)this.type='text'" onchange="this.form.submit()">
                        <span style="color: #9CA3AF;">&rarr;</span>
                        <input type="text" placeholder="Joining end" name="date_end" value="<?php echo htmlspecialchars($dateEnd); ?>" onfocus="(this.type='date')" onblur="if(!this.value)this.type='text'" onchange="this.form.submit()">
                        <i class="fa fa-calendar-o"></i>
                    </div>

                    <select name="user_active" class="gf-select-control" onchange="this.form.submit()">
                        <option value="active" <?php echo $userActiveFilter === 'active' ? 'selected' : ''; ?>>Active Users</option>
                        <option value="inactive" <?php echo $userActiveFilter === 'inactive' ? 'selected' : ''; ?>>Inactive Users</option>
                        <option value="all" <?php echo $userActiveFilter === 'all' ? 'selected' : ''; ?>>All Users</option>
                    </select>

                    <div style="margin-left: auto; display: flex; gap: 8px;">
                        <button type="button" class="btn btn-sm" style="border: 1.5px solid #C7D2FE; color: #4F46E5; background: #EEF2FF; border-radius: 6px; padding: 7px 14px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;" onclick="openBulkImportModal()">
                            <i class="fa fa-upload"></i> Bulk Import <?php echo $roleFilter === 'teacher' ? 'Teachers' : 'Students'; ?>
                        </button>
                        <button type="button" class="btn btn-sm btn-primary" style="border-radius: 6px; padding: 7px 14px; font-weight: 600; background: #4F46E5; border-color: #4F46E5; display: inline-flex; align-items: center; gap: 6px;" onclick="openQuickAddModal()">
                            <i class="fa fa-plus"></i> + Add <?php echo $roleFilter === 'teacher' ? 'Teacher' : 'Student'; ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Table Container matching user screenshot -->
        <div class="gf-table-wrapper">
            <form id="gfTableForm" method="post" action="user_dashboard.php">
                <input type="hidden" name="action" id="tableActionInput" value="">
                
                <table class="gf-table">
                    <thead>
                        <tr>
                            <th style="width: 36px; text-align: center;">
                                <input type="checkbox" id="masterCheckbox" onclick="toggleSelectAll(this)">
                            </th>
                            <th>Student</th>
                            <th>Status</th>
                            <th>Tags</th>
                            <th>Activity</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($userList)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 48px; color: #9CA3AF;">
                                    <i class="fa fa-users" style="font-size: 32px; display: block; margin-bottom: 8px; opacity: 0.4;"></i>
                                    No users found matching the selected filters.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($userList as $u): 
                                $userTags = !empty($u['tags']) ? array_filter(array_map('trim', explode(',', $u['tags']))) : [];
                                $regFormatted = date('M j, Y', strtotime($u['registration_date']));
                            ?>
                                <tr>
                                    <td style="text-align: center;">
                                        <input type="checkbox" name="user_ids[]" value="<?php echo $u['id']; ?>" class="row-checkbox" onchange="updateBatchState()">
                                    </td>
                                    <td>
                                        <div class="gf-student-cell">
                                            <div class="gf-avatar-circle">
                                                <i class="fa fa-user"></i>
                                            </div>
                                            <div class="gf-student-details">
                                                <strong><?php echo htmlspecialchars($u['firstname'].' '.$u['lastname']); ?></strong>
                                                <span><?php echo htmlspecialchars($u['email']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="gf-status-cell">
                                            <?php if ($u['verification_status'] === 'verified'): ?>
                                                <span class="gf-badge-verified">Verified</span>
                                            <?php else: ?>
                                                <span class="gf-badge-unverified">Unverified</span>
                                            <?php endif; ?>

                                            <?php if ($u['approval_status'] === 'approved'): ?>
                                                <span class="gf-badge-approved">Approved</span>
                                            <?php else: ?>
                                                <span class="gf-badge-pending">Pending</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 4px;">
                                            <?php foreach ($userTags as $t): ?>
                                                <span class="gf-tag-pill"><?php echo htmlspecialchars($t); ?></span>
                                            <?php endforeach; ?>
                                            <button type="button" class="gf-tag-plus-box" onclick="openTagModal(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars(addslashes($u['tags'])); ?>')" title="Add tag">
                                                <i class="fa fa-plus"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="gf-activity-cell">
                                            <span class="gf-activity-date"><?php echo $regFormatted; ?></span>
                                            <span class="gf-activity-login">Never logged in</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="gf-actions-cell">
                                            <button type="button" class="gf-action-btn-link" onclick="openUserInfoModal(<?php echo $u['id']; ?>)">
                                                <i class="fa fa-user-o"></i> Info
                                            </button>
                                            <button type="button" class="gf-action-btn-link" onclick="openUserEnrollModal(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars(addslashes($u['firstname'].' '.$u['lastname'])); ?>')">
                                                <i class="fa fa-eye"></i> Enrollments
                                            </button>
                                            <?php if ($u['id'] != $currentUserId): ?>
                                                <button type="button" class="gf-action-btn-link" onclick="openDeleteUserModal(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars(addslashes($u['firstname'].' '.$u['lastname'])); ?>', '<?php echo htmlspecialchars(addslashes($u['email'])); ?>')" title="Delete User" style="color: #DC2626;">
                                                    <i class="fa fa-trash-o"></i> Delete
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($u['id'] != $currentUserId): ?>
                                                <?php $uMagicUrl = MagicLoginManager::getMagicLoginUrl($u['id']); ?>
                                                <button type="button" class="gf-action-btn-link" onclick="openMagicLoginModal(this)" data-user-id="<?php echo $u['id']; ?>" data-user-name="<?php echo htmlspecialchars($u['firstname'].' '.$u['lastname'], ENT_QUOTES); ?>" data-user-email="<?php echo htmlspecialchars($u['email'], ENT_QUOTES); ?>" data-user-username="<?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>" data-magic-url="<?php echo htmlspecialchars($uMagicUrl, ENT_QUOTES); ?>" title="Magic Login Link" style="color: #4F46E5;">
                                                    <i class="fa fa-magic"></i> Magic Login
                                                </button>
                                            <?php endif; ?>
                                            <div class="dropdown gf-more-dropdown" style="display: inline-block;">
                                                <button type="button" class="gf-action-btn-link dropdown-toggle" data-toggle="dropdown">
                                                    <i class="fa fa-ellipsis-v"></i> More
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-right gf-more-menu">
                                                    <li>
                                                        <a href="user_edit.php?user_id=<?php echo $u['id']; ?>">
                                                            <i class="fa fa-pencil"></i> Edit Profile
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a href="javascript:void(0)" onclick="triggerUserAction('toggle_verification', <?php echo $u['id']; ?>, '<?php echo $u['verification_status'] === 'verified' ? 'unverified' : 'verified'; ?>')">
                                                            <i class="fa fa-check-square-o"></i> Toggle Verification
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a href="javascript:void(0)" onclick="triggerUserAction('toggle_approval', <?php echo $u['id']; ?>, '<?php echo $u['approval_status'] === 'approved' ? 'pending' : 'approved'; ?>')">
                                                            <i class="fa fa-shield"></i> Toggle Approval
                                                        </a>
                                                    </li>
                                                    <?php if ($u['id'] != $currentUserId): ?>
                                                        <li>
                                                            <a href="javascript:void(0)" onclick="quickCopyMagicLink('<?php echo htmlspecialchars($uMagicUrl, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($u['firstname'].' '.$u['lastname'], ENT_QUOTES); ?>')" style="color: #4F46E5; font-weight: 600;">
                                                                <i class="fa fa-link" style="color: #4F46E5;"></i> Copy Magic Link
                                                            </a>
                                                        </li>
                                                        <li role="separator" class="divider"></li>
                                                        <li>
                                                            <a href="javascript:void(0)" onclick="openDeleteUserModal(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars(addslashes($u['firstname'].' '.$u['lastname'])); ?>', '<?php echo htmlspecialchars(addslashes($u['email'])); ?>')" style="color: #DC2626; font-weight: 600;">
                                                                <i class="fa fa-trash-o"></i> Delete User
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
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

<!-- MODAL: Enrollments Manager -->
<div class="modal fade" id="modalUserEnrollments" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header" style="background: #4F46E5; color: #FFFFFF; padding: 16px 20px;">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff; opacity:0.8;"><span>&times;</span></button>
                <h4 class="modal-title" id="enrollModalTitle" style="font-weight: 700;"><i class="fa fa-graduation-cap"></i> Manage Enrollments</h4>
            </div>
            <div class="modal-body" style="padding: 20px;">
                <h5 style="font-weight: 700; margin-top: 0; color: #111827;">Active Course Enrollments</h5>
                <div id="enrolledCoursesList" style="margin-bottom: 20px; min-height: 60px;">
                    <p class="text-muted"><i class="fa fa-spinner fa-spin"></i> Loading courses...</p>
                </div>

                <hr style="margin: 16px 0;">

                <h5 style="font-weight: 700; color: #111827;">Enroll in New Course / Program</h5>
                <form method="post" action="user_dashboard.php" id="formSingleEnroll">
                    <input type="hidden" name="action" value="enroll_single">
                    <input type="hidden" name="user_id" id="enrollModalUserId" value="">
                    
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label style="font-size: 13px; font-weight: 600;">Select Course:</label>
                        <select name="course_code" class="form-control" required style="font-size: 13.5px;">
                            <option value="">-- Choose a Course / Masterclass --</option>
                            <?php foreach ($allCourses as $c): ?>
                                <option value="<?php echo htmlspecialchars($c['code']); ?>">
                                    <?php echo htmlspecialchars($c['title'].' ('.$c['code'].')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%; border-radius: 6px; font-weight: 600; background: #4F46E5; border-color: #4F46E5;">
                        <i class="fa fa-plus"></i> + Enroll User
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Student Info -->
<div class="modal fade" id="modalUserInfo" tabindex="-1" role="dialog">
    <div class="modal-dialog" style="max-width: 450px; margin: 30px auto;" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header" style="background: #111827; color: #FFFFFF; padding: 14px 18px;">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff; opacity:0.8;"><span>&times;</span></button>
                <h5 class="modal-title" style="font-weight: 700;"><i class="fa fa-user-circle"></i> Student Info</h5>
            </div>
            <div class="modal-body" id="userInfoBody" style="padding: 18px; font-size: 13px;">
                <p class="text-muted"><i class="fa fa-spinner fa-spin"></i> Loading info...</p>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Tag Editor -->
<div class="modal fade" id="modalTagEditor" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden;">
            <form method="post" action="user_dashboard.php">
                <input type="hidden" name="action" value="update_tags">
                <input type="hidden" name="user_id" id="tagEditorUserId" value="">
                
                <div class="modal-header" style="background: #111827; color: #FFFFFF; padding: 14px 18px;">
                    <button type="button" class="close" data-dismiss="modal" style="color:#fff; opacity:0.8;"><span>&times;</span></button>
                    <h5 class="modal-title" style="font-weight: 700;"><i class="fa fa-tags"></i> Edit Tags</h5>
                </div>
                <div class="modal-body" style="padding: 18px;">
                    <div class="form-group">
                        <label style="font-size: 12px; font-weight: 600;">Tags (comma-separated):</label>
                        <input type="text" name="tags" id="tagEditorInput" class="form-control" placeholder="e.g. VIP, Cohort-2026, FastTrack">
                    </div>
                </div>
                <div class="modal-footer" style="padding: 10px 18px;">
                    <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: Quick Add User -->
<div class="modal fade" id="modalQuickAddUser" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document" style="max-width: 580px;">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden; border: none; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);">
            <form method="post" action="user_dashboard.php">
                <input type="hidden" name="action" value="quick_add_user">
                
                <div class="modal-header" style="background: #4F46E5; color: #FFFFFF; padding: 16px 20px;">
                    <button type="button" class="close" data-dismiss="modal" style="color:#fff; opacity:0.85; font-size: 22px;"><span>&times;</span></button>
                    <h4 class="modal-title" style="font-weight: 700; display: flex; align-items: center; gap: 8px;">
                        <i class="fa fa-user-plus"></i> Add New User
                    </h4>
                </div>
                <div class="modal-body" style="padding: 22px;">
                    <!-- Row 1: Name -->
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label style="font-size: 13px; font-weight: 600;">First Name *</label>
                                <input type="text" name="firstname" id="quickAddFirstname" class="form-control" required placeholder="e.g. Rahul" oninput="suggestQuickUsername()">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label style="font-size: 13px; font-weight: 600;">Last Name *</label>
                                <input type="text" name="lastname" id="quickAddLastname" class="form-control" required placeholder="e.g. Sharma" oninput="suggestQuickUsername()">
                            </div>
                        </div>
                    </div>

                    <!-- Row 2: Email & Phone -->
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label style="font-size: 13px; font-weight: 600;">Email *</label>
                                <input type="email" name="email" id="quickAddEmail" class="form-control" required placeholder="rahul@example.com" oninput="suggestQuickUsername()">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label style="font-size: 13px; font-weight: 600;">Phone</label>
                                <input type="text" name="phone" class="form-control" placeholder="+91 9876543210">
                            </div>
                        </div>
                    </div>

                    <!-- Row 3: Username & Role -->
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label style="font-size: 13px; font-weight: 600;">Username <small class="text-muted">(optional)</small></label>
                                <input type="text" name="username" id="quickAddUsername" class="form-control" placeholder="Auto-generated if blank">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label style="font-size: 13px; font-weight: 600;">Role</label>
                                <select name="role_type" class="form-control">
                                    <option value="student" <?php echo $roleFilter === 'student' ? 'selected' : ''; ?>>Student / Learner</option>
                                    <option value="teacher" <?php echo $roleFilter === 'teacher' ? 'selected' : ''; ?>>Teacher / Instructor</option>
                                    <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Platform Administrator</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Row 4: Password with Auto-Generate Button -->
                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                            <label style="font-size: 13px; font-weight: 600; margin: 0;">Password</label>
                            <a href="javascript:void(0)" onclick="generateQuickPassword()" style="font-size: 12px; font-weight: 700; color: #E3122B; text-decoration: none;">
                                <i class="fa fa-magic"></i> Auto-Generate
                            </a>
                        </div>
                        <div class="input-group">
                            <input type="text" name="password" id="quickAddPassword" class="form-control" placeholder="Click Auto-Generate or enter custom password" style="font-family: 'Courier New', monospace; font-size: 14px; font-weight: 600;">
                            <span class="input-group-btn">
                                <button type="button" class="btn btn-default" onclick="generateQuickPassword()" title="Generate Strong Password" style="border-color: #D1D5DB; background: #F9FAFB; font-weight: 600;">
                                    <i class="fa fa-magic text-danger"></i> Generate
                                </button>
                                <button type="button" class="btn btn-default" onclick="toggleQuickPassword()" title="Show/Hide Password" style="border-color: #D1D5DB; background: #F9FAFB;">
                                    <i id="quickPasswordEye" class="fa fa-eye-slash"></i>
                                </button>
                            </span>
                        </div>
                        <small class="text-muted" style="font-size: 11px; display: block; margin-top: 4px;">
                            Click <strong>Generate</strong> to create an instant password, or leave empty to auto-generate upon saving.
                        </small>
                    </div>

                    <!-- Row 5: Direct Course Enrollment with Checkboxes -->
                    <div class="form-group" style="margin-top: 16px; margin-bottom: 0;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <label style="font-size: 13px; font-weight: 600; margin: 0; color: #1F2937;">
                                <i class="fa fa-graduation-cap text-danger"></i> Direct Course Enrollment:
                            </label>
                            <div>
                                <button type="button" class="btn btn-xs btn-link" onclick="toggleAllQuickCourses(true)" style="padding: 0 4px; font-weight: 600; text-decoration: none; color: #E3122B;">Select All</button>
                                <span style="color: #D1D5DB;">|</span>
                                <button type="button" class="btn btn-xs btn-link" onclick="toggleAllQuickCourses(false)" style="padding: 0 4px; font-weight: 600; text-decoration: none; color: #6B7280;">Clear</button>
                            </div>
                        </div>
                        
                        <div style="max-height: 190px; overflow-y: auto; border: 1px solid #D1D5DB; border-radius: 8px; padding: 6px; background: #F8FAFC;">
                            <?php if (!empty($allCourses)): ?>
                                <?php foreach ($allCourses as $c): ?>
                                    <label style="display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; margin: 4px 0; background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 6px; cursor: pointer; transition: all 0.15s ease; user-select: none;">
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <input type="checkbox" name="assign_courses[]" value="<?php echo htmlspecialchars($c['code']); ?>" class="gf-quick-course-check" style="width: 17px; height: 17px; accent-color: #E3122B; cursor: pointer; margin: 0;">
                                            <span style="font-weight: 600; font-size: 13px; color: #1E293B;"><?php echo htmlspecialchars($c['title']); ?></span>
                                        </div>
                                        <span class="badge" style="background: #F1F5F9; color: #475569; font-weight: 600; font-size: 11px; border: 1px solid #E2E8F0;"><?php echo htmlspecialchars($c['code']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="padding: 10px; color: #64748B; font-size: 12px; text-align: center;">No courses available.</div>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted" style="font-size: 11px; display: block; margin-top: 4px;">
                            <i class="fa fa-check-square-o"></i> Check the courses you want to enroll the student into right now.
                        </small>
                    </div>
                </div>
                <div class="modal-footer" style="background: #F9FAFB; border-top: 1px solid #E5E7EB; padding: 14px 20px; display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn btn-default" data-dismiss="modal" style="font-weight: 600; padding: 8px 18px; border-radius: 6px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="font-weight: 700; background: #4F46E5; border-color: #4F46E5; padding: 8px 22px; border-radius: 6px; box-shadow: 0 2px 6px rgba(79,70,229,0.3);">
                        <i class="fa fa-user-plus"></i> Create User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: Bulk Import Students -->
<div class="modal fade" id="modalBulkImportStudents" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">
            <form method="post" action="user_dashboard.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="bulk_import_students">
                
                <div class="modal-header" style="background: #4F46E5; color: #FFFFFF; padding: 18px 22px;">
                    <button type="button" class="close" data-dismiss="modal" style="color:#fff; opacity:0.9;"><span>&times;</span></button>
                    <h4 class="modal-title" style="font-weight: 700; font-size: 17px; display: flex; align-items: center; gap: 8px;">
                        <i class="fa fa-users"></i> Bulk Import <?php echo $roleFilter === 'teacher' ? 'Teachers' : 'Students'; ?>
                    </h4>
                </div>
                <div class="modal-body" style="padding: 22px;">
                    <!-- Template Download Callout -->
                    <div style="background: #EEF2FF; border: 1px solid #C7D2FE; border-radius: 8px; padding: 14px 16px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-weight: 700; color: #3730A3; font-size: 13px;">CSV Template Required</div>
                            <div style="font-size: 12px; color: #4338CA; margin-top: 2px;">Ensure your file follows the standard student column headers.</div>
                        </div>
                        <a href="user_dashboard.php?action=download_sample_csv" class="btn btn-xs" style="background: #4F46E5; color: #FFF; font-weight: 600; border-radius: 6px; padding: 6px 12px; display: inline-flex; align-items: center; gap: 5px; text-decoration: none;">
                            <i class="fa fa-download"></i> Download Sample CSV
                        </a>
                    </div>

                    <!-- File Upload Input -->
                    <div class="form-group" style="margin-bottom: 18px;">
                        <label style="font-size: 13px; font-weight: 600; color: #374151; display: block; margin-bottom: 6px;">
                            Upload CSV File <span class="text-danger">*</span>
                        </label>
                        <div style="border: 2px dashed #D1D5DB; border-radius: 8px; padding: 18px; text-align: center; background: #F9FAFB;">
                            <i class="fa fa-file-excel-o" style="font-size: 32px; color: #4F46E5; margin-bottom: 8px; display: block;"></i>
                            <input type="file" name="import_file" id="import_file" accept=".csv, text/csv, .txt" required style="display: inline-block; font-size: 13px;">
                            <p style="margin-top: 8px; font-size: 11px; color: #6B7280;">Supports comma (,) or semicolon (;) delimited .csv files</p>
                        </div>
                    </div>

                    <!-- Column Structure Help -->
                    <div class="form-group" style="margin-bottom: 18px;">
                        <label style="font-size: 12px; font-weight: 600; color: #4B5563; margin-bottom: 4px;">Expected Columns:</label>
                        <code style="display: block; background: #F3F4F6; color: #1F2937; padding: 8px 12px; border-radius: 6px; font-size: 11px; word-break: break-all;">
                            firstname, lastname, email, phone, username, password, course_codes
                        </code>
                        <small style="display: block; color: #6B7280; font-size: 11px; margin-top: 4px;">
                            <i class="fa fa-info-circle"></i> Username and password will be auto-generated if left blank. Existing accounts with matching email will be automatically skipped.
                        </small>
                    </div>

                    <!-- Batch Course Enrollment -->
                    <div class="form-group" style="margin-bottom: 18px;">
                        <label style="font-size: 13px; font-weight: 600; color: #374151;">Directly Enroll All Into Course(s) (Optional):</label>
                        <select name="assign_courses[]" class="form-control" multiple size="4" style="font-size: 13px; border-radius: 6px;">
                            <?php foreach ($allCourses as $c): ?>
                                <option value="<?php echo htmlspecialchars($c['code']); ?>">
                                    <?php echo htmlspecialchars($c['title'].' ('.$c['code'].')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #6B7280; font-size: 11px;">Hold Ctrl / Cmd to select multiple courses.</small>
                    </div>

                    <!-- Verified Status Checkbox -->
                    <div class="checkbox" style="margin: 0;">
                        <label style="font-size: 13px; font-weight: 600; color: #1F2937; display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" name="mark_verified" value="1" checked style="margin: 0; width: 16px; height: 16px;">
                            Mark all imported students as Verified & Approved
                        </label>
                    </div>
                </div>
                <div class="modal-footer" style="background: #F9FAFB; padding: 14px 22px; border-top: 1px solid #E5E7EB;">
                    <button type="button" class="btn btn-default" data-dismiss="modal" style="font-weight: 600; border-radius: 6px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="font-weight: 600; background: #4F46E5; border-color: #4F46E5; border-radius: 6px; padding: 7px 18px;">
                        <i class="fa fa-upload"></i> Start Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Hidden Single Action Form for Verification & Approval -->
<form id="gfSingleActionForm" method="post" action="user_dashboard.php" style="display:none;">
    <input type="hidden" name="action" id="singleFormAction" value="">
    <input type="hidden" name="user_id" id="singleFormUserId" value="">
    <input type="hidden" name="new_status" id="singleFormNewStatus" value="">
</form>

<!-- MODAL: Delete Student / User Confirmation -->
<div class="modal fade" id="modalDeleteUser" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document" style="max-width: 480px;">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden; border: none; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);">
            <form method="post" action="user_dashboard.php" id="deleteUserForm">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="user_id" id="deleteUserModalId" value="">
                
                <div class="modal-header" style="background: #FEF2F2; border-bottom: 1px solid #FEE2E2; padding: 18px 24px;">
                    <button type="button" class="close" data-dismiss="modal" style="font-size: 24px; color: #991B1B;"><span>&times;</span></button>
                    <h4 class="modal-title" style="color: #991B1B; font-weight: 700; display: flex; align-items: center; gap: 10px; margin: 0; font-size: 17px;">
                        <i class="fa fa-exclamation-triangle" style="color: #DC2626; font-size: 20px;"></i> Delete Student / User
                    </h4>
                </div>
                <div class="modal-body" style="padding: 24px;">
                    <p style="font-size: 14px; color: #374151; margin-bottom: 16px;">
                        Are you sure you want to permanently delete this user?
                    </p>
                    <div style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px; padding: 14px 16px; margin-bottom: 16px;">
                        <div style="font-weight: 700; color: #111827; font-size: 15px;" id="deleteUserModalName"></div>
                        <div style="font-size: 12.5px; color: #6B7280; margin-top: 2px;" id="deleteUserModalEmail"></div>
                    </div>
                    <div class="alert alert-danger" style="font-size: 12.5px; margin: 0; border-radius: 6px; padding: 10px 14px;">
                        <i class="fa fa-warning"></i> <strong>Warning:</strong> All enrolled courses, quiz grades, tracking progress, and certificates associated with this user will be erased.
                    </div>
                </div>
                <div class="modal-footer" style="background: #F9FAFB; border-top: 1px solid #E5E7EB; padding: 14px 24px; display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn btn-default" data-dismiss="modal" style="font-weight: 600; padding: 8px 18px; border-radius: 6px;">Cancel</button>
                    <button type="submit" class="btn btn-danger" style="background: #DC2626; border-color: #DC2626; font-weight: 700; padding: 8px 20px; border-radius: 6px; box-shadow: 0 2px 6px rgba(220,38,38,0.3);">
                        <i class="fa fa-trash"></i> Yes, Delete User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- MODAL: Magic Login Link Generator -->
<div class="modal fade" id="modalMagicLogin" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document" style="max-width: 540px;">
        <div class="modal-content" style="border-radius: 14px; overflow: hidden; border: none; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
            <div class="modal-header" style="background: linear-gradient(135deg, #4F46E5 0%, #3730A3 100%); padding: 18px 24px; color: #ffffff;">
                <button type="button" class="close" data-dismiss="modal" style="font-size: 24px; color: #ffffff; opacity: 0.85; text-shadow: none;"><span>&times;</span></button>
                <h4 class="modal-title" style="color: #ffffff; font-weight: 700; display: flex; align-items: center; gap: 10px; margin: 0; font-size: 17px;">
                    <i class="fa fa-magic" style="font-size: 19px;"></i> Magic Login Link
                </h4>
            </div>
            <div class="modal-body" style="padding: 24px;">
                <!-- User Details Card -->
                <div style="display: flex; align-items: center; gap: 14px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 12px 16px; margin-bottom: 20px;">
                    <div style="width: 44px; height: 44px; border-radius: 50%; background: #EEF2FF; color: #4F46E5; display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 700; flex-shrink: 0;">
                        <i class="fa fa-user"></i>
                    </div>
                    <div style="flex-grow: 1; min-width: 0;">
                        <div style="font-weight: 700; color: #1E293B; font-size: 15px;" id="magicModalUserName">Student Name</div>
                        <div style="font-size: 12.5px; color: #64748B; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;" id="magicModalUserEmail">user@example.com</div>
                    </div>
                    <span class="label label-success" style="background: #10B981; font-weight: 600; padding: 5px 9px; font-size: 11px; border-radius: 6px;">Passwordless</span>
                </div>

                <p style="font-size: 13.5px; color: #475569; line-height: 1.5; margin-bottom: 14px;">
                    This link allows this specific user to authenticate directly without typing username or password credentials. Share it directly with the student:
                </p>

                <!-- Link Input Group -->
                <div class="form-group" style="margin-bottom: 12px;">
                    <label style="font-size: 12px; font-weight: 600; color: #334155; text-transform: uppercase; letter-spacing: 0.5px;">Magic Login URL</label>
                    <div class="input-group">
                        <input type="text" id="magicLoginUrlInput" class="form-control" readonly style="font-size: 12.5px; font-family: monospace; background: #FFFFFF; color: #0F172A; border-color: #CBD5E1; cursor: pointer; height: 40px;" onclick="this.select();">
                        <span class="input-group-btn">
                            <button class="btn btn-primary" type="button" id="copyMagicBtn" onclick="copyMagicLink()" style="background: #4F46E5; border-color: #4F46E5; font-weight: 600; height: 40px; padding: 0 18px;">
                                <i class="fa fa-copy"></i> <span id="copyMagicBtnText">Copy Link</span>
                            </button>
                        </span>
                    </div>
                </div>

                <!-- Helper Notes & Status -->
                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 12px; color: #64748B; margin-top: 10px;">
                    <span><i class="fa fa-shield" style="color: #10B981;"></i> Secure 64-bit token • Valid for 30 days</span>
                    <button type="button" class="btn btn-link btn-xs" onclick="regenerateMagicLink()" style="color: #4F46E5; text-decoration: none; font-weight: 600; padding: 0;">
                        <i class="fa fa-refresh"></i> Generate New Link
                    </button>
                </div>

                <div id="magicCopyFeedback" style="display: none; margin-top: 16px;" class="alert alert-success">
                    <i class="fa fa-check-circle"></i> Magic link copied to clipboard!
                </div>
            </div>
            <div class="modal-footer" style="background: #F8FAFC; border-top: 1px solid #E2E8F0; padding: 14px 24px; display: flex; justify-content: space-between; align-items: center;">
                <a href="#" id="magicOpenTabBtn" target="_blank" class="btn btn-default" style="font-size: 12.5px; font-weight: 600; color: #475569; border-color: #CBD5E1;">
                    <i class="fa fa-external-link"></i> Test Login in New Tab
                </a>
                <button type="button" class="btn btn-default" data-dismiss="modal" style="font-weight: 600; padding: 8px 18px; border-radius: 6px;">Close</button>
            </div>
        </div>
    </div>
</div>

<script>

var currentMagicUserId = 0;

function openMagicLoginModal(el, userName, userEmail, username) {
    var userId, magicUrl;
    if (typeof el === 'object' && el !== null && el.getAttribute) {
        userId = el.getAttribute('data-user-id');
        userName = el.getAttribute('data-user-name');
        userEmail = el.getAttribute('data-user-email');
        username = el.getAttribute('data-user-username');
        magicUrl = el.getAttribute('data-magic-url');
    } else {
        userId = el;
    }

    currentMagicUserId = userId;
    var nameEl = document.getElementById('magicModalUserName');
    var emailEl = document.getElementById('magicModalUserEmail');
    if (nameEl) nameEl.innerText = userName || ('User #' + userId);
    if (emailEl) emailEl.innerText = (userEmail ? userEmail : '') + (username ? ' (' + username + ')' : '');

    var urlInput = document.getElementById('magicLoginUrlInput');
    var copyBtn = document.getElementById('copyMagicBtn');
    var copyBtnText = document.getElementById('copyMagicBtnText');
    var openTabBtn = document.getElementById('magicOpenTabBtn');
    var feedback = document.getElementById('magicCopyFeedback');

    if (magicUrl) {
        urlInput.value = magicUrl;
        copyBtnText.innerText = 'Copy Link';
        copyBtn.disabled = false;
        openTabBtn.setAttribute('href', magicUrl);
        openTabBtn.style.pointerEvents = 'auto';
        feedback.style.display = 'none';
        $('#modalMagicLogin').modal('show');
    } else {
        urlInput.value = 'Generating magic link...';
        copyBtnText.innerText = 'Copy Link';
        copyBtn.disabled = true;
        openTabBtn.setAttribute('href', '#');
        openTabBtn.style.pointerEvents = 'none';
        feedback.style.display = 'none';
        $('#modalMagicLogin').modal('show');

        fetch('user_dashboard.php?action=get_magic_link&user_id=' + userId)
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success && data.url) {
                    urlInput.value = data.url;
                    copyBtn.disabled = false;
                    openTabBtn.setAttribute('href', data.url);
                    openTabBtn.style.pointerEvents = 'auto';
                } else {
                    urlInput.value = 'Error generating link: ' + (data.error || 'Unknown error');
                }
            })
            .catch(function(err) {
                urlInput.value = 'Failed to generate magic link.';
            });
    }
}

function quickCopyMagicLink(url, name) {
    if (!url) return;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(function() {
            alert('Magic Login Link for ' + (name || 'user') + ' copied to clipboard!\n\n' + url);
        }).catch(function() {
            prompt('Copy Magic Login Link below:', url);
        });
    } else {
        prompt('Copy Magic Login Link below:', url);
    }
}

function copyMagicLink() {
    var urlInput = document.getElementById('magicLoginUrlInput');
    var url = urlInput.value;
    if (!url || url.indexOf('http') !== 0) return;
    
    var copyBtnText = document.getElementById('copyMagicBtnText');
    var feedback = document.getElementById('magicCopyFeedback');
    
    var done = function() {
        copyBtnText.innerText = 'Copied!';
        feedback.className = 'alert alert-success';
        feedback.innerHTML = '<i class="fa fa-check-circle"></i> Magic link copied to clipboard!';
        feedback.style.display = 'block';
        setTimeout(function() {
            copyBtnText.innerText = 'Copy Link';
        }, 2500);
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(done).catch(function() {
            urlInput.select();
            document.execCommand('copy');
            done();
        });
    } else {
        urlInput.select();
        document.execCommand('copy');
        done();
    }
}

function regenerateMagicLink() {
    if (!currentMagicUserId) return;
    var urlInput = document.getElementById('magicLoginUrlInput');
    var copyBtn = document.getElementById('copyMagicBtn');
    var copyBtnText = document.getElementById('copyMagicBtnText');
    var openTabBtn = document.getElementById('magicOpenTabBtn');
    var feedback = document.getElementById('magicCopyFeedback');
    
    urlInput.value = 'Regenerating fresh token...';
    copyBtn.disabled = true;
    copyBtnText.innerText = 'Copy Link';
    feedback.style.display = 'none';
    openTabBtn.style.pointerEvents = 'none';
    
    fetch('user_dashboard.php?action=get_magic_link&user_id=' + currentMagicUserId + '&force_new=1')
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success && data.url) {
                urlInput.value = data.url;
                copyBtn.disabled = false;
                openTabBtn.setAttribute('href', data.url);
                openTabBtn.style.pointerEvents = 'auto';
                feedback.className = 'alert alert-info';
                feedback.innerHTML = '<i class="fa fa-refresh"></i> New magic link generated! Prior links for this user were invalidated.';
                feedback.style.display = 'block';
            } else {
                urlInput.value = 'Error generating link.';
            }
        });
}


function triggerUserAction(action, userId, newStatus) {
    document.getElementById("singleFormAction").value = action;
    document.getElementById("singleFormUserId").value = userId;
    document.getElementById("singleFormNewStatus").value = newStatus;
    document.getElementById("gfSingleActionForm").submit();
}

function openDeleteUserModal(userId, userName, userEmail) {
    document.getElementById("deleteUserModalId").value = userId;
    document.getElementById("deleteUserModalName").innerText = userName;
    document.getElementById("deleteUserModalEmail").innerText = userEmail || ("ID: #" + userId);
    $("#modalDeleteUser").modal("show");
}

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
    var btn = document.getElementById('topDeleteBtn');
    if (checked.length > 0) {
        btn.classList.add('active');
    } else {
        btn.classList.remove('active');
    }
}

function triggerBatchDelete() {
    var checked = document.querySelectorAll('.row-checkbox:checked');
    if (checked.length === 0) {
        alert('Please select at least one user to delete.');
        return;
    }
    if (confirm('Permanently delete ' + checked.length + ' selected user(s)?')) {
        document.getElementById('tableActionInput').value = 'batch_delete';
        document.getElementById('gfTableForm').submit();
    }
}

function openTagModal(userId, currentTags) {
    document.getElementById('tagEditorUserId').value = userId;
    document.getElementById('tagEditorInput').value = currentTags || '';
    $('#modalTagEditor').modal('show');
}

function openQuickAddModal() {
    $('#modalQuickAddUser').modal('show');
}

function openBulkImportModal() {
    $('#modalBulkImportStudents').modal('show');
}


// Quick Add User helpers
function generateQuickPassword() {
    var uppers = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    var lowers = 'abcdefghijkmnpqrstuvwxyz';
    var nums = '23456789';
    var specs = '!@#$%&*';
    var pass = '';
    pass += uppers.charAt(Math.floor(Math.random() * uppers.length));
    pass += lowers.charAt(Math.floor(Math.random() * lowers.length));
    pass += nums.charAt(Math.floor(Math.random() * nums.length));
    pass += specs.charAt(Math.floor(Math.random() * specs.length));
    var allChars = uppers + lowers + nums + specs;
    for (var i = 0; i < 6; i++) {
        pass += allChars.charAt(Math.floor(Math.random() * allChars.length));
    }
    var input = document.getElementById('quickAddPassword');
    if (input) {
        input.value = pass;
        input.type = 'text';
        var eye = document.getElementById('quickPasswordEye');
        if (eye) eye.className = 'fa fa-eye-slash';
    }
}

function toggleQuickPassword() {
    var input = document.getElementById('quickAddPassword');
    var eye = document.getElementById('quickPasswordEye');
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        if (eye) eye.className = 'fa fa-eye-slash';
    } else {
        input.type = 'password';
        if (eye) eye.className = 'fa fa-eye';
    }
}

function toggleAllQuickCourses(check) {
    var checks = document.querySelectorAll('.gf-quick-course-check');
    checks.forEach(function(c) {
        c.checked = check;
    });
}

function suggestQuickUsername() {
    var unInput = document.getElementById('quickAddUsername');
    if (!unInput || (unInput.dataset.manualEdited === 'true' && unInput.value.trim() !== '')) {
        return;
    }
    var fn = document.getElementById('quickAddFirstname').value.trim().toLowerCase();
    var ln = document.getElementById('quickAddLastname').value.trim().toLowerCase();
    var em = document.getElementById('quickAddEmail').value.trim().toLowerCase();
    if (fn && ln) {
        unInput.value = (fn.charAt(0) + ln).replace(/[^a-z0-9_]/g, '');
    } else if (em && em.indexOf('@') !== -1) {
        unInput.value = em.split('@')[0].replace(/[^a-z0-9_]/g, '');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var unInput = document.getElementById('quickAddUsername');
    if (unInput) {
        unInput.addEventListener('input', function() {
            unInput.dataset.manualEdited = 'true';
        });
    }
});

function copyLoginCredentials(username, password, courses) {
    var loginUrl = '<?php echo api_get_path(WEB_PATH); ?>';
    var text = "Welcome to ETupskill!\n\n" +
               "Login URL: " + loginUrl + "\n" +
               "Username: " + username + "\n" +
               "Password: " + password + "\n" +
               (courses ? ("Enrolled Courses: " + courses + "\n") : "");
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function() {
            alert("Login credentials copied to clipboard!\n\n" + text);
        }).catch(function() {
            prompt("Copy credentials below:", text);
        });
    } else {
        prompt("Copy credentials below:", text);
    }
}

var _infoModalRawPw = '';
var _infoModalPwHidden = false;

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function openUserInfoModal(userId) {
    var body = document.getElementById('userInfoBody');
    body.innerHTML = '<p class="text-muted"><i class="fa fa-spinner fa-spin"></i> Loading student details...</p>';
    $('#modalUserInfo').modal('show');

    fetch('user_dashboard.php?action=ajax_get_user&user_id=' + userId)
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.error) {
                body.innerHTML = '<div class="alert alert-danger">' + data.error + '</div>';
                return;
            }

            _infoModalRawPw = data.password || '';
            _infoModalPwHidden = false;

            var passwordHtml = '';
            if (data.password && data.password.trim() !== '') {
                passwordHtml = 
                    '<div style="margin: 10px 0; padding: 10px 12px; background: #FEF2F2; border: 1px solid #FEE2E2; border-radius: 8px;">' +
                        '<div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">' +
                            '<div>' +
                                '<strong style="color: #991B1B; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 2px;">Password:</strong>' +
                                '<span id="infoModalPwDisplay" style="font-family: monospace; font-size: 14px; font-weight: 700; color: #DC2626; letter-spacing: 0.5px;">' + escapeHtml(data.password) + '</span>' +
                            '</div>' +
                            '<div style="display: flex; gap: 4px;">' +
                                '<button type="button" class="btn btn-xs btn-default" onclick="toggleInfoModalPw()" id="btnToggleInfoPw" title="Show / Hide" style="background: #fff; border-color: #FECACA; color: #991B1B;"><i class="fa fa-eye-slash" id="infoPwEyeIcon"></i></button>' +
                                '<button type="button" class="btn btn-xs btn-default" onclick="copyInfoModalPw()" title="Copy Password" style="background: #fff; border-color: #FECACA; color: #991B1B;"><i class="fa fa-clipboard"></i> Copy</button>' +
                                '<button type="button" class="btn btn-xs btn-default" onclick="toggleResetPwBox()" title="Change Password" style="background: #fff; border-color: #FECACA; color: #991B1B;"><i class="fa fa-pencil"></i></button>' +
                            '</div>' +
                        '</div>' +
                    '</div>';
            } else {
                passwordHtml = 
                    '<div style="margin: 10px 0; padding: 10px 12px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px;">' +
                        '<div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">' +
                            '<div>' +
                                '<strong style="color: #475569; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 2px;">Password:</strong>' +
                                '<span style="color: #64748B; font-size: 12.5px; font-style: italic;">Encrypted / Not recorded</span>' +
                            '</div>' +
                            '<button type="button" class="btn btn-xs btn-primary" onclick="toggleResetPwBox()" style="background: #E3122B; border-color: #E3122B; font-weight: 600; font-size: 11.5px; padding: 3px 10px;">' +
                                '<i class="fa fa-key"></i> Set Password' +
                            '</button>' +
                        '</div>' +
                    '</div>';
            }

            var resetPwBoxHtml = 
                '<div id="infoModalResetBox" style="display: none; margin: 8px 0 12px 0; padding: 12px; background: #F1F5F9; border: 1px solid #CBD5E1; border-radius: 8px;">' +
                    '<label style="font-size: 11px; font-weight: 700; color: #334155; display: block; margin-bottom: 5px;">Set New Password:</label>' +
                    '<div style="display: flex; gap: 6px;">' +
                        '<input type="text" id="infoNewPwInput" class="form-control input-sm" placeholder="Enter new password..." style="font-family: monospace; font-size: 13px;">' +
                        '<button type="button" class="btn btn-sm btn-default" onclick="generateInfoModalPw()" title="Generate Random Password" style="background: #fff;"><i class="fa fa-random"></i></button>' +
                        '<button type="button" class="btn btn-sm btn-success" onclick="saveInfoModalPw(' + data.id + ')" style="background: #10B981; border-color: #059669; font-weight: 600;"><i class="fa fa-check"></i> Save</button>' +
                    '</div>' +
                    '<small style="color: #64748B; font-size: 11px; display: block; margin-top: 4px;">Immediately updates student\'s login password and saves it for admin visibility.</small>' +
                '</div>';

            body.innerHTML = 
                '<div style="text-align: center; margin-bottom: 15px;">' +
                    '<div style="width: 50px; height: 50px; border-radius: 50%; background: #E5E7EB; color: #9CA3AF; display: inline-flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 8px;"><i class="fa fa-user"></i></div>' +
                    '<h4 style="margin: 0; font-weight: 700;">' + escapeHtml(data.name) + '</h4>' +
                    '<span style="color: #6B7280; font-size: 12px;">' + escapeHtml(data.email) + '</span>' +
                '</div>' +
                '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 10px; font-size: 12.5px;">' +
                    '<div><strong style="color: #475569;">Username:</strong> <br><code style="background: #F1F5F9; color: #0F172A; font-weight: 700; font-size: 12.5px; padding: 2px 6px; border-radius: 4px;">' + escapeHtml(data.username) + '</code></div>' +
                    '<div><strong style="color: #475569;">Phone:</strong> <br>' + escapeHtml(data.phone || 'N/A') + '</div>' +
                '</div>' +
                passwordHtml +
                resetPwBoxHtml +
                '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 10px; font-size: 12.5px;">' +
                    '<div><strong style="color: #475569;">Registered:</strong> <br>' + escapeHtml(data.registered) + '</div>' +
                    '<div><strong style="color: #475569;">Status:</strong> <br>' + (data.active == 1 ? '<span class="text-success" style="font-weight: 600;"><i class="fa fa-circle" style="font-size: 9px;"></i> Active</span>' : '<span class="text-danger" style="font-weight: 600;"><i class="fa fa-circle" style="font-size: 9px;"></i> Inactive</span>') + '</div>' +
                    '<div><strong style="color: #475569;">Verification:</strong> <br>' + escapeHtml(data.verification) + '</div>' +
                    '<div><strong style="color: #475569;">Enrolled Programs:</strong> <br>' + (data.courses ? data.courses.length : 0) + '</div>' +
                '</div>' +
                '<div style="margin-top: 18px; padding-top: 14px; border-top: 1px solid #E5E7EB; text-align: center;">' +
                    '<button type="button" class="btn btn-sm btn-default" onclick="openMagicLoginModal(' + data.id + ', \'' + (data.name || '').replace(/'/g, "\\'") + '\', \'' + (data.email || '').replace(/'/g, "\\'") + '\', \'' + (data.username || '').replace(/'/g, "\\'") + '\')" style="font-weight: 600; color: #4F46E5; border-color: #C7D2FE; background: #EEF2FF;">' +
                        '<i class="fa fa-magic"></i> Get Magic Login Link' +
                    '</button>' +
                '</div>';
        });
}

function toggleInfoModalPw() {
    var span = document.getElementById('infoModalPwDisplay');
    var icon = document.getElementById('infoPwEyeIcon');
    if (!span) return;
    _infoModalPwHidden = !_infoModalPwHidden;
    if (_infoModalPwHidden) {
        span.innerText = '••••••••';
        if (icon) icon.className = 'fa fa-eye';
    } else {
        span.innerText = _infoModalRawPw;
        if (icon) icon.className = 'fa fa-eye-slash';
    }
}

function copyInfoModalPw(pw) {
    var val = pw || _infoModalRawPw;
    if (!val) return;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(val).then(function() {
            alert('Password copied to clipboard:\n\n' + val);
        }).catch(function() {
            prompt('Copy password below:', val);
        });
    } else {
        prompt('Copy password below:', val);
    }
}

function toggleResetPwBox() {
    var box = document.getElementById('infoModalResetBox');
    if (box) {
        var isHidden = (box.style.display === 'none' || box.style.display === '');
        box.style.display = isHidden ? 'block' : 'none';
        if (isHidden) {
            var inp = document.getElementById('infoNewPwInput');
            if (inp) inp.focus();
        }
    }
}

function generateInfoModalPw() {
    var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789@#$';
    var pass = '';
    for (var i = 0; i < 10; i++) {
        pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    var inp = document.getElementById('infoNewPwInput');
    if (inp) inp.value = pass;
}

function saveInfoModalPw(userId) {
    var inp = document.getElementById('infoNewPwInput');
    var newPw = inp ? inp.value.trim() : '';
    if (!newPw) {
        alert('Please enter a new password or click the random generator button.');
        return;
    }
    var formData = new FormData();
    formData.append('action', 'ajax_set_password');
    formData.append('user_id', userId);
    formData.append('new_password', newPw);

    fetch('user_dashboard.php', {
        method: 'POST',
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) {
            alert('Password updated and saved successfully!\n\nNew Password: ' + res.password);
            openUserInfoModal(userId);
        } else {
            alert('Error updating password: ' + (res.error || 'Unknown error'));
        }
    })
    .catch(function(err) {
        alert('Request failed: ' + err);
    });
}

function openUserEnrollModal(userId, userName) {
    document.getElementById('enrollModalUserId').value = userId;
    document.getElementById('enrollModalTitle').innerHTML = '<i class="fa fa-graduation-cap"></i> Enrollments: ' + userName;
    var list = document.getElementById('enrolledCoursesList');
    list.innerHTML = '<p class="text-muted"><i class="fa fa-spinner fa-spin"></i> Fetching active enrollments...</p>';
    $('#modalUserEnrollments').modal('show');

    fetch('user_dashboard.php?action=ajax_get_user&user_id=' + userId)
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (!data.courses || data.courses.length === 0) {
                list.innerHTML = '<div class="alert alert-info" style="font-size: 13px; margin: 0;">This student is not enrolled in any courses yet.</div>';
                return;
            }
            var html = '<ul class="list-group" style="margin: 0;">';
            data.courses.forEach(function(c) {
                html += '<li class="list-group-item" style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px;">' +
                    '<div><strong>' + c.title + '</strong> <br><small class="text-muted">' + c.code + '</small></div>' +
                    '<form method="post" action="user_dashboard.php" style="margin: 0;" onsubmit="return confirm(\'Unenroll student from ' + c.title + '?\');">' +
                        '<input type="hidden" name="action" value="unenroll_single">' +
                        '<input type="hidden" name="user_id" value="' + userId + '">' +
                        '<input type="hidden" name="course_code" value="' + c.code + '">' +
                        '<button type="submit" class="btn btn-xs btn-outline-danger" style="border: 1px solid #EF4444; color: #DC2626; border-radius: 4px; padding: 4px 8px;">' +
                            '<i class="fa fa-times"></i> Remove' +
                        '</button>' +
                    '</form>' +
                '</li>';
            });
            html += '</ul>';
            list.innerHTML = html;
        });
}
</script>

<?php
Display::display_footer();
