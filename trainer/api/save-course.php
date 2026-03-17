<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/core/helpers.php';
require_once dirname(__DIR__, 2) . '/core/UserAuth.php';
require_once dirname(__DIR__, 2) . '/core/BatchRepository.php';
require_once dirname(__DIR__, 2) . '/core/CourseRepository.php';

UserAuth::requireTrainer();
require_post();

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) json_response(['error' => 'Invalid JSON'], 400);

// Verify trainer owns a batch that uses this course
$courseId  = preg_replace('/[^a-zA-Z0-9_\-]/', '', $body['id'] ?? '');
$batchId   = preg_replace('/[^a-zA-Z0-9_\-]/', '', $body['batch_id'] ?? '');
$batchRepo = new BatchRepository();
$batch     = $batchRepo->getBatch($batchId);
if (empty($batch) || $batch['trainer_id'] !== UserAuth::userId() || $batch['course_id'] !== $courseId) {
    json_response(['error' => 'Unauthorized'], 403);
}

$data = [
    'id'       => $courseId,
    'metadata' => [
        'title'            => trim($body['metadata']['title'] ?? ''),
        'description'      => trim($body['metadata']['description'] ?? ''),
        'author'           => trim($body['metadata']['author'] ?? ''),
        'language'         => 'en',
        'duration_minutes' => max(0, (int)($body['metadata']['duration_minutes'] ?? 30)),
    ],
    'scorm' => [
        'version'      => '1.2',
        'masteryScore' => max(0, min(100, (int)($body['scorm']['masteryScore'] ?? 80))),
    ],
];

if (empty($data['metadata']['title'])) {
    json_response(['error' => 'Title is required'], 400);
}

$repo  = new CourseRepository();
$saved = $repo->saveCourse($data);
json_response(['success' => true, 'id' => $saved['id']]);
