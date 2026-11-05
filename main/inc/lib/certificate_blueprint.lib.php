<?php
/* For licensing terms, see /license.txt */

/**
 * Certificate Blueprint Library
 * Manages certificate templates, dynamic fields, and course assignments
 */
class CertificateBlueprint
{
    private static $tableBlueprint = 'c_certificate_blueprint';
    private static $tableCourse = 'c_certificate_blueprint_course';

    public static function initTables()
    {
        $tableBlueprint = self::$tableBlueprint;
        $tableCourse = self::$tableCourse;

        $sql1 = "CREATE TABLE IF NOT EXISTS `$tableBlueprint` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `template_key` VARCHAR(64) NOT NULL DEFAULT 'classic_gold',
            `title` VARCHAR(255) NOT NULL DEFAULT 'CERTIFICATE OF COMPLETION',
            `text_above_student` VARCHAR(255) NOT NULL DEFAULT 'This is to certify that',
            `text_above_course` VARCHAR(255) NOT NULL DEFAULT 'Has pursued studies and completed all the requirements for',
            `text_below_course` VARCHAR(255) NOT NULL DEFAULT 'Successfully',
            `issuer_name` VARCHAR(255) NOT NULL DEFAULT 'Sparky Learning',
            `signatory_name` VARCHAR(255) NOT NULL DEFAULT 'Academic Director',
            `signatory_title` VARCHAR(255) NOT NULL DEFAULT 'Director of Academic Affairs',
            `date_text` VARCHAR(255) NOT NULL DEFAULT 'August 22nd - 23rd, 2026',
            `font_family` VARCHAR(64) NOT NULL DEFAULT 'Playfair Display',
            `primary_color` VARCHAR(32) NOT NULL DEFAULT '#B45309',
            `custom_css` TEXT NULL,
            `custom_html` TEXT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        Database::query($sql1);

        $sql2 = "CREATE TABLE IF NOT EXISTS `$tableCourse` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `blueprint_id` INT NOT NULL,
            `course_code` VARCHAR(40) NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (`blueprint_id`),
            INDEX (`course_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        Database::query($sql2);

        // Ensure background_image column exists
        $colCheck = Database::query("SHOW COLUMNS FROM `$tableBlueprint` LIKE 'background_image'");
        if (Database::num_rows($colCheck) == 0) {
            Database::query("ALTER TABLE `$tableBlueprint` ADD COLUMN `background_image` VARCHAR(500) NULL AFTER `primary_color`");
        }

        $extraCols = [
            'student_font' => "VARCHAR(64) NULL DEFAULT ''",
            'student_rotation' => "INT NOT NULL DEFAULT 0",
            'student_font_size' => "INT NOT NULL DEFAULT 32",
            'student_offset_x' => "INT NOT NULL DEFAULT 0",
            'student_offset_y' => "INT NOT NULL DEFAULT 0",
            'student_font_weight' => "VARCHAR(20) NOT NULL DEFAULT '700'",
            'student_letter_spacing' => "INT NOT NULL DEFAULT 0"
        ];
        foreach ($extraCols as $col => $def) {
            $check = Database::query("SHOW COLUMNS FROM `$tableBlueprint` LIKE '$col'");
            if (Database::num_rows($check) == 0) {
                Database::query("ALTER TABLE `$tableBlueprint` ADD COLUMN `$col` $def");
            }
        }
    }


    public static function seedStarterBlueprints()
    {
        $starters = [
            [
                'id' => 2384,
                'name' => 'AI in investing',
                'template_key' => 'et_masterclass',
                'title' => 'CERTIFICATE OF COMPLETION',
                'text_above_student' => 'This is to certify that',
                'text_above_course' => 'Has pursued studies and completed all the requirements for',
                'text_below_course' => 'on August 22nd – 23rd, 2026.',
                'issuer_name' => 'Sparky Masterclass',
                'signatory_name' => 'Academic Director',
                'signatory_title' => 'Academic Director, Sparky',
                'date_text' => 'August 22nd - 23rd, 2026',
                'font_family' => 'Playfair Display',
                'primary_color' => '#4F46E5',
                'created_at' => '2026-09-05 18:03:00'
            ],
            [
                'id' => 2377,
                'name' => 'Summer camp batch 7',
                'template_key' => 'modern_blue',
                'title' => 'CERTIFICATE OF ACHIEVEMENT',
                'text_above_student' => 'Proudly presented to',
                'text_above_course' => 'For outstanding participation and completion of',
                'text_below_course' => 'Awarded with high distinction',
                'issuer_name' => 'Sparky Academy',
                'signatory_name' => 'Dr. R. Sharma',
                'signatory_title' => 'Academic Director',
                'date_text' => 'September 2nd, 2026',
                'font_family' => 'Montserrat',
                'primary_color' => '#0284C7',
                'created_at' => '2026-09-02 13:42:00'
            ],
            [
                'id' => 2357,
                'name' => 'HCL certificate',
                'template_key' => 'classic_gold',
                'title' => 'CERTIFICATE OF EXCELLENCE',
                'text_above_student' => 'This acknowledges that',
                'text_above_course' => 'Has successfully fulfilled all training modules for',
                'text_below_course' => 'Verified Executive Credential',
                'issuer_name' => 'Enterprise Learning Division',
                'signatory_name' => 'Amitabh Ray',
                'signatory_title' => 'VP, Talent Development',
                'date_text' => 'August 21st, 2026',
                'font_family' => 'Playfair Display',
                'primary_color' => '#D97706',
                'created_at' => '2026-08-21 13:31:00'
            ],
            [
                'id' => 2328,
                'name' => 'AIBP batch 9',
                'template_key' => 'et_masterclass',
                'title' => 'EXECUTIVE CERTIFICATE',
                'text_above_student' => 'Presented to',
                'text_above_course' => 'For completing the Artificial Intelligence Business Professional Program',
                'text_below_course' => 'With Specialization in Strategic AI Deployment',
                'issuer_name' => 'Sparky Learning',
                'signatory_name' => 'Academic Director',
                'signatory_title' => 'Director, Sparky Learning',
                'date_text' => 'August 10th, 2026',
                'font_family' => 'Cinzel',
                'primary_color' => '#4F46E5',
                'created_at' => '2026-08-10 11:09:00'
            ],
            [
                'id' => 2280,
                'name' => 'AIML batch 2',
                'template_key' => 'minimalist_gold',
                'title' => 'CERTIFICATE OF COMPLETION',
                'text_above_student' => 'This is awarded to',
                'text_above_course' => 'For demonstrating mastery in',
                'text_below_course' => 'Advanced Machine Learning & Neural Networks',
                'issuer_name' => 'AI Leadership Institute',
                'signatory_name' => 'Vikram Singhania',
                'signatory_title' => 'Head of AI Research',
                'date_text' => 'July 27th, 2026',
                'font_family' => 'Inter',
                'primary_color' => '#CA8A04',
                'created_at' => '2026-07-27 19:35:00'
            ],
            [
                'id' => 2269,
                'name' => 'AI for Leaders',
                'template_key' => 'et_masterclass',
                'title' => 'CERTIFICATE OF COMPLETION',
                'text_above_student' => 'This is to certify that',
                'text_above_course' => 'Has attended and graduated from the Masterclass',
                'text_below_course' => 'Empowering Leaders in the AI-First World',
                'issuer_name' => 'Sparky Learning',
                'signatory_name' => 'Academic Director',
                'signatory_title' => 'Director, Sparky Learning',
                'date_text' => 'July 17th, 2026',
                'font_family' => 'Playfair Display',
                'primary_color' => '#4F46E5',
                'created_at' => '2026-07-17 17:00:00'
            ]
        ];

        $tableBlueprint = self::$tableBlueprint;
        $tableCourse = self::$tableCourse;

        // Fetch sample courses
        $courses = CourseManager::get_courses_list();
        $courseCodes = !empty($courses) ? array_column($courses, 'code') : ['AI101', 'DEV202'];

        foreach ($starters as $idx => $s) {
            $sql = "INSERT INTO `$tableBlueprint` (
                `id`, `name`, `template_key`, `title`, `text_above_student`, `text_above_course`,
                `text_below_course`, `issuer_name`, `signatory_name`, `signatory_title`, `date_text`,
                `font_family`, `primary_color`, `created_at`, `updated_at`
            ) VALUES (
                {$s['id']},
                '".Database::escape_string($s['name'])."',
                '".Database::escape_string($s['template_key'])."',
                '".Database::escape_string($s['title'])."',
                '".Database::escape_string($s['text_above_student'])."',
                '".Database::escape_string($s['text_above_course'])."',
                '".Database::escape_string($s['text_below_course'])."',
                '".Database::escape_string($s['issuer_name'])."',
                '".Database::escape_string($s['signatory_name'])."',
                '".Database::escape_string($s['signatory_title'])."',
                '".Database::escape_string($s['date_text'])."',
                '".Database::escape_string($s['font_family'])."',
                '".Database::escape_string($s['primary_color'])."',
                '{$s['created_at']}',
                '{$s['created_at']}'
            )";
            Database::query($sql);

            // Assign course if available
            if ($s['id'] !== 2357 && !empty($courseCodes)) {
                $cCode = $courseCodes[$idx % count($courseCodes)];
                Database::query("INSERT INTO `$tableCourse` (`blueprint_id`, `course_code`) VALUES ({$s['id']}, '".Database::escape_string($cCode)."')");
            }
        }
    }

    public static function getAllBlueprints($search = '')
    {
        self::initTables();
        $tableBlueprint = self::$tableBlueprint;
        $tableCourse = self::$tableCourse;

        $where = '';
        if (!empty($search)) {
            $s = Database::escape_string($search);
            $where = "WHERE b.name LIKE '%$s%' OR b.title LIKE '%$s%'";
        }

        $sql = "SELECT b.*, COUNT(bc.course_code) AS course_count
                FROM `$tableBlueprint` b
                LEFT JOIN `$tableCourse` bc ON b.id = bc.blueprint_id
                $where
                GROUP BY b.id
                ORDER BY b.id DESC";

        $res = Database::query($sql);
        $list = [];
        while ($row = Database::fetch_assoc($res)) {
            $list[] = $row;
        }
        return $list;
    }

    public static function getBlueprint($id)
    {
        self::initTables();
        $id = (int)$id;
        if ($id <= 0) return null;

        $tableBlueprint = self::$tableBlueprint;
        $tableCourse = self::$tableCourse;

        $sql = "SELECT * FROM `$tableBlueprint` WHERE id = $id LIMIT 1";
        $res = Database::query($sql);
        $bp = Database::fetch_assoc($res);
        if (!$bp) return null;

        // Get linked courses
        $sqlC = "SELECT course_code FROM `$tableCourse` WHERE blueprint_id = $id";
        $resC = Database::query($sqlC);
        $bp['course_codes'] = [];
        while ($rC = Database::fetch_assoc($resC)) {
            $bp['course_codes'][] = $rC['course_code'];
        }

        return $bp;
    }

    public static function saveBlueprint($data, $id = 0)
    {
        self::initTables();
        $id = (int)$id;
        $tableBlueprint = self::$tableBlueprint;
        $tableCourse = self::$tableCourse;

        $name = isset($data['name']) ? trim($data['name']) : 'New Certificate';
        $templateKey = isset($data['template_key']) ? trim($data['template_key']) : 'classic_gold';
        $title = isset($data['title']) ? trim($data['title']) : 'CERTIFICATE OF COMPLETION';
        $textAboveStudent = isset($data['text_above_student']) ? trim($data['text_above_student']) : 'This is to certify that';
        $textAboveCourse = isset($data['text_above_course']) ? trim($data['text_above_course']) : 'Has pursued studies and completed all the requirements for';
        $textBelowCourse = isset($data['text_below_course']) ? trim($data['text_below_course']) : 'Successfully';
        $issuerName = isset($data['issuer_name']) ? trim($data['issuer_name']) : 'Sparky Learning';
        $signatoryName = isset($data['signatory_name']) ? trim($data['signatory_name']) : 'Academic Director';
        $signatoryTitle = isset($data['signatory_title']) ? trim($data['signatory_title']) : 'Director of Academic Affairs';
        $dateText = isset($data['date_text']) ? trim($data['date_text']) : date('F jS, Y');
        $fontFamily = isset($data['font_family']) ? trim($data['font_family']) : 'Playfair Display';
        $primaryColor = isset($data['primary_color']) ? trim($data['primary_color']) : '#4F46E5';
        $backgroundImage = isset($data['background_image']) ? trim($data['background_image']) : '';
        $studentFont = isset($data['student_font']) ? trim($data['student_font']) : '';
        $studentRotation = isset($data['student_rotation']) ? (int)$data['student_rotation'] : 0;
        $studentFontSize = isset($data['student_font_size']) ? (int)$data['student_font_size'] : 34;
        $studentOffsetX = isset($data['student_offset_x']) ? (int)$data['student_offset_x'] : 0;
        $studentOffsetY = isset($data['student_offset_y']) ? (int)$data['student_offset_y'] : 0;
        $studentFontWeight = isset($data['student_font_weight']) ? trim($data['student_font_weight']) : '700';
        $studentLetterSpacing = isset($data['student_letter_spacing']) ? (int)$data['student_letter_spacing'] : 0;
        $customHtml = isset($data['custom_html']) ? trim($data['custom_html']) : '';
        $customCss = isset($data['custom_css']) ? trim($data['custom_css']) : '';
        $courses = isset($data['courses']) && is_array($data['courses']) ? $data['courses'] : [];

        if ($id > 0) {
            $sql = "UPDATE `$tableBlueprint` SET
                `name` = '".Database::escape_string($name)."',
                `template_key` = '".Database::escape_string($templateKey)."',
                `title` = '".Database::escape_string($title)."',
                `text_above_student` = '".Database::escape_string($textAboveStudent)."',
                `text_above_course` = '".Database::escape_string($textAboveCourse)."',
                `text_below_course` = '".Database::escape_string($textBelowCourse)."',
                `issuer_name` = '".Database::escape_string($issuerName)."',
                `signatory_name` = '".Database::escape_string($signatoryName)."',
                `signatory_title` = '".Database::escape_string($signatoryTitle)."',
                `date_text` = '".Database::escape_string($dateText)."',
                `font_family` = '".Database::escape_string($fontFamily)."',
                `primary_color` = '".Database::escape_string($primaryColor)."',
                `background_image` = '".Database::escape_string($backgroundImage)."',
                `student_font` = '".Database::escape_string($studentFont)."',
                `student_rotation` = $studentRotation,
                `student_font_size` = $studentFontSize,
                `student_offset_x` = $studentOffsetX,
                `student_offset_y` = $studentOffsetY,
                `student_font_weight` = '".Database::escape_string($studentFontWeight)."',
                `student_letter_spacing` = $studentLetterSpacing,
                `custom_html` = '".Database::escape_string($customHtml)."',
                `custom_css` = '".Database::escape_string($customCss)."',
                `updated_at` = NOW()
                WHERE id = $id";
            Database::query($sql);
            $blueprintId = $id;
        } else {
            $sql = "INSERT INTO `$tableBlueprint` (
                `name`, `template_key`, `title`, `text_above_student`, `text_above_course`,
                `text_below_course`, `issuer_name`, `signatory_name`, `signatory_title`, `date_text`,
                `font_family`, `primary_color`, `background_image`, `student_font`, `student_rotation`,
                `student_font_size`, `student_offset_x`, `student_offset_y`, `student_font_weight`,
                `student_letter_spacing`, `custom_html`, `custom_css`, `created_at`, `updated_at`
            ) VALUES (
                '".Database::escape_string($name)."',
                '".Database::escape_string($templateKey)."',
                '".Database::escape_string($title)."',
                '".Database::escape_string($textAboveStudent)."',
                '".Database::escape_string($textAboveCourse)."',
                '".Database::escape_string($textBelowCourse)."',
                '".Database::escape_string($issuerName)."',
                '".Database::escape_string($signatoryName)."',
                '".Database::escape_string($signatoryTitle)."',
                '".Database::escape_string($dateText)."',
                '".Database::escape_string($fontFamily)."',
                '".Database::escape_string($primaryColor)."',
                '".Database::escape_string($backgroundImage)."',
                '".Database::escape_string($studentFont)."',
                $studentRotation,
                $studentFontSize,
                $studentOffsetX,
                $studentOffsetY,
                '".Database::escape_string($studentFontWeight)."',
                $studentLetterSpacing,
                '".Database::escape_string($customHtml)."',
                '".Database::escape_string($customCss)."',
                NOW(),
                NOW()
            )";
            Database::query($sql);
            $blueprintId = Database::insert_id();
        }

        if ($blueprintId > 0 && isset($data['courses'])) {
            Database::query("DELETE FROM `$tableCourse` WHERE blueprint_id = $blueprintId");
            foreach ($courses as $cCode) {
                $cCode = trim($cCode);
                if (!empty($cCode)) {
                    Database::query("INSERT INTO `$tableCourse` (`blueprint_id`, `course_code`) VALUES ($blueprintId, '".Database::escape_string($cCode)."')");
                }
            }
        }

        return $blueprintId;
    }

    public static function deleteBlueprint($id)
    {
        self::initTables();
        $id = (int)$id;
        if ($id <= 0) return false;

        Database::query("DELETE FROM `".self::$tableCourse."` WHERE blueprint_id = $id");
        Database::query("DELETE FROM `".self::$tableBlueprint."` WHERE id = $id");
        return true;
    }

    public static function duplicateBlueprint($id)
    {
        $bp = self::getBlueprint($id);
        if (!$bp) return false;

        $bp['name'] = $bp['name'] . ' (Copy)';
        $bp['courses'] = [];
        return self::saveBlueprint($bp, 0);
    }

    public static function getBlueprintForCourse($courseCode)
    {
        self::initTables();
        $courseCode = Database::escape_string($courseCode);
        $tableBlueprint = self::$tableBlueprint;
        $tableCourse = self::$tableCourse;

        $sql = "SELECT b.* FROM `$tableBlueprint` b 
                INNER JOIN `$tableCourse` bc ON b.id = bc.blueprint_id 
                WHERE bc.course_code = '$courseCode' LIMIT 1";
        $res = Database::query($sql);
        return Database::fetch_assoc($res);
    }

    public static function setCourseBlueprint($courseCode, $blueprintId)
    {
        self::initTables();
        $courseCode = Database::escape_string($courseCode);
        $tableCourse = self::$tableCourse;

        Database::query("DELETE FROM `$tableCourse` WHERE course_code = '$courseCode'");
        $blueprintId = (int)$blueprintId;
        if ($blueprintId > 0) {
            Database::query("INSERT INTO `$tableCourse` (`blueprint_id`, `course_code`) VALUES ($blueprintId, '$courseCode')");
        }
    }

    /**
     * Render the certificate HTML for a blueprint with dynamic placeholders replaced
     */
    public static function renderHtml($bp, $studentName, $courseTitle, $dateText = '', $certId = '')
    {
        if (empty($bp)) return '';

        if (empty($dateText)) {
            $dateText = !empty($bp['date_text']) ? $bp['date_text'] : date('F jS, Y');
        }
        if (empty($certId)) {
            $certId = 'SPARKY-'.strtoupper(substr(md5($studentName.$courseTitle.$dateText), 0, 10));
        }

        // If custom HTML is provided, replace placeholders
        if (!empty($bp['custom_html']) && trim($bp['custom_html']) !== '') {
            $html = $bp['custom_html'];
            $replacements = [
                '{{STUDENT_NAME}}' => htmlspecialchars($studentName),
                '{{COURSE_NAME}}' => htmlspecialchars($courseTitle),
                '{{DATE}}' => htmlspecialchars($dateText),
                '{{CERTIFICATE_ID}}' => htmlspecialchars($certId),
                '{{ISSUER}}' => htmlspecialchars(!empty($bp['issuer_name']) ? $bp['issuer_name'] : 'Sparky Learning'),
                '{{SIGNATORY_NAME}}' => htmlspecialchars(!empty($bp['signatory_name']) ? $bp['signatory_name'] : 'Academic Director'),
                '{{SIGNATORY_TITLE}}' => htmlspecialchars(!empty($bp['signatory_title']) ? $bp['signatory_title'] : 'Director of Academic Affairs'),
            ];
            foreach ($replacements as $tag => $val) {
                $html = str_replace($tag, $val, $html);
            }
            if (!empty($bp['custom_css'])) {
                $html = '<style>'.$bp['custom_css'].'</style>' . $html;
            }
            return $html;
        }

        // Standard structured template rendering
        $tKey = !empty($bp['template_key']) ? $bp['template_key'] : 'et_masterclass';
        $title = !empty($bp['title']) ? $bp['title'] : 'CERTIFICATE OF COMPLETION';
        $textAboveStudent = !empty($bp['text_above_student']) ? $bp['text_above_student'] : 'This is to certify that';
        $textAboveCourse = !empty($bp['text_above_course']) ? $bp['text_above_course'] : 'Has pursued studies and completed all the requirements for';
        $textBelowCourse = !empty($bp['text_below_course']) ? $bp['text_below_course'] : 'Successfully';
        $issuer = !empty($bp['issuer_name']) ? $bp['issuer_name'] : 'Sparky Learning';
        $sigName = !empty($bp['signatory_name']) ? $bp['signatory_name'] : 'Academic Director';
        $sigTitle = !empty($bp['signatory_title']) ? $bp['signatory_title'] : 'Director of Academic Affairs';
        $font = !empty($bp['font_family']) ? $bp['font_family'] : 'Playfair Display';
        $primary = !empty($bp['primary_color']) ? $bp['primary_color'] : '#4F46E5';
        $bgImage = !empty($bp['background_image']) ? $bp['background_image'] : '';

        // Student typography & rotation
        $studentFont = !empty($bp['student_font']) ? $bp['student_font'] : $font;
        $studentRotation = isset($bp['student_rotation']) ? (int)$bp['student_rotation'] : 0;
        $studentFontSize = !empty($bp['student_font_size']) ? (int)$bp['student_font_size'] : 34;
        $studentOffsetX = isset($bp['student_offset_x']) ? (int)$bp['student_offset_x'] : 0;
        $studentOffsetY = isset($bp['student_offset_y']) ? (int)$bp['student_offset_y'] : 0;
        $studentFontWeight = !empty($bp['student_font_weight']) ? $bp['student_font_weight'] : '700';
        $studentLetterSpacing = isset($bp['student_letter_spacing']) ? (int)$bp['student_letter_spacing'] : 0;

        $studentTransform = "transform: translate({$studentOffsetX}px, {$studentOffsetY}px) rotate({$studentRotation}deg); transform-origin: center center; display: inline-block;";
        $studentInlineStyle = "color: {$primary}; font-family: '{$studentFont}', cursive, serif; font-size: {$studentFontSize}px; font-weight: {$studentFontWeight}; letter-spacing: {$studentLetterSpacing}px; {$studentTransform}";

        // Plain Blank Template: Only student name centered for custom image background
        if ($tKey === 'blank_student_only') {
            $canvasClass = 'cert-canvas cert-blank-student-only';
            $canvasBg = $bgImage ? "background-image: url('".htmlspecialchars($bgImage)."'); background-size: cover; background-position: center; background-repeat: no-repeat;" : 'background: #FFFFFF; border: 1px dashed #CBD5E1;';
            return '
            <div class="'.$canvasClass.'" style="font-family: \''.$font.'\', serif; '.$canvasBg.' width: 760px; height: 540px; padding: 36px 44px; box-sizing: border-box; display: flex; flex-direction: column; justify-content: space-between; text-align: center; position: relative; margin: 0 auto; user-select: none; overflow: hidden;">
                <div class="cert-header-sec" style="text-align: center;">
                    <div style="display: inline-block; position: relative; margin-bottom: 12px;">
                        <div class="cert-student-name" style="'.$studentInlineStyle.' text-align: center; border-bottom: none; padding: 0 32px 4px 32px; display: inline-block;">
                            '.htmlspecialchars($studentName).'
                        </div>
                    </div>
                </div>
            </div>';
        }

        $emblemHtml = '';
        if ($tKey === 'et_masterclass') {
            $emblemHtml = '<div style="font-weight: 900; font-size: 19px; letter-spacing: 2px; color: #111827; margin-bottom: 2px;">SPARKY LEARNING</div><div style="font-weight: 800; font-size: 13px; letter-spacing: 3px; color: #4F46E5; margin-bottom: 10px;">MASTERCLASS</div>';
        } elseif ($tKey === 'modern_blue') {
            $emblemHtml = '<div style="color: #0284C7; font-size: 28px; margin-bottom: 6px;"><i class="fa fa-graduation-cap"></i></div>';
        } elseif ($tKey === 'custom_bg') {
            $emblemHtml = '';
        } else {
            $emblemHtml = '<div style="color: '.$primary.'; font-size: 26px; margin-bottom: 6px;"><i class="fa fa-trophy"></i></div>';
        }

        $canvasClass = 'cert-canvas cert-' . str_replace('_', '-', $tKey);
        $canvasBg = $bgImage ? "background-image: url('".htmlspecialchars($bgImage)."'); background-size: cover; background-position: center; background-repeat: no-repeat;" : '';

        $html = '
        <div class="'.$canvasClass.'" style="font-family: \''.$font.'\', serif; '.$canvasBg.' width: 760px; height: 540px; padding: 36px 44px; box-sizing: border-box; display: flex; flex-direction: column; justify-content: space-between; text-align: center; position: relative; margin: 0 auto; user-select: none; overflow: hidden;">
            <div class="cert-header-sec" style="text-align: center;">
                '.$emblemHtml.'
                <div class="cert-title-text" style="font-size: 22px; font-weight: 700; letter-spacing: 3px; color: #1E293B; text-transform: uppercase; margin-bottom: 10px;">'.htmlspecialchars($title).'</div>
                <div class="cert-sub-text" style="font-size: 13.5px; color: #64748B; font-style: italic; margin-bottom: 12px;">'.htmlspecialchars($textAboveStudent).'</div>
                <div style="display: inline-block; position: relative; margin-bottom: 12px;">
                    <div class="cert-student-name" style="'.$studentInlineStyle.' border-bottom: 2px solid #E2E8F0; padding: 0 32px 4px 32px; display: inline-block;">'.htmlspecialchars($studentName).'</div>
                </div>
                <div class="cert-sub-text" style="font-size: 13px; color: #64748B; margin-bottom: 8px;">'.htmlspecialchars($textAboveCourse).'</div>
                <div class="cert-course-name" style="font-size: 19px; font-weight: 700; color: #0F172A; margin-bottom: 8px;">'.htmlspecialchars($courseTitle).'</div>
                <div class="cert-sub-text" style="font-size: 12.5px; color: #64748B;">'.htmlspecialchars($textBelowCourse).' &bull; '.htmlspecialchars($dateText).'</div>
            </div>
            <div class="cert-footer-sec" style="display: flex; justify-content: space-between; align-items: flex-end; padding-top: 18px; border-top: 1px solid #F1F5F9;">
                <div class="cert-footer-left" style="text-align: left;">
                    <div class="cert-label" style="font-size: 10.5px; color: #94A3B8; text-transform: uppercase; letter-spacing: 1px;">Issuer</div>
                    <div class="cert-val" style="font-size: 13px; font-weight: 600; color: #334155;">'.htmlspecialchars($issuer).'</div>
                    <div class="cert-id" style="font-size: 10px; color: #94A3B8; margin-top: 3px;">ID: '.htmlspecialchars($certId).'</div>
                </div>
                <div class="cert-seal" style="text-align: center;">
                    <div class="cert-seal-badge" style="width: 50px; height: 50px; border-radius: 50%; background: linear-gradient(135deg, #F59E0B, #D97706); color: #FFF; display: inline-flex; align-items: center; justify-content: center; font-size: 22px; box-shadow: 0 4px 8px rgba(217,119,6,0.3);"><i class="fa fa-shield"></i></div>
                    <div class="cert-seal-text" style="font-size: 9.5px; font-weight: 700; color: #D97706; text-transform: uppercase; margin-top: 3px; letter-spacing: 1px;">Official Seal</div>
                </div>
                <div class="cert-footer-right" style="text-align: right;">
                    <div class="cert-sig-name" style="font-family: \'Great Vibes\', cursive; font-size: 26px; color: #1E293B; line-height: 1;">'.htmlspecialchars($sigName).'</div>
                    <div class="cert-sig-title" style="border-top: 1px solid #CBD5E1; padding-top: 2px; margin-top: 2px; font-size: 11px; font-weight: 600; color: #64748B;">'.htmlspecialchars($sigTitle).'</div>
                </div>
            </div>
        </div>';

        return $html;
    }

    public static function hasCertificateForCourse($courseCode)
    {
        $bp = self::getBlueprintForCourse($courseCode);
        return !empty($bp);
    }

    public static function getCourseBlueprintMap()
    {
        self::initTables();
        $tableBlueprint = self::$tableBlueprint;
        $tableCourse = self::$tableCourse;

        $sql = "SELECT bc.course_code, b.id, b.name, b.title, b.template_key, b.issuer_name, b.primary_color
                FROM `$tableCourse` bc
                INNER JOIN `$tableBlueprint` b ON bc.blueprint_id = b.id";
        $res = Database::query($sql);
        $map = [];
        while ($row = Database::fetch_assoc($res)) {
            $map[$row['course_code']] = $row;
        }
        return $map;
    }

    /**
     * Helper to render blueprint for a given course and user
     * Returns empty string if the course has no assigned certificate
     */
    public static function renderForStudent($courseCode, $userId = 0)
    {
        $blueprint = self::getBlueprintForCourse($courseCode);
        if (!$blueprint) {
            // No certificate assigned to this course
            return '';
        }

        $userId = $userId > 0 ? $userId : api_get_user_id();
        $userInfo = api_get_user_info($userId);
        $studentName = !empty($userInfo) ? api_get_person_name($userInfo['firstname'], $userInfo['lastname']) : 'Learner';

        $courseInfo = api_get_course_info($courseCode);
        $courseTitle = !empty($courseInfo['title']) ? $courseInfo['title'] : $courseCode;

        $certId = 'SPARKY-CERT-' . $userId . '-' . strtoupper(substr(md5($courseCode.$userId), 0, 6));
        return self::renderHtml($blueprint, $studentName, $courseTitle, date('F jS, Y'), $certId);
    }
}

