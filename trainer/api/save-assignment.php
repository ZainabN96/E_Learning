<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/core/helpers.php';
require_once dirname(__DIR__, 2) . '/core/UserAuth.php';
require_once dirname(__DIR__, 2) . '/core/BatchRepository.php';

UserAuth::requireTrainer();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$batchId    = trim($_POST['batch_id']      ?? '');
$assignId   = trim($_POST['assignment_id'] ?? '');
$batchRepo  = new BatchRepository();
$batch      = $batchRepo->getBatch($batchId);

if (empty($batch) || $batch['trainer_id'] !== UserAuth::userId()) {
    http_response_code(403); exit;
}

$data = [
    'id'          => $assignId ?: '',
    'title'       => trim($_POST['title']       ?? ''),
    'description' => trim($_POST['description'] ?? ''),
    'module_id'   => trim($_POST['module_id']   ?? ''),
    'due_date'    => preg_replace('/[^0-9\-]/', '', $_POST['due_date'] ?? ''),
    'max_marks'   => max(1, (int)($_POST['max_marks'] ?? 100)),
];

if ($data['title'] === '') {
    header('Location: /E_Learning/trainer/assignments.php?batch=' . urlencode($batchId) . '&error=notitle');
    exit;
}

$batchRepo->saveAssignment($batchId, $data);
header('Location: /E_Learning/trainer/assignments.php?batch=' . urlencode($batchId) . '&msg=saved');
exit;
