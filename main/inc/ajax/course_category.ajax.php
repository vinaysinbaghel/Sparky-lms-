<?php

/* For licensing terms, see /license.txt */

/**
 * Responses to AJAX calls for Course Categories.
 */
require_once __DIR__.'/../global.inc.php';

if (!api_is_platform_admin() && !api_is_allowed_to_create_course()) {
    api_not_allowed(true);
    exit;
}

$action = $_REQUEST['a'] ?? '';

switch ($action) {
    case 'show_courses':
        $categoryId = (int) $_REQUEST['id'];
        $categoryInfo = CourseCategory::getCategoryById($categoryId);
        if (!empty($categoryInfo)) {
            $courses = CourseCategory::getCoursesInCategory($categoryInfo['code'], '', false, false);

            $table = new HTML_Table(['class' => 'table table-hover table-striped data_table']);
            $headers = [
                get_lang('Name'),
            ];
            $row = 0;
            $column = 0;
            foreach ($headers as $header) {
                $table->setHeaderContents($row, $column, $header);
                $column++;
            }
            foreach ($courses as $course) {
                $row++;
                $courseLink = '<a href="'.api_get_path(WEB_PATH).'courses/'.$course['directory'].'/index.php">'.$course['title'].'</a>';
                $table->setCellContents($row, 0, $courseLink);
            }

            echo $table->toHtml();
            exit;
        }
        break;

    case 'add_category':
        header('Content-Type: application/json');
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $parent_id = trim($_POST['parent_id'] ?? '');

        if (empty($name)) {
            echo json_encode(['status' => 'error', 'message' => get_lang('ThisFieldIsRequired')]);
            exit;
        }

        if (empty($code)) {
            // Generate clean code from name
            $code = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $name), 0, 8));
            if (empty($code)) {
                $code = 'CAT' . rand(100, 999);
            }
        }

        // Check if code exists, append random if so
        $existing = CourseCategory::getCategory($code);
        if (!empty($existing)) {
            $code .= rand(10, 99);
        }

        $res = CourseCategory::addNode($code, $name, 'TRUE', $parent_id);
        if ($res) {
            echo json_encode([
                'status' => 'success',
                'id' => $res,
                'code' => $code,
                'name' => $name,
                'message' => get_lang('Created'),
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => get_lang('CatCodeAlreadyUsed'),
            ]);
        }
        exit;

    case 'delete_category':
        header('Content-Type: application/json');
        $code = trim($_POST['code'] ?? '');
        if (empty($code)) {
            echo json_encode(['status' => 'error', 'message' => get_lang('NoCategorySelected')]);
            exit;
        }

        $res = CourseCategory::deleteNode($code);
        if ($res) {
            echo json_encode([
                'status' => 'success',
                'code' => $code,
                'message' => get_lang('Deleted'),
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => get_lang('Error'),
            ]);
        }
        exit;

    case 'get_categories':
        header('Content-Type: application/json');
        $accessUrlId = api_get_current_access_url_id();
        $em = Database::getManager();
        /** @var \Chamilo\CoreBundle\Entity\Repository\CourseCategoryRepository $repo */
        $repo = $em->getRepository('ChamiloCoreBundle:CourseCategory');
        $categories = $repo->findAllInAccessUrl($accessUrlId, api_get_configuration_value('allow_base_course_category'));
        $data = [];
        foreach ($categories as $cat) {
            $data[] = [
                'code' => $cat->getCode(),
                'name' => (string) $cat,
                'id' => $cat->getId(),
            ];
        }
        echo json_encode(['status' => 'success', 'categories' => $data]);
        exit;
}
exit;

