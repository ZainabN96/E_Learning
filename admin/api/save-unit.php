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
if (!$courseId) json_response(['error' => 'course_id required'], 400);

$unit = [
    'id'    => preg_replace('/[^a-zA-Z0-9_\-]/', '', $body['id'] ?? ''),
    'title' => trim($body['title'] ?? ''),
    'order' => max(1, (int)($body['order'] ?? 1)),
];
if (empty($unit['title'])) json_response(['error' => 'Titel ist erforderlich'], 400);

$repo   = new CourseRepository();
$saved  = $repo->saveUnit($courseId, $unit);
json_response(['success' => true, 'id' => $saved['id']]);
