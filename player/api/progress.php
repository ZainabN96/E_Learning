<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/core/helpers.php';
require_once dirname(__DIR__, 2) . '/core/CourseRepository.php';

require_post();

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) json_response(['error' => 'Invalid JSON body'], 400);

$courseId  = preg_replace('/[^a-zA-Z0-9_\-]/', '', $body['course_id'] ?? '');
$sessionId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $body['session_id'] ?? '');
$data      = $body['data'] ?? [];

if (!$courseId || !$sessionId) {
    json_response(['error' => 'course_id and session_id required'], 400);
}

$repo = new CourseRepository();
$repo->saveProgress($courseId, $sessionId, $data);
json_response(['success' => true]);
