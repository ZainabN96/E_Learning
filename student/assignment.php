<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/UserAuth.php';
require_once dirname(__DIR__) . '/core/BatchRepository.php';

UserAuth::requireStudent();
$lang          = load_lang();
$langCode      = get_lang_code();
$platformTitle = $lang['platform_title'];
$studentId     = UserAuth::userId();

$batchId   = $_GET['batch']      ?? '';
$assignId  = $_GET['assignment'] ?? '';
$batchRepo = new BatchRepository();
$batch     = $batchId ? $batchRepo->getBatch($batchId) : [];

// Verify student is enrolled
if (empty($batch) || !in_array($studentId, $batch['student_ids'] ?? [], true)) {
    header('Location: /E_Learning/student/dashboard.php'); exit;
}

$assignment = $batchRepo->getAssignment($batchId, $assignId);
if (empty($assignment)) {
    header('Location: /E_Learning/student/batch.php?id=' . urlencode($batchId)); exit;
}

$sub     = $batchRepo->getSubmission($batchId, $assignId, $studentId);
$maxMark = (int)($assignment['max_marks'] ?? 100);
$msg     = $_GET['msg'] ?? '';
$error   = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="<?= $langCode ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($assignment['title'] ?? '') ?> — <?= htmlspecialchars($platformTitle) ?></title>
    <link rel="stylesheet" href="/E_Learning/assets/css/portal.css">
</head>
<body>
<div class="portal-shell">

    <header class="topbar">
        <span class="topbar__brand">🎓 <?= htmlspecialchars($platformTitle) ?></span>
        <div class="topbar__user">
            <span>👤 <?= htmlspecialchars(UserAuth::userName()) ?></span>
            <a href="/E_Learning/student/batch.php?id=<?= urlencode($batchId) ?>">← Back to Batch</a>
            <a href="/E_Learning/student/logout.php">Sign out</a>
        </div>
    </header>

    <div class="portal-body">
        <nav class="sidebar">
            <div class="sidebar__nav">
                <a href="/E_Learning/student/dashboard.php"><span class="nav-icon">📋</span> My Courses</a>
                <a href="/E_Learning/student/batch.php?id=<?= urlencode($batchId) ?>">
                    <span class="nav-icon">📦</span> <?= htmlspecialchars($batch['name'] ?? '') ?>
                </a>
                <a href="#" class="active"><span class="nav-icon">📝</span> Assignment</a>
            </div>
        </nav>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1><?= htmlspecialchars($assignment['title'] ?? '') ?></h1>
                    <p class="subtitle">
                        Max marks: <?= $maxMark ?> ·
                        Due: <?= $assignment['due_date'] ? date('d M Y', strtotime($assignment['due_date'])) : '—' ?>
                    </p>
                </div>
                <?php if (isset($sub['marks'])): ?>
                    <div class="grade-badge graded" style="font-size:1.1rem;padding:.5rem 1rem">
                        <?= $sub['marks'] ?> / <?= $maxMark ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($msg === 'submitted'): ?>
                <div class="alert alert--success">Your submission has been saved.</div>
            <?php elseif ($error === 'empty'): ?>
                <div class="alert alert--error">Please write an answer or upload a file.</div>
            <?php endif; ?>

            <!-- Assignment description -->
            <?php if (!empty($assignment['description'])): ?>
                <div class="card">
                    <div class="card__header"><h2>Instructions</h2></div>
                    <div class="card__body">
                        <?= nl2br(htmlspecialchars($assignment['description'])) ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Feedback (if graded) -->
            <?php if (isset($sub['marks'])): ?>
                <div class="card">
                    <div class="card__header"><h2>✅ Graded Result</h2></div>
                    <div class="card__body">
                        <div class="stats-grid">
                            <div class="stat-tile">
                                <div class="stat-tile__value" style="color:<?= $sub['marks'] >= $maxMark * 0.5 ? 'var(--color-success)' : 'var(--color-danger)' ?>">
                                    <?= $sub['marks'] ?>
                                </div>
                                <div class="stat-tile__label">Marks out of <?= $maxMark ?></div>
                            </div>
                            <?php if (!empty($sub['feedback'])): ?>
                                <div style="flex:2;padding:.75rem 1rem;background:var(--color-gray-50);border-radius:var(--radius-sm)">
                                    <strong>Trainer Feedback:</strong>
                                    <p style="margin-top:.5rem"><?= nl2br(htmlspecialchars($sub['feedback'])) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Submission form / view -->
            <div class="card">
                <div class="card__header">
                    <h2><?= empty($sub) ? '📤 Submit Assignment' : '📎 Your Submission' ?></h2>
                    <?php if (!empty($sub) && !empty($sub['submitted_at'])): ?>
                        <span style="font-size:.82rem;color:var(--color-gray-500)">
                            Submitted: <?= date('d M Y, H:i', strtotime($sub['submitted_at'])) ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="card__body">
                    <?php if (!empty($sub)): ?>
                        <!-- Show previous submission -->
                        <?php if (!empty($sub['text'])): ?>
                            <div style="margin-bottom:1rem;padding:1rem;background:var(--color-gray-50);border-radius:4px;border:1px solid var(--color-gray-200)">
                                <strong style="font-size:.8rem;text-transform:uppercase;color:var(--color-gray-500)">Your Written Answer:</strong>
                                <p style="margin-top:.5rem"><?= nl2br(htmlspecialchars($sub['text'])) ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($sub['file_path'])): ?>
                            <p style="margin-bottom:1.25rem">
                                📎 <a href="/E_Learning/<?= htmlspecialchars($sub['file_path']) ?>" target="_blank">
                                    View submitted file
                                </a>
                            </p>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Allow re-submission if not yet graded -->
                    <?php if (!isset($sub['marks'])): ?>
                        <form method="post" action="/E_Learning/student/api/submit-assignment.php"
                              enctype="multipart/form-data">
                            <input type="hidden" name="batch_id"      value="<?= htmlspecialchars($batchId) ?>">
                            <input type="hidden" name="assignment_id" value="<?= htmlspecialchars($assignId) ?>">

                            <div class="form-group">
                                <label>Written Answer</label>
                                <textarea name="text" class="form-control" rows="6"
                                          placeholder="Type your answer here..."><?= htmlspecialchars($sub['text'] ?? '') ?></textarea>
                            </div>

                            <div class="form-group">
                                <label>Upload File (PDF, DOC, DOCX, ZIP — optional)</label>
                                <div class="submission-box">
                                    <p>Drag & drop or click to choose a file</p>
                                    <input type="file" name="submission_file"
                                           accept=".pdf,.doc,.docx,.zip,.txt,.xlsx,.ppt,.pptx">
                                    <?php if (!empty($sub['file_path'])): ?>
                                        <p style="margin-top:.5rem;font-size:.85rem;color:var(--color-gray-500)">
                                            Previous file: <?= htmlspecialchars(basename($sub['file_path'])) ?>
                                            (uploading a new file will replace it)
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <button type="submit" class="btn btn--primary btn--lg">
                                <?= empty($sub) ? '📤 Submit Assignment' : '🔄 Update Submission' ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <p style="color:var(--color-gray-500);font-size:.9rem">
                            This assignment has been graded and can no longer be updated.
                        </p>
                    <?php endif; ?>
                </div>
            </div>

        </main>
    </div>
</div>
</body>
</html>
