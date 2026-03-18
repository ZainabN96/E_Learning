<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/UserAuth.php';
require_once dirname(__DIR__) . '/core/BatchRepository.php';
require_once dirname(__DIR__) . '/core/CourseRepository.php';
require_once dirname(__DIR__) . '/core/UserRepository.php';
require_once dirname(__DIR__) . '/core/CertificateRepository.php';

UserAuth::requireStudent();
$lang          = load_lang();
$langCode      = get_lang_code();
$platformTitle = $lang['platform_title'];
$studentId     = UserAuth::userId();
$studentName   = UserAuth::userName();

$batchRepo  = new BatchRepository();
$courseRepo = new CourseRepository();
$userRepo   = new UserRepository();
$certRepo   = new CertificateRepository();
$batches    = $batchRepo->listBatchesForStudent($studentId);
$courses    = array_column($courseRepo->listCourses(), null, 'id');

$statusLabel = ['active' => 'Active', 'upcoming' => 'Upcoming', 'completed' => 'Completed'];
$statusClass = ['active' => 'badge--success', 'upcoming' => 'badge--primary', 'completed' => 'badge--gray'];

// ── Per-batch stats ──────────────────────────────────────────────────────────
$batchDetails   = [];
$globalPresent  = 0; $globalAttDays  = 0;
$globalPending  = 0; $globalGraded   = 0; $globalScorePct = 0;

foreach ($batches as $b) {
    $bId = $b['id'];

    // Attendance
    $allAtt  = $batchRepo->getAllAttendance($bId);
    $p = $a = $l = 0;
    foreach ($allAtt as $recs) {
        $val = $recs[$studentId] ?? '';
        if ($val === 'present')    $p++;
        elseif ($val === 'absent') $a++;
        elseif ($val === 'late')   $l++;
    }
    $tracked = $p + $a + $l;
    $attPct  = $tracked > 0 ? round($p / $tracked * 100) : null;
    $globalPresent += $p;
    $globalAttDays += $tracked;

    // Assignments
    $assignments = $batchRepo->listAssignments($bId);
    $pending = $submitted = $graded = 0;
    $totalScore = $maxScore = 0;
    foreach ($assignments as $asn) {
        $sub = $batchRepo->getSubmission($bId, $asn['id'], $studentId);
        if (empty($sub)) {
            $pending++;
        } else {
            $submitted++;
            if (isset($sub['marks'])) {
                $graded++;
                $totalScore += (float)$sub['marks'];
                $maxScore   += (int)($asn['max_marks'] ?? 100);
            }
        }
    }
    $avgPct = ($graded > 0 && $maxScore > 0) ? round($totalScore / $maxScore * 100) : null;
    $globalPending += $pending;
    if ($avgPct !== null) { $globalGraded++; $globalScorePct += $avgPct; }

    $trainerData = $userRepo->getUser($b['trainer_id'] ?? '');
    $batchCert   = $certRepo->findForStudentBatch($studentId, $bId);
    $batchDetails[$bId] = [
        'present'   => $p, 'absent' => $a, 'late' => $l, 'tracked' => $tracked,
        'attPct'    => $attPct,
        'pending'   => $pending, 'submitted' => $submitted,
        'total'     => count($assignments), 'graded' => $graded, 'avgPct' => $avgPct,
        'trainer'   => $trainerData['name'] ?? '—',
        'cert'      => $batchCert,
    ];
}

$overallAttPct   = $globalAttDays > 0 ? round($globalPresent / $globalAttDays * 100) : null;
$overallAvgGrade = $globalGraded  > 0 ? round($globalScorePct / $globalGraded) : null;
$activeCount     = count(array_filter($batches, fn($b) => ($b['status'] ?? '') === 'active'));
?>
<!DOCTYPE html>
<html lang="<?= $langCode ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Dashboard — <?= htmlspecialchars($platformTitle) ?></title>
    <link rel="stylesheet" href="/E_Learning/assets/css/portal.css">
    <style>
        .progress-bar-wrap {
            background: var(--color-gray-200);
            border-radius: 99px;
            height: 6px;
            margin: .3rem 0 .15rem;
            overflow: hidden;
        }
        .progress-bar { height: 100%; border-radius: 99px; }
        .stat-tile { border-top: 3px solid var(--color-primary); }
        .att-pill {
            display: inline-block;
            padding: .1rem .45rem;
            border-radius: 99px;
            font-size: .75rem;
            font-weight: 600;
        }
        .att-pill--good { background: var(--color-success-light); color: var(--color-success); }
        .att-pill--warn { background: var(--color-warning-light); color: var(--color-warning); }
        .att-pill--bad  { background: var(--color-danger-light);  color: var(--color-danger);  }
        .att-pill--none { background: var(--color-gray-100);      color: var(--color-gray-400);}
        .batch-card__actions {
            display: flex;
            gap: .5rem;
            flex-wrap: wrap;
            margin-top: .75rem;
            padding-top: .75rem;
            border-top: 1px solid var(--color-gray-100);
        }
    </style>
</head>
<body>
<div class="portal-shell">

    <header class="topbar">
        <span class="topbar__brand">🎓 <?= htmlspecialchars($platformTitle) ?></span>
        <div class="topbar__user">
            <span>👤 <?= htmlspecialchars($studentName) ?></span>
            <a href="/E_Learning/student/logout.php">Sign out</a>
        </div>
    </header>

    <div class="portal-body">
        <nav class="sidebar">
            <div class="sidebar__nav">
                <a href="/E_Learning/student/dashboard.php" class="active">
                    <span class="nav-icon">📋</span> My Dashboard
                </a>
                <a href="/E_Learning/student/browse.php">
                    <span class="nav-icon">🔍</span> Browse &amp; Enroll
                </a>
            </div>
        </nav>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1>My Dashboard</h1>
                    <p class="subtitle">Welcome back, <?= htmlspecialchars($studentName) ?></p>
                </div>
                <a href="/E_Learning/student/browse.php" class="btn btn--primary">Browse Courses</a>
            </div>

            <!-- Summary Stats -->
            <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(130px,1fr))">
                <div class="stat-tile">
                    <div class="stat-tile__value"><?= count($batches) ?></div>
                    <div class="stat-tile__label">Enrolled</div>
                </div>
                <div class="stat-tile">
                    <div class="stat-tile__value"><?= $activeCount ?></div>
                    <div class="stat-tile__label">Active</div>
                </div>
                <div class="stat-tile">
                    <div class="stat-tile__value" style="color:<?= $overallAttPct !== null ? ($overallAttPct >= 75 ? 'var(--color-success)' : ($overallAttPct >= 50 ? 'var(--color-warning)' : 'var(--color-danger)')) : 'var(--color-gray-400)' ?>">
                        <?= $overallAttPct !== null ? $overallAttPct . '%' : '—' ?>
                    </div>
                    <div class="stat-tile__label">Attendance</div>
                </div>
                <div class="stat-tile">
                    <div class="stat-tile__value" style="color:<?= $globalPending > 0 ? 'var(--color-warning)' : 'var(--color-success)' ?>">
                        <?= $globalPending ?>
                    </div>
                    <div class="stat-tile__label">Pending Tasks</div>
                </div>
                <?php if ($overallAvgGrade !== null): ?>
                <div class="stat-tile">
                    <div class="stat-tile__value" style="color:var(--color-primary)"><?= $overallAvgGrade ?>%</div>
                    <div class="stat-tile__label">Avg Grade</div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Batch Cards -->
            <?php if (empty($batches)): ?>
                <div class="empty-state">
                    <div style="font-size:3rem;margin-bottom:.75rem">📭</div>
                    <p>You are not enrolled in any course yet.</p>
                    <a href="/E_Learning/student/browse.php" class="btn btn--primary" style="margin-top:.75rem">
                        Browse Open Courses →
                    </a>
                </div>
            <?php else: ?>
                <div class="batch-grid">
                    <?php foreach ($batches as $b): ?>
                        <?php
                        $bId      = $b['id'];
                        $d        = $batchDetails[$bId];
                        $cInfo    = $courses[$b['course_id'] ?? ''] ?? [];
                        $status   = $b['status'] ?? 'upcoming';
                        $aP       = $d['attPct'];
                        $aCls     = $aP === null ? 'att-pill--none' : ($aP >= 75 ? 'att-pill--good' : ($aP >= 50 ? 'att-pill--warn' : 'att-pill--bad'));
                        $aColor   = $aP === null ? 'var(--color-gray-300)' : ($aP >= 75 ? 'var(--color-success)' : ($aP >= 50 ? 'var(--color-warning)' : 'var(--color-danger)'));
                        ?>
                        <div class="batch-card">

                            <!-- Header row -->
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem">
                                <div class="batch-card__name"><?= htmlspecialchars($b['name'] ?? '') ?></div>
                                <span class="badge <?= $statusClass[$status] ?? 'badge--gray' ?>" style="flex-shrink:0">
                                    <?= $statusLabel[$status] ?? ucfirst($status) ?>
                                </span>
                            </div>

                            <!-- Course & trainer -->
                            <?php if (!empty($cInfo['title'])): ?>
                                <div class="batch-card__meta" style="font-weight:600;color:var(--color-gray-800)">
                                    📚 <?= htmlspecialchars($cInfo['title']) ?>
                                </div>
                            <?php endif; ?>
                            <div class="batch-card__meta">👨‍🏫 <?= htmlspecialchars($d['trainer']) ?></div>
                            <div class="batch-card__meta">
                                📅 <?= $b['start_date'] ? date('d M Y', strtotime($b['start_date'])) : '—' ?>
                                → <?= $b['end_date'] ? date('d M Y', strtotime($b['end_date'])) : '—' ?>
                            </div>

                            <!-- Attendance -->
                            <div style="margin-top:.65rem">
                                <div style="display:flex;justify-content:space-between;font-size:.78rem;color:var(--color-gray-500)">
                                    <span>Attendance</span>
                                    <span class="att-pill <?= $aCls ?>"><?= $aP !== null ? $aP . '%' : 'Not tracked' ?></span>
                                </div>
                                <?php if ($d['tracked'] > 0): ?>
                                    <div class="progress-bar-wrap">
                                        <div class="progress-bar" style="width:<?= $aP ?>%;background:<?= $aColor ?>"></div>
                                    </div>
                                    <div style="font-size:.74rem;color:var(--color-gray-400)">
                                        <?= $d['present'] ?>P · <?= $d['absent'] ?>A · <?= $d['late'] ?>L
                                        of <?= $d['tracked'] ?> days
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Assignments -->
                            <?php if ($d['total'] > 0): ?>
                                <div style="margin-top:.65rem">
                                    <div style="display:flex;justify-content:space-between;font-size:.78rem;color:var(--color-gray-500)">
                                        <span>Assignments</span>
                                        <?php if ($d['avgPct'] !== null): ?>
                                            <span style="font-weight:700;color:var(--color-primary)">Avg: <?= $d['avgPct'] ?>%</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="progress-bar-wrap">
                                        <?php $sp = $d['total'] > 0 ? round($d['submitted'] / $d['total'] * 100) : 0; ?>
                                        <div class="progress-bar" style="width:<?= $sp ?>%;background:var(--color-primary-dark, #1d4ed8)"></div>
                                    </div>
                                    <div style="font-size:.74rem;color:var(--color-gray-400)">
                                        <?= $d['submitted'] ?>/<?= $d['total'] ?> submitted
                                        <?php if ($d['graded'] > 0): ?> · <?= $d['graded'] ?> graded<?php endif; ?>
                                        <?php if ($d['pending'] > 0): ?>
                                            · <span style="color:var(--color-warning);font-weight:600"><?= $d['pending'] ?> pending</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Buttons -->
                            <div class="batch-card__actions">
                                <a href="/E_Learning/student/batch.php?id=<?= urlencode($bId) ?>"
                                   class="btn btn--secondary btn--sm">Details</a>
                                <?php if (!empty($b['course_id'])): ?>
                                    <a href="/E_Learning/player/?course=<?= urlencode($b['course_id']) ?>"
                                       target="_blank" class="btn btn--primary btn--sm">▶ Start Course</a>
                                <?php endif; ?>
                                <?php if (!empty($d['cert'])): ?>
                                    <a href="/E_Learning/student/certificate.php?id=<?= urlencode($d['cert']['id']) ?>"
                                       target="_blank"
                                       class="btn btn--sm"
                                       style="background:#fef3c7;color:#92400e;border:1px solid #f59e0b">
                                        📜 Certificate
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>
</body>
</html>
