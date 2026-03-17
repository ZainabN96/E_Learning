<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/UserAuth.php';
require_once dirname(__DIR__) . '/core/BatchRepository.php';
require_once dirname(__DIR__) . '/core/CourseRepository.php';
require_once dirname(__DIR__) . '/core/UserRepository.php';

UserAuth::requireTrainer();
$lang          = load_lang();
$langCode      = get_lang_code();
$platformTitle = $lang['platform_title'];
$trainerId     = UserAuth::userId();

$batchId   = $_GET['id'] ?? '';
$batchRepo = new BatchRepository();
$batch     = $batchId ? $batchRepo->getBatch($batchId) : [];

if (empty($batch) || $batch['trainer_id'] !== $trainerId) {
    header('Location: /E_Learning/trainer/dashboard.php'); exit;
}

$courseRepo  = new CourseRepository();
$userRepo    = new UserRepository();
$course      = $courseRepo->getCourse($batch['course_id'] ?? '');
$assignments = $batchRepo->listAssignments($batchId);
$msg         = $_GET['msg'] ?? '';

// Build students list
$students = [];
foreach ($batch['student_ids'] ?? [] as $sid) {
    $s = $userRepo->getUser($sid);
    if (!empty($s)) $students[$sid] = $s;
}

// Attendance summary (present days per student)
$allAtt = $batchRepo->getAllAttendance($batchId);
$presentCount = [];
foreach ($allAtt as $date => $records) {
    foreach ($records as $sid => $val) {
        if ($val === 'present') {
            $presentCount[$sid] = ($presentCount[$sid] ?? 0) + 1;
        }
    }
}
$totalDays = count($allAtt);
?>
<!DOCTYPE html>
<html lang="<?= $langCode ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($batch['name'] ?? '') ?> — <?= htmlspecialchars($platformTitle) ?></title>
    <link rel="stylesheet" href="/E_Learning/assets/css/portal.css">
</head>
<body>
<div class="portal-shell">

    <header class="topbar">
        <span class="topbar__brand">🎓 <?= htmlspecialchars($platformTitle) ?></span>
        <div class="topbar__user">
            <span>👤 <?= htmlspecialchars(UserAuth::userName()) ?></span>
            <a href="/E_Learning/trainer/dashboard.php">My Batches</a>
                <a href="/E_Learning/trainer/reports.php"><span class="nav-icon">&#128202;</span> Reports</a>
            <a href="/E_Learning/trainer/logout.php">Sign out</a>
        </div>
    </header>

    <div class="portal-body">
        <nav class="sidebar">
            <div class="sidebar__nav">
                <a href="/E_Learning/trainer/dashboard.php"><span class="nav-icon">📋</span> My Batches</a>
                <a href="/E_Learning/trainer/batch.php?id=<?= urlencode($batchId) ?>" class="active">
                    <span class="nav-icon">📦</span> Batch Overview
                </a>
                <a href="/E_Learning/trainer/attendance.php?batch=<?= urlencode($batchId) ?>">
                    <span class="nav-icon">✅</span> Attendance
                </a>
                <a href="/E_Learning/trainer/assignments.php?batch=<?= urlencode($batchId) ?>">
                    <span class="nav-icon">📝</span> Assignments
                </a>
                <a href="/E_Learning/trainer/course.php?batch=<?= urlencode($batchId) ?>">
                    <span class="nav-icon">📚</span> Course Outline
                </a>
            </div>
        </nav>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1><?= htmlspecialchars($batch['name'] ?? '') ?></h1>
                    <p class="subtitle">
                        📚 <?= htmlspecialchars($course['metadata']['title'] ?? '—') ?> ·
                        📅 <?= $batch['start_date'] ? date('d M Y', strtotime($batch['start_date'])) : '—' ?>
                        → <?= $batch['end_date'] ? date('d M Y', strtotime($batch['end_date'])) : '—' ?>
                    </p>
                </div>
            </div>

            <?php if ($msg === 'assign_saved'): ?>
                <div class="alert alert--success">Assignment saved.</div>
            <?php elseif ($msg === 'assign_deleted'): ?>
                <div class="alert alert--success">Assignment deleted.</div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-tile">
                    <div class="stat-tile__value"><?= count($students) ?></div>
                    <div class="stat-tile__label">Students</div>
                </div>
                <div class="stat-tile">
                    <div class="stat-tile__value"><?= $totalDays ?></div>
                    <div class="stat-tile__label">Attendance Days</div>
                </div>
                <div class="stat-tile">
                    <div class="stat-tile__value"><?= count($assignments) ?></div>
                    <div class="stat-tile__label">Assignments</div>
                </div>
                <div class="stat-tile">
                    <div class="stat-tile__value"><?= count($course['units'] ?? []) ?></div>
                    <div class="stat-tile__label">Course Modules</div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem">

                <!-- Students -->
                <div class="card">
                    <div class="card__header">
                        <h2>👥 Students (<?= count($students) ?>)</h2>
                    </div>
                    <div class="card__body" style="padding:0">
                        <?php if (empty($students)): ?>
                            <p style="padding:1rem;color:var(--color-gray-400)">No students enrolled.</p>
                        <?php else: ?>
                            <table class="data-table">
                                <thead><tr><th>Name</th><th>Email</th><th>Attendance</th></tr></thead>
                                <tbody>
                                <?php foreach ($students as $sid => $s): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($s['name'] ?? '') ?></td>
                                        <td style="font-size:.82rem"><?= htmlspecialchars($s['email'] ?? '') ?></td>
                                        <td>
                                            <?php if ($totalDays > 0): ?>
                                                <?= $presentCount[$sid] ?? 0 ?>/<?= $totalDays ?>
                                            <?php else: ?>—<?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Course Modules -->
                <div class="card">
                    <div class="card__header">
                        <h2>📚 Course Modules</h2>
                        <a href="/E_Learning/trainer/course.php?batch=<?= urlencode($batchId) ?>"
                           class="btn btn--primary btn--sm">Manage →</a>
                    </div>
                    <div class="card__body" style="padding:0">
                        <?php if (empty($course['units'])): ?>
                            <p style="padding:1rem;color:var(--color-gray-400)">No modules in this course.</p>
                        <?php else: ?>
                            <table class="data-table">
                                <thead><tr><th>#</th><th>Module</th><th>Slides</th></tr></thead>
                                <tbody>
                                <?php foreach ($course['units'] as $i => $unit): ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td><?= htmlspecialchars($unit['title'] ?? '') ?></td>
                                        <td><?= count($unit['slides'] ?? []) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Assignments -->
            <div class="card">
                <div class="card__header">
                    <h2>📝 Assignments</h2>
                    <a href="/E_Learning/trainer/assignments.php?batch=<?= urlencode($batchId) ?>"
                       class="btn btn--primary btn--sm">Manage →</a>
                </div>
                <div class="card__body" style="padding:0">
                    <?php if (empty($assignments)): ?>
                        <p style="padding:1rem;color:var(--color-gray-400)">No assignments yet.</p>
                    <?php else: ?>
                        <table class="data-table">
                            <thead><tr><th>Title</th><th>Due Date</th><th>Max Marks</th><th>Submissions</th></tr></thead>
                            <tbody>
                            <?php foreach ($assignments as $a): ?>
                                <?php $subs = $batchRepo->listSubmissions($batchId, $a['id']); ?>
                                <tr>
                                    <td><?= htmlspecialchars($a['title'] ?? '') ?></td>
                                    <td><?= $a['due_date'] ? date('d M Y', strtotime($a['due_date'])) : '—' ?></td>
                                    <td><?= (int)($a['max_marks'] ?? 100) ?></td>
                                    <td>
                                        <?= count($subs) ?>/<?= count($students) ?>
                                        <a href="/E_Learning/trainer/grade.php?batch=<?= urlencode($batchId) ?>&assignment=<?= urlencode($a['id']) ?>"
                                           class="btn btn--sm btn--secondary" style="margin-left:.5rem">Grade</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

        </main>
    </div>
</div>
</body>
</html>
