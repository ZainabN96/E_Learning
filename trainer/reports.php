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
$trainerName   = UserAuth::userName();

$batchRepo  = new BatchRepository();
$courseRepo = new CourseRepository();
$userRepo   = new UserRepository();

$batches    = $batchRepo->listBatches($trainerId);
$courses    = array_column($courseRepo->listCourses(), null, 'id');

// Selected batch (default to first active, then first batch)
$activeBatch = $_GET['batch'] ?? '';
if (!$activeBatch && !empty($batches)) {
    foreach ($batches as $b) {
        if (($b['status'] ?? '') === 'active') { $activeBatch = $b['id']; break; }
    }
    if (!$activeBatch) $activeBatch = $batches[0]['id'];
}

$batch       = $activeBatch ? $batchRepo->getBatch($activeBatch) : [];
$batchCourse = !empty($batch['course_id']) ? ($courses[$batch['course_id']] ?? []) : [];

// Verify batch belongs to this trainer
if (!empty($batch) && ($batch['trainer_id'] ?? '') !== $trainerId) {
    $batch = [];
}

// ── Per-student stats for this batch ────────────────────────────────────────
$studentStats = [];
if (!empty($batch)) {
    $allStudentIds = $batch['student_ids'] ?? [];
    $allAtt        = $batchRepo->getAllAttendance($activeBatch);
    $assignments   = $batchRepo->listAssignments($activeBatch);
    $nDays         = count($allAtt);
    $nAssign       = count($assignments);

    // Pre-load all submissions
    $allSubs = [];
    foreach ($assignments as $a) {
        $allSubs[$a['id']] = $batchRepo->listSubmissions($activeBatch, $a['id']);
    }

    foreach ($allStudentIds as $sid) {
        $stu = $userRepo->getUser($sid);

        // Attendance
        $p = $a = $l = 0;
        foreach ($allAtt as $recs) {
            $val = $recs[$sid] ?? '';
            if ($val === 'present')    $p++;
            elseif ($val === 'absent') $a++;
            elseif ($val === 'late')   $l++;
        }
        $tracked = $p + $a + $l;
        $attPct  = $tracked > 0 ? round($p / $tracked * 100) : null;

        // Assignments
        $submitted = $graded = 0;
        $totalScore = $maxScore = 0;
        $latePendings = [];
        foreach ($assignments as $asn) {
            $sub = $allSubs[$asn['id']][$sid] ?? [];
            if (empty($sub)) {
                $latePendings[] = $asn['title'] ?? '';
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
        $pending = $nAssign - $submitted;

        // Overall score (avg of att% + grade%)
        $scores  = array_filter([$attPct, $avgPct], fn($v) => $v !== null);
        $overall = count($scores) > 0 ? round(array_sum($scores) / count($scores)) : null;

        $studentStats[] = [
            'student'   => $stu,
            'p' => $p, 'a' => $a, 'l' => $l, 'tracked' => $tracked,
            'attPct'    => $attPct,
            'submitted' => $submitted, 'graded' => $graded, 'pending' => $pending,
            'avgPct'    => $avgPct,
            'overall'   => $overall,
        ];
    }

    // Sort by overall score desc (null last)
    usort($studentStats, function($x, $y) {
        if ($x['overall'] === null && $y['overall'] === null) return 0;
        if ($x['overall'] === null) return 1;
        if ($y['overall'] === null) return -1;
        return $y['overall'] - $x['overall'];
    });
}

// Batch summary tiles
$totalStudents = count($batch['student_ids'] ?? []);
$avgAtt        = $studentStats ? (function() use ($studentStats) {
    $vals = array_filter(array_column($studentStats, 'attPct'), fn($v) => $v !== null);
    return count($vals) > 0 ? round(array_sum($vals) / count($vals)) : null;
})() : null;
$avgGrade      = $studentStats ? (function() use ($studentStats) {
    $vals = array_filter(array_column($studentStats, 'avgPct'), fn($v) => $v !== null);
    return count($vals) > 0 ? round(array_sum($vals) / count($vals)) : null;
})() : null;
$nAssign       = isset($assignments) ? count($assignments) : 0;
?>
<!DOCTYPE html>
<html lang="<?= $langCode ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reports — <?= htmlspecialchars($platformTitle) ?></title>
    <link rel="stylesheet" href="/E_Learning/assets/css/portal.css">
    <style>
        .mini-bar-wrap { background: var(--color-gray-200); border-radius: 4px; height: 5px; width: 70px; display: inline-block; vertical-align: middle; overflow: hidden; }
        .mini-bar { height: 100%; border-radius: 4px; }
        .rank-badge { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; font-size: .75rem; font-weight: 700; background: var(--color-gray-100); color: var(--color-gray-500); }
        .rank-badge--1 { background: #fef3c7; color: #d97706; }
        .rank-badge--2 { background: #f3f4f6; color: #374151; }
        .rank-badge--3 { background: #fde8d8; color: #b45309; }
    </style>
</head>
<body>
<div class="portal-shell">

    <header class="topbar">
        <span class="topbar__brand">🎓 <?= htmlspecialchars($platformTitle) ?></span>
        <div class="topbar__user">
            <span>👤 <?= htmlspecialchars($trainerName) ?></span>
            <a href="/E_Learning/trainer/logout.php">Sign out</a>
        </div>
    </header>

    <div class="portal-body">
        <nav class="sidebar">
            <div class="sidebar__nav">
                <a href="/E_Learning/trainer/dashboard.php"><span class="nav-icon">📋</span> My Batches</a>
                <a href="/E_Learning/trainer/reports.php" class="active"><span class="nav-icon">📊</span> Reports</a>
                <?php if ($activeBatch): ?>
                    <div style="border-top:1px solid var(--color-gray-200);margin:.75rem 0;padding-top:.75rem">
                    <?php foreach ($batches as $b): ?>
                        <a href="?batch=<?= urlencode($b['id']) ?>"
                           class="<?= $b['id'] === $activeBatch ? 'active' : '' ?>"
                           style="font-size:.82rem">
                            <?= htmlspecialchars($b['name'] ?? '') ?>
                        </a>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </nav>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1>Reports</h1>
                    <p class="subtitle">
                        <?php if (!empty($batch)): ?>
                            <?= htmlspecialchars($batch['name'] ?? '') ?>
                            <?php if (!empty($batchCourse['title'])): ?>
                                · <?= htmlspecialchars($batchCourse['title']) ?>
                            <?php endif; ?>
                        <?php else: ?>
                            Select a batch to view report
                        <?php endif; ?>
                    </p>
                </div>
                <?php if (empty($batches)): ?>
                <?php elseif (count($batches) > 1): ?>
                    <form method="get" style="display:flex;gap:.5rem;align-items:center">
                        <select name="batch" class="form-control" style="min-width:200px" onchange="this.form.submit()">
                            <?php foreach ($batches as $b): ?>
                                <option value="<?= htmlspecialchars($b['id']) ?>" <?= $b['id'] === $activeBatch ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($b['name'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                <?php endif; ?>
            </div>

            <?php if (empty($batches)): ?>
                <div class="empty-state">
                    <div style="font-size:3rem;margin-bottom:.75rem">📊</div>
                    <p>No batches assigned to you yet.</p>
                </div>

            <?php elseif (empty($batch)): ?>
                <div class="alert alert--info">Batch not found or access denied.</div>

            <?php else: ?>

                <!-- Summary tiles -->
                <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(130px,1fr))">
                    <div class="stat-tile">
                        <div class="stat-tile__value"><?= $totalStudents ?></div>
                        <div class="stat-tile__label">Students</div>
                    </div>
                    <div class="stat-tile">
                        <div class="stat-tile__value"><?= $nAssign ?></div>
                        <div class="stat-tile__label">Assignments</div>
                    </div>
                    <div class="stat-tile">
                        <div class="stat-tile__value" style="color:<?= $avgAtt !== null ? ($avgAtt >= 75 ? 'var(--color-success)' : ($avgAtt >= 50 ? 'var(--color-warning)' : 'var(--color-danger)')) : 'var(--color-gray-400)' ?>">
                            <?= $avgAtt !== null ? $avgAtt . '%' : '—' ?>
                        </div>
                        <div class="stat-tile__label">Avg Attendance</div>
                    </div>
                    <div class="stat-tile">
                        <div class="stat-tile__value" style="color:var(--color-primary)">
                            <?= $avgGrade !== null ? $avgGrade . '%' : '—' ?>
                        </div>
                        <div class="stat-tile__label">Avg Grade</div>
                    </div>
                </div>

                <!-- Student performance table -->
                <?php if (empty($studentStats)): ?>
                    <div class="empty-state">
                        <div style="font-size:3rem;margin-bottom:.75rem">👥</div>
                        <p>No students enrolled in this batch.</p>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card__header"><h2>Student Performance</h2></div>
                        <div class="card__body" style="padding:0;overflow-x:auto">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th style="width:32px">#</th>
                                        <th>Student</th>
                                        <th>Present</th>
                                        <th>Absent</th>
                                        <th>Late</th>
                                        <th>Attendance</th>
                                        <th>Submitted</th>
                                        <th>Graded</th>
                                        <th>Pending</th>
                                        <th>Avg Grade</th>
                                        <th>Overall</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($studentStats as $rank => $s):
                                    $stu      = $s['student'];
                                    $attP     = $s['attPct'];
                                    $attColor = $attP === null ? '#9ca3af' : ($attP >= 75 ? '#16a34a' : ($attP >= 50 ? '#d97706' : '#dc2626'));
                                    $grP      = $s['avgPct'];
                                    $ov       = $s['overall'];
                                    $ovColor  = $ov === null ? '#9ca3af' : ($ov >= 75 ? '#16a34a' : ($ov >= 50 ? '#d97706' : '#dc2626'));
                                    $rankN    = $rank + 1;
                                    $rkCls    = $rankN <= 3 ? 'rank-badge--' . $rankN : '';
                                ?>
                                    <tr>
                                        <td><span class="rank-badge <?= $rkCls ?>"><?= $rankN ?></span></td>
                                        <td>
                                            <strong><?= htmlspecialchars($stu['name'] ?? '—') ?></strong><br>
                                            <small style="color:var(--color-gray-400)"><?= htmlspecialchars($stu['email'] ?? '') ?></small>
                                        </td>
                                        <td style="text-align:center;color:var(--color-success);font-weight:600"><?= $s['p'] ?></td>
                                        <td style="text-align:center;color:var(--color-danger)"><?= $s['a'] ?></td>
                                        <td style="text-align:center;color:var(--color-warning)"><?= $s['l'] ?></td>
                                        <td>
                                            <?php if ($attP !== null): ?>
                                                <div style="display:flex;align-items:center;gap:.35rem">
                                                    <div class="mini-bar-wrap">
                                                        <div class="mini-bar" style="width:<?= $attP ?>%;background:<?= $attColor ?>"></div>
                                                    </div>
                                                    <span style="font-size:.82rem;color:<?= $attColor ?>;font-weight:600"><?= $attP ?>%</span>
                                                </div>
                                            <?php else: ?>
                                                <span style="color:var(--color-gray-300)">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:center"><?= $s['submitted'] ?>/<?= $nAssign ?></td>
                                        <td style="text-align:center"><?= $s['graded'] ?></td>
                                        <td style="text-align:center;color:<?= $s['pending'] > 0 ? 'var(--color-warning)' : 'var(--color-success)' ?>;font-weight:600">
                                            <?= $s['pending'] ?>
                                        </td>
                                        <td style="text-align:center;font-weight:700;color:var(--color-primary)">
                                            <?= $grP !== null ? $grP . '%' : '—' ?>
                                        </td>
                                        <td>
                                            <?php if ($ov !== null): ?>
                                                <div style="display:flex;align-items:center;gap:.35rem">
                                                    <div class="mini-bar-wrap">
                                                        <div class="mini-bar" style="width:<?= $ov ?>%;background:<?= $ovColor ?>"></div>
                                                    </div>
                                                    <span style="font-size:.82rem;color:<?= $ovColor ?>;font-weight:700"><?= $ov ?>%</span>
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
                    </div>

                    <!-- Assignment breakdown per student -->
                    <?php if (!empty($assignments)): ?>
                    <div class="card" style="margin-top:1.25rem">
                        <div class="card__header"><h2>Assignment Grades Breakdown</h2></div>
                        <div class="card__body" style="padding:0;overflow-x:auto">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <?php foreach ($assignments as $a): ?>
                                            <th style="max-width:120px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"
                                                title="<?= htmlspecialchars($a['title'] ?? '') ?>">
                                                <?= htmlspecialchars(mb_substr($a['title'] ?? '', 0, 14)) . (mb_strlen($a['title'] ?? '') > 14 ? '…' : '') ?>
                                                <br><small style="font-weight:400;color:var(--color-gray-400)">/<?= (int)($a['max_marks'] ?? 100) ?></small>
                                            </th>
                                        <?php endforeach; ?>
                                        <th>Average</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($studentStats as $s):
                                    $stu    = $s['student'];
                                    $sid2   = $stu['id'];
                                    $sumPct = 0; $cnt = 0;
                                ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($stu['name'] ?? '—') ?></strong></td>
                                        <?php foreach ($assignments as $a):
                                            $sub2 = $allSubs[$a['id']][$sid2] ?? [];
                                            $maxM = (int)($a['max_marks'] ?? 100);
                                        ?>
                                            <td style="text-align:center">
                                                <?php if (empty($sub2)): ?>
                                                    <span style="color:var(--color-gray-300)">—</span>
                                                <?php elseif (isset($sub2['marks'])): ?>
                                                    <?php $pct2 = $maxM > 0 ? round($sub2['marks'] / $maxM * 100) : 0;
                                                          $col2 = $pct2 >= 75 ? '#16a34a' : ($pct2 >= 50 ? '#d97706' : '#dc2626');
                                                          $sumPct += $pct2; $cnt++;
                                                    ?>
                                                    <span style="font-weight:700;color:<?= $col2 ?>"><?= $sub2['marks'] ?></span>
                                                <?php else: ?>
                                                    <span class="badge badge--primary" style="font-size:.7rem">Submitted</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                        <td style="text-align:center;font-weight:700;color:var(--color-primary)">
                                            <?= $cnt > 0 ? round($sumPct / $cnt) . '%' : '—' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>
</div>
</body>
</html>
