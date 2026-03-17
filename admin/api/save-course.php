<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/core/helpers.php';
require_once dirname(__DIR__, 2) . '/core/Auth.php';
require_once dirname(__DIR__, 2) . '/core/CourseRepository.php';

Auth::requireLogin();
require_post();

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) json_response(['error' => 'Invalid JSON'], 400);

// Sanitize input
$data = [
    'id'       => preg_replace('/[^a-zA-Z0-9_\-]/', '', $body['id'] ?? ''),
    'metadata' => [
        'title'            => trim($body['metadata']['title'] ?? ''),
        'description'      => trim($body['metadata']['description'] ?? ''),
        'author'           => trim($body['metadata']['author'] ?? ''),
        'language'         => 'de',
        'duration_minutes' => max(0, (int)($body['metadata']['duration_minutes'] ?? 30)),
    ],
    'scorm' => [
        'version'      => '1.2',
        'masteryScore' => max(0, min(100, (int)($body['scorm']['masteryScore'] ?? 80))),
    ],
];

if (empty($data['metadata']['title'])) {
    json_response(['error' => 'Titel ist erforderlich'], 400);
}

$repo   = new CourseRepository();
$saved  = $repo->saveCourse($data);
json_response(['success' => true, 'id' => $saved['id']]);
