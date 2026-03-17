<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/core/helpers.php';
require_once dirname(__DIR__, 2) . '/core/Auth.php';
require_once dirname(__DIR__, 2) . '/core/CourseRepository.php';

Auth::requireLogin();
require_post();

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) json_response(['error' => 'Invalid JSON'], 400);

$courseId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $body['course_id'] ?? '');
$type     = $body['type'] ?? ''; // 'units' or 'slides'
$unitId   = preg_replace('/[^a-zA-Z0-9_\-]/', '', $body['unit_id'] ?? '');
$order    = $body['order'] ?? []; // array of IDs in new order

if (!$courseId || !$type || !is_array($order)) {
    json_response(['error' => 'Invalid payload'], 400);
}

$repo   = new CourseRepository();
$course = $repo->getCourse($courseId);
if (empty($course)) json_response(['error' => 'Course not found'], 404);

if ($type === 'units') {
    $idxMap = array_flip(array_column($course['units'], 'id'));
    foreach ($order as $newOrder => $id) {
        $id = preg_replace('/[^a-zA-Z0-9_\-]/', '', $id);
        if (isset($idxMap[$id])) {
            $course['units'][$idxMap[$id]]['order'] = $newOrder + 1;
        }
    }
    usort($course['units'], fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
} elseif ($type === 'slides' && $unitId) {
    foreach ($course['units'] as &$unit) {
        if ($unit['id'] !== $unitId) continue;
        $idxMap = array_flip(array_column($unit['slides'], 'id'));
        foreach ($order as $newOrder => $id) {
            $id = preg_replace('/[^a-zA-Z0-9_\-]/', '', $id);
            if (isset($idxMap[$id])) {
                $unit['slides'][$idxMap[$id]]['order'] = $newOrder + 1;
            }
        }
        usort($unit['slides'], fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
        break;
    }
    unset($unit);
}

$repo->saveCourse($course);
json_response(['success' => true]);
