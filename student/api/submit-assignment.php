<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/core/helpers.php';
require_once dirname(__DIR__, 2) . '/core/UserAuth.php';
require_once dirname(__DIR__, 2) . '/core/BatchRepository.php';

UserAuth::requireStudent();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$studentId = UserAuth::userId();
$batchId   = trim($_POST['batch_id']      ?? '');
$assignId  = trim($_POST['assignment_id'] ?? '');
$text      = trim($_POST['text']          ?? '');

$batchRepo  = new BatchRepository();
$batch      = $batchRepo->getBatch($batchId);

// Validate enrollment
if (empty($batch) || !in_array($studentId, $batch['student_ids'] ?? [], true)) {
    http_response_code(403); exit;
}

$assignment = $batchRepo->getAssignment($batchId, $assignId);
if (empty($assignment)) {
    header('Location: /E_Learning/student/batch.php?id=' . urlencode($batchId)); exit;
}

// Block re-submission if already graded
$existing = $batchRepo->getSubmission($batchId, $assignId, $studentId);
if (isset($existing['marks'])) {
    header('Location: /E_Learning/student/assignment.php?batch=' . urlencode($batchId) . '&assignment=' . urlencode($assignId));
    exit;
}

// Require at least text or file
$hasFile = !empty($_FILES['submission_file']['name']);
if ($text === '' && !$hasFile) {
    header('Location: /E_Learning/student/assignment.php?batch=' . urlencode($batchId) . '&assignment=' . urlencode($assignId) . '&error=empty');
    exit;
}

$data = [
    'student_id' => $studentId,
    'text'       => $text,
];

// Handle file upload
if ($hasFile && $_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
    $config   = json_read(data_dir() . '/config.json');
    $maxBytes = ((int)($config['max_upload_mb'] ?? 50)) * 1024 * 1024;
    $file     = $_FILES['submission_file'];
    $allowed  = ['pdf','doc','docx','zip','txt','xlsx','xls','ppt','pptx'];
    $origName = basename($file['name']);
    $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    if ($file['size'] <= $maxBytes && in_array($ext, $allowed, true)) {
        $safeName  = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $origName);
        $safeName  = $studentId . '_' . time() . '.' . $ext;
        $destDir   = project_root() . '/data/submissions/' . $batchId . '/' . $assignId;
        if (!is_dir($destDir)) mkdir($destDir, 0755, true);
        $destPath  = $destDir . '/' . $safeName;
        if (move_uploaded_file($file['tmp_name'], $destPath)) {
            $data['file_path'] = 'data/submissions/' . $batchId . '/' . $assignId . '/' . $safeName;
        }
    }
}

$batchRepo->saveSubmission($batchId, $assignId, $data);
header('Location: /E_Learning/student/assignment.php?batch=' . urlencode($batchId) . '&assignment=' . urlencode($assignId) . '&msg=submitted');
exit;
