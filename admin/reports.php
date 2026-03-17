<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/Auth.php';
require_once dirname(__DIR__) . '/core/BatchRepository.php';
require_once dirname(__DIR__) . '/core/CourseRepository.php';
require_once dirname(__DIR__) . '/core/UserRepository.php';

Auth::requireLogin();
$lang          = load_lang();
$langCode      = get_lang_code();
$platformTitle = $lang['platform_title'];

$batchRepo  = new BatchRepository();
$courseRepo = new CourseRepository();
$userRepo   = new UserRepository();

$allBatches  = $batchRepo->listBatches();
$allCourses  = array_column($courseRepo->listCourses(), null, 'id');
$allTrainers = array_column($userRepo->listUsers('trainer'), null, 'id');
$allStudents = $userRepo->listUsers('student');

// ── Batch performance stats ──────────────────────────────────────────────────
$batchStats = [];
foreach ($allBatches as $b) {
    $bId    = $b['id'];
    $sIds   = $b['student_ids'] ?? [];
    $nS     = count($sIds);

    // Attendance average across all students
    $allAtt  = $batchRepo->getAllAttendance($bId);
    $nDays   = count($allAtt);
    $present = 0;
    foreach ($allAtt as $recs) {
        foreach ($sIds as $sid) {
            if (($recs[$sid] ?? '') === 'present') $present++;
        }
    }
    $denomAtt = $nDays * max(1, $nS);
    $attPct   = $nDays > 0 && $nS > 0 ? round($present / $denomAtt * 100) : null;

    // Assignments
    $assigns     = $batchRepo->listAssignments($bId);
    $nAssign     = count($assigns);
    $submitted   = 0; $gradedCount = 0; $totalPct = 0;
    foreach ($assigns as $a) {
        $subs = $batchRepo->listSubmissions($bId, $a['id']);
        $submitted += count($subs);
        foreach ($subs as $sub) {
            if (isset($sub['marks'])) {
                $gradedCount++;
                $maxM = max(1, (int)($a['max_marks'] ?? 100));
                $totalPct += round($sub['marks'] / $maxM * 100);
            }
        }
    }
    $submittedPct = ($nAssign > 0 && $nS > 0) ? round($submitted / ($nAssign * max(1,$nS)) * 100) : null;
    $avgGrade     = $gradedCount > 0 ? round($totalPct / $gradedCount) : null;

    $batchStats[] = [
        'batch'       => $b,
        'course'      => $allCourses[$b['course_id'] ?? ''] ?? [],
        'trainer'     => $allTrainers[$b['trainer_id'] ?? ''] ?? [],
        'nStudents'   => $nS,
        'nDays'       => $nDays,
        'attPct'      => $attPct,
        'nAssign'     => $nAssign,
        'submitted'   => $submitted,
        'submPct'     => $submittedPct,
        'gradedCount' => $gradedCount,
        'avgGrade'    => $avgGrade,
    ];
}

// ── Student overview ─────────────────────────────────────────────────────────
$studentStats = [];
foreach ($allStudents as $stu) {
    $sid        = $stu['id'];
    $stuBatches = $batchRepo->listBatchesForStudent($sid);
    $nBatches   = count($stuBatches);
    $totPresent = 0; $totDays = 0;
    $totGraded  = 0; $totPct  = 0;
    foreach ($stuBatches as $b) {
        $allAtt = $batchRepo->getAllAttendance($b['id']);
        foreach ($allAtt as $recs) {
            $totDays++;
            if (($recs[$sid] ?? '') === 'present') $totPresent++;
        }
        foreach ($batchRepo->listAssignments($b['id']) as $a) {
            $sub = $batchRepo->getSubmission($b['id'], $a['id'], $sid);
            if (isset($sub['marks'])) {
                $totGraded++;
                $maxM = max(1, (int)($a['max_marks'] ?? 100));
                $totPct += round($sub['marks'] / $maxM * 100);
            }
        }
    }
    $studentStats[] = [
        'student'   => $stu,
        'nBatches'  => $nBatches,
        'attPct'    => $totDays  > 0 ? round($totPresent / $totDays * 100) : null,
        'avgGrade'  => $totGraded > 0 ? round($totPct / $totGraded) : null,
    ];
}
usort($studentStats, fn($x,$y) => strcmp($x['student']['name'] ?? '', $y['student']['name'] ?? ''));

// ── Platform totals ───────────────────────────────────────────────────────────
$totalStudents  = count($allStudents);
$totalTrainers  = count($allTrainers);
$totalBatches   = count($allBatches);
$activeBatches  = count(array_filter($allBatches, fn($b) => ($b['status'] ?? '') === 'active'));
$totalCourses   = count($allCourses);
?>
<!DOCTYPE html>
<html lang="<?= $langCode ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reports — <?= htmlspecialchars($platformTitle) ?></title>
    <link rel="stylesheet" href="/E_Learning/assets/css/main.css">
    <link rel="stylesheet" href="/E_Learning/assets/css/admin.css">
    <style>
        .report-section { margin-bottom: 2.5rem; }
        .report-section h2 { font-size: 1.05rem; font-weight: 700; margin-bottom: 1rem; color: var(--color-gray-800); border-bottom: 2px solid var(--color-gray-200); padding-bottom: .5rem; }
        .mini-bar-wrap { background: #e5e7eb; border-radius: 4px; height: 5px; width: 80px; display: inline-block; vertical-align: middle; overflow: hidden; }
        .mini-bar { height: 100%; border-radius: 4px; }
        .pill { display: inline-block; padding: .15rem .5rem; border-radius: 99px; font-size: .75rem; font-weight: 600; }
        .pill--green { background: #dcfce7; color: #16a34a; }
        .pill--yellow { background: #fef3c7; color: #d97706; }
        .pill--red { background: #fee2e2; color: #dc2626; }
        .pill--gray { background: #f3f4f6; color: #6b7280; }
        .summary-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px,1fr)); gap: 1rem; margin-bottom: 2rem; }
        .summary-card { background: #fff; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,.07); padding: 1.25rem 1.5rem; border-top: 3px solid var(--color-primary); }
        .summary-card__val { font-size: 2rem; font-weight: 800; color: var(--color-primary); line-height: 1; }
        .summary-card__lbl { font-size: .8rem; color: #6b7280; margin-top: .35rem; }
    </style>
</head>
<body class="admin-body">

<header class="admin-topbar">
    <span class="admin-topbar__brand"><?= htmlspecialchars($platformTitle) ?></span>
    <div class="admin-topbar__actions">
        <a href="/E_Learning/admin/">Courses</a>
        <a href="/E_Learning/admin/users.php">Users</a>
        <a href="/E_Learning/admin/batches.php">Batches</a>
        <a href="/E_Learning/admin/reports.php" style="font-weight:700">Reports</a>
        <a href="/E_Learning/admin/logout.php"><?= $lang['admin_logout'] ?></a>
    </div>
</header>

<div class="admin-container">
    <div class="admin-page-header">
        <h1>Platform Reports</h1>
        <small style="color:var(--color-gray-400)">Generated: <?= date('d M Y, H:i') ?></small>
    </div>

    <!-- ── Platform Summary ── -->
    <div class="summary-cards">
        <div class="summary-card">
            <div class="summary-card__val"><?= $totalStudents ?></div>
            <div class="summary-card__lbl">Total Students</div>
        </div>
        <div class="summary-card">
            <div class="summary-card__val"><?= $totalTrainers ?></div>
            <div class="summary-card__lbl">Trainers</div>
        </div>
        <div class="summary-card" style="border-top-color:var(--color-success)">
            <div class="summary-card__val" style="color:var(--color-success)"><?= $activeBatches ?></div>
            <div class="summary-card__lbl">Active Batches</div>
        </div>
        <div class="summary-card" style="border-top-color:var(--color-gray-400)">
            <div class="summary-card__val" style="color:var(--color-gray-600)"><?= $totalBatches ?></div>
            <div class="summary-card__lbl">Total Batches</div>
        </div>
        <div class="summary-card" style="border-top-color:var(--color-warning)">
            <div class="summary-card__val" style="color:var(--color-warning)"><?= $totalCourses ?></div>
            <div class="summary-card__lbl">Courses</div>
        </div>
    </div>

    <!-- ── Batch Performance ── -->
    <div class="report-section">
        <h2>Batch Performance</h2>
        <?php if (empty($batchStats)): ?>
            <p style="color:var(--color-gray-400)">No batches yet.</p>
        <?php else: ?>
            <div class="card">
                <table class="course-table">
                    <thead>
                        <tr>
                            <th>Batch</th>
                            <th>Course</th>
                            <th>Trainer</th>
                            <th>Status</th>
                            <th>Students</th>
                            <th>Attendance</th>
                            <th>Assignments</th>
                            <th>Submissions</th>
                            <th>Avg Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $statusColors = ['active' => 'pill--green', 'upcoming' => 'pill--gray', 'completed' => 'pill--gray'];
                    foreach ($batchStats as $s):
                        $b = $s['batch'];
                        $st = $b['status'] ?? 'upcoming';
                        $attPct   = $s['attPct'];
                        $attColor = $attPct === null ? '' : ($attPct >= 75 ? '#16a34a' : ($attPct >= 50 ? '#d97706' : '#dc2626'));
                    ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($b['name'] ?? '') ?></strong><br>
                                <small style="color:var(--color-gray-400)">
                                    <?= $b['start_date'] ? date('d M Y', strtotime($b['start_date'])) : '—' ?>
                                    → <?= $b['end_date'] ? date('d M Y', strtotime($b['end_date'])) : '—' ?>
                                </small>
                            </td>
                            <td><?= htmlspecialchars($s['course']['title'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($s['trainer']['name'] ?? '—') ?></td>
                            <td><span class="pill <?= $statusColors[$st] ?? 'pill--gray' ?>"><?= ucfirst($st) ?></span></td>
                            <td style="text-align:center"><?= $s['nStudents'] ?></td>
                            <td>
                                <?php if ($attPct !== null): ?>
                                    <div style="display:flex;align-items:center;gap:.4rem">
                                        <div class="mini-bar-wrap">
                                            <div class="mini-bar" style="width:<?= $attPct ?>%;background:<?= $attColor ?>"></div>
                                        </div>
                                        <span style="font-size:.82rem;color:<?= $attColor ?>;font-weight:600"><?= $attPct ?>%</span>
                                    </div>
                                    <div style="font-size:.72rem;color:var(--color-gray-400)"><?= $s['nDays'] ?> day<?= $s['nDays'] !== 1 ? 's' : '' ?> tracked</div>
                                <?php else: ?>
                                    <span style="color:var(--color-gray-300)">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center"><?= $s['nAssign'] ?></td>
                            <td>
                                <?php if ($s['submPct'] !== null): ?>
                                    <div style="display:flex;align-items:center;gap:.4rem">
                                        <div class="mini-bar-wrap">
                                            <div class="mini-bar" style="width:<?= $s['submPct'] ?>%;background:var(--color-primary)"></div>
                                        </div>
                                        <span style="font-size:.82rem;font-weight:600"><?= $s['submPct'] ?>%</span>
                                    </div>
                                    <div style="font-size:.72rem;color:var(--color-gray-400)"><?= $s['submitted'] ?> total</div>
                                <?php else: ?>
                                    <span style="color:var(--color-gray-300)">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;font-weight:700;color:var(--color-primary)">
                                <?= $s['avgGrade'] !== null ? $s['avgGrade'] . '%' : '—' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- ── Student Overview ── -->
    <div class="report-section">
        <h2>Student Overview</h2>
        <?php if (empty($studentStats)): ?>
            <p style="color:var(--color-gray-400)">No students yet.</p>
        <?php else: ?>
            <div class="card">
                <table class="course-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Email</th>
                            <th>Batches</th>
                            <th>Attendance</th>
                            <th>Avg Grade</th>
                            <th>Overall</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($studentStats as $s):
                        $stu      = $s['student'];
                        $attPct   = $s['attPct'];
                        $avgGrade = $s['avgGrade'];
                        $attColor = $attPct === null ? '#9ca3af' : ($attPct >= 75 ? '#16a34a' : ($attPct >= 50 ? '#d97706' : '#dc2626'));

                        // Overall performance: average of attendance % and grade %
                        $scores = array_filter([$attPct, $avgGrade], fn($v) => $v !== null);
                        $overall = count($scores) > 0 ? round(array_sum($scores) / count($scores)) : null;
                        $ovColor = $overall === null ? '#9ca3af' : ($overall >= 75 ? '#16a34a' : ($overall >= 50 ? '#d97706' : '#dc2626'));
                    ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($stu['name'] ?? '') ?></strong></td>
                            <td><?= htmlspecialchars($stu['email'] ?? '') ?></td>
                            <td style="text-align:center"><?= $s['nBatches'] ?></td>
                            <td>
                                <?php if ($attPct !== null): ?>
                                    <div style="display:flex;align-items:center;gap:.4rem">
                                        <div class="mini-bar-wrap">
                                            <div class="mini-bar" style="width:<?= $attPct ?>%;background:<?= $attColor ?>"></div>
                                        </div>
                                        <span style="font-size:.82rem;color:<?= $attColor ?>;font-weight:600"><?= $attPct ?>%</span>
                                    </div>
                                <?php else: ?>
                                    <span style="color:var(--color-gray-300)">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;font-weight:700;color:var(--color-primary)">
                                <?= $avgGrade !== null ? $avgGrade . '%' : '—' ?>
                            </td>
                            <td>
                                <?php if ($overall !== null): ?>
                                    <div style="display:flex;align-items:center;gap:.4rem">
                                        <div class="mini-bar-wrap">
                                            <div class="mini-bar" style="width:<?= $overall ?>%;background:<?= $ovColor ?>"></div>
                                        </div>
                                        <span style="font-size:.82rem;color:<?= $ovColor ?>;font-weight:700"><?= $overall ?>%</span>
                                    </div>
                                <?php else: ?>
                                    <span style="color:var(--color-gray-300)">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
