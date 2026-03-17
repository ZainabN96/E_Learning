<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/core/helpers.php';
require_once dirname(__DIR__, 2) . '/core/Auth.php';
require_once dirname(__DIR__, 2) . '/core/CourseRepository.php';

Auth::requireLogin();

// Support both JSON body (AJAX) and form POST (HTML form)
if ($_SERVER['CONTENT_TYPE'] === 'application/json' || strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
} else {
    $body = $_POST;
}

$type     = $body['type'] ?? '';
$courseId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $body['course_id'] ?? '');
$unitId   = preg_replace('/[^a-zA-Z0-9_\-]/', '', $body['unit_id'] ?? '');
$slideId  = preg_replace('/[^a-zA-Z0-9_\-]/', '', $body['slide_id'] ?? '');

$repo = new CourseRepository();
$isAjax = strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false;

switch ($type) {
    case 'course':
        if (!$courseId) { if ($isAjax) json_response(['error' => 'course_id required'], 400); exit; }
        $repo->deleteCourse($courseId);
        break;
    case 'unit':
        if (!$courseId || !$unitId) { if ($isAjax) json_response(['error' => 'Missing IDs'], 400); exit; }
        $repo->deleteUnit($courseId, $unitId);
        break;
    case 'slide':
        if (!$courseId || !$unitId || !$slideId) { if ($isAjax) json_response(['error' => 'Missing IDs'], 400); exit; }
        $repo->deleteSlide($courseId, $unitId, $slideId);
        break;
    default:
        if ($isAjax) json_response(['error' => 'Unknown type'], 400);
        header('Location: /E_Learning/admin/');
        exit;
}

if ($isAjax) {
    json_response(['success' => true]);
} else {
    header('Location: /E_Learning/admin/?msg=course_deleted');
    exit;
}
