<?php
/* For licensing terms, see /license.txt */

/**
 * View and Print Certificate Blueprint for Student.
 * Logs student collection in c_certificate_awarded.
 */
require_once __DIR__ . '/../inc/global.inc.php';
require_once __DIR__ . '/../inc/lib/certificate_blueprint.lib.php';

api_block_anonymous_users();

$courseCode = isset($_GET['course_code']) ? Security::remove_XSS($_GET['course_code']) : '';
$userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : api_get_user_id();
$action = isset($_GET['action']) ? Security::remove_XSS($_GET['action']) : 'view';
$isEmbed = isset($_GET['embed']) && $_GET['embed'] == '1';

// Security check: only allow viewing own certificate unless admin or course teacher
if ($userId !== api_get_user_id() && !api_is_platform_admin() && !api_is_course_admin()) {
    api_not_allowed(true);
}

if (empty($courseCode)) {
    api_not_allowed(true);
}

$certificateHtml = CertificateBlueprint::renderForStudent($courseCode, $userId);
if (empty($certificateHtml)) {
    $certificateHtml = '<div style="padding: 60px 40px; text-align: center; background: #FFFFFF; border-radius: 12px; max-width: 600px; margin: 40px auto; box-shadow: 0 10px 25px rgba(0,0,0,0.1); font-family: sans-serif;">
        <i class="fa fa-certificate" style="font-size: 52px; color: #F59E0B; display: block; margin-bottom: 16px;"></i>
        <h3 style="color: #0F172A; font-weight: 700; margin-bottom: 8px; font-size: 20px;">No Certificate Blueprint Linked</h3>
        <p style="color: #64748B; font-size: 14px; line-height: 1.5; margin-bottom: 20px;">This course does not have a certificate template attached yet. You can attach a template in the Curriculum Settings tab.</p>
    </div>';
}

$userInfo = api_get_user_info($userId);
$studentName = api_get_person_name($userInfo['firstname'], $userInfo['lastname']);
$userEmail = !empty($userInfo['email']) ? $userInfo['email'] : '';
$courseInfo = api_get_course_info($courseCode);
$courseTitle = !empty($courseInfo['title']) ? $courseInfo['title'] : $courseCode;
$certCode = 'ET-CERT-' . $userId . '-' . strtoupper(substr(md5($courseCode . $userId), 0, 6));

// Get blueprint details
$blueprint = CertificateBlueprint::getBlueprintForCourse($courseCode);
$blueprintId = !empty($blueprint['id']) ? (int)$blueprint['id'] : 0;
$blueprintName = !empty($blueprint['name']) ? $blueprint['name'] : 'Executive Certificate Blueprint';

// Log or update collection record in c_certificate_awarded
$safeName = Database::escape_string($studentName);
$safeEmail = Database::escape_string($userEmail);
$safeCode = Database::escape_string($courseCode);
$safeTitle = Database::escape_string($courseTitle);
$safeBpName = Database::escape_string($blueprintName);
$safeCertCode = Database::escape_string($certCode);

$logSql = "INSERT INTO `c_certificate_awarded` 
    (`user_id`, `user_fullname`, `user_email`, `course_code`, `course_title`, `blueprint_id`, `blueprint_name`, `certificate_code`, `download_count`, `first_collected_at`, `last_collected_at`)
VALUES 
    ($userId, '$safeName', '$safeEmail', '$safeCode', '$safeTitle', $blueprintId, '$safeBpName', '$safeCertCode', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE 
    `download_count` = `download_count` + 1,
    `last_collected_at` = NOW();";

Database::query($logSql);

$webPath = api_get_path(WEB_PATH);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($studentName . ' - ' . $courseTitle . ' Certificate'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Playfair+Display:ital,wght@0,600;0,700;0,900;1,400&family=Poppins:wght@400;500;600;700&family=Cinzel:wght@600;700;900&family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="<?php echo $webPath; ?>web/css/themes/chamilo/default.css" rel="stylesheet">
    <link href="<?php echo $webPath; ?>web/assets/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo $webPath; ?>web/assets/fontawesome/css/font-awesome.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #0F172A;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
        }
        .cert-toolbar {
            width: 100%;
            background: #1E293B;
            border-bottom: 1px solid #334155;
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: #FFFFFF;
            box-sizing: border-box;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .cert-container {
            padding: 2.5rem 1rem;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            box-sizing: border-box;
        }
        
        <?php if (!empty($isEmbed)): ?>
        .cert-toolbar {
            display: none !important;
        }
        body {
            background-color: #0F172A !important;
            padding: 10px !important;
            min-height: 100vh !important;
            overflow-y: auto !important;
        }
        .cert-container {
            padding: 10px 0 !important;
            width: 100% !important;
        }
        .cert-paper {
            max-width: 100% !important;
        }
        <?php endif; ?>

        .cert-paper {
            background: #FFFFFF;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            overflow: hidden;
            display: inline-block;
        }
        .btn-et-print {
            background: #E3122B;
            color: #FFFFFF !important;
            font-weight: 700;
            border: none;
            padding: 0.6rem 1.4rem;
            border-radius: 9999px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none !important;
            box-shadow: 0 4px 14px rgba(227, 18, 43, 0.35);
            transition: all 0.2s ease;
        }
        .btn-et-print:hover {
            background: #C00F23;
            transform: translateY(-1px);
        }
        .btn-et-back {
            background: transparent;
            color: #94A3B8 !important;
            font-weight: 600;
            border: 1px solid #475569;
            padding: 0.55rem 1.25rem;
            border-radius: 9999px;
            text-decoration: none !important;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
        }
        .btn-et-back:hover {
            color: #FFFFFF !important;
            border-color: #CBD5E1;
        }
        @media print {
            .cert-toolbar {
                display: none !important;
            }
            body {
                background: #FFFFFF !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .cert-container {
                padding: 0 !important;
                margin: 0 !important;
            }
            .cert-paper {
                box-shadow: none !important;
                border-radius: 0 !important;
                width: 100% !important;
                height: 100% !important;
            }
            @page {
                size: landscape;
                margin: 0;
            }
        }
    </style>
</head>
<body>

    <?php if (empty($isEmbed)): ?>
    <div class="cert-toolbar d-print-none">
        <div class="d-flex align-items-center gap-3">
            <a href="<?php echo api_get_path(WEB_CODE_PATH); ?>gradebook/my_certificates.php" class="btn-et-back">
                <i class="fa fa-arrow-left"></i> My Certificates
            </a>
            <div>
                <span class="text-white fw-bold d-block fs-6"><?php echo htmlspecialchars($courseTitle); ?></span>
                <small class="text-muted">Certificate Code: <strong class="text-info"><?php echo htmlspecialchars($certCode); ?></strong> &bull; Collected by <?php echo htmlspecialchars($studentName); ?></small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button onclick="window.print()" class="btn-et-print">
                <i class="fa fa-print"></i> Print / Download PDF
            </button>
        </div>
    </div>

    <?php endif; ?>

    <div class="cert-container">
        <div class="cert-paper">
            <?php echo $certificateHtml; ?>
        </div>
    </div>

    <?php if ($action === 'print' || $action === 'export'): ?>
    <script>
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
            }, 600);
        });
    </script>
    <?php endif; ?>

</body>
</html>
