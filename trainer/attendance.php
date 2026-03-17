<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/UserAuth.php';
require_once dirname(__DIR__) . '/core/BatchRepository.php';
require_once dirname(__DIR__) . '/core/UserRepository.php';

UserAuth::requireTrainer();
$lang          = load_lang();
$langCode      = get_lang_code();
$platformTitle = $lang['platform_title'];
$trainerId     = UserAuth::userId();

$batchId   = $_GET['batch'] ?? '';
$batchRepo = new BatchRepository();
$batch     = $batchId ? $batchRepo->getBatch($batchId) : [];

if (empty($batch) || $batch['trainer_id'] !== $trainerId) {
    header('Location: /E_Learning/trainer/dashboard.php'); exit;
}

$userRepo = new UserRepository();
$students = [];
foreach ($batch['student_ids'] ?? [] as $sid) {
    $s = $userRepo->getUser($sid);
    if (!empty($s)) $students[$sid] = $s;
}

// Selected date (default: today)
$date = preg_replace('/[^0-9\-]/', '', $_GET['date'] ?? date('Y-m-d'));
$existing = $batchRepo->getAttendance($batchId, $date);
$records  = $existing['records'] ?? [];

// All attendance dates for history
$allAtt = $batchRepo->getAllAttendance($batchId);
ksort($allAtt);
$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="<?= $langCode ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Attendance — <?= htmlspecialchars($batch['name'] ?? '') ?></title>
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
                <a href="/E_Learning/trainer/batch.php?id=<?= urlencode($batchId) ?>"><span class="nav-icon">📦</span> Batch Overview</a>
                <a href="/E_Learning/trainer/attendance.php?batch=<?= urlencode($batchId) ?>" class="active">
                    <span class="nav-icon">✅</span> Attendance
                </a>
                <a href="/E_Learning/trainer/assignments.php?batch=<?= urlencode($batchId) ?>">
                    <span class="nav-icon">📝</span> Assignments
                </a>
            </div>
        </nav>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1>Attendance — <?= htmlspecialchars($batch['name'] ?? '') ?></h1>
                </div>
            </div>

            <?php if ($msg === 'saved'): ?>
                <div class="alert alert--success">Attendance saved.</div>
            <?php endif; ?>

            <div style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem">

                <!-- Mark attendance -->
                <div class="card">
                    <div class="card__header">
                        <h2>Mark Attendance</h2>
                        <!-- Date picker -->
                        <form method="get" style="display:flex;gap:.5rem;align-items:center">
                            <input type="hidden" name="batch" value="<?= htmlspecialchars($batchId) ?>">
                            <input type="date" name="date" class="form-control" style="width:auto"
                                   value="<?= htmlspecialchars($date) ?>"
                                   min="<?= $batch['start_date'] ?? '' ?>"
                                   max="<?= $batch['end_date'] ?? '' ?>">
                            <button type="submit" class="btn btn--secondary btn--sm">Load</button>
                        </form>
                    </div>
                    <div class="card__body">
                        <p style="margin-bottom:1rem;font-weight:500">
                            Date: <?= date('l, d F Y', strtotime($date)) ?>
                        </p>

                        <?php if (empty($students)): ?>
                            <p style="color:var(--color-gray-400)">No students enrolled in this batch.</p>
                        <?php else: ?>
                            <form method="post" action="/E_Learning/trainer/api/save-attendance.php">
                                <input type="hidden" name="batch_id" value="<?= htmlspecialchars($batchId) ?>">
                                <input type="hidden" name="date" value="<?= htmlspecialchars($date) ?>">

                                <div style="margin-bottom:1rem;display:flex;gap:.5rem">
                                    <button type="button" class="btn btn--sm btn--success" onclick="markAll('present')">All Present</button>
                                    <button type="button" class="btn btn--sm btn--danger"  onclick="markAll('absent')">All Absent</button>
                                </div>

                                <div class="attendance-grid">
                                    <?php foreach ($students as $sid => $s): ?>
                                        <?php $val = $records[$sid] ?? ''; ?>
                                        <div class="attendance-row" id="row_<?= htmlspecialchars($sid) ?>">
                                            <span class="student-name"><?= htmlspecialchars($s['name'] ?? '') ?></span>
                                            <div class="att-btns">
                                                <button type="button" class="att-btn <?= $val === 'present' ? 'active' : '' ?>"
                                                        data-val="present" data-sid="<?= htmlspecialchars($sid) ?>"
                                                        onclick="setAtt(this)">P</button>
                                                <button type="button" class="att-btn <?= $val === 'absent' ? 'active' : '' ?>"
                                                        data-val="absent" data-sid="<?= htmlspecialchars($sid) ?>"
                                                        onclick="setAtt(this)">A</button>
                                                <button type="button" class="att-btn <?= $val === 'late' ? 'active' : '' ?>"
                                                        data-val="late" data-sid="<?= htmlspecialchars($sid) ?>"
                                                        onclick="setAtt(this)">L</button>
                                            </div>
                                            <input type="hidden" name="records[<?= htmlspecialchars($sid) ?>]"
                                                   id="val_<?= htmlspecialchars($sid) ?>"
                                                   value="<?= htmlspecialchars($val) ?>">
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div style="margin-top:1.25rem">
                                    <button type="submit" class="btn btn--primary">Save Attendance</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Attendance history -->
                <div class="card">
                    <div class="card__header"><h2>History</h2></div>
                    <div class="card__body" style="padding:0;max-height:500px;overflow-y:auto">
                        <?php if (empty($allAtt)): ?>
                            <p style="padding:1rem;color:var(--color-gray-400)">No records yet.</p>
                        <?php else: ?>
                            <table class="data-table">
                                <thead><tr><th>Date</th><th>P</th><th>A</th><th>L</th></tr></thead>
                                <tbody>
                                <?php foreach (array_reverse($allAtt, true) as $d => $recs): ?>
                                    <?php
                                    $p = count(array_filter($recs, fn($v) => $v === 'present'));
                                    $a = count(array_filter($recs, fn($v) => $v === 'absent'));
                                    $l = count(array_filter($recs, fn($v) => $v === 'late'));
                                    ?>
                                    <tr>
                                        <td>
                                            <a href="?batch=<?= urlencode($batchId) ?>&date=<?= $d ?>"
                                               style="font-size:.82rem">
                                                <?= date('d M', strtotime($d)) ?>
                                            </a>
                                        </td>
                                        <td><span class="badge badge--success"><?= $p ?></span></td>
                                        <td><span class="badge badge--danger"><?= $a ?></span></td>
                                        <td><span class="badge badge--warning"><?= $l ?></span></td>
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
function setAtt(btn) {
    const sid = btn.dataset.sid;
    const val = btn.dataset.val;
    // Deactivate all buttons for this student
    document.querySelectorAll(`.att-btn[data-sid="${sid}"]`).forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('val_' + sid).value = val;
}
function markAll(val) {
    document.querySelectorAll('.att-btn[data-val="' + val + '"]').forEach(btn => {
        const sid = btn.dataset.sid;
        document.querySelectorAll(`.att-btn[data-sid="${sid}"]`).forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('val_' + sid).value = val;
    });
}
</script>
</body>
</html>
