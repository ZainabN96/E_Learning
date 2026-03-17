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

$courseId  = preg_replace('/[^a-zA-Z0-9_\-]/', '', $body['course_id'] ?? '');
$batchId   = preg_replace('/[^a-zA-Z0-9_\-]/', '', $body['batch_id']  ?? '');
$batchRepo = new BatchRepository();
$batch     = $batchRepo->getBatch($batchId);
if (empty($batch) || $batch['trainer_id'] !== UserAuth::userId() || $batch['course_id'] !== $courseId) {
    json_response(['error' => 'Unauthorized'], 403);
}

$type    = $body['type']     ?? '';
$unitId  = preg_replace('/[^a-zA-Z0-9_\-]/', '', $body['unit_id']  ?? '');
$slideId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $body['slide_id'] ?? '');
$repo    = new CourseRepository();

switch ($type) {
    case 'unit':
        if (!$unitId) json_response(['error' => 'unit_id required'], 400);
        $repo->deleteUnit($courseId, $unitId);
        break;
    case 'slide':
        if (!$unitId || !$slideId) json_response(['error' => 'unit_id and slide_id required'], 400);
        $repo->deleteSlide($courseId, $unitId, $slideId);
        break;
    default:
        json_response(['error' => 'Unknown type'], 400);
}

json_response(['success' => true]);
