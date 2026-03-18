<?php
declare(strict_types=1);
require_once dirname(dirname(__DIR__)) . '/core/helpers.php';
require_once dirname(dirname(__DIR__)) . '/core/UserAuth.php';
require_once dirname(dirname(__DIR__)) . '/core/BatchRepository.php';
require_once dirname(dirname(__DIR__)) . '/core/CourseRepository.php';
require_once dirname(dirname(__DIR__)) . '/core/UserRepository.php';
require_once dirname(dirname(__DIR__)) . '/core/CertificateRepository.php';

header('Content-Type: application/json');
UserAuth::requireTrainer();

$trainerId = UserAuth::userId();
$batchId   = trim($_POST['batch_id']   ?? '');
$studentId = trim($_POST['student_id'] ?? '');

if (!$batchId || !$studentId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing batch_id or student_id']);
    exit;
}

$batchRepo = new BatchRepository();
$batch     = $batchRepo->getBatch($batchId);

if (empty($batch) || ($batch['trainer_id'] ?? '') !== $trainerId) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Access denied']);
    exit;
}

if (!in_array($studentId, $batch['student_ids'] ?? [], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Student not enrolled in this batch']);
    exit;
}

$certRepo = new CertificateRepository();
if ($certRepo->exists($studentId, $batchId)) {
    $existing = $certRepo->findForStudentBatch($studentId, $batchId);
    echo json_encode(['ok' => true, 'cert' => $existing, 'already_issued' => true]);
    exit;
}

$userRepo    = new UserRepository();
$courseRepo  = new CourseRepository();
$student     = $userRepo->getUser($studentId);
$trainer     = $userRepo->getUser($trainerId);
$course      = $courseRepo->getCourse($batch['course_id'] ?? '');

$cert = $certRepo->issue([
    'student_id'     => $studentId,
    'student_name'   => $student['name'] ?? '—',
    'batch_id'       => $batchId,
    'batch_name'     => $batch['name'] ?? '—',
    'course_id'      => $batch['course_id'] ?? '',
    'course_title'   => $course['metadata']['title'] ?? ($batch['course_id'] ?? '—'),
    'trainer_id'     => $trainerId,
    'trainer_name'   => $trainer['name'] ?? '—',
    'start_date'     => $batch['start_date'] ?? '',
    'end_date'       => $batch['end_date'] ?? '',
    'issued_by'      => $trainerId,
]);

echo json_encode(['ok' => true, 'cert' => $cert]);
