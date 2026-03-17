<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/core/helpers.php';
require_once dirname(__DIR__, 2) . '/core/UserAuth.php';
require_once dirname(__DIR__, 2) . '/core/BatchRepository.php';

UserAuth::requireTrainer();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$batchId = trim($_POST['batch_id'] ?? '');
$date    = preg_replace('/[^0-9\-]/', '', $_POST['date'] ?? '');
$records = $_POST['records'] ?? [];

if (!$batchId || !$date) {
    header('Location: /E_Learning/trainer/dashboard.php'); exit;
}

$batchRepo = new BatchRepository();
$batch     = $batchRepo->getBatch($batchId);
if (empty($batch) || $batch['trainer_id'] !== UserAuth::userId()) {
    http_response_code(403); exit;
}

// Sanitize records — only allow present|absent|late
$clean = [];
foreach ($records as $sid => $val) {
    $sid = preg_replace('/[^a-zA-Z0-9_\-]/', '', $sid);
    if (in_array($val, ['present','absent','late'], true) && $sid) {
        $clean[$sid] = $val;
    }
}

$batchRepo->saveAttendance($batchId, $date, $clean);
header('Location: /E_Learning/trainer/attendance.php?batch=' . urlencode($batchId) . '&date=' . urlencode($date) . '&msg=saved');
exit;
