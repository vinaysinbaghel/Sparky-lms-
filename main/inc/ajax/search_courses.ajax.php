<?php
/* For licensing terms, see /license.txt */

/**
 * Autocomplete Course & Masterclass Search
 */

require_once __DIR__.'/../global.inc.php';

header('Content-Type: application/json; charset=utf-8');

$q = isset($_GET['q']) ? trim($_GET['q']) : (isset($_GET['term']) ? trim($_GET['term']) : '');

if (empty($q) || strlen($q) < 2) {
    echo json_encode(['status' => 'success', 'results' => []]);
    exit;
}

$dbTable = Database::get_main_table(TABLE_MAIN_COURSE);
$keyword = Database::escape_string($q);

$sql = "SELECT id, code, title, category_code, directory, visual_code 
        FROM $dbTable 
        WHERE (title LIKE '%$keyword%' OR code LIKE '%$keyword%' OR visual_code LIKE '%$keyword%')
          AND visibility > 0 
        ORDER BY id DESC 
        LIMIT 8";

$res = Database::query($sql);
$results = [];

while ($row = Database::fetch_assoc($res)) {
    $courseId = (int)$row['id'];
    $categoryName = 'Executive Masterclass';
    if (!empty($row['category_code'])) {
        $catObj = CourseCategory::getCategory($row['category_code']);
        if (!empty($catObj) && !empty($catObj['name'])) {
            $categoryName = $catObj['name'];
        }
    }

    $teacherName = CourseManager::getTeacherListFromCourseCodeToString($row['code']);
    if (empty($teacherName)) {
        $teacherName = 'ETupskill Faculty';
    }

    $thumb = '';
    if (!empty($row['directory']) && file_exists(api_get_path(SYS_COURSE_PATH).$row['directory'].'/course-pic.png')) {
        $thumb = api_get_path(WEB_COURSE_PATH).$row['directory'].'/course-pic.png';
    } else {
        $thumb = api_get_path(WEB_CSS_THEME_PATH).'images/et/et-mark-placeholder.svg';
    }

    $courseUrl = api_get_path(WEB_COURSE_PATH).$row['directory'].'/';

    $results[] = [
        'id' => $courseId,
        'code' => $row['code'],
        'title' => $row['title'],
        'category' => $categoryName,
        'instructor' => $teacherName,
        'thumbnail' => $thumb,
        'url' => $courseUrl
    ];
}

echo json_encode(['status' => 'success', 'results' => $results]);
exit;
