<?php
/* For licensing terms, see /license.txt */

/**
 * Sparky Learning Platform - Certificate Editor
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

// Handle AJAX Fetch
if (isset($_GET['action']) && $_GET['action'] === 'ajax_get_blueprint') {
    header('Content-Type: application/json');
    $bpId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $bp = CertificateBlueprint::getBlueprint($bpId);
    if (!$bp) {
        echo json_encode(['error' => 'Blueprint not found']);
        exit;
    }
    echo json_encode($bp);
    exit;
}

$id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
$blueprint = null;
if ($id > 0) {
    $blueprint = CertificateBlueprint::getBlueprint($id);
}

// Default blueprint structure for new creation
if (!$blueprint) {
    $blueprint = [
        'id' => 0,
        'name' => 'New Executive Certificate Blueprint',
        'template_key' => 'et_masterclass',
        'title' => 'CERTIFICATE OF COMPLETION',
        'text_above_student' => 'This is to certify that',
        'text_above_course' => 'Has successfully fulfilled all requirements for',
        'text_below_course' => 'Awarded with Distinction',
        'issuer_name' => 'Sparky Learning',
        'signatory_name' => 'Academic Director',
        'signatory_title' => 'Sparky Learning Platform',
        'date_text' => date('F jS, Y'),
        'font_family' => 'Playfair Display',
        'primary_color' => '#4F46E5',
        'background_image' => '',
        'student_font' => 'Playfair Display',
        'student_rotation' => 0,
        'student_font_size' => 32,
        'student_offset_x' => 0,
        'student_offset_y' => 0,
        'student_font_weight' => '700',
        'student_letter_spacing' => 0,
        'custom_html' => '',
        'custom_css' => '',
        'course_codes' => []
    ];
} else {
    // Ensure default values for new typography keys
    if (!isset($blueprint['student_font'])) $blueprint['student_font'] = $blueprint['font_family'];
    if (!isset($blueprint['student_rotation'])) $blueprint['student_rotation'] = 0;
    if (!isset($blueprint['student_font_size'])) $blueprint['student_font_size'] = 32;
    if (!isset($blueprint['student_offset_x'])) $blueprint['student_offset_x'] = 0;
    if (!isset($blueprint['student_offset_y'])) $blueprint['student_offset_y'] = 0;
    if (!isset($blueprint['student_font_weight'])) $blueprint['student_font_weight'] = '700';
    if (!isset($blueprint['student_letter_spacing'])) $blueprint['student_letter_spacing'] = 0;
}


$message = '';
$messageType = 'success';

// Handle Save / Update with Image Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_blueprint'])) {
    $bgImage = isset($_POST['existing_bg_image']) ? trim($_POST['existing_bg_image']) : '';
    
    // Check if user clicked remove background image
    if (isset($_POST['remove_bg_image']) && $_POST['remove_bg_image'] == '1') {
        $bgImage = '';
    }

    // Handle new uploaded template background image
    if (isset($_FILES['bg_image_file']) && $_FILES['bg_image_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = api_get_path(SYS_APP_PATH).'upload/certificates/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $fileInfo = pathinfo($_FILES['bg_image_file']['name']);
        $ext = strtolower($fileInfo['extension']);
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'svg', 'gif'];
        
        if (in_array($ext, $allowedExts)) {
            $newFileName = 'cert_bg_'.time().'_'.uniqid().'.'.$ext;
            $destPath = $uploadDir.$newFileName;
            if (move_uploaded_file($_FILES['bg_image_file']['tmp_name'], $destPath)) {
                $bgImage = api_get_path(WEB_APP_PATH).'upload/certificates/'.$newFileName;
            }
        }
    }

    $saveData = [
        'name' => trim($_POST['name']),
        'template_key' => trim($_POST['template_key']),
        'title' => trim($_POST['title']),
        'text_above_student' => trim($_POST['text_above_student']),
        'text_above_course' => trim($_POST['text_above_course']),
        'text_below_course' => trim($_POST['text_below_course']),
        'issuer_name' => trim($_POST['issuer_name']),
        'signatory_name' => trim($_POST['signatory_name']),
        'signatory_title' => trim($_POST['signatory_title']),
        'date_text' => trim($_POST['date_text']),
        'font_family' => trim($_POST['font_family']),
        'primary_color' => trim($_POST['primary_color']),
        'background_image' => $bgImage,
        'student_font' => isset($_POST['student_font']) ? trim($_POST['student_font']) : '',
        'student_rotation' => isset($_POST['student_rotation']) ? (int)$_POST['student_rotation'] : 0,
        'student_font_size' => isset($_POST['student_font_size']) ? (int)$_POST['student_font_size'] : 32,
        'student_offset_x' => isset($_POST['student_offset_x']) ? (int)$_POST['student_offset_x'] : 0,
        'student_offset_y' => isset($_POST['student_offset_y']) ? (int)$_POST['student_offset_y'] : 0,
        'student_font_weight' => isset($_POST['student_font_weight']) ? trim($_POST['student_font_weight']) : '700',
        'student_letter_spacing' => isset($_POST['student_letter_spacing']) ? (int)$_POST['student_letter_spacing'] : 0,
        'custom_html' => isset($_POST['custom_html']) ? $_POST['custom_html'] : '',
        'custom_css' => isset($_POST['custom_css']) ? $_POST['custom_css'] : '',
        'courses' => isset($_POST['courses']) && is_array($_POST['courses']) ? $_POST['courses'] : []
    ];

    $savedId = CertificateBlueprint::saveBlueprint($saveData, $id);
    if ($savedId) {
        header("Location: certificate_blueprints.php?msg=saved");
        exit;
    } else {
        $message = "Could not save certificate blueprint.";
        $messageType = "danger";
    }
}

$allCourses = CourseManager::get_courses_list();

// Load page header
Display::display_header('Certificate Editor');
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Alex+Brush&family=Allura&family=Cinzel:wght@500;700;900&family=Cinzel+Decorative:wght@700&family=Cormorant+Garamond:ital,wght@0,600;0,700;1,400&family=Dancing+Script:wght@600;700&family=Great+Vibes&family=Inter:wght@400;500;600;700&family=Merriweather:ital,wght@0,400;0,700;1,400&family=Montserrat:wght@400;600;700;800&family=Outfit:wght@400;600;700&family=Parisienne&family=Pinyon+Script&family=Playfair+Display:ital,wght@0,600;0,700;1,400&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

<style>
:root {
    --color-primary: #E3122B;
    --color-primary-hover: #BE123C;
    --color-purple-btn: #6366F1;
    --color-purple-hover: #4F46E5;
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

.gf-editor-container {
    width: 100%;
    max-width: 100% !important;
    margin: 0 auto 40px auto;
    padding: 20px 24px;
}

/* Header */
.gf-editor-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.gf-editor-title {
    font-size: 24px;
    font-weight: 700;
    color: #0F172A;
    margin: 0;
}

.gf-header-btns {
    display: flex;
    gap: 12px;
}

.btn-discard {
    background: #FFFFFF;
    border: 1px solid #D1D5DB;
    color: #374151;
    font-size: 14px;
    font-weight: 600;
    padding: 8px 20px;
    border-radius: 8px;
    text-decoration: none !important;
    transition: all 0.15s;
}

.btn-discard:hover {
    background: #F3F4F6;
    color: #111827;
}

.btn-update {
    background: var(--color-purple-btn);
    border: none;
    color: #FFFFFF;
    font-size: 14px;
    font-weight: 600;
    padding: 8px 24px;
    border-radius: 8px;
    cursor: pointer;
    box-shadow: 0 2px 4px rgba(99, 102, 241, 0.2);
    transition: all 0.15s;
}

.btn-update:hover {
    background: var(--color-purple-hover);
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
}

/* Form Top Section */
.gf-form-card {
    background: #FFFFFF;
    border-radius: 12px;
    border: 1px solid #E2E8F0;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}

.gf-form-label {
    font-size: 13.5px;
    font-weight: 600;
    color: #334155;
    margin-bottom: 8px;
    display: block;
}

.gf-input-field {
    width: 100%;
    max-width: 400px;
    padding: 10px 14px;
    border: 1px solid #CBD5E1;
    border-radius: 8px;
    font-size: 14px;
    color: #1E293B;
    outline: none;
    transition: border-color 0.15s;
}

.gf-input-field:focus {
    border-color: var(--color-purple-btn);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

/* Template Selector Cards */
.template-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 16px;
    margin-top: 12px;
}

.template-card {
    border: 2px solid #E2E8F0;
    border-radius: 10px;
    padding: 12px;
    cursor: pointer;
    text-align: center;
    background: #FFFFFF;
    transition: all 0.2s;
    position: relative;
}

.template-card:hover {
    border-color: #CBD5E1;
    transform: translateY(-2px);
}

.template-card.active {
    border-color: var(--color-purple-btn);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

.template-thumb {
    width: 100%;
    height: 95px;
    border-radius: 6px;
    margin-bottom: 8px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 6px;
    box-sizing: border-box;
    position: relative;
    overflow: hidden;
}

.template-title {
    font-size: 12px;
    font-weight: 600;
    color: #334155;
}

/* Upload Box */
.upload-template-box {
    border: 2px dashed #CBD5E1;
    border-radius: 8px;
    padding: 14px;
    text-align: center;
    background: #F8FAFC;
    margin-top: 10px;
    transition: all 0.2s;
}

.upload-template-box:hover {
    border-color: var(--color-purple-btn);
    background: #EEF2FF;
}

/* Main Split View */
.gf-editor-main {
    display: flex;
    gap: 24px;
    align-items: flex-start;
}

/* Left Panel: Tabs & Inputs */
.gf-left-panel {
    width: 380px;
    flex-shrink: 0;
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}

.gf-tab-nav {
    display: flex;
    border-bottom: 1px solid #E2E8F0;
    margin-bottom: 20px;
}

.gf-tab-btn {
    padding: 8px 18px;
    font-size: 14px;
    font-weight: 600;
    color: #64748B;
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    transition: all 0.15s;
}

.gf-tab-btn.active {
    color: var(--color-purple-btn);
    border-bottom-color: var(--color-purple-btn);
}

.gf-tab-content {
    display: none;
}

.gf-tab-content.active {
    display: block;
}

.gf-control-group {
    margin-bottom: 18px;
}

.gf-control-label {
    font-size: 12.5px;
    font-weight: 600;
    color: #475569;
    margin-bottom: 6px;
    display: block;
}

.gf-control-input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #CBD5E1;
    border-radius: 6px;
    font-size: 13.5px;
    color: #1E293B;
    outline: none;
    transition: border-color 0.15s;
    box-sizing: border-box;
}

.gf-control-input:focus {
    border-color: var(--color-purple-btn);
}

/* Right Panel: Preview & Code */
.gf-right-panel {
    flex: 1;
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    min-height: 600px;
}

.gf-preview-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #E2E8F0;
    padding-bottom: 12px;
    margin-bottom: 20px;
}

.gf-view-tabs {
    display: flex;
    gap: 8px;
}

.gf-view-tab-btn {
    font-size: 13.5px;
    font-weight: 600;
    color: #64748B;
    background: none;
    border: none;
    cursor: pointer;
    padding: 6px 14px;
    border-radius: 6px;
}

.gf-view-tab-btn.active {
    color: var(--color-purple-btn);
    background: #EEF2FF;
}

.gf-canvas-container {
    background: #F1F5F9;
    border-radius: 10px;
    padding: 24px;
    display: flex;
    justify-content: center;
    align-items: center;
    overflow-x: auto;
}

/* CERTIFICATE CANVAS */
.cert-canvas {
    width: 760px;
    height: 540px;
    background: #FFFFFF;
    padding: 36px 44px;
    box-shadow: 0 12px 30px rgba(0,0,0,0.12);
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    text-align: center;
    position: relative;
    user-select: none;
    transition: all 0.2s;
    background-size: cover !important;
    background-position: center !important;
    background-repeat: no-repeat !important;
}

/* Template 1: Classic Gold */
.cert-classic-gold {
    border: 12px double #D4AF37;
    background: #FFFFFF;
}

/* Template 2: Modern Blue */
.cert-modern-blue {
    border: 8px solid #0284C7;
    background: linear-gradient(135deg, #FFFFFF 0%, #F0F9FF 100%);
}

/* Template 3: Plain Blank (Student Name Only for Custom Template) */
.cert-blank-student-only {
    border: none;
    background-color: transparent;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}

/* Template 4: ET Masterclass */
.cert-et-masterclass {
    border: 8px solid #E3122B;
    outline: 3px solid #D4AF37;
    outline-offset: -12px;
    background: radial-gradient(circle, #FFFFFF 70%, #FFF5F5 100%);
}

/* Template 5: Custom Uploaded Background */
.cert-custom-bg {
    border: 1px solid #CBD5E1;
    background-color: #FFFFFF;
}

/* Code Editor Styling */
.code-editor-wrap {
    background: #0F172A;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: inset 0 2px 6px rgba(0,0,0,0.4);
}

.code-editor-header {
    background: #1E293B;
    padding: 10px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #334155;
}

.tag-badge {
    background: #334155;
    color: #38BDF8;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    cursor: pointer;
    font-family: monospace;
    transition: background 0.15s;
}

.tag-badge:hover {
    background: #0284C7;
    color: #FFFFFF;
}

.code-textarea {
    width: 100%;
    height: 480px;
    background: #0F172A;
    color: #38BDF8;
    font-family: 'Fira Code', Consolas, Monaco, monospace;
    font-size: 13px;
    line-height: 1.5;
    padding: 16px;
    border: none;
    outline: none;
    resize: vertical;
    box-sizing: border-box;
}
</style>

<div class="gf-editor-container">

    <!-- Top Action Bar -->
    <form method="post" action="certificate_editor.php<?php echo $id > 0 ? '?id='.$id : ''; ?>" id="certEditorForm" enctype="multipart/form-data">
        <input type="hidden" name="save_blueprint" value="1">
        <input type="hidden" name="template_key" id="inputTemplateKey" value="<?php echo htmlspecialchars($blueprint['template_key']); ?>">
        <input type="hidden" name="existing_bg_image" id="inputExistingBgImage" value="<?php echo htmlspecialchars($blueprint['background_image']); ?>">
        <input type="hidden" name="remove_bg_image" id="inputRemoveBgImage" value="0">
        <input type="hidden" name="is_custom_code_active" id="inputIsCustomCodeActive" value="<?php echo !empty($blueprint['custom_html']) ? '1' : '0'; ?>">

        <div class="gf-editor-header">
            <h1 class="gf-editor-title">Certificate Editor</h1>
            <div class="gf-header-btns">
                <a href="certificate_blueprints.php" class="btn-discard">Discard</a>
                <button type="submit" class="btn-update">
                    <?php echo $id > 0 ? 'Update' : 'Create Blueprint'; ?>
                </button>
            </div>
        </div>

        <!-- Notification Banner -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Blueprint Name & Template Chooser -->
        <div class="gf-form-card">
            <div style="margin-bottom: 20px;">
                <label class="gf-form-label"><span class="text-danger">*</span> Certificate name</label>
                <input type="text" name="name" id="inputCertName" class="gf-input-field" required placeholder="e.g. AI in investing" value="<?php echo htmlspecialchars($blueprint['name']); ?>" oninput="updateLivePreview()">
            </div>

            <div>
                <label class="gf-form-label">Choose template or Upload custom background</label>
                <div class="template-grid">
                    <!-- Template 1: Classic Gold -->
                    <div class="template-card <?php echo $blueprint['template_key'] === 'classic_gold' ? 'active' : ''; ?>" onclick="selectTemplate('classic_gold', this)">
                        <div class="template-thumb" style="border: 4px double #D4AF37; background: #FFFBF0;">
                            <div style="font-size: 8px; font-weight: 700; color: #B45309; letter-spacing: 1px;">CERTIFICATE</div>
                            <div style="font-size: 6px; color: #64748B; margin: 2px 0;">[Student Name]</div>
                            <div style="width: 14px; height: 14px; border-radius: 50%; background: #D4AF37; margin-top: 4px;"></div>
                        </div>
                        <div class="template-title">Classic Gold Border</div>
                    </div>

                    <!-- Template 2: Modern Blue -->
                    <div class="template-card <?php echo $blueprint['template_key'] === 'modern_blue' ? 'active' : ''; ?>" onclick="selectTemplate('modern_blue', this)">
                        <div class="template-thumb" style="border: 3px solid #0284C7; background: #F0F9FF;">
                            <div style="font-size: 8px; font-weight: 700; color: #0369A1; letter-spacing: 1px;">CERTIFICATE</div>
                            <div style="font-size: 6px; color: #64748B; margin: 2px 0;">[Student Name]</div>
                            <div style="width: 14px; height: 14px; border-radius: 50%; background: #0284C7; margin-top: 4px;"></div>
                        </div>
                        <div class="template-title">Modern Blue Geometric</div>
                    </div>

                    <!-- Template 3: Plain Blank (Student Name Only for Custom Template) -->
                    <div class="template-card <?php echo $blueprint['template_key'] === 'blank_student_only' ? 'active' : ''; ?>" onclick="selectTemplate('blank_student_only', this)">
                        <div class="template-thumb" style="border: 2px dashed #94A3B8; background: #FFFFFF; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 4px;">
                            <div style="font-size: 7.5px; font-weight: 700; color: #E3122B; border-bottom: 1px dashed #CBD5E1; padding-bottom: 2px;">{{STUDENT_NAME}}</div>
                            <span style="font-size: 6px; color: #64748B; margin-top: 4px; font-weight: 600;">Plain Blank Overlay</span>
                        </div>
                        <div class="template-title">Blank (Name Only)</div>
                    </div>

                    <!-- Template 4: Sparky Excellence -->
                    <div class="template-card <?php echo $blueprint['template_key'] === 'et_masterclass' ? 'active' : ''; ?>" onclick="selectTemplate('et_masterclass', this)">
                        <div class="template-thumb" style="border: 3px solid #4F46E5; background: #EEF2FF;">
                            <div style="font-size: 7px; font-weight: 800; color: #111827;">⚡ SPARKY</div>
                            <div style="font-size: 6px; font-weight: 800; color: #4F46E5; letter-spacing: 1px;">EXCELLENCE</div>
                            <div style="font-size: 5.5px; color: #64748B;">[Student Name]</div>
                        </div>
                        <div class="template-title">Sparky Excellence</div>
                    </div>

                    <!-- Template 5: Custom Image Template -->
                    <div class="template-card <?php echo $blueprint['template_key'] === 'custom_bg' || !empty($blueprint['background_image']) ? 'active' : ''; ?>" onclick="selectTemplate('custom_bg', this)">
                        <div class="template-thumb" style="border: 2px dashed #6366F1; background: #EEF2FF;">
                            <i class="fa fa-image" style="font-size: 20px; color: #6366F1; margin-bottom: 4px;"></i>
                            <div style="font-size: 7.5px; font-weight: 700; color: #4F46E5;">CUSTOM IMAGE</div>
                        </div>
                        <div class="template-title">Upload Background</div>
                    </div>
                </div>

                <!-- Background Image Upload Widget -->
                <div class="upload-template-box" id="uploadTemplateWidget" style="<?php echo ($blueprint['template_key'] === 'custom_bg' || $blueprint['template_key'] === 'blank_student_only' || !empty($blueprint['background_image'])) ? '' : 'display: none;'; ?>">
                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                        <div style="text-align: left;">
                            <div style="font-weight: 700; font-size: 13.5px; color: #1E293B;"><i class="fa fa-cloud-upload text-primary"></i> Upload Certificate Background Template Image</div>
                            <div style="font-size: 12px; color: #64748B;">Upload high-res PNG, JPG, or SVG blank certificate (Recommended: 1920x1080 or A4 Landscape).</div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <input type="file" name="bg_image_file" id="bgImageFileInput" accept="image/*" onchange="handleBgImageSelected(this)" style="font-size: 12.5px;">
                            <?php if (!empty($blueprint['background_image'])): ?>
                                <button type="button" class="btn btn-xs btn-outline-danger" style="border: 1px solid #EF4444; color: #DC2626;" onclick="removeBackgroundImage()">
                                    <i class="fa fa-times"></i> Remove Image
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Split Editor Workspace -->
        <div class="gf-editor-main">

            <!-- LEFT: Configuration Panels -->
            <div class="gf-left-panel">
                <div class="gf-tab-nav">
                    <button type="button" class="gf-tab-btn active" onclick="switchConfigTab('contentTab', this)">Content</button>
                    <button type="button" class="gf-tab-btn" onclick="switchConfigTab('styleTab', this)">Style</button>
                    <button type="button" class="gf-tab-btn" onclick="switchConfigTab('layoutTab', this)">Layers</button>
                </div>

                <!-- CONTENT TAB -->
                <div id="contentTab" class="gf-tab-content active">
                    <div class="gf-control-group">
                        <label class="gf-control-label">Title</label>
                        <input type="text" name="title" id="inputTitle" class="gf-control-input" value="<?php echo htmlspecialchars($blueprint['title']); ?>" oninput="updateLivePreview()">
                    </div>

                    <div class="gf-control-group">
                        <label class="gf-control-label">Text above student name</label>
                        <input type="text" name="text_above_student" id="inputTextAboveStudent" class="gf-control-input" value="<?php echo htmlspecialchars($blueprint['text_above_student']); ?>" oninput="updateLivePreview()">
                    </div>

                    <div class="gf-control-group">
                        <label class="gf-control-label">Text above course name</label>
                        <input type="text" name="text_above_course" id="inputTextAboveCourse" class="gf-control-input" value="<?php echo htmlspecialchars($blueprint['text_above_course']); ?>" oninput="updateLivePreview()">
                    </div>

                    <div class="gf-control-group">
                        <label class="gf-control-label">Text below course name</label>
                        <input type="text" name="text_below_course" id="inputTextBelowCourse" class="gf-control-input" value="<?php echo htmlspecialchars($blueprint['text_below_course']); ?>" oninput="updateLivePreview()">
                    </div>

                    <div class="gf-control-group">
                        <label class="gf-control-label">Organization / Issuer</label>
                        <input type="text" name="issuer_name" id="inputIssuerName" class="gf-control-input" value="<?php echo htmlspecialchars($blueprint['issuer_name']); ?>" oninput="updateLivePreview()">
                    </div>

                    <div class="row">
                        <div class="col-xs-6" style="padding-right: 6px;">
                            <div class="gf-control-group">
                                <label class="gf-control-label">Signatory Name</label>
                                <input type="text" name="signatory_name" id="inputSignatoryName" class="gf-control-input" value="<?php echo htmlspecialchars($blueprint['signatory_name']); ?>" oninput="updateLivePreview()">
                            </div>
                        </div>
                        <div class="col-xs-6" style="padding-left: 6px;">
                            <div class="gf-control-group">
                                <label class="gf-control-label">Signatory Title</label>
                                <input type="text" name="signatory_title" id="inputSignatoryTitle" class="gf-control-input" value="<?php echo htmlspecialchars($blueprint['signatory_title']); ?>" oninput="updateLivePreview()">
                            </div>
                        </div>
                    </div>

                    <div class="gf-control-group">
                        <label class="gf-control-label">Date Format / Text</label>
                        <input type="text" name="date_text" id="inputDateText" class="gf-control-input" value="<?php echo htmlspecialchars($blueprint['date_text']); ?>" oninput="updateLivePreview()">
                    </div>

                    <div class="gf-control-group">
                        <label class="gf-control-label">Assign to Courses (Optional):</label>
                        <select name="courses[]" class="gf-control-input" multiple size="4" style="font-size: 12.5px;">
                            <?php foreach ($allCourses as $c): 
                                $selected = in_array($c['code'], $blueprint['course_codes']) ? 'selected' : '';
                            ?>
                                <option value="<?php echo htmlspecialchars($c['code']); ?>" <?php echo $selected; ?>>
                                    <?php echo htmlspecialchars($c['title'].' ('.$c['code'].')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- STYLE TAB -->
                <div id="styleTab" class="gf-tab-content">
                    <div class="gf-control-group">
                        <label class="gf-control-label">Overall Template Font</label>
                        <select name="font_family" id="inputFontFamily" class="gf-control-input" onchange="updateLivePreview()">
                            <optgroup label="Luxury & Classical Serif">
                                <option value="Playfair Display" <?php echo $blueprint['font_family'] === 'Playfair Display' ? 'selected' : ''; ?>>Playfair Display (Luxury Serif)</option>
                                <option value="Cinzel" <?php echo $blueprint['font_family'] === 'Cinzel' ? 'selected' : ''; ?>>Cinzel (Classical Roman)</option>
                                <option value="Cinzel Decorative" <?php echo $blueprint['font_family'] === 'Cinzel Decorative' ? 'selected' : ''; ?>>Cinzel Decorative (Monumental)</option>
                                <option value="Cormorant Garamond" <?php echo $blueprint['font_family'] === 'Cormorant Garamond' ? 'selected' : ''; ?>>Cormorant Garamond (Refined Serif)</option>
                                <option value="Merriweather" <?php echo $blueprint['font_family'] === 'Merriweather' ? 'selected' : ''; ?>>Merriweather (Sturdy Editorial)</option>
                            </optgroup>
                            <optgroup label="Modern Sans-Serif">
                                <option value="Inter" <?php echo $blueprint['font_family'] === 'Inter' ? 'selected' : ''; ?>>Inter (Clean Ultra Sans)</option>
                                <option value="Montserrat" <?php echo $blueprint['font_family'] === 'Montserrat' ? 'selected' : ''; ?>>Montserrat (Modern Geometric)</option>
                                <option value="Outfit" <?php echo $blueprint['font_family'] === 'Outfit' ? 'selected' : ''; ?>>Outfit (Contemporary Sans)</option>
                                <option value="Poppins" <?php echo $blueprint['font_family'] === 'Poppins' ? 'selected' : ''; ?>>Poppins (Balanced Sans)</option>
                            </optgroup>
                        </select>
                    </div>

                    <div class="gf-control-group" style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 12px;">
                        <label class="gf-control-label" style="color: #6366F1; font-weight: 700; margin-bottom: 8px;">
                            <i class="fa fa-paint-brush"></i> Student Name Typography & Rotation
                        </label>

                        <!-- Student Font Family -->
                        <div style="margin-bottom: 12px;">
                            <label class="gf-control-label" style="font-size: 12px;">Student Name Font Family</label>
                            <select name="student_font" id="inputStudentFont" class="gf-control-input" onchange="adjustLayerPositions()">
                                <optgroup label="Calligraphy & Elegant Script">
                                    <option value="Great Vibes" <?php echo $blueprint['student_font'] === 'Great Vibes' ? 'selected' : ''; ?>>Great Vibes (Cursive Calligraphy)</option>
                                    <option value="Alex Brush" <?php echo $blueprint['student_font'] === 'Alex Brush' ? 'selected' : ''; ?>>Alex Brush (Fluid Signature)</option>
                                    <option value="Pinyon Script" <?php echo $blueprint['student_font'] === 'Pinyon Script' ? 'selected' : ''; ?>>Pinyon Script (Formal Aristocratic)</option>
                                    <option value="Parisienne" <?php echo $blueprint['student_font'] === 'Parisienne' ? 'selected' : ''; ?>>Parisienne (Chic French Script)</option>
                                    <option value="Dancing Script" <?php echo $blueprint['student_font'] === 'Dancing Script' ? 'selected' : ''; ?>>Dancing Script (Lively Script)</option>
                                    <option value="Allura" <?php echo $blueprint['student_font'] === 'Allura' ? 'selected' : ''; ?>>Allura (Graceful Handwritten)</option>
                                </optgroup>
                                <optgroup label="Luxury & Classical Serif">
                                    <option value="Playfair Display" <?php echo $blueprint['student_font'] === 'Playfair Display' || empty($blueprint['student_font']) ? 'selected' : ''; ?>>Playfair Display (Luxury Serif)</option>
                                    <option value="Cinzel" <?php echo $blueprint['student_font'] === 'Cinzel' ? 'selected' : ''; ?>>Cinzel (Classical Roman)</option>
                                    <option value="Cinzel Decorative" <?php echo $blueprint['student_font'] === 'Cinzel Decorative' ? 'selected' : ''; ?>>Cinzel Decorative (Regal Roman)</option>
                                    <option value="Cormorant Garamond" <?php echo $blueprint['student_font'] === 'Cormorant Garamond' ? 'selected' : ''; ?>>Cormorant Garamond (Editorial Serif)</option>
                                    <option value="Merriweather" <?php echo $blueprint['student_font'] === 'Merriweather' ? 'selected' : ''; ?>>Merriweather (Sturdy Serif)</option>
                                </optgroup>
                                <optgroup label="Modern Sans-Serif">
                                    <option value="Montserrat" <?php echo $blueprint['student_font'] === 'Montserrat' ? 'selected' : ''; ?>>Montserrat (Geometric Sans)</option>
                                    <option value="Inter" <?php echo $blueprint['student_font'] === 'Inter' ? 'selected' : ''; ?>>Inter (Clean Sans)</option>
                                    <option value="Outfit" <?php echo $blueprint['student_font'] === 'Outfit' ? 'selected' : ''; ?>>Outfit (Modern Sans)</option>
                                    <option value="Poppins" <?php echo $blueprint['student_font'] === 'Poppins' ? 'selected' : ''; ?>>Poppins (Bold Sans)</option>
                                </optgroup>
                            </select>
                        </div>

                        <!-- Student Rotation -->
                        <div style="margin-bottom: 12px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                <label class="gf-control-label" style="font-size: 12px; margin: 0;">Student Name Rotation Angle</label>
                                <div style="display: flex; align-items: center; gap: 4px;">
                                    <input type="number" id="inputStudentRotationNum" min="-180" max="180" value="<?php echo (int)$blueprint['student_rotation']; ?>" style="width: 55px; text-align: right; padding: 2px 4px; font-size: 12px; border: 1px solid #CBD5E1; border-radius: 4px;" oninput="syncRotationInput(this.value)">
                                    <span style="font-size: 12px; color: #64748B;">deg</span>
                                </div>
                            </div>
                            <input type="range" name="student_rotation" id="sliderStudentRotation" min="-180" max="180" value="<?php echo (int)$blueprint['student_rotation']; ?>" class="form-control" style="cursor: pointer;" oninput="syncRotationSlider(this.value)">
                            
                            <div style="display: flex; gap: 4px; margin-top: 6px; flex-wrap: wrap;">
                                <button type="button" class="btn btn-xs btn-default" onclick="setRotationPreset(-90)" style="font-size: 11px; padding: 2px 6px;">-90°</button>
                                <button type="button" class="btn btn-xs btn-default" onclick="setRotationPreset(-45)" style="font-size: 11px; padding: 2px 6px;">-45°</button>
                                <button type="button" class="btn btn-xs btn-default" onclick="setRotationPreset(-15)" style="font-size: 11px; padding: 2px 6px;">-15°</button>
                                <button type="button" class="btn btn-xs btn-info" onclick="setRotationPreset(0)" style="font-size: 11px; padding: 2px 6px; font-weight: 600;">0° Flat</button>
                                <button type="button" class="btn btn-xs btn-default" onclick="setRotationPreset(15)" style="font-size: 11px; padding: 2px 6px;">+15°</button>
                                <button type="button" class="btn btn-xs btn-default" onclick="setRotationPreset(45)" style="font-size: 11px; padding: 2px 6px;">+45°</button>
                                <button type="button" class="btn btn-xs btn-default" onclick="setRotationPreset(90)" style="font-size: 11px; padding: 2px 6px;">+90°</button>
                            </div>
                        </div>

                        <!-- Font Weight & Letter Spacing -->
                        <div class="row">
                            <div class="col-xs-6" style="padding-right: 4px;">
                                <label class="gf-control-label" style="font-size: 11.5px;">Font Weight</label>
                                <select name="student_font_weight" id="inputStudentFontWeight" class="gf-control-input" style="font-size: 12px; padding: 6px;" onchange="adjustLayerPositions()">
                                    <option value="400" <?php echo $blueprint['student_font_weight'] == '400' ? 'selected' : ''; ?>>400 Regular</option>
                                    <option value="500" <?php echo $blueprint['student_font_weight'] == '500' ? 'selected' : ''; ?>>500 Medium</option>
                                    <option value="600" <?php echo $blueprint['student_font_weight'] == '600' ? 'selected' : ''; ?>>600 Semi-Bold</option>
                                    <option value="700" <?php echo $blueprint['student_font_weight'] == '700' || empty($blueprint['student_font_weight']) ? 'selected' : ''; ?>>700 Bold</option>
                                    <option value="800" <?php echo $blueprint['student_font_weight'] == '800' ? 'selected' : ''; ?>>800 Extra Bold</option>
                                    <option value="900" <?php echo $blueprint['student_font_weight'] == '900' ? 'selected' : ''; ?>>900 Black</option>
                                </select>
                            </div>
                            <div class="col-xs-6" style="padding-left: 4px;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <label class="gf-control-label" style="font-size: 11.5px; margin: 0;">Spacing</label>
                                    <span id="valStudentLetterSpacing" style="font-size: 11px; color: #64748B;"><?php echo (int)$blueprint['student_letter_spacing']; ?>px</span>
                                </div>
                                <input type="range" name="student_letter_spacing" id="sliderStudentLetterSpacing" min="-2" max="20" value="<?php echo (int)$blueprint['student_letter_spacing']; ?>" class="form-control" style="margin-top: 4px;" oninput="adjustLayerPositions()">
                            </div>
                        </div>
                    </div>

                    <div class="gf-control-group">
                        <label class="gf-control-label">Student Highlight Color</label>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <input type="color" name="primary_color" id="inputPrimaryColor" value="<?php echo htmlspecialchars($blueprint['primary_color']); ?>" style="width: 44px; height: 38px; border: 1px solid #CBD5E1; border-radius: 6px; padding: 2px; cursor: pointer;" oninput="updateLivePreview()">
                            <input type="text" id="inputPrimaryColorHex" class="gf-control-input" value="<?php echo htmlspecialchars($blueprint['primary_color']); ?>" oninput="document.getElementById('inputPrimaryColor').value = this.value; updateLivePreview();">
                        </div>
                    </div>

                    <div class="gf-control-group">
                        <label class="gf-control-label">Quick Color Presets:</label>
                        <div style="display: flex; gap: 8px;">
                            <span onclick="setPresetColor('#E3122B')" style="width: 24px; height: 24px; border-radius: 50%; background: #E3122B; cursor: pointer; display: inline-block;" title="ET Signature Red"></span>
                            <span onclick="setPresetColor('#D97706')" style="width: 24px; height: 24px; border-radius: 50%; background: #D97706; cursor: pointer; display: inline-block;" title="Imperial Gold"></span>
                            <span onclick="setPresetColor('#0284C7')" style="width: 24px; height: 24px; border-radius: 50%; background: #0284C7; cursor: pointer; display: inline-block;" title="Ocean Blue"></span>
                            <span onclick="setPresetColor('#059669')" style="width: 24px; height: 24px; border-radius: 50%; background: #059669; cursor: pointer; display: inline-block;" title="Emerald Green"></span>
                            <span onclick="setPresetColor('#1E293B')" style="width: 24px; height: 24px; border-radius: 50%; background: #1E293B; cursor: pointer; display: inline-block;" title="Midnight Slate"></span>
                        </div>
                    </div>

                    <div class="gf-control-group">
                        <label class="gf-control-label">Orientation</label>
                        <input type="text" class="gf-control-input" value="Landscape (Standard 4:3 / A4)" readonly style="background: #F8FAFC;">
                    </div>
                </div>

                <!-- LAYERS / POSITIONING TAB (for Custom Background Templates) -->
                <div id="layoutTab" class="gf-tab-content">
                    <p style="font-size: 12px; color: #64748B; margin-bottom: 14px;">
                        Fine-tune the exact positioning, rotation, and font sizing of dynamic text layers over your template image.
                    </p>

                    <div class="gf-control-group">
                        <label class="gf-control-label">Student Name Font Size: <span id="valStudentFontSize"><?php echo (int)$blueprint['student_font_size']; ?>px</span></label>
                        <input type="range" name="student_font_size" id="sliderStudentFontSize" min="16" max="90" value="<?php echo (int)$blueprint['student_font_size']; ?>" class="form-control" oninput="adjustLayerPositions()">
                    </div>

                    <div class="gf-control-group">
                        <label class="gf-control-label">Student Name Horizontal (X) Offset: <span id="valStudentOffsetX"><?php echo (int)$blueprint['student_offset_x']; ?>px</span></label>
                        <input type="range" name="student_offset_x" id="sliderStudentOffsetX" min="-350" max="350" value="<?php echo (int)$blueprint['student_offset_x']; ?>" class="form-control" oninput="adjustLayerPositions()">
                    </div>

                    <div class="gf-control-group">
                        <label class="gf-control-label">Student Name Vertical (Y) Offset: <span id="valStudentOffsetY"><?php echo (int)$blueprint['student_offset_y']; ?>px</span></label>
                        <input type="range" name="student_offset_y" id="sliderStudentOffsetY" min="-250" max="350" value="<?php echo (int)$blueprint['student_offset_y']; ?>" class="form-control" oninput="adjustLayerPositions()">
                    </div>

                    <div class="gf-control-group">
                        <label class="gf-control-label">Student Rotation: <span id="valStudentRotationLayers"><?php echo (int)$blueprint['student_rotation']; ?>°</span></label>
                        <input type="range" id="sliderStudentRotationLayers" min="-180" max="180" value="<?php echo (int)$blueprint['student_rotation']; ?>" class="form-control" oninput="syncRotationSlider(this.value)">
                    </div>

                    <div class="gf-control-group">
                        <label class="gf-control-label">Course Title Vertical Offset: <span id="valCourseOffset">0px</span></label>
                        <input type="range" id="sliderCourseOffset" min="-100" max="150" value="0" class="form-control" oninput="adjustLayerPositions()">
                    </div>

                    <div class="checkbox" style="margin-top: 15px;">
                        <label style="font-size: 12.5px; font-weight: 600; color: #334155;">
                            <input type="checkbox" id="toggleOnlyNameCourse" onchange="toggleMinimalLayers(this.checked)">
                            Overlay Only Name & Course (Hide default borders/emblems)
                        </label>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Preview & Editable Code View -->
            <div class="gf-right-panel">
                <div class="gf-preview-header">
                    <div class="gf-view-tabs">
                        <button type="button" id="tabBtnPreview" class="gf-view-tab-btn active" onclick="switchViewTab('previewPane', this)">
                            <i class="fa fa-eye"></i> Visual Preview
                        </button>
                        <button type="button" id="tabBtnCode" class="gf-view-tab-btn" onclick="switchViewTab('codePane', this)">
                            <i class="fa fa-code"></i> HTML Code Editor
                        </button>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span id="canvasModeIndicator" class="label label-info" style="font-size: 11px; padding: 4px 8px; border-radius: 4px;">
                            <i class="fa fa-refresh fa-spin"></i> Visual Mode
                        </span>
                    </div>
                </div>

                <!-- PREVIEW PANE -->
                <div id="previewPane">
                    <div class="gf-canvas-container" id="canvasMasterContainer">
                        
                        <!-- Visual Structured Canvas -->
                        <div id="liveCertCanvas" class="cert-canvas cert-et-masterclass">
                            <!-- Live Header -->
                            <div id="certHeaderSection">
                                <div id="certEmblemHtml"></div>
                                <div id="certTitleText" style="font-size: 22px; font-weight: 700; letter-spacing: 3px; color: #1E293B; text-transform: uppercase; margin-bottom: 10px;">CERTIFICATE OF COMPLETION</div>
                                <div id="certAboveStudentText" style="font-size: 13.5px; color: #64748B; font-style: italic; margin-bottom: 12px;">This is to certify that</div>
                                
                                <!-- DYNAMIC STUDENT NAME LAYER (with rotation & typography) -->
                                <div id="certStudentNameWrapper" style="display: inline-block; position: relative; margin-bottom: 12px;">
                                    <div id="certStudentNameLayer" style="font-size: 32px; font-weight: 700; color: #E3122B; border-bottom: 2px solid #E2E8F0; display: inline-block; padding: 0 32px 4px 32px; font-family: 'Playfair Display', serif; transition: transform 0.05s ease, font-size 0.05s ease; transform-origin: center center;">
                                        {{STUDENT_NAME}}
                                    </div>
                                </div>
                                
                                <div id="certAboveCourseText" style="font-size: 13px; color: #64748B; margin-bottom: 8px;">Has pursued studies and completed all the requirements for</div>
                                <div id="certCourseNameText" style="font-size: 19px; font-weight: 700; color: #0F172A; margin-bottom: 8px; transition: all 0.15s;">AI in Investing Masterclass</div>
                                <div id="certBelowCourseText" style="font-size: 12.5px; color: #64748B;">on August 22nd – 23rd, 2026.</div>
                            </div>

                            <!-- Live Footer -->
                            <div id="certFooterSection" style="display: flex; justify-content: space-between; align-items: flex-end; padding-top: 18px; border-top: 1px solid #F1F5F9;">
                                <div style="text-align: left;">
                                    <div style="font-size: 10.5px; color: #94A3B8; text-transform: uppercase; letter-spacing: 1px;">Issuer</div>
                                    <div id="certIssuerText" style="font-size: 13px; font-weight: 600; color: #334155;">Sparky Learning</div>
                                </div>
                                <div id="certSealWrap">
                                    <div style="width: 50px; height: 50px; border-radius: 50%; background: linear-gradient(135deg, #F59E0B, #D97706); color: #FFF; display: inline-flex; align-items: center; justify-content: center; font-size: 22px; box-shadow: 0 4px 8px rgba(217,119,6,0.3);"><i class="fa fa-shield"></i></div>
                                    <div style="font-size: 9.5px; font-weight: 700; color: #D97706; text-transform: uppercase; margin-top: 3px; letter-spacing: 1px;">Official Seal</div>
                                </div>
                                <div style="text-align: right;">
                                    <div id="certSignatoryNameText" style="font-family: 'Great Vibes', cursive; font-size: 26px; color: #1E293B; line-height: 1;">Academic Director</div>
                                    <div id="certSignatoryTitleText" style="border-top: 1px solid #CBD5E1; padding-top: 2px; margin-top: 2px; font-size: 11px; font-weight: 600; color: #64748B;">Sparky Learning Platform</div>
                                </div>
                            </div>
                        </div>

                        <!-- Custom HTML Sandbox Preview Container -->
                        <div id="customCodePreviewWrap" style="display: none; width: 100%; display: none; justify-content: center; align-items: center;"></div>

                    </div>
                </div>

                <!-- EDITABLE CODE PANE -->
                <div id="codePane" style="display: none;">
                    <div class="code-editor-wrap">
                        <div class="code-editor-header" style="flex-wrap: wrap; gap: 8px;">
                            <div style="display: flex; gap: 5px; align-items: center; flex-wrap: wrap;">
                                <span style="font-size: 11px; color: #94A3B8; font-weight: 700; margin-right: 2px;">TAGS:</span>
                                <span class="tag-badge" onclick="insertTagIntoCode('{{STUDENT_NAME}}')">{{STUDENT_NAME}}</span>
                                <span class="tag-badge" onclick="insertTagIntoCode('{{COURSE_NAME}}')">{{COURSE_NAME}}</span>
                                <span class="tag-badge" onclick="insertTagIntoCode('{{DATE}}')">{{DATE}}</span>
                                <span class="tag-badge" onclick="insertTagIntoCode('{{CERTIFICATE_ID}}')">{{CERTIFICATE_ID}}</span>
                                <span class="tag-badge" onclick="insertTagIntoCode('{{ISSUER}}')">{{ISSUER}}</span>
                                <span class="tag-badge" onclick="insertTagIntoCode('{{SIGNATORY_NAME}}')">{{SIGNATORY_NAME}}</span>
                            </div>
                            
                            <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                                <button type="button" class="btn btn-xs btn-default" onclick="insertRotatedStudentTagSnippet()" title="Insert rotated student name tag with calligraphy">
                                    <i class="fa fa-repeat text-primary"></i> + Rotated Name Tag
                                </button>
                                <button type="button" class="btn btn-xs btn-default" onclick="insertSealSnippet()" title="Insert official seal badge">
                                    <i class="fa fa-shield text-warning"></i> + Gold Seal
                                </button>
                                <button type="button" class="btn btn-xs btn-default" onclick="insertSignatureSnippet()" title="Insert signatory signature block">
                                    <i class="fa fa-pencil-square-o text-info"></i> + Signatory
                                </button>
                            </div>

                            <div style="display: flex; gap: 6px; align-items: center;">
                                <button type="button" class="btn btn-xs btn-primary" onclick="syncVisualToCode()" style="background: #4F46E5; border: none; font-weight: 600;" title="Convert visual canvas into full editable HTML">
                                    <i class="fa fa-exchange"></i> Sync Designer to Code
                                </button>
                                <button type="button" class="btn btn-xs btn-default" onclick="beautifyHtmlCode()" title="Format and indent HTML code">
                                    <i class="fa fa-magic"></i> Format HTML
                                </button>
                                <button type="button" class="btn btn-xs btn-warning" onclick="resetCodeToTemplate()" title="Reset back to structured template">
                                    <i class="fa fa-undo"></i> Reset to Template
                                </button>
                                <button type="button" class="btn btn-xs btn-success" onclick="applyCodeToPreview()" style="font-weight: 600;">
                                    <i class="fa fa-play"></i> Apply to Preview
                                </button>
                            </div>
                        </div>
                        <textarea name="custom_html" id="customHtmlCode" class="code-textarea" placeholder="Enter custom HTML / CSS for certificate..." oninput="handleCodeInput()"><?php echo htmlspecialchars(!empty($blueprint['custom_html']) ? $blueprint['custom_html'] : ''); ?></textarea>
                    </div>
                    
                    <div style="margin-top: 10px; display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #64748B;">
                        <div>
                            <i class="fa fa-lightbulb-o text-warning"></i> Tip: Use <code>{{STUDENT_NAME}}</code>, <code>{{COURSE_NAME}}</code>, and inline CSS transforms like <code>transform: rotate(-10deg);</code> to create stunning certificate typography.
                        </div>
                        <div id="codeCharCount">0 characters</div>
                    </div>
                </div>

            </div>

        </div>

    </form>
</div>

<script>
var currentBgImageUrl = "<?php echo addslashes($blueprint['background_image']); ?>";
var isCustomCodeMode = <?php echo !empty($blueprint['custom_html']) ? 'true' : 'false'; ?>;
var codeDebounceTimer = null;

function syncRotationSlider(val) {
    document.getElementById('inputStudentRotationNum').value = val;
    document.getElementById('sliderStudentRotation').value = val;
    var layerSlider = document.getElementById('sliderStudentRotationLayers');
    if (layerSlider) layerSlider.value = val;
    var layerValText = document.getElementById('valStudentRotationLayers');
    if (layerValText) layerValText.innerText = val + '°';
    adjustLayerPositions();
}

function syncRotationInput(val) {
    var intVal = parseInt(val) || 0;
    if (intVal < -180) intVal = -180;
    if (intVal > 180) intVal = 180;
    syncRotationSlider(intVal);
}

function setRotationPreset(deg) {
    syncRotationSlider(deg);
}

function selectTemplate(tKey, cardElem) {
    document.getElementById('inputTemplateKey').value = tKey;
    document.querySelectorAll('.template-card').forEach(function(c) {
        c.classList.remove('active');
    });
    if (cardElem) cardElem.classList.add('active');

    var uploadWidget = document.getElementById('uploadTemplateWidget');
    if (tKey === 'custom_bg' || tKey === 'blank_student_only') {
        uploadWidget.style.display = 'block';
    } else {
        if (!currentBgImageUrl) {
            uploadWidget.style.display = 'none';
        }
    }

    if (tKey === 'blank_student_only') {
        // Set good default font for name overlay if not set
        var studentFontEl = document.getElementById('inputStudentFont');
        if (studentFontEl && studentFontEl.value === 'Playfair Display') {
            studentFontEl.value = 'Great Vibes';
        }
    }

    updateLivePreview();
}

function handleBgImageSelected(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            currentBgImageUrl = e.target.result;
            document.getElementById('inputExistingBgImage').value = currentBgImageUrl;
            document.getElementById('inputRemoveBgImage').value = '0';
            
            var tKey = document.getElementById('inputTemplateKey').value;
            if (tKey !== 'blank_student_only') {
                document.getElementById('inputTemplateKey').value = 'custom_bg';
                document.querySelectorAll('.template-card').forEach(function(c) { c.classList.remove('active'); });
                var customCard = document.querySelectorAll('.template-card')[4];
                if (customCard) customCard.classList.add('active');
            }

            updateLivePreview();
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function removeBackgroundImage() {
    currentBgImageUrl = '';
    document.getElementById('inputExistingBgImage').value = '';
    document.getElementById('inputRemoveBgImage').value = '1';
    document.getElementById('bgImageFileInput').value = '';
    document.getElementById('inputTemplateKey').value = 'classic_gold';
    
    document.querySelectorAll('.template-card').forEach(function(c) { c.classList.remove('active'); });
    var firstCard = document.querySelectorAll('.template-card')[0];
    if (firstCard) firstCard.classList.add('active');

    updateLivePreview();
}

function switchConfigTab(tabId, btnElem) {
    document.querySelectorAll('.gf-tab-content').forEach(function(t) {
        t.classList.remove('active');
    });
    document.querySelectorAll('.gf-tab-btn').forEach(function(b) {
        b.classList.remove('active');
    });
    document.getElementById(tabId).classList.add('active');
    btnElem.classList.add('active');
}

function switchViewTab(viewId, btnElem) {
    var previewPane = document.getElementById('previewPane');
    var codePane = document.getElementById('codePane');
    var tabBtnPreview = document.getElementById('tabBtnPreview');
    var tabBtnCode = document.getElementById('tabBtnCode');

    if (viewId === 'codePane') {
        previewPane.style.display = 'none';
        codePane.style.display = 'block';
        tabBtnPreview.classList.remove('active');
        tabBtnCode.classList.add('active');
        
        var codeArea = document.getElementById('customHtmlCode');
        if (!codeArea.value.trim()) {
            syncVisualToCode();
        }
        updateCodeCharCount();
    } else {
        previewPane.style.display = 'block';
        codePane.style.display = 'none';
        tabBtnCode.classList.remove('active');
        tabBtnPreview.classList.add('active');
        
        if (isCustomCodeMode) {
            applyCodeToPreview();
        } else {
            updateLivePreview();
        }
    }
}

function setPresetColor(hex) {
    document.getElementById('inputPrimaryColor').value = hex;
    document.getElementById('inputPrimaryColorHex').value = hex;
    updateLivePreview();
}

function adjustLayerPositions() {
    var studentFontSize = document.getElementById('sliderStudentFontSize').value;
    var studentOffsetX = document.getElementById('sliderStudentOffsetX').value;
    var studentOffsetY = document.getElementById('sliderStudentOffsetY').value;
    var studentRotation = document.getElementById('sliderStudentRotation').value;
    var studentLetterSpacing = document.getElementById('sliderStudentLetterSpacing').value;
    var studentFontWeight = document.getElementById('inputStudentFontWeight').value;
    var studentFont = document.getElementById('inputStudentFont').value;
    var primary = document.getElementById('inputPrimaryColor').value || '#E3122B';
    var courseOffset = document.getElementById('sliderCourseOffset').value;

    // Update text labels
    document.getElementById('valStudentFontSize').innerText = studentFontSize + 'px';
    document.getElementById('valStudentOffsetX').innerText = studentOffsetX + 'px';
    document.getElementById('valStudentOffsetY').innerText = studentOffsetY + 'px';
    document.getElementById('valStudentLetterSpacing').innerText = studentLetterSpacing + 'px';
    document.getElementById('valCourseOffset').innerText = courseOffset + 'px';
    var layerRotLabel = document.getElementById('valStudentRotationLayers');
    if (layerRotLabel) layerRotLabel.innerText = studentRotation + '°';

    var nameEl = document.getElementById('certStudentNameLayer');
    if (nameEl) {
        nameEl.style.transform = 'translate(' + studentOffsetX + 'px, ' + studentOffsetY + 'px) rotate(' + studentRotation + 'deg)';
        nameEl.style.fontSize = studentFontSize + 'px';
        nameEl.style.fontFamily = "'" + studentFont + "', cursive, serif";
        nameEl.style.fontWeight = studentFontWeight;
        nameEl.style.letterSpacing = studentLetterSpacing + 'px';
        nameEl.style.color = primary;
    }

    var courseEl = document.getElementById('certCourseNameText');
    if (courseEl) {
        courseEl.style.transform = 'translateY(' + courseOffset + 'px)';
    }
}

function toggleMinimalLayers(hideDefaults) {
    var emblem = document.getElementById('certEmblemHtml');
    var title = document.getElementById('certTitleText');
    var aboveStudent = document.getElementById('certAboveStudentText');
    var aboveCourse = document.getElementById('certAboveCourseText');
    var belowCourse = document.getElementById('certBelowCourseText');
    var footer = document.getElementById('certFooterSection');

    var displayVal = hideDefaults ? 'none' : 'block';
    if (emblem) emblem.style.display = displayVal;
    if (title) title.style.display = displayVal;
    if (aboveStudent) aboveStudent.style.display = displayVal;
    if (aboveCourse) aboveCourse.style.display = displayVal;
    if (belowCourse) belowCourse.style.display = displayVal;
    if (footer) footer.style.display = hideDefaults ? 'none' : 'flex';
}

function updateLivePreview() {
    var visualCanvas = document.getElementById('liveCertCanvas');
    var customWrap = document.getElementById('customCodePreviewWrap');
    var indicator = document.getElementById('canvasModeIndicator');

    if (isCustomCodeMode) {
        if (visualCanvas) visualCanvas.style.display = 'none';
        if (customWrap) {
            customWrap.style.display = 'flex';
            applyCodeToPreview();
        }
        if (indicator) {
            indicator.className = 'label label-warning';
            indicator.innerHTML = '<i class="fa fa-code"></i> Custom HTML Mode';
        }
        return;
    }

    if (visualCanvas) visualCanvas.style.display = 'flex';
    if (customWrap) customWrap.style.display = 'none';
    if (indicator) {
        indicator.className = 'label label-info';
        indicator.innerHTML = '<i class="fa fa-paint-brush"></i> Visual Designer Mode';
    }

    var tKey = document.getElementById('inputTemplateKey').value || 'classic_gold';
    var title = document.getElementById('inputTitle').value || 'CERTIFICATE OF COMPLETION';
    var textAboveStudent = document.getElementById('inputTextAboveStudent').value || 'This is to certify that';
    var textAboveCourse = document.getElementById('inputTextAboveCourse').value || 'Has pursued studies and completed all the requirements for';
    var textBelowCourse = document.getElementById('inputTextBelowCourse').value || 'Successfully';
    var issuer = document.getElementById('inputIssuerName').value || 'Sparky Learning';
    var sigName = document.getElementById('inputSignatoryName').value || 'Academic Director';
    var sigTitle = document.getElementById('inputSignatoryTitle').value || 'Sparky Learning Platform';
    var dateText = document.getElementById('inputDateText').value || '';
    var font = document.getElementById('inputFontFamily').value || 'Playfair Display';
    var primary = document.getElementById('inputPrimaryColor').value || '#4F46E5';
    var certName = document.getElementById('inputCertName').value || 'AI in Investing Masterclass';

    visualCanvas.style.fontFamily = "'" + font + "', serif";

    // Handle Background Image if uploaded
    if (currentBgImageUrl) {
        visualCanvas.className = 'cert-canvas cert-custom-bg';
        visualCanvas.style.backgroundImage = "url('" + currentBgImageUrl + "')";
    } else {
        visualCanvas.style.backgroundImage = 'none';
        visualCanvas.className = 'cert-canvas cert-' + tKey.replace(/_/g, '-');
    }

    if (tKey === 'blank_student_only') {
        toggleMinimalLayers(true);
        if (document.getElementById('certEmblemHtml')) document.getElementById('certEmblemHtml').innerHTML = '';
        if (document.getElementById('certCourseNameText')) document.getElementById('certCourseNameText').style.display = 'none';
        if (document.getElementById('certBelowCourseText')) document.getElementById('certBelowCourseText').style.display = 'none';
        var nameLayer = document.getElementById('certStudentNameLayer');
        if (nameLayer) {
            nameLayer.style.borderBottom = 'none';
            nameLayer.style.display = 'inline-block';
            nameLayer.style.padding = '0 32px 4px 32px';
            nameLayer.innerText = 'Johnathan Doe (Learner)';
        }
        adjustLayerPositions();
        return;
    }

    var minimalChecked = document.getElementById('toggleOnlyNameCourse') ? document.getElementById('toggleOnlyNameCourse').checked : false;
    if (!minimalChecked) {
        toggleMinimalLayers(false);
        if (document.getElementById('certCourseNameText')) document.getElementById('certCourseNameText').style.display = 'block';
        if (document.getElementById('certBelowCourseText')) document.getElementById('certBelowCourseText').style.display = 'block';
        if (document.getElementById('certStudentNameLayer')) {
            document.getElementById('certStudentNameLayer').style.borderBottom = '2px solid #E2E8F0';
            document.getElementById('certStudentNameLayer').style.display = 'inline-block';
            document.getElementById('certStudentNameLayer').style.padding = '0 32px 4px 32px';
            document.getElementById('certStudentNameLayer').innerText = 'Johnathan Doe (Learner)';
        }
    }

    var emblemHtml = '';
    if (tKey === 'et_masterclass') {
        emblemHtml = '<div style="font-weight: 900; font-size: 19px; letter-spacing: 2px; color: #111827; margin-bottom: 2px;">⚡ SPARKY</div><div style="font-weight: 800; font-size: 13px; letter-spacing: 3px; color: #4F46E5; margin-bottom: 10px;">EXCELLENCE</div>';
    } else if (tKey === 'modern_blue') {
        emblemHtml = '<div style="color: #0284C7; font-size: 28px; margin-bottom: 6px;"><i class="fa fa-graduation-cap"></i></div>';
    } else if (tKey === 'custom_bg') {
        emblemHtml = '';
    } else {
        emblemHtml = '<div style="color: ' + primary + '; font-size: 26px; margin-bottom: 6px;"><i class="fa fa-trophy"></i></div>';
    }
    
    if (document.getElementById('certEmblemHtml')) document.getElementById('certEmblemHtml').innerHTML = emblemHtml;
    if (document.getElementById('certTitleText')) document.getElementById('certTitleText').innerText = title;
    if (document.getElementById('certAboveStudentText')) document.getElementById('certAboveStudentText').innerText = textAboveStudent;
    if (document.getElementById('certAboveCourseText')) document.getElementById('certAboveCourseText').innerText = textAboveCourse;
    if (document.getElementById('certCourseNameText')) document.getElementById('certCourseNameText').innerText = certName;
    if (document.getElementById('certBelowCourseText')) document.getElementById('certBelowCourseText').innerText = textBelowCourse + (dateText ? ' • ' + dateText : '');
    if (document.getElementById('certIssuerText')) document.getElementById('certIssuerText').innerText = issuer;
    if (document.getElementById('certSignatoryNameText')) document.getElementById('certSignatoryNameText').innerText = sigName;
    if (document.getElementById('certSignatoryTitleText')) document.getElementById('certSignatoryTitleText').innerText = sigTitle;

    adjustLayerPositions();
}

function handleCodeInput() {
    isCustomCodeMode = true;
    document.getElementById('inputIsCustomCodeActive').value = '1';
    updateCodeCharCount();
    
    clearTimeout(codeDebounceTimer);
    codeDebounceTimer = setTimeout(function() {
        applyCodeToPreview();
    }, 250);
}

function updateCodeCharCount() {
    var txt = document.getElementById('customHtmlCode').value;
    document.getElementById('codeCharCount').innerText = txt.length + ' characters';
}

function applyCodeToPreview() {
    var rawHtml = document.getElementById('customHtmlCode').value;
    var customWrap = document.getElementById('customCodePreviewWrap');
    var visualCanvas = document.getElementById('liveCertCanvas');
    var indicator = document.getElementById('canvasModeIndicator');

    if (!rawHtml.trim()) {
        isCustomCodeMode = false;
        document.getElementById('inputIsCustomCodeActive').value = '0';
        if (customWrap) customWrap.style.display = 'none';
        if (visualCanvas) visualCanvas.style.display = 'flex';
        updateLivePreview();
        return;
    }

    isCustomCodeMode = true;
    document.getElementById('inputIsCustomCodeActive').value = '1';
    if (visualCanvas) visualCanvas.style.display = 'none';
    if (customWrap) customWrap.style.display = 'flex';
    
    if (indicator) {
        indicator.className = 'label label-warning';
        indicator.innerHTML = '<i class="fa fa-code"></i> Custom HTML Mode';
    }

    // Interpolate placeholders safely with live preview values
    var studentName = 'Johnathan Doe (Learner)';
    var courseName = document.getElementById('inputCertName').value || 'AI in Investing Masterclass';
    var dateText = document.getElementById('inputDateText').value || 'October 2026';
    var certId = 'SPARKY-CERT-DEMO-94827';
    var issuer = document.getElementById('inputIssuerName').value || 'Sparky Learning';
    var sigName = document.getElementById('inputSignatoryName').value || 'Academic Director';
    var sigTitle = document.getElementById('inputSignatoryTitle').value || 'Sparky Learning Platform';

    var rendered = rawHtml
        .replace(/\{\{STUDENT_NAME\}\}/g, studentName)
        .replace(/\{\{COURSE_NAME\}\}/g, courseName)
        .replace(/\{\{DATE\}\}/g, dateText)
        .replace(/\{\{CERTIFICATE_ID\}\}/g, certId)
        .replace(/\{\{ISSUER\}\}/g, issuer)
        .replace(/\{\{SIGNATORY_NAME\}\}/g, sigName)
        .replace(/\{\{SIGNATORY_TITLE\}\}/g, sigTitle);

    customWrap.innerHTML = rendered;
}

function syncVisualToCode() {
    var tKey = document.getElementById('inputTemplateKey').value || 'classic_gold';
    var title = document.getElementById('inputTitle').value || 'CERTIFICATE OF COMPLETION';
    var textAboveStudent = document.getElementById('inputTextAboveStudent').value || 'This is to certify that';
    var textAboveCourse = document.getElementById('inputTextAboveCourse').value || 'Has pursued studies and completed all the requirements for';
    var textBelowCourse = document.getElementById('inputTextBelowCourse').value || 'Awarded with Distinction';
    var issuer = document.getElementById('inputIssuerName').value || 'Sparky Learning';
    var sigName = document.getElementById('inputSignatoryName').value || 'Academic Director';
    var sigTitle = document.getElementById('inputSignatoryTitle').value || 'Sparky Learning Platform';
    var dateText = document.getElementById('inputDateText').value || 'October 2026';
    var font = document.getElementById('inputFontFamily').value || 'Playfair Display';
    var studentFont = document.getElementById('inputStudentFont').value || font;
    var primary = document.getElementById('inputPrimaryColor').value || '#4F46E5';
    var studentRotation = document.getElementById('sliderStudentRotation').value || '0';
    var studentFontSize = document.getElementById('sliderStudentFontSize').value || '32';
    var studentOffsetX = document.getElementById('sliderStudentOffsetX').value || '0';
    var studentOffsetY = document.getElementById('sliderStudentOffsetY').value || '0';
    var studentFontWeight = document.getElementById('inputStudentFontWeight').value || '700';
    var studentLetterSpacing = document.getElementById('sliderStudentLetterSpacing').value || '0';

    var studentTransformCss = 'transform: translate(' + studentOffsetX + 'px, ' + studentOffsetY + 'px) rotate(' + studentRotation + 'deg); transform-origin: center center; display: inline-block;';
    var studentStyleCss = 'font-family: \'' + studentFont + '\', cursive, serif; font-size: ' + studentFontSize + 'px; font-weight: ' + studentFontWeight + '; letter-spacing: ' + studentLetterSpacing + 'px; color: ' + primary + '; ' + studentTransformCss;

    var bgCss = '';
    var borderCss = 'border: 8px solid ' + primary + '; outline: 3px solid #D4AF37; outline-offset: -12px; background: radial-gradient(circle, #FFFFFF 70%, #FFF5F5 100%);';
    if (currentBgImageUrl) {
        bgCss = 'background-image: url(\'' + currentBgImageUrl + '\'); background-size: cover; background-position: center; border: 1px solid #CBD5E1;';
        borderCss = '';
    }

    var generatedHtml = '';

    if (tKey === 'blank_student_only') {
        generatedHtml = '<div style="width: 760px; height: 540px; box-sizing: border-box; display: flex; align-items: center; justify-content: center; position: relative; ' + (currentBgImageUrl ? bgCss : 'background: #FFFFFF; border: 2px dashed #CBD5E1;') + ' text-align: center; box-shadow: 0 10px 25px rgba(0,0,0,0.15);">\n' +
            '    <div style="' + studentStyleCss + '">\n' +
            '        {{STUDENT_NAME}}\n' +
            '    </div>\n' +
            '</div>';
    } else {
        generatedHtml = '<div style="width: 760px; height: 540px; padding: 36px 44px; box-sizing: border-box; display: flex; flex-direction: column; justify-content: space-between; text-align: center; position: relative; font-family: \'' + font + '\', serif; box-shadow: 0 12px 30px rgba(0,0,0,0.12); ' + (currentBgImageUrl ? bgCss : borderCss) + '">\n' +
            '    <div>\n' +
            '        <div style="font-weight: 900; font-size: 18px; letter-spacing: 2px; color: #111827; margin-bottom: 2px;">⚡ SPARKY</div>\n' +
            '        <div style="font-weight: 800; font-size: 12px; letter-spacing: 3px; color: ' + primary + '; margin-bottom: 8px;">EXCELLENCE</div>\n' +
            '        <div style="font-size: 20px; font-weight: 700; letter-spacing: 3px; color: #1E293B; text-transform: uppercase; margin-bottom: 8px;">' + title + '</div>\n' +
            '        <div style="font-size: 13px; color: #64748B; font-style: italic; margin-bottom: 10px;">' + textAboveStudent + '</div>\n' +
            '        <div style="margin-bottom: 10px;">\n' +
            '            <div style="' + studentStyleCss + ' border-bottom: 2px solid #E2E8F0; padding: 0 28px 4px 28px;">\n' +
            '                {{STUDENT_NAME}}\n' +
            '            </div>\n' +
            '        </div>\n' +
            '        <div style="font-size: 13px; color: #64748B; margin-bottom: 6px;">' + textAboveCourse + '</div>\n' +
            '        <div style="font-size: 18px; font-weight: 700; color: #0F172A; margin-bottom: 6px;">{{COURSE_NAME}}</div>\n' +
            '        <div style="font-size: 12px; color: #64748B;">' + textBelowCourse + ' &bull; {{DATE}}</div>\n' +
            '    </div>\n' +
            '    <div style="display: flex; justify-content: space-between; align-items: flex-end; padding-top: 14px; border-top: 1px solid #F1F5F9;">\n' +
            '        <div style="text-align: left;">\n' +
            '            <div style="font-size: 10px; color: #94A3B8; text-transform: uppercase; letter-spacing: 1px;">Issuer</div>\n' +
            '            <div style="font-size: 12.5px; font-weight: 600; color: #334155;">{{ISSUER}}</div>\n' +
            '            <div style="font-size: 10px; color: #94A3B8; margin-top: 2px;">ID: {{CERTIFICATE_ID}}</div>\n' +
            '        </div>\n' +
            '        <div>\n' +
            '            <div style="width: 48px; height: 48px; border-radius: 50%; background: linear-gradient(135deg, #F59E0B, #D97706); color: #FFF; display: inline-flex; align-items: center; justify-content: center; font-size: 20px; box-shadow: 0 4px 8px rgba(217,119,6,0.3);"><i class="fa fa-shield"></i></div>\n' +
            '            <div style="font-size: 9px; font-weight: 700; color: #D97706; text-transform: uppercase; margin-top: 2px;">Official Seal</div>\n' +
            '        </div>\n' +
            '        <div style="text-align: right;">\n' +
            '            <div style="font-family: \'Great Vibes\', cursive; font-size: 24px; color: #1E293B; line-height: 1;">{{SIGNATORY_NAME}}</div>\n' +
            '            <div style="border-top: 1px solid #CBD5E1; padding-top: 2px; margin-top: 2px; font-size: 11px; font-weight: 600; color: #64748B;">{{SIGNATORY_TITLE}}</div>\n' +
            '        </div>\n' +
            '    </div>\n' +
            '</div>';
    }

    document.getElementById('customHtmlCode').value = generatedHtml;
    isCustomCodeMode = true;
    document.getElementById('inputIsCustomCodeActive').value = '1';
    updateCodeCharCount();
    applyCodeToPreview();
}

function resetCodeToTemplate() {
    isCustomCodeMode = false;
    document.getElementById('inputIsCustomCodeActive').value = '0';
    document.getElementById('customHtmlCode').value = '';
    updateCodeCharCount();
    updateLivePreview();
}

function insertTagIntoCode(tag) {
    var textarea = document.getElementById('customHtmlCode');
    var startPos = textarea.selectionStart || 0;
    var endPos = textarea.selectionEnd || 0;
    var text = textarea.value;
    textarea.value = text.substring(0, startPos) + tag + text.substring(endPos, text.length);
    textarea.selectionStart = startPos + tag.length;
    textarea.selectionEnd = startPos + tag.length;
    textarea.focus();
    handleCodeInput();
}

function insertRotatedStudentTagSnippet() {
    var currentAngle = document.getElementById('sliderStudentRotation').value || '-5';
    var currentFont = document.getElementById('inputStudentFont').value || 'Great Vibes';
    var snippet = '<div style="font-family: \'' + currentFont + '\', cursive; font-size: 42px; font-weight: 700; color: #4F46E5; transform: rotate(' + currentAngle + 'deg); transform-origin: center center; display: inline-block;">\n    {{STUDENT_NAME}}\n</div>';
    insertTagIntoCode(snippet);
}

function insertSealSnippet() {
    var snippet = '<div style="text-align: center; display: inline-block;">\n    <div style="width: 52px; height: 52px; border-radius: 50%; background: linear-gradient(135deg, #F59E0B, #D97706); color: #FFF; display: inline-flex; align-items: center; justify-content: center; font-size: 22px; box-shadow: 0 4px 8px rgba(217,119,6,0.3);"><i class="fa fa-shield"></i></div>\n    <div style="font-size: 9.5px; font-weight: 700; color: #D97706; text-transform: uppercase; margin-top: 3px; letter-spacing: 1px;">Official Seal</div>\n</div>';
    insertTagIntoCode(snippet);
}

function insertSignatureSnippet() {
    var snippet = '<div style="text-align: right; display: inline-block;">\n    <div style="font-family: \'Great Vibes\', cursive; font-size: 26px; color: #1E293B; line-height: 1;">{{SIGNATORY_NAME}}</div>\n    <div style="border-top: 1px solid #CBD5E1; padding-top: 2px; margin-top: 2px; font-size: 11px; font-weight: 600; color: #64748B;">{{SIGNATORY_TITLE}}</div>\n</div>';
    insertTagIntoCode(snippet);
}

function beautifyHtmlCode() {
    var val = document.getElementById('customHtmlCode').value;
    if (!val) return;
    // Clean and normalize whitespace
    val = val.replace(/>\s*</g, '>\n<');
    var lines = val.split('\n');
    var formatted = '';
    var indent = 0;
    for (var i = 0; i < lines.length; i++) {
        var line = lines[i].trim();
        if (!line) continue;
        if (line.match(/^<\/\w/) || line.match(/^-->/)) {
            indent = Math.max(0, indent - 1);
        }
        var pad = '';
        for (var j = 0; j < indent; j++) pad += '    ';
        formatted += pad + line + '\n';
        if (line.match(/^<\w[^>]*[^\/>]$/) && !line.match(/^<(img|input|br|hr|meta|link)/i)) {
            indent++;
        }
    }
    document.getElementById('customHtmlCode').value = formatted.trim();
    handleCodeInput();
}

// Initial Live Render
if (isCustomCodeMode) {
    applyCodeToPreview();
} else {
    updateLivePreview();
}
</script>

<?php
Display::display_footer();
