<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/core/helpers.php';
require_once dirname(__DIR__, 2) . '/core/UserAuth.php';
require_once dirname(__DIR__, 2) . '/core/BatchRepository.php';

UserAuth::requireTrainer();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$batchId   = trim($_POST['batch_id']      ?? '');
$assignId  = trim($_POST['assignment_id'] ?? '');
$studentId = trim($_POST['student_id']    ?? '');
$marksRaw  = $_POST['marks'] ?? '';
$feedback  = trim($_POST['feedback'] ?? '');

$batchRepo  = new BatchRepository();
$batch      = $batchRepo->getBatch($batchId);
if (empty($batch) || $batch['trainer_id'] !== UserAuth::userId()) {
    http_response_code(403); exit;
}

$assignment = $batchRepo->getAssignment($batchId, $assignId);
$maxMarks   = (int)($assignment['max_marks'] ?? 100);

$data = [
    'student_id' => $studentId,
    'feedback'   => $feedback,
    'graded_at'  => date('c'),
    'graded_by'  => UserAuth::userId(),
];
if ($marksRaw !== '') {
    $data['marks'] = min($maxMarks, max(0, (int)$marksRaw));
}

$batchRepo->saveSubmission($batchId, $assignId, $data);
header('Location: /E_Learning/trainer/grade.php?batch=' . urlencode($batchId) . '&assignment=' . urlencode($assignId) . '&msg=graded');
exit;
