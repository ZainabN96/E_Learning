<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/UserAuth.php';
require_once dirname(__DIR__) . '/core/BatchRepository.php';
require_once dirname(__DIR__) . '/core/CourseRepository.php';
require_once dirname(__DIR__) . '/core/UserRepository.php';
require_once dirname(__DIR__) . '/core/CertificateRepository.php';

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
$certRepo    = new CertificateRepository();
$course      = $courseRepo->getCourse($batch['course_id'] ?? '');
$assignments = $batchRepo->listAssignments($batchId);
$msg         = $_GET['msg'] ?? '';

// Students
$students = [];
foreach ($batch['student_ids'] ?? [] as $sid) {
    $s = $userRepo->getUser($sid);
    if (!empty($s)) $students[$sid] = $s;
}

// Attendance
$allAtt       = $batchRepo->getAllAttendance($batchId);
$totalDays    = count($allAtt);

// Per-student stats
$studentStats = [];
foreach ($students as $sid => $s) {
    $p = $a = $l = 0;
    foreach ($allAtt as $recs) {
        $v = $recs[$sid] ?? '';
        if ($v === 'present')    $p++;
        elseif ($v === 'absent') $a++;
        elseif ($v === 'late')   $l++;
    }
    $tracked = $p + $a + $l;
    $attPct  = $tracked > 0 ? round($p / $tracked * 100) : null;

    $submitted = $graded = 0;
    $totalScore = $maxScore = 0;
    foreach ($assignments as $asn) {
        $sub = $batchRepo->getSubmission($batchId, $asn['id'], $sid);
        if (!empty($sub)) {
            $submitted++;
            if (isset($sub['marks'])) {
                $graded++;
                $totalScore += (float)$sub['marks'];
                $maxScore   += (int)($asn['max_marks'] ?? 100);
            }
        }
    }
    $avgPct = ($graded > 0 && $maxScore > 0) ? round($totalScore / $maxScore * 100) : null;

    // Eligibility: attendance >= 75% (if tracked) AND assignments >= 50% submitted
    $nAssign    = count($assignments);
    $attOk      = $attPct === null || $attPct >= 75;
    $subOk      = $nAssign === 0 || ($submitted / max(1, $nAssign)) >= 0.5;
    $eligible   = $attOk && $subOk;

    $cert = $certRepo->findForStudentBatch($sid, $batchId);

    $studentStats[$sid] = [
        'p' => $p, 'a' => $a, 'l' => $l, 'tracked' => $tracked,
        'attPct'    => $attPct,
        'submitted' => $submitted, 'graded' => $graded,
        'avgPct'    => $avgPct,
        'eligible'  => $eligible,
        'cert'      => $cert,
    ];
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
            <a href="/E_Learning/trainer/dashboard.php">My Batches</a>
            <a href="/E_Learning/trainer/logout.php">Sign out</a>
        </div>
    </header>

    <div class="portal-body">
        <nav class="sidebar">
            <div class="sidebar__nav">
                <a href="/E_Learning/trainer/dashboard.php"><span class="nav-icon">📋</span> My Batches</a>
                <a href="/E_Learning/trainer/reports.php"><span class="nav-icon">📊</span> Reports</a>
                <div style="border-top:1px solid var(--color-gray-200);margin:.75rem 0;padding-top:.75rem">
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
            <div id="batch-msg" style="display:none" class="alert alert--success"></div>

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
                    <div class="stat-tile__value">
                        <?= count(array_filter($studentStats, fn($s) => !empty($s['cert']))) ?>
                    </div>
                    <div class="stat-tile__label">Certified</div>
                </div>
            </div>

            <!-- Students + Certificates -->
            <div class="card">
                <div class="card__header">
                    <h2>👥 Students &amp; Certificates</h2>
                </div>
                <div class="card__body" style="padding:0;overflow-x:auto">
                    <?php if (empty($students)): ?>
                        <p style="padding:1rem;color:var(--color-gray-400)">No students enrolled.</p>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Attendance</th>
                                    <th>Assignments</th>
                                    <th>Avg Grade</th>
                                    <th>Status</th>
                                    <th>Certificate</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($students as $sid => $s):
                                $st  = $studentStats[$sid];
                                $aP  = $st['attPct'];
                                $aC  = $aP === null ? 'var(--color-gray-400)' : ($aP >= 75 ? 'var(--color-success)' : ($aP >= 50 ? 'var(--color-warning)' : 'var(--color-danger)'));
                                $cert = $st['cert'];
                            ?>
                                <tr id="row-<?= htmlspecialchars($sid) ?>">
                                    <td>
                                        <strong><?= htmlspecialchars($s['name'] ?? '') ?></strong><br>
                                        <small style="color:var(--color-gray-400)"><?= htmlspecialchars($s['email'] ?? '') ?></small>
                                    </td>
                                    <td>
                                        <?php if ($st['tracked'] > 0): ?>
                                            <span style="font-weight:600;color:<?= $aC ?>"><?= $aP ?>%</span>
                                            <span style="font-size:.76rem;color:var(--color-gray-400)">
                                                (<?= $st['p'] ?>P/<?= $st['a'] ?>A/<?= $st['l'] ?>L)
                                            </span>
                                        <?php else: ?>
                                            <span style="color:var(--color-gray-300)">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= $st['submitted'] ?>/<?= count($assignments) ?> submitted
                                        <?php if ($st['graded'] > 0): ?>
                                            <br><small style="color:var(--color-gray-400)"><?= $st['graded'] ?> graded</small>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-weight:700;color:var(--color-primary)">
                                        <?= $st['avgPct'] !== null ? $st['avgPct'] . '%' : '—' ?>
                                    </td>
                                    <td>
                                        <?php if ($st['eligible']): ?>
                                            <span class="badge badge--success">Eligible</span>
                                        <?php else: ?>
                                            <span class="badge badge--warning">Not eligible</span>
                                        <?php endif; ?>
                                    </td>
                                    <td id="cert-cell-<?= htmlspecialchars($sid) ?>">
                                        <?php if (!empty($cert)): ?>
                                            <a href="/E_Learning/student/certificate.php?id=<?= urlencode($cert['id']) ?>"
                                               target="_blank" class="btn btn--sm btn--secondary">📜 View</a>
                                        <?php else: ?>
                                            <button type="button"
                                                    class="btn btn--sm <?= $st['eligible'] ? 'btn--primary' : 'btn--secondary' ?>"
                                                    onclick="issueCert('<?= addslashes($sid) ?>', '<?= addslashes($s['name'] ?? '') ?>')">
                                                🎓 Issue Cert
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Two column: Modules + Assignments -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-top:1.25rem">

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
                                <thead><tr><th>Title</th><th>Due</th><th>Submissions</th></tr></thead>
                                <tbody>
                                <?php foreach ($assignments as $a): ?>
                                    <?php $subs = $batchRepo->listSubmissions($batchId, $a['id']); ?>
                                    <tr>
                                        <td><?= htmlspecialchars($a['title'] ?? '') ?></td>
                                        <td><?= $a['due_date'] ? date('d M Y', strtotime($a['due_date'])) : '—' ?></td>
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
            </div>

        </main>
    </div>
</div>

<script>
const BATCH_ID = <?= json_encode($batchId) ?>;

function issueCert(studentId, studentName) {
    if (!confirm('Issue certificate to "' + studentName + '"?')) return;

    const btn = document.querySelector('#cert-cell-' + studentId + ' button');
    if (btn) { btn.disabled = true; btn.textContent = 'Issuing…'; }

    fetch('/E_Learning/trainer/api/issue-certificate.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'batch_id=' + encodeURIComponent(BATCH_ID) + '&student_id=' + encodeURIComponent(studentId)
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok && data.cert) {
            const cell = document.getElementById('cert-cell-' + studentId);
            cell.innerHTML = '<a href="/E_Learning/student/certificate.php?id=' + encodeURIComponent(data.cert.id) +
                '" target="_blank" class="btn btn--sm btn--secondary">📜 View</a>';
            showMsg('Certificate issued for ' + studentName);
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
            if (btn) { btn.disabled = false; btn.textContent = '🎓 Issue Cert'; }
        }
    })
    .catch(() => {
        alert('Request failed. Please try again.');
        if (btn) { btn.disabled = false; btn.textContent = '🎓 Issue Cert'; }
    });
}

function showMsg(text) {
    const el = document.getElementById('batch-msg');
    el.textContent = text;
    el.style.display = '';
    setTimeout(() => el.style.display = 'none', 4000);
}
</script>
</body>
</html>
