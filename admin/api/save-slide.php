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
$unitId   = preg_replace('/[^a-zA-Z0-9_\-]/', '', $body['unit_id'] ?? '');
$slideIn  = $body['slide'] ?? [];

if (!$courseId || !$unitId) json_response(['error' => 'course_id and unit_id required'], 400);

$allowed_types = ['html', 'video', 'quiz', 'interactive'];
$type = in_array($slideIn['type'] ?? '', $allowed_types) ? $slideIn['type'] : 'html';

$slide = [
    'id'      => preg_replace('/[^a-zA-Z0-9_\-]/', '', $slideIn['id'] ?? ''),
    'title'   => trim($slideIn['title'] ?? ''),
    'type'    => $type,
    'order'   => max(1, (int)($slideIn['order'] ?? 1)),
    'content' => $slideIn['content'] ?? [],
];
if (empty($slide['title'])) json_response(['error' => 'Titel ist erforderlich'], 400);

$repo  = new CourseRepository();
$saved = $repo->saveSlide($courseId, $unitId, $slide);
json_response(['success' => true, 'id' => $saved['id']]);
