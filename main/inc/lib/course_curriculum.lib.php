<?php
/* For licensing terms, see /license.txt */

/**
 * Sparky-Style Course Curriculum Library
 * Manages Chapters, Lessons, Formatted Media, and Document Engine Integration
 */

class CourseCurriculum
{
    private static $tblChapter = 'c_curriculum_chapter';
    private static $tblLesson = 'c_curriculum_lesson';

    public static function initTables()
    {
        $tblChapter = self::$tblChapter;
        $tblLesson = self::$tblLesson;

        $sql1 = "CREATE TABLE IF NOT EXISTS `$tblChapter` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `course_code` VARCHAR(40) NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `description` TEXT NULL,
            `display_order` INT NOT NULL DEFAULT 1,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (`course_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        Database::query($sql1);

        $sql2 = "CREATE TABLE IF NOT EXISTS `$tblLesson` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `chapter_id` INT NOT NULL,
            `course_code` VARCHAR(40) NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `lesson_type` VARCHAR(50) NOT NULL DEFAULT 'video',
            `content_url` TEXT NULL,
            `content_text` LONGTEXT NULL,
            `file_path` VARCHAR(255) NULL,
            `file_size` INT NOT NULL DEFAULT 0,
            `mime_type` VARCHAR(100) NULL,
            `duration` VARCHAR(50) NOT NULL DEFAULT '10:00',
            `is_draft` TINYINT(1) NOT NULL DEFAULT 0,
            `is_free_preview` TINYINT(1) NOT NULL DEFAULT 0,
            `is_prerequisite` TINYINT(1) NOT NULL DEFAULT 0,
            `downloadable_web` TINYINT(1) NOT NULL DEFAULT 0,
            `downloadable_app` TINYINT(1) NOT NULL DEFAULT 0,
            `enable_discussions` TINYINT(1) NOT NULL DEFAULT 1,
            `completion_required` TINYINT(1) NOT NULL DEFAULT 1,
            `block_forward_seeking` TINYINT(1) NOT NULL DEFAULT 0,
            `autoplay` TINYINT(1) NOT NULL DEFAULT 1,
            `enable_analytics` TINYINT(1) NOT NULL DEFAULT 1,
            `motivational_popup` VARCHAR(255) NULL,
            `display_order` INT NOT NULL DEFAULT 1,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (`chapter_id`),
            INDEX (`course_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        Database::query($sql2);

        // Ensure new columns exist
        $sql3 = "CREATE TABLE IF NOT EXISTS `c_curriculum_lesson_track` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `course_code` VARCHAR(40) NOT NULL,
            `lesson_id` INT NOT NULL,
            `completed` TINYINT(1) NOT NULL DEFAULT 1,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `user_course_lesson` (`user_id`, `course_code`, `lesson_id`),
            INDEX (`user_id`, `course_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        Database::query($sql3);

        $cols = ['file_path' => 'VARCHAR(255) NULL', 'file_size' => 'INT NOT NULL DEFAULT 0', 'mime_type' => 'VARCHAR(100) NULL'];
        foreach ($cols as $colName => $colDef) {
            $check = Database::query("SHOW COLUMNS FROM `$tblLesson` LIKE '$colName'");
            if (Database::num_rows($check) == 0) {
                Database::query("ALTER TABLE `$tblLesson` ADD `$colName` $colDef");
            }
        }
    }

    public static function resolveMediaUrl($url)
    {
        if (empty($url)) {
            return '';
        }
        $url = trim($url);
        // If it's a local upload in app/upload/curriculum/<course>/<file>
        if (preg_match('~upload/curriculum/([^/?#]+)/([^/?#]+)~i', $url, $m)) {
            return '/app/upload/curriculum/' . $m[1] . '/' . $m[2];
        }
        return $url;
    }
public static function formatVideoEmbedUrl($url)
    {
        $url = trim($url);
        if (empty($url)) return '';

        // YouTube: https://www.youtube.com/watch?v=XYZ or https://youtu.be/XYZ or embed
        if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i', $url, $m)) {
            return 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?rel=0&modestbranding=1&enablejsapi=1&autoplay=0';
        }

        // Vimeo: https://vimeo.com/123456789
        if (preg_match('/vimeo\.com\/(?:channels\/(?:\w+\/)?|groups\/([^\/]*)\/videos\/|album\/(\d+)\/video\/|video\/|)(\d+)/i', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[3] . '?title=0&byline=0&portrait=0';
        }

        return $url;
    }

    public static function detectLessonTypeFromFilename($filename)
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $videoExts = ['mp4', 'webm', 'mkv', 'mov', 'm4v', 'avi', 'flv', 'wmv', 'ogv', '3gp'];
        $pdfExts = ['pdf'];
        $audioExts = ['mp3', 'wav', 'm4a', 'ogg', 'flac', 'aac', 'wma'];
        $docExts = ['doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'epub', 'rtf', 'odt', 'csv', 'zip', 'rar'];

        if (in_array($ext, $videoExts)) return 'video';
        if (in_array($ext, $pdfExts)) return 'pdf';
        if (in_array($ext, $audioExts)) return 'audio';
        if (in_array($ext, $docExts)) return 'document';
        return 'document';
    }

    /**
     * Upload and register study material in Chamilo Document engine and curriculum storage
     */
    public static function handleUploadedFile($fileArray, $courseCode, $lessonTitle = '')
    {
        if (empty($fileArray) || empty($fileArray['name']) || $fileArray['error'] !== UPLOAD_ERR_OK) {
            return false;
        }

        $courseInfo = api_get_course_info($courseCode);
        $cRealId = !empty($courseInfo['real_id']) ? (int)$courseInfo['real_id'] : 0;
        if ($cRealId === 0) {
            $courseTable = Database::get_main_table(TABLE_MAIN_COURSE);
            $cRes = Database::query("SELECT id FROM $courseTable WHERE code = '" . Database::escape_string($courseCode) . "' LIMIT 1");
            if ($cRow = Database::fetch_assoc($cRes)) {
                $cRealId = (int)$cRow['id'];
            }
        }
        $currentUserId = api_get_user_id();
        if ($currentUserId <= 0) {
            $currentUserId = 1;
        }

        $origName = $fileArray['name'];
        $fileSize = (int)$fileArray['size'];
        $mimeType = !empty($fileArray['type']) ? $fileArray['type'] : mime_content_type($fileArray['tmp_name']);
        $detectedType = self::detectLessonTypeFromFilename($origName);

        // Sanitize filename
        $fileInfo = pathinfo($origName);
        $ext = strtolower($fileInfo['extension']);
        $cleanBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', $fileInfo['filename']);
        $savedFileName = $cleanBase . '_' . time() . '.' . $ext;

        // Ensure physical upload directory exists
        $uploadSysDir = api_get_path(SYS_APP_PATH) . 'upload/curriculum/' . $courseCode . '/';
        if (!is_dir($uploadSysDir)) {
            @mkdir($uploadSysDir, 0777, true);
        }

        $destSysPath = $uploadSysDir . $savedFileName;
        $moved = @move_uploaded_file($fileArray['tmp_name'], $destSysPath);
        if (!$moved) {
            $moved = @copy($fileArray['tmp_name'], $destSysPath);
            if ($moved) {
                @unlink($fileArray['tmp_name']);
            }
        }
        if (!$moved) {
            return false;
        }

        $webUrl = api_get_path(WEB_APP_PATH) . 'upload/curriculum/' . $courseCode . '/' . $savedFileName;
        $relPath = 'upload/curriculum/' . $courseCode . '/' . $savedFileName;

        // Register in Chamilo Document database engine (c_document & c_item_property)
        if ($cRealId > 0) {
            try {
                $docTable = Database::get_course_table(TABLE_DOCUMENT);
                $itemPropTable = Database::get_course_table(TABLE_ITEM_PROPERTY);
                $docPath = '/curriculum/' . $savedFileName;
                $docTitle = !empty($lessonTitle) ? $lessonTitle : $origName;

                // Insert into c_document (session_id = 0 for standard course document)
                $sqlDoc = "INSERT INTO $docTable (c_id, path, comment, title, filetype, size, readonly, session_id) 
                           VALUES ($cRealId, '".Database::escape_string($docPath)."', 'Uploaded via Curriculum Engine', '".Database::escape_string($docTitle)."', 'file', $fileSize, 0, 0)";
                Database::query($sqlDoc);
                $docId = Database::insert_id();

                if ($docId > 0) {
                    // Register in c_item_property (session_id MUST be NULL due to FK constraint to session table)
                    $now = api_get_utc_datetime();
                    $sqlProp = "INSERT INTO $itemPropTable (c_id, tool, insert_user_id, insert_date, lastedit_date, ref, lastedit_type, lastedit_user_id, visibility, session_id)
                                VALUES ($cRealId, 'document', $currentUserId, '$now', '$now', $docId, 'DocumentAdded', $currentUserId, 1, NULL)";
                    Database::query($sqlProp);
                }
            } catch (\Throwable $t) {
                // Non-fatal document indexing failure, physical file is safely stored
                error_log("Curriculum Document indexing notice: " . $t->getMessage());
            }
        }

        return [
            'original_name' => $origName,
            'saved_name' => $savedFileName,
            'web_url' => $webUrl,
            'rel_path' => $relPath,
            'file_size' => $fileSize,
            'mime_type' => $mimeType,
            'lesson_type' => $detectedType
        ];
    }

    public static function getCurriculum($courseCode)
    {
        self::initTables();
        $courseCode = Database::escape_string($courseCode);
        $tblChapter = self::$tblChapter;
        $tblLesson = self::$tblLesson;

        $sql = "SELECT * FROM `$tblChapter` WHERE course_code = '$courseCode' ORDER BY display_order ASC, id ASC";
        $res = Database::query($sql);
        $chapters = [];

        while ($row = Database::fetch_assoc($res)) {
            $chId = (int)$row['id'];
            $sqlLessons = "SELECT * FROM `$tblLesson` WHERE chapter_id = $chId ORDER BY display_order ASC, id ASC";
            $resL = Database::query($sqlLessons);
            $lessons = [];
            while ($lRow = Database::fetch_assoc($resL)) {
                $lessons[] = $lRow;
            }
            $row['lessons'] = $lessons;
            $chapters[] = $row;
        }

        if (empty($chapters)) {
            self::seedDefaultCurriculum($courseCode);
            return self::getCurriculum($courseCode);
        }

        return $chapters;
    }

    public static function seedDefaultCurriculum($courseCode)
    {
        self::initTables();
        $courseCode = Database::escape_string($courseCode);
        $tblChapter = self::$tblChapter;
        $tblLesson = self::$tblLesson;

        Database::query("INSERT INTO `$tblChapter` (`course_code`, `title`, `display_order`) VALUES ('$courseCode', 'Chapter 1: Orientation & Course Roadmap', 1)");
        $ch1 = Database::insert_id();

        Database::query("INSERT INTO `$tblLesson` (
            `chapter_id`, `course_code`, `title`, `lesson_type`, `content_url`, `content_text`, `duration`,
            `is_free_preview`, `enable_discussions`, `completion_required`, `autoplay`, `enable_analytics`, `display_order`
        ) VALUES (
            $ch1, '$courseCode', 'Welcome & Program Orientation', 'video',
            'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4', '', '08:45',
            1, 1, 1, 1, 1, 1
        )");

        Database::query("INSERT INTO `$tblChapter` (`course_code`, `title`, `display_order`) VALUES ('$courseCode', 'Study Material - Module 1 (English)', 2)");
        $ch2 = Database::insert_id();

        Database::query("INSERT INTO `$tblLesson` (
            `chapter_id`, `course_code`, `title`, `lesson_type`, `content_url`, `content_text`, `duration`,
            `is_free_preview`, `enable_discussions`, `completion_required`, `autoplay`, `enable_analytics`, `display_order`
        ) VALUES (
            $ch2, '$courseCode', 'Introduction to financial planning', 'video',
            'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4', '', '14:20',
            1, 1, 1, 1, 1, 1
        )");

        Database::query("INSERT INTO `$tblLesson` (
            `chapter_id`, `course_code`, `title`, `lesson_type`, `content_url`, `content_text`, `duration`,
            `downloadable_web`, `enable_discussions`, `completion_required`, `enable_analytics`, `display_order`
        ) VALUES (
            $ch2, '$courseCode', 'Benefits of financial planning', 'pdf',
            'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf', '', '15 min read',
            1, 1, 1, 1, 2
        )");

        Database::query("INSERT INTO `$tblLesson` (
            `chapter_id`, `course_code`, `title`, `lesson_type`, `content_url`, `content_text`, `duration`,
            `enable_discussions`, `completion_required`, `enable_analytics`, `display_order`
        ) VALUES (
            $ch2, '$courseCode', 'Steps for financial planning', 'document', '',
            '<h2>Mastering the 5 Pillars of Financial Strategy</h2><p>In this module, we dissect the foundational principles of long-term asset allocation, risk mitigation, and cash flow compounding.</p><ul><li>Step 1: Emergency Reserve Fund Management</li><li>Step 2: Debt Optimization & Cost of Capital</li><li>Step 3: Equity Growth Allocation</li><li>Step 4: Tax Optimization</li><li>Step 5: Estate & Succession Planning</li></ul>',
            '10 min read', 1, 1, 1, 3
        )");
    }

    public static function createChapter($courseCode, $title)
    {
        self::initTables();
        $courseCode = Database::escape_string($courseCode);
        $title = Database::escape_string($title);
        $tblChapter = self::$tblChapter;

        $maxOrder = (int)Database::result(Database::query("SELECT MAX(display_order) FROM `$tblChapter` WHERE course_code = '$courseCode'"), 0, 0);
        $order = $maxOrder + 1;

        Database::query("INSERT INTO `$tblChapter` (`course_code`, `title`, `display_order`) VALUES ('$courseCode', '$title', $order)");
        return Database::insert_id();
    }

    public static function createLesson($chapterId, $courseCode, $title, $type = 'video')
    {
        self::initTables();
        $chapterId = (int)$chapterId;
        $courseCode = Database::escape_string($courseCode);
        $title = Database::escape_string($title);
        $type = Database::escape_string($type);
        $tblLesson = self::$tblLesson;

        $maxOrder = (int)Database::result(Database::query("SELECT MAX(display_order) FROM `$tblLesson` WHERE chapter_id = $chapterId"), 0, 0);
        $order = $maxOrder + 1;

        Database::query("INSERT INTO `$tblLesson` (`chapter_id`, `course_code`, `title`, `lesson_type`, `display_order`) VALUES ($chapterId, '$courseCode', '$title', '$type', $order)");
        return Database::insert_id();
    }

    public static function getLesson($id)
    {
        self::initTables();
        $id = (int)$id;
        $tblLesson = self::$tblLesson;
        $sql = "SELECT * FROM `$tblLesson` WHERE id = $id LIMIT 1";
        $res = Database::query($sql);
        return Database::fetch_assoc($res);
    }

    public static function saveLesson($id, $data)
    {
        self::initTables();
        $id = (int)$id;
        if ($id <= 0) return false;
        $tblLesson = self::$tblLesson;

        $title = isset($data['title']) ? Database::escape_string(trim($data['title'])) : '';
        $lessonType = isset($data['lesson_type']) ? Database::escape_string(trim($data['lesson_type'])) : 'video';
        $contentUrl = isset($data['content_url']) ? Database::escape_string(trim($data['content_url'])) : '';
        $contentText = isset($data['content_text']) ? Database::escape_string($data['content_text']) : '';
        $duration = isset($data['duration']) ? Database::escape_string(trim($data['duration'])) : '10:00';
        $filePath = isset($data['file_path']) ? Database::escape_string(trim($data['file_path'])) : '';
        $fileSize = isset($data['file_size']) ? (int)$data['file_size'] : 0;
        $mimeType = isset($data['mime_type']) ? Database::escape_string(trim($data['mime_type'])) : '';

        $isDraft = !empty($data['is_draft']) ? 1 : 0;
        $isFreePreview = !empty($data['is_free_preview']) ? 1 : 0;
        $isPrerequisite = !empty($data['is_prerequisite']) ? 1 : 0;
        $downloadableWeb = !empty($data['downloadable_web']) ? 1 : 0;
        $downloadableApp = !empty($data['downloadable_app']) ? 1 : 0;
        $enableDiscussions = !empty($data['enable_discussions']) ? 1 : 0;
        $completionRequired = !empty($data['completion_required']) ? 1 : 0;
        $blockForwardSeeking = !empty($data['block_forward_seeking']) ? 1 : 0;
        $autoplay = !empty($data['autoplay']) ? 1 : 0;
        $enableAnalytics = !empty($data['enable_analytics']) ? 1 : 0;
        $motivationalPopup = isset($data['motivational_popup']) ? Database::escape_string(trim($data['motivational_popup'])) : '';

        // If filePath or contentUrl is updated with empty fileSize, keep existing if not provided
        $extraUpdates = "";
        if (!empty($filePath)) {
            $extraUpdates .= ", `file_path` = '$filePath'";
        }
        if ($fileSize > 0) {
            $extraUpdates .= ", `file_size` = $fileSize";
        }
        if (!empty($mimeType)) {
            $extraUpdates .= ", `mime_type` = '$mimeType'";
        }

        $sql = "UPDATE `$tblLesson` SET
            `title` = '$title',
            `lesson_type` = '$lessonType',
            `content_url` = '$contentUrl',
            `content_text` = '$contentText',
            `duration` = '$duration',
            `is_draft` = $isDraft,
            `is_free_preview` = $isFreePreview,
            `is_prerequisite` = $isPrerequisite,
            `downloadable_web` = $downloadableWeb,
            `downloadable_app` = $downloadableApp,
            `enable_discussions` = $enableDiscussions,
            `completion_required` = $completionRequired,
            `block_forward_seeking` = $blockForwardSeeking,
            `autoplay` = $autoplay,
            `enable_analytics` = $enableAnalytics,
            `motivational_popup` = '$motivationalPopup'
            $extraUpdates
            WHERE id = $id";

        return Database::query($sql);
    }

    public static function deleteLesson($id)
    {
        self::initTables();
        $id = (int)$id;
        if ($id <= 0) return false;
        Database::query("DELETE FROM `".self::$tblLesson."` WHERE id = $id");
        return true;
    }

    public static function deleteChapter($id)
    {
        self::initTables();
        $id = (int)$id;
        if ($id <= 0) return false;
        Database::query("DELETE FROM `".self::$tblLesson."` WHERE chapter_id = $id");
        Database::query("DELETE FROM `".self::$tblChapter."` WHERE id = $id");
        return true;
    }

    public static function parseDurationToMinutes($str)
    {
        if (empty($str)) return 0;
        $str = strtolower(trim($str));

        // Case 1: MM:SS or HH:MM:SS format
        if (preg_match('/^(\d+):(\d+)(?::(\d+))?$/', $str, $m)) {
            if (!empty($m[3])) {
                return ((int)$m[1] * 60) + (int)$m[2] + round((int)$m[3] / 60);
            } else {
                return (int)$m[1] + round((int)$m[2] / 60);
            }
        }

        $minutes = 0;
        $hasMatch = false;

        // Check hours
        if (preg_match('/([\d.]+)\s*(?:hours|hour|hrs|hr|h)\b/i', $str, $m)) {
            $minutes += round((float)$m[1] * 60);
            $hasMatch = true;
        }

        // Check minutes
        if (preg_match('/(\d+)\s*(?:minutes|minute|mins|min|m)\b/i', $str, $m)) {
            $minutes += (int)$m[1];
            $hasMatch = true;
        }

        if ($hasMatch) {
            return $minutes;
        }

        if (is_numeric($str)) {
            return (int)$str;
        }

        return 10;
    }

    public static function formatMinutes($totalMinutes)
    {
        if ($totalMinutes <= 0) return '';
        if ($totalMinutes < 60) {
            return $totalMinutes . ' Mins';
        }
        $hours = floor($totalMinutes / 60);
        $remMins = $totalMinutes % 60;
        if ($remMins == 0) {
            return $hours == 1 ? '1 Hour' : $hours . ' Hours';
        }
        return $hours . 'h ' . $remMins . 'm';
    }

    public static function getCourseStats($courseCode)
    {
        self::initTables();
        $courseCode = Database::escape_string($courseCode);
        $tblLesson = self::$tblLesson;

        $sql = "SELECT id, duration, lesson_type FROM `$tblLesson` WHERE course_code = '$courseCode' AND is_draft = 0";
        $res = Database::query($sql);
        $totalLessons = Database::num_rows($res);

        $totalMinutes = 0;
        while ($row = Database::fetch_assoc($res)) {
            $totalMinutes += self::parseDurationToMinutes($row['duration']);
        }

        // If no curriculum lessons, check standard LP items or documents
        if ($totalLessons === 0) {
            $courseInfo = api_get_course_info($courseCode);
            if (!empty($courseInfo['real_id'])) {
                $cRealId = (int)$courseInfo['real_id'];
                $tLpItem = Database::get_course_table(TABLE_LP_ITEM);
                $resLp = Database::query("SELECT count(*) as c FROM $tLpItem WHERE c_id = $cRealId");
                if ($rowLp = Database::fetch_assoc($resLp)) {
                    $totalLessons = (int)$rowLp['c'];
                }
                if ($totalLessons > 0) {
                    $totalMinutes = $totalLessons * 15;
                }
            }
        }

        $durationFormatted = self::formatMinutes($totalMinutes);

        return [
            'lesson_count' => $totalLessons,
            'total_minutes' => $totalMinutes,
            'duration_formatted' => $durationFormatted
        ];
    }

    public static function getUserCompletedLessons($userId, $courseCode)
    {
        self::initTables();
        $userId = (int)$userId;
        $courseCode = Database::escape_string($courseCode);
        if ($userId <= 0 || empty($courseCode)) {
            return [];
        }

        $sql = "SELECT lesson_id FROM `c_curriculum_lesson_track` WHERE user_id = $userId AND course_code = '$courseCode' AND completed = 1";
        $res = Database::query($sql);
        $completedIds = [];
        while ($row = Database::fetch_assoc($res)) {
            $completedIds[] = (int)$row['lesson_id'];
        }
        return $completedIds;
    }

    public static function saveUserLessonCompletion($userId, $courseCode, $lessonId, $completed = 1)
    {
        self::initTables();
        $userId = (int)$userId;
        $courseCode = Database::escape_string($courseCode);
        $lessonId = (int)$lessonId;
        $completedVal = $completed ? 1 : 0;

        if ($userId <= 0 || empty($courseCode) || $lessonId <= 0) {
            return false;
        }

        $sql = "INSERT INTO `c_curriculum_lesson_track` (`user_id`, `course_code`, `lesson_id`, `completed`)
                VALUES ($userId, '$courseCode', $lessonId, $completedVal)
                ON DUPLICATE KEY UPDATE `completed` = $completedVal, `updated_at` = NOW()";
        Database::query($sql);
        return true;
    }

    public static function getUserProgress($userId, $courseCode)
    {
        self::initTables();
        $userId = (int)$userId;
        $courseCode = Database::escape_string($courseCode);
        if ($userId <= 0 || empty($courseCode)) {
            return 0;
        }

        $tblLesson = self::$tblLesson;
        $resTotal = Database::query("SELECT COUNT(*) as c FROM `$tblLesson` WHERE course_code = '$courseCode' AND is_draft = 0");
        $rowTotal = Database::fetch_assoc($resTotal);
        $totalLessons = !empty($rowTotal['c']) ? (int)$rowTotal['c'] : 0;

        if ($totalLessons > 0) {
            $resCompleted = Database::query("SELECT COUNT(DISTINCT lesson_id) as c FROM `c_curriculum_lesson_track` WHERE user_id = $userId AND course_code = '$courseCode' AND completed = 1");
            $rowCompleted = Database::fetch_assoc($resCompleted);
            $completedLessons = !empty($rowCompleted['c']) ? (int)$rowCompleted['c'] : 0;

            return (int)min(100, round(($completedLessons / $totalLessons) * 100));
        }

        // Fallback to standard Chamilo LP progress if no curriculum lessons
        $lpProgress = Tracking::get_avg_student_progress($userId, $courseCode);
        if ($lpProgress !== false && $lpProgress !== null) {
            return (int)min(100, round($lpProgress));
        }

        return 0;
    }
}
