<?php
/* For licensing terms, see /license.txt */

/**
 * Awarded Certificates Record & Management for Administrators.
 */

$cidReset = true;
require_once __DIR__ . '/../inc/global.inc.php';
require_once __DIR__ . '/../inc/lib/certificate_blueprint.lib.php';

api_protect_admin_script(true);
$this_section = SECTION_PLATFORM_ADMIN;

$search = isset($_GET['search']) ? Security::remove_XSS(trim($_GET['search'])) : '';
$courseFilter = isset($_GET['course']) ? Security::remove_XSS(trim($_GET['course'])) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Export CSV handler
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=awarded_certificates_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Student Name', 'Student Email', 'Course Code', 'Course Title', 'Blueprint Name', 'Certificate Code', 'Times Collected', 'First Collected Date', 'Last Collected Date']);
    
    $exportSql = "SELECT * FROM `c_certificate_awarded` ORDER BY `last_collected_at` DESC";
    $exportRes = Database::query($exportSql);
    while ($row = Database::fetch_array($exportRes, 'ASSOC')) {
        fputcsv($output, [
            $row['id'],
            $row['user_fullname'],
            $row['user_email'],
            $row['course_code'],
            $row['course_title'],
            $row['blueprint_name'],
            $row['certificate_code'],
            $row['download_count'],
            $row['first_collected_at'],
            $row['last_collected_at']
        ]);
    }
    fclose($output);
    exit;
}

// Delete / Revoke Handler
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $delId = (int)$_GET['id'];
    Database::query("DELETE FROM `c_certificate_awarded` WHERE id = $delId");
    Display::addFlash(Display::return_message('Certificate collection record successfully removed.', 'success'));
    header('Location: awarded_certificates.php');
    exit;
}

// Ensure table exists
CertificateBlueprint::initTables();
$tableCheck = Database::query("SHOW TABLES LIKE 'c_certificate_awarded'");
if (Database::num_rows($tableCheck) == 0) {
    Database::query("CREATE TABLE IF NOT EXISTS `c_certificate_awarded` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `user_fullname` VARCHAR(255) NOT NULL,
        `user_email` VARCHAR(255) NOT NULL,
        `course_code` VARCHAR(40) NOT NULL,
        `course_title` VARCHAR(255) NOT NULL,
        `blueprint_id` INT NOT NULL DEFAULT 0,
        `blueprint_name` VARCHAR(255) NOT NULL DEFAULT '',
        `certificate_code` VARCHAR(64) NOT NULL,
        `download_count` INT NOT NULL DEFAULT 1,
        `first_collected_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `last_collected_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `user_course` (`user_id`, `course_code`),
        INDEX (`user_id`),
        INDEX (`course_code`),
        INDEX (`certificate_code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// Metrics
$totalAwarded = (int)Database::fetch_assoc(Database::query("SELECT COUNT(*) as cnt FROM `c_certificate_awarded`"))['cnt'];
$totalStudents = (int)Database::fetch_assoc(Database::query("SELECT COUNT(DISTINCT user_id) as cnt FROM `c_certificate_awarded`"))['cnt'];
$totalDownloads = (int)Database::fetch_assoc(Database::query("SELECT SUM(download_count) as cnt FROM `c_certificate_awarded`"))['cnt'];
$totalCoursesWithBp = (int)Database::fetch_assoc(Database::query("SELECT COUNT(DISTINCT course_code) as cnt FROM `c_certificate_blueprint_course`"))['cnt'];

// Build Filter SQL
$where = ["1=1"];
if (!empty($search)) {
    $s = Database::escape_string($search);
    $where[] = "(`user_fullname` LIKE '%$s%' OR `user_email` LIKE '%$s%' OR `certificate_code` LIKE '%$s%' OR `course_title` LIKE '%$s%')";
}
if (!empty($courseFilter)) {
    $c = Database::escape_string($courseFilter);
    $where[] = "`course_code` = '$c'";
}
$whereClause = implode(' AND ', $where);

$totalFiltered = (int)Database::fetch_assoc(Database::query("SELECT COUNT(*) as cnt FROM `c_certificate_awarded` WHERE $whereClause"))['cnt'];
$recordsRes = Database::query("SELECT * FROM `c_certificate_awarded` WHERE $whereClause ORDER BY `last_collected_at` DESC LIMIT $offset, $limit");

$allCoursesRes = Database::query("SELECT DISTINCT c.code, c.title FROM `course` c JOIN `c_certificate_blueprint_course` cbc ON c.code = cbc.course_code ORDER BY c.title ASC");

$tool_name = 'Awarded Certificates Log';
$interbreadcrumb[] = ['url' => 'index.php', 'name' => get_lang('PlatformAdmin')];
$interbreadcrumb[] = ['url' => 'certificate_blueprints.php', 'name' => 'Certificate Blueprints'];

$webPath = api_get_path(WEB_PATH);
$webCodePath = api_get_path(WEB_CODE_PATH);

Display::display_header($tool_name);
?>

<div class="row" style="margin-top: 15px;">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <div>
                <h2 style="font-weight: 800; color: #0F172A; margin: 0 0 5px 0;">Awarded Certificates Log</h2>
                <p class="text-muted" style="margin: 0; font-size: 14px;">Real-time audit log of all students who have collected and downloaded their completion certificates.</p>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="awarded_certificates.php?action=export_csv" class="btn btn-default rounded-pill" style="border-radius: 9999px;">
                    <i class="fa fa-download me-1"></i> Export CSV
                </a>
                <a href="certificate_generate.php" class="btn btn-default rounded-pill" style="border-radius: 9999px; font-weight: 600; color: #4338CA; border-color: #C7D2FE; background: #EEF2FF;">
                    <i class="fa fa-certificate me-1"></i> Batch Issue Certificates
                </a>
                <a href="certificate_blueprints.php" class="btn btn-primary rounded-pill" style="border-radius: 9999px; background-color: #e3122b; border-color: #e3122b;">
                    <i class="fa fa-sliders me-1"></i> Certificate Blueprints
                </a>
            </div>
        </div>

        <!-- Metric KPI Cards -->
        <div class="row" style="margin-bottom: 25px;">
            <div class="col-md-3 col-sm-6">
                <div class="panel panel-default" style="border-radius: 12px; border: 1px solid #E2E8F0; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <div style="font-size: 12px; font-weight: 700; color: #64748B; text-transform: uppercase;">Total Certificates Issued</div>
                    <div style="font-size: 28px; font-weight: 800; color: #0F172A; margin-top: 5px;"><?php echo number_format($totalAwarded); ?></div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="panel panel-default" style="border-radius: 12px; border: 1px solid #E2E8F0; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <div style="font-size: 12px; font-weight: 700; color: #64748B; text-transform: uppercase;">Certified Students</div>
                    <div style="font-size: 28px; font-weight: 800; color: #10B981; margin-top: 5px;"><?php echo number_format($totalStudents); ?></div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="panel panel-default" style="border-radius: 12px; border: 1px solid #E2E8F0; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <div style="font-size: 12px; font-weight: 700; color: #64748B; text-transform: uppercase;">Total Downloads / Views</div>
                    <div style="font-size: 28px; font-weight: 800; color: #0284C7; margin-top: 5px;"><?php echo number_format($totalDownloads); ?></div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="panel panel-default" style="border-radius: 12px; border: 1px solid #E2E8F0; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <div style="font-size: 12px; font-weight: 700; color: #64748B; text-transform: uppercase;">Active Courses with Certs</div>
                    <div style="font-size: 28px; font-weight: 800; color: #e3122b; margin-top: 5px;"><?php echo number_format($totalCoursesWithBp); ?></div>
                </div>
            </div>
        </div>

        <!-- Filter & Search Bar -->
        <div class="panel panel-default" style="border-radius: 12px; border: 1px solid #E2E8F0; padding: 15px; margin-bottom: 20px;">
            <form method="get" action="awarded_certificates.php" class="form-inline" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <div class="form-group" style="flex: 1; min-width: 250px;">
                    <input type="text" name="search" class="form-control" placeholder="Search by student name, email, code..." value="<?php echo htmlspecialchars($search); ?>" style="width: 100%; border-radius: 8px;">
                </div>
                <div class="form-group" style="min-width: 200px;">
                    <select name="course" class="form-control" style="width: 100%; border-radius: 8px;" onchange="this.form.submit()">
                        <option value="">All Certified Courses</option>
                        <?php while ($cRow = Database::fetch_array($allCoursesRes, 'ASSOC')): ?>
                            <option value="<?php echo htmlspecialchars($cRow['code']); ?>" <?php echo ($courseFilter === $cRow['code']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cRow['title'] . ' (' . $cRow['code'] . ')'); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-default" style="border-radius: 8px;"><i class="fa fa-search"></i> Filter</button>
                <?php if (!empty($search) || !empty($courseFilter)): ?>
                    <a href="awarded_certificates.php" class="btn btn-link text-muted">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Awarded Certificates Table -->
        <div class="panel panel-default" style="border-radius: 12px; border: 1px solid #E2E8F0; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle" style="margin-bottom: 0;">
                    <thead style="background-color: #F8FAFC; border-bottom: 1px solid #E2E8F0;">
                        <tr>
                            <th style="padding: 12px 16px; font-weight: 700; color: #475569; text-transform: uppercase; font-size: 11px;">Student</th>
                            <th style="padding: 12px 16px; font-weight: 700; color: #475569; text-transform: uppercase; font-size: 11px;">Course</th>
                            <th style="padding: 12px 16px; font-weight: 700; color: #475569; text-transform: uppercase; font-size: 11px;">Certificate Code</th>
                            <th style="padding: 12px 16px; font-weight: 700; color: #475569; text-transform: uppercase; font-size: 11px;">Blueprint</th>
                            <th style="padding: 12px 16px; font-weight: 700; color: #475569; text-transform: uppercase; font-size: 11px; text-align: center;">Downloads</th>
                            <th style="padding: 12px 16px; font-weight: 700; color: #475569; text-transform: uppercase; font-size: 11px;">Collected Date</th>
                            <th style="padding: 12px 16px; font-weight: 700; color: #475569; text-transform: uppercase; font-size: 11px; text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (Database::num_rows($recordsRes) == 0): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted" style="padding: 40px;">
                                    <i class="fa fa-certificate fs-1 text-muted mb-2 d-block" style="font-size: 36px; opacity: 0.5;"></i>
                                    <div style="font-size: 16px; font-weight: 600;">No certificate collection records found</div>
                                    <small class="text-muted">When students claim or download certificates from "My certificates", they will be recorded here automatically.</small>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php while ($r = Database::fetch_array($recordsRes, 'ASSOC')): ?>
                                <tr>
                                    <td style="padding: 14px 16px;">
                                        <div style="font-weight: 700; color: #0F172A;"><?php echo htmlspecialchars($r['user_fullname']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($r['user_email']); ?></small>
                                    </td>
                                    <td style="padding: 14px 16px;">
                                        <div style="font-weight: 600; color: #1E293B;"><?php echo htmlspecialchars($r['course_title']); ?></div>
                                        <small class="text-muted">Code: <?php echo htmlspecialchars($r['course_code']); ?></small>
                                    </td>
                                    <td style="padding: 14px 16px;">
                                        <code style="background-color: #EEF2FF; color: #635BFF; padding: 4px 8px; border-radius: 4px; font-weight: 700; font-size: 12px;">
                                            <?php echo htmlspecialchars($r['certificate_code']); ?>
                                        </code>
                                    </td>
                                    <td style="padding: 14px 16px;">
                                        <span class="text-muted" style="font-size: 13px;"><?php echo htmlspecialchars($r['blueprint_name']); ?></span>
                                    </td>
                                    <td style="padding: 14px 16px; text-align: center;">
                                        <span class="badge" style="background-color: #E0F2FE; color: #0284C7; font-weight: 700; font-size: 12px; border-radius: 9999px; padding: 4px 10px;">
                                            <?php echo (int)$r['download_count']; ?>x
                                        </span>
                                    </td>
                                    <td style="padding: 14px 16px;">
                                        <div style="font-size: 13px; color: #334155;"><?php echo date('M d, Y', strtotime($r['last_collected_at'])); ?></div>
                                        <small class="text-muted"><?php echo date('h:i A', strtotime($r['last_collected_at'])); ?></small>
                                    </td>
                                    <td style="padding: 14px 16px; text-align: right;">
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?php echo $webCodePath; ?>gradebook/view_blueprint_certificate.php?course_code=<?php echo urlencode($r['course_code']); ?>&user_id=<?php echo (int)$r['user_id']; ?>" target="_blank" class="btn btn-default" title="View Certificate" style="border-radius: 6px;">
                                                <i class="fa fa-eye"></i> View
                                            </a>
                                            <a href="<?php echo $webCodePath; ?>gradebook/view_blueprint_certificate.php?course_code=<?php echo urlencode($r['course_code']); ?>&user_id=<?php echo (int)$r['user_id']; ?>&action=print" target="_blank" class="btn btn-danger" title="Download PDF" style="border-radius: 6px; background-color: #e3122b; border-color: #e3122b;">
                                                <i class="fa fa-download"></i> PDF
                                            </a>
                                            <a href="awarded_certificates.php?action=delete&id=<?php echo (int)$r['id']; ?>" class="btn btn-default text-danger" title="Remove Record" onclick="return confirm('Are you sure you want to remove this record?');" style="border-radius: 6px;">
                                                <i class="fa fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalFiltered > $limit): 
                $totalPages = ceil($totalFiltered / $limit);
            ?>
                <div class="panel-footer" style="background-color: #FFFFFF; border-top: 1px solid #E2E8F0; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center;">
                    <span class="text-muted small">Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $totalFiltered); ?> of <?php echo $totalFiltered; ?> records</span>
                    <ul class="pagination pagination-sm" style="margin: 0;">
                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <li class="<?php echo ($p === $page) ? 'active' : ''; ?>">
                                <a href="awarded_certificates.php?page=<?php echo $p; ?>&search=<?php echo urlencode($search); ?>&course=<?php echo urlencode($courseFilter); ?>"><?php echo $p; ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php
Display::display_footer();
