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

$batchId    = $_GET['batch']      ?? '';
$assignId   = $_GET['assignment'] ?? '';
$batchRepo  = new BatchRepository();
$batch      = $batchId ? $batchRepo->getBatch($batchId) : [];

if (empty($batch) || $batch['trainer_id'] !== $trainerId) {
    header('Location: /E_Learning/trainer/dashboard.php'); exit;
}

$assignment = $batchRepo->getAssignment($batchId, $assignId);
if (empty($assignment)) {
    header('Location: /E_Learning/trainer/assignments.php?batch=' . urlencode($batchId)); exit;
}

$userRepo   = new UserRepository();
$students   = [];
foreach ($batch['student_ids'] ?? [] as $sid) {
    $s = $userRepo->getUser($sid);
    if (!empty($s)) $students[$sid] = $s;
}

$submissions = $batchRepo->listSubmissions($batchId, $assignId);
$msg         = $_GET['msg'] ?? '';
$maxMarks    = (int)($assignment['max_marks'] ?? 100);

// Submission file base path for downloads
$uploadBase  = '/E_Learning/data/submissions/' . $batchId . '/' . $assignId . '/';
?>
<!DOCTYPE html>
<html lang="<?= $langCode ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Grade: <?= htmlspecialchars($assignment['title'] ?? '') ?></title>
    <link rel="stylesheet" href="/E_Learning/assets/css/portal.css">
</head>
<body>
<div class="portal-shell">

    <header class="topbar">
        <span class="topbar__brand">🎓 <?= htmlspecialchars($platformTitle) ?></span>
        <div class="topbar__user">
            <span>👤 <?= htmlspecialchars(UserAuth::userName()) ?></span>
            <a href="/E_Learning/trainer/assignments.php?batch=<?= urlencode($batchId) ?>">← Assignments</a>
            <a href="/E_Learning/trainer/logout.php">Sign out</a>
        </div>
    </header>

    <div class="portal-body">
        <nav class="sidebar">
            <div class="sidebar__nav">
                <a href="/E_Learning/trainer/dashboard.php"><span class="nav-icon">📋</span> My Batches</a>
                <a href="/E_Learning/trainer/reports.php"><span class="nav-icon">&#128202;</span> Reports</a>
                <a href="/E_Learning/trainer/batch.php?id=<?= urlencode($batchId) ?>"><span class="nav-icon">📦</span> Batch Overview</a>
                <a href="/E_Learning/trainer/attendance.php?batch=<?= urlencode($batchId) ?>"><span class="nav-icon">✅</span> Attendance</a>
                <a href="/E_Learning/trainer/assignments.php?batch=<?= urlencode($batchId) ?>" class="active">
                    <span class="nav-icon">📝</span> Assignments
                </a>
            </div>
        </nav>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1>Grade: <?= htmlspecialchars($assignment['title'] ?? '') ?></h1>
                    <p class="subtitle">
                        Batch: <?= htmlspecialchars($batch['name'] ?? '') ?> ·
                        Max marks: <?= $maxMarks ?> ·
                        Due: <?= $assignment['due_date'] ? date('d M Y', strtotime($assignment['due_date'])) : '—' ?>
                    </p>
                </div>
            </div>

            <?php if ($msg === 'graded'): ?>
                <div class="alert alert--success">Grade saved.</div>
            <?php endif; ?>

            <?php if (!empty($assignment['description'])): ?>
                <div class="card" style="margin-bottom:1.25rem">
                    <div class="card__header"><h2>Assignment Instructions</h2></div>
                    <div class="card__body">
                        <?= nl2br(htmlspecialchars($assignment['description'])) ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <?php
            $submitted = count($submissions);
            $graded    = count(array_filter($submissions, fn($s) => isset($s['marks'])));
            $avgMarks  = $submitted > 0
                ? array_sum(array_map(fn($s) => $s['marks'] ?? 0, array_filter($submissions, fn($s) => isset($s['marks'])))) / max($graded, 1)
                : 0;
            ?>
            <div class="stats-grid" style="margin-bottom:1.25rem">
                <div class="stat-tile">
                    <div class="stat-tile__value"><?= count($students) ?></div>
                    <div class="stat-tile__label">Total Students</div>
                </div>
                <div class="stat-tile">
                    <div class="stat-tile__value"><?= $submitted ?></div>
                    <div class="stat-tile__label">Submitted</div>
                </div>
                <div class="stat-tile">
                    <div class="stat-tile__value"><?= $graded ?></div>
                    <div class="stat-tile__label">Graded</div>
                </div>
                <div class="stat-tile">
                    <div class="stat-tile__value"><?= $graded > 0 ? round($avgMarks, 1) : '—' ?></div>
                    <div class="stat-tile__label">Avg Marks</div>
                </div>
            </div>

            <!-- Student submissions -->
            <?php foreach ($students as $sid => $student): ?>
                <?php $sub = $submissions[$sid] ?? []; ?>
                <div class="card" style="margin-bottom:1rem">
                    <div class="card__header">
                        <h2>
                            <?= htmlspecialchars($student['name'] ?? '') ?>
                            <span style="font-weight:400;color:var(--color-gray-500);font-size:.875rem">
                                — <?= htmlspecialchars($student['email'] ?? '') ?>
                            </span>
                        </h2>
                        <?php if (!empty($sub)): ?>
                            <span class="badge <?= isset($sub['marks']) ? 'badge--success' : 'badge--warning' ?>">
                                <?= isset($sub['marks']) ? 'Graded: ' . $sub['marks'] . '/' . $maxMarks : 'Submitted — Not graded' ?>
                            </span>
                        <?php else: ?>
                            <span class="badge badge--gray">Not submitted</span>
                        <?php endif; ?>
                    </div>
                    <div class="card__body">
                        <?php if (empty($sub)): ?>
                            <p style="color:var(--color-gray-400)">No submission yet.</p>
                        <?php else: ?>
                            <!-- Submission content -->
                            <?php if (!empty($sub['text'])): ?>
                                <div style="margin-bottom:1rem;padding:1rem;background:var(--color-gray-50);border-radius:4px;border:1px solid var(--color-gray-200)">
                                    <strong style="font-size:.8rem;text-transform:uppercase;color:var(--color-gray-500)">Written Answer:</strong>
                                    <p style="margin-top:.5rem"><?= nl2br(htmlspecialchars($sub['text'])) ?></p>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($sub['file_path'])): ?>
                                <p style="margin-bottom:1rem">
                                    📎 <a href="/E_Learning/<?= htmlspecialchars($sub['file_path']) ?>" target="_blank">
                                        Download submitted file
                                    </a>
                                    <span style="color:var(--color-gray-500);font-size:.82rem">
                                        (submitted <?= $sub['submitted_at'] ? date('d M Y H:i', strtotime($sub['submitted_at'])) : '' ?>)
                                    </span>
                                </p>
                            <?php endif; ?>
                        <?php endif; ?>

                        <!-- Grade form -->
                        <form method="post" action="/E_Learning/trainer/api/grade-submission.php"
                              style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap;margin-top:.75rem">
                            <input type="hidden" name="batch_id"      value="<?= htmlspecialchars($batchId) ?>">
                            <input type="hidden" name="assignment_id" value="<?= htmlspecialchars($assignId) ?>">
                            <input type="hidden" name="student_id"    value="<?= htmlspecialchars($sid) ?>">

                            <div class="form-group" style="margin:0">
                                <label style="font-size:.8rem">Marks (out of <?= $maxMarks ?>)</label>
                                <input type="number" name="marks" class="form-control" style="width:100px"
                                       min="0" max="<?= $maxMarks ?>"
                                       value="<?= htmlspecialchars((string)($sub['marks'] ?? '')) ?>"
                                       placeholder="0–<?= $maxMarks ?>">
                            </div>
                            <div class="form-group" style="margin:0;flex:1;min-width:180px">
                                <label style="font-size:.8rem">Feedback</label>
                                <input type="text" name="feedback" class="form-control"
                                       value="<?= htmlspecialchars($sub['feedback'] ?? '') ?>"
                                       placeholder="Optional feedback...">
                            </div>
                            <button type="submit" class="btn btn--primary btn--sm" style="margin-bottom:0">
                                Save Grade
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>

        </main>
    </div>
</div>
</body>
</html>
