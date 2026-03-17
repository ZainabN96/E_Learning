<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/core/helpers.php';
require_once dirname(__DIR__, 2) . '/core/UserAuth.php';
require_once dirname(__DIR__, 2) . '/core/BatchRepository.php';

UserAuth::requireStudent();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$studentId = UserAuth::userId();
$batchId   = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['batch_id'] ?? '');

if (!$batchId) {
    header('Location: /E_Learning/student/browse.php'); exit;
}

$batchRepo = new BatchRepository();
$batch     = $batchRepo->getBatch($batchId);

// Must exist, be open for enrollment, and be upcoming/active
if (empty($batch) || empty($batch['open_enrollment']) ||
    !in_array($batch['status'] ?? '', ['upcoming', 'active'], true)) {
    header('Location: /E_Learning/student/browse.php?msg=closed'); exit;
}

// Already enrolled?
if (in_array($studentId, $batch['student_ids'] ?? [], true)) {
    header('Location: /E_Learning/student/browse.php?msg=already'); exit;
}

// Add student
$batch['student_ids'][] = $studentId;
$batchRepo->saveBatch($batch);

header('Location: /E_Learning/student/browse.php?msg=enrolled');
exit;
