<?php
/* For licensing terms, see /license.txt */

require_once __DIR__.'/../global.inc.php';

$action = $_REQUEST['a'] ?? '';
$type = $_REQUEST['type'] ?? 'personal';

if (api_is_anonymous() || empty($_SESSION['_user'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$agenda = new Agenda($type);

switch ($action) {
    case 'add_event':
        $eventScope = $_REQUEST['event_scope'] ?? null;
        $targetAgenda = new Agenda('personal', api_get_user_id());
        $targetAgenda->setIsAllowedToEdit(true);

        if (!empty($eventScope) && $eventScope !== 'personal') {
            if (strpos($eventScope, 'course_') === 0) {
                $targetCourseId = (int) str_replace('course_', '', $eventScope);
                $targetCourseInfo = api_get_course_info_by_id($targetCourseId);
                $canManageCourse = api_is_platform_admin() || (!empty($targetCourseInfo) && CourseManager::is_course_teacher(api_get_user_id(), $targetCourseInfo['code']));
                if (!$canManageCourse) {
                    http_response_code(403);
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'Permission denied: You are only allowed to create personal events.']);
                    exit;
                }
                $targetAgenda = new Agenda('course', api_get_user_id(), $targetCourseId);
                $targetAgenda->setIsAllowedToEdit(true);
            } elseif ($eventScope === 'platform') {
                if (!api_is_platform_admin()) {
                    http_response_code(403);
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'Permission denied: Only administrators can create platform announcements.']);
                    exit;
                }
                $targetAgenda = new Agenda('admin', api_get_user_id());
                $targetAgenda->setIsAllowedToEdit(true);
            }
        }

        $title = trim($_REQUEST['title'] ?? '');
        if (empty($title)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Title is required']);
            exit;
        }

        $content = $_REQUEST['content'] ?? '';
        $comment = $_REQUEST['comment'] ?? null;
        $add_as_announcement = !empty($_REQUEST['add_as_annonuncement']);

        $startRaw = $_REQUEST['start'] ?? '';
        $endRaw = $_REQUEST['end'] ?? '';

        if (empty($startRaw)) {
            $startRaw = date('Y-m-d H:i:s');
        } else {
            $startRaw = str_replace('T', ' ', $startRaw);
            if (strlen($startRaw) === 16) {
                $startRaw .= ':00';
            }
        }

        if (empty($endRaw)) {
            $endRaw = date('Y-m-d H:i:s', strtotime($startRaw) + 3600);
        } else {
            $endRaw = str_replace('T', ' ', $endRaw);
            if (strlen($endRaw) === 16) {
                $endRaw .= ':00';
            }
        }

        $allDay = !empty($_REQUEST['all_day']) && ($_REQUEST['all_day'] === 'true' || $_REQUEST['all_day'] == 1) ? 1 : 0;

        $userToSend = $_REQUEST['users_to_send'] ?? [];
        if ($targetAgenda->getType() === 'course' && empty($userToSend)) {
            $userToSend = ['everyone'];
        }

        $careerId = (int) ($_REQUEST['career_id'] ?? 0);
        $promotionId = (int) ($_REQUEST['promotion_id'] ?? 0);
        $subscriptionVisibility = (int) ($_REQUEST['subscription_visibility'] ?? 0);
        $subscriptionItemId = isset($_REQUEST['subscription_item']) ? (int) $_REQUEST['subscription_item'] : null;
        $maxSubscriptions = (int) ($_REQUEST['max_subscriptions'] ?? 0);

        $eventId = $targetAgenda->addEvent(
            $startRaw,
            $endRaw,
            $allDay,
            $title,
            $content,
            $userToSend,
            $add_as_announcement,
            null,
            [],
            null,
            $comment,
            '',
            $_REQUEST['invitees'] ?? [],
            isset($_REQUEST['collective']),
            [],
            $careerId,
            $promotionId,
            $subscriptionVisibility,
            $subscriptionItemId,
            $maxSubscriptions
        );

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'event_id' => $eventId,
            'title' => $title,
            'message' => 'Event saved successfully'
        ]);
        exit;

    case 'delete_event':
        $rawId = $_REQUEST['id'] ?? '';
        $id_list = explode('_', $rawId);
        $eventType = $id_list[0] ?? 'personal';
        $eventId = (int) ($id_list[1] ?? 0);

        if ($eventType === 'course') {
            $tlb_course_agenda = Database::get_course_table(TABLE_AGENDA);
            $sql = "SELECT c_id FROM $tlb_course_agenda WHERE id = $eventId OR iid = $eventId LIMIT 1";
            $res = Database::query($sql);
            $c_id = 0;
            if ($row = Database::fetch_assoc($res)) {
                $c_id = (int) $row['c_id'];
            }
            if ($c_id > 0) {
                $courseInfo = api_get_course_info_by_id($c_id);
                $canEdit = api_is_platform_admin() || (!empty($courseInfo) && CourseManager::is_course_teacher(api_get_user_id(), $courseInfo['code']));
                if (!$canEdit) {
                    http_response_code(403);
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'Permission denied: Only course managers or administrators can delete course events.']);
                    exit;
                }
                $targetAgenda = new Agenda('course', api_get_user_id(), $c_id);
                $targetAgenda->setIsAllowedToEdit(true);
                $deleteAllEventsFromSerie = isset($_REQUEST['delete_all_events']);
                $targetAgenda->deleteEvent($eventId, $deleteAllEventsFromSerie);
                header('Content-Type: application/json');
                echo json_encode(['status' => 'success', 'deleted' => $eventId]);
                exit;
            }
        } elseif ($eventType === 'admin' || $eventType === 'platform') {
            if (!api_is_platform_admin()) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Permission denied: Only administrators can delete platform announcements.']);
                exit;
            }
            $targetAgenda = new Agenda('admin', api_get_user_id());
            $targetAgenda->setIsAllowedToEdit(true);
            $targetAgenda->deleteEvent($eventId);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'deleted' => $eventId]);
            exit;
        } else {
            // personal event: verify user owns this event or is platform admin
            $tbl_personal_agenda = Database::get_main_table(TABLE_PERSONAL_AGENDA);
            $sql = "SELECT user FROM $tbl_personal_agenda WHERE id = $eventId LIMIT 1";
            $res = Database::query($sql);
            $ownerId = 0;
            if ($row = Database::fetch_assoc($res)) {
                $ownerId = (int) $row['user'];
            }
            if (!api_is_platform_admin() && $ownerId !== (int) api_get_user_id()) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Permission denied: You can only delete your own personal events.']);
                exit;
            }
            $targetAgenda = new Agenda('personal', api_get_user_id());
            $targetAgenda->setIsAllowedToEdit(true);
            $targetAgenda->deleteEvent($eventId);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'deleted' => $eventId]);
            exit;
        }

        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Permission denied to delete event']);
        exit;

    case 'get_events':
        $filter = $_REQUEST['user_id'] ?? null;
        $sessionId = $_REQUEST['session_id'] ?? null;
        $result = $agenda->parseAgendaFilter($filter);

        $groupId = current($result['groups']);
        $userId = current($result['users']);

        $start = isset($_REQUEST['start']) ? api_strtotime($_REQUEST['start']) : null;
        $end = isset($_REQUEST['end']) ? api_strtotime($_REQUEST['end']) : null;

        if ($type === 'personal' && !empty($sessionId)) {
            $agenda->setSessionId($sessionId);
        }

        $events = $agenda->getEvents(
            $start,
            $end,
            api_get_course_int_id(),
            $groupId,
            $userId
        );
        header('Content-Type: application/json');
        echo $events;
        exit;

    default:
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok']);
        exit;
}