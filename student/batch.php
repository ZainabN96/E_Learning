<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/UserAuth.php';
require_once dirname(__DIR__) . '/core/BatchRepository.php';
require_once dirname(__DIR__) . '/core/CourseRepository.php';
require_once dirname(__DIR__) . '/core/UserRepository.php';

UserAuth::requireStudent();
$lang          = load_lang();
$langCode      = get_lang_code();
$platformTitle = $lang['platform_title'];
$studentId     = UserAuth::userId();

$batchId   = $_GET['id'] ?? '';
$batchRepo = new BatchRepository();
$batch     = $batchId ? $batchRepo->getBatch($batchId) : [];

// Verify student is enrolled in this batch
if (empty($batch) || !in_array($studentId, $batch['student_ids'] ?? [], true)) {
    header('Location: /E_Learning/student/dashboard.php'); exit;
}

$course      = (new CourseRepository())->getCourse($batch['course_id'] ?? '');
$userRepo    = new UserRepository();
$trainer     = $userRepo->getUser($batch['trainer_id'] ?? '');
$assignments = $batchRepo->listAssignments($batchId);

// Attendance summary for this student
$allAtt   = $batchRepo->getAllAttendance($batchId);
$totalDays = count($allAtt);
$present   = 0; $absent = 0; $late = 0;
foreach ($allAtt as $date => $records) {
    $val = $records[$studentId] ?? '';
    if ($val === 'present') $present++;
    elseif ($val === 'absent') $absent++;
    elseif ($val === 'late') $late++;
}
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
            <a href="/E_Learning/student/dashboard.php">My Courses</a>
            <a href="/E_Learning/student/logout.php">Sign out</a>
        </div>
    </header>

    <div class="portal-body">
        <nav class="sidebar">
            <div class="sidebar__nav">
                <a href="/E_Learning/student/dashboard.php"><span class="nav-icon">📋</span> My Courses</a>
                <a href="/E_Learning/student/batch.php?id=<?= urlencode($batchId) ?>" class="active">
                    <span class="nav-icon">📦</span> <?= htmlspecialchars($batch['name'] ?? '') ?>
                </a>
            </div>
        </nav>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1><?= htmlspecialchars($batch['name'] ?? '') ?></h1>
                    <p class="subtitle">
                        📚 <?= htmlspecialchars($course['metadata']['title'] ?? '—') ?> ·
                        👨‍🏫 Trainer: <?= htmlspecialchars($trainer['name'] ?? '—') ?> ·
                        📅 <?= $batch['start_date'] ? date('d M Y', strtotime($batch['start_date'])) : '—' ?>
                        → <?= $batch['end_date'] ? date('d M Y', strtotime($batch['end_date'])) : '—' ?>
                    </p>
                </div>
                <?php if ($course): ?>
                    <a href="/E_Learning/player/?course=<?= urlencode($batch['course_id'] ?? '') ?>"
                       target="_blank" class="btn btn--primary">▶ Start Course</a>
                <?php endif; ?>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-tile">
                    <div class="stat-tile__value"><?= $present ?></div>
                    <div class="stat-tile__label">Days Present</div>
                </div>
                <div class="stat-tile">
                    <div class="stat-tile__value"><?= $absent ?></div>
                    <div class="stat-tile__label">Days Absent</div>
                </div>
                <div class="stat-tile">
                    <div class="stat-tile__value"><?= $late ?></div>
                    <div class="stat-tile__label">Late Arrivals</div>
                </div>
                <div class="stat-tile">
                    <div class="stat-tile__value">
                        <?= $totalDays > 0 ? round(($present / $totalDays) * 100) . '%' : '—' ?>
                    </div>
                    <div class="stat-tile__label">Attendance %</div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem">

                <!-- Course Modules -->
                <div class="card">
                    <div class="card__header"><h2>📚 Course Modules</h2></div>
                    <div class="card__body" style="padding:0">
                        <?php if (empty($course['units'])): ?>
                            <p style="padding:1rem;color:var(--color-gray-400)">No modules yet.</p>
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
                    <?php if ($course): ?>
                        <div class="card__footer">
                            <a href="/E_Learning/player/?course=<?= urlencode($batch['course_id'] ?? '') ?>"
                               target="_blank">▶ Open Course Player →</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Attendance detail -->
                <div class="card">
                    <div class="card__header"><h2>✅ My Attendance</h2></div>
                    <div class="card__body" style="padding:0;max-height:300px;overflow-y:auto">
                        <?php if (empty($allAtt)): ?>
                            <p style="padding:1rem;color:var(--color-gray-400)">No attendance records yet.</p>
                        <?php else: ?>
                            <table class="data-table">
                                <thead><tr><th>Date</th><th>Status</th></tr></thead>
                                <tbody>
                                <?php foreach (array_reverse($allAtt, true) as $d => $recs): ?>
                                    <?php $val = $recs[$studentId] ?? '—'; ?>
                                    <tr>
                                        <td><?= date('d M Y', strtotime($d)) ?></td>
                                        <td>
                                            <?php if ($val === 'present'): ?>
                                                <span class="badge badge--success">Present</span>
                                            <?php elseif ($val === 'absent'): ?>
                                                <span class="badge badge--danger">Absent</span>
                                            <?php elseif ($val === 'late'): ?>
                                                <span class="badge badge--warning">Late</span>
                                            <?php else: ?>
                                                <span class="badge badge--gray">—</span>
                                            <?php endif; ?>
                                        </td>
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
                <div class="card__header"><h2>📝 Assignments</h2></div>
                <div class="card__body" style="padding:0">
                    <?php if (empty($assignments)): ?>
                        <p style="padding:1rem;color:var(--color-gray-400)">No assignments posted yet.</p>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr><th>Title</th><th>Due Date</th><th>Max Marks</th><th>Status</th><th></th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($assignments as $a): ?>
                                <?php
                                $sub    = $batchRepo->getSubmission($batchId, $a['id'], $studentId);
                                $graded = isset($sub['marks']);
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($a['title'] ?? '') ?></strong></td>
                                    <td><?= $a['due_date'] ? date('d M Y', strtotime($a['due_date'])) : '—' ?></td>
                                    <td><?= (int)($a['max_marks'] ?? 100) ?></td>
                                    <td>
                                        <?php if ($graded): ?>
                                            <span class="badge badge--success">
                                                Graded: <?= $sub['marks'] ?>/<?= (int)($a['max_marks'] ?? 100) ?>
                                            </span>
                                        <?php elseif (!empty($sub)): ?>
                                            <span class="badge badge--primary">Submitted</span>
                                        <?php else: ?>
                                            <span class="badge badge--warning">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="/E_Learning/student/assignment.php?batch=<?= urlencode($batchId) ?>&assignment=<?= urlencode($a['id']) ?>"
                                           class="btn btn--sm btn--<?= empty($sub) ? 'primary' : 'secondary' ?>">
                                            <?= empty($sub) ? 'Submit' : 'View' ?>
                                        </a>
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
