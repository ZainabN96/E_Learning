<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/UserAuth.php';
require_once dirname(__DIR__) . '/core/BatchRepository.php';
require_once dirname(__DIR__) . '/core/CourseRepository.php';

UserAuth::requireTrainer();
$lang          = load_lang();
$langCode      = get_lang_code();
$platformTitle = $lang['platform_title'];
$trainerId     = UserAuth::userId();
$trainerName   = UserAuth::userName();

$batchRepo  = new BatchRepository();
$courseRepo = new CourseRepository();
$batches    = $batchRepo->listBatches($trainerId);
$courses    = array_column($courseRepo->listCourses(), null, 'id');

$statusLabel = ['active' => 'Active', 'upcoming' => 'Upcoming', 'completed' => 'Completed'];
$statusClass = ['active' => 'badge--success', 'upcoming' => 'badge--primary', 'completed' => 'badge--gray'];
?>
<!DOCTYPE html>
<html lang="<?= $langCode ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Trainer Dashboard — <?= htmlspecialchars($platformTitle) ?></title>
    <link rel="stylesheet" href="/E_Learning/assets/css/portal.css">
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
                <a href="/E_Learning/trainer/dashboard.php" class="active">
                    <span class="nav-icon">📋</span> My Batches
                </a>
                <a href="/E_Learning/trainer/reports.php"><span class="nav-icon">&#128202;</span> Reports</a>
            </div>
        </nav>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1>My Batches</h1>
                    <p class="subtitle">Welcome back, <?= htmlspecialchars($trainerName) ?></p>
                </div>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-tile">
                    <div class="stat-tile__value"><?= count($batches) ?></div>
                    <div class="stat-tile__label">Total Batches</div>
                </div>
                <div class="stat-tile">
                    <div class="stat-tile__value">
                        <?= count(array_filter($batches, fn($b) => ($b['status'] ?? '') === 'active')) ?>
                    </div>
                    <div class="stat-tile__label">Active Batches</div>
                </div>
                <div class="stat-tile">
                    <div class="stat-tile__value">
                        <?= array_sum(array_map(fn($b) => count($b['student_ids'] ?? []), $batches)) ?>
                    </div>
                    <div class="stat-tile__label">Total Students</div>
                </div>
            </div>

            <?php if (empty($batches)): ?>
                <div class="empty-state">
                    <p>No batches assigned to you yet. Contact admin to get assigned.</p>
                </div>
            <?php else: ?>
                <div class="batch-grid">
                    <?php foreach ($batches as $b): ?>
                        <?php
                        $courseTitle  = $courses[$b['course_id'] ?? '']['title'] ?? 'Unknown Course';
                        $studentCount = count($b['student_ids'] ?? []);
                        $status       = $b['status'] ?? 'upcoming';
                        $assignCount  = count($batchRepo->listAssignments($b['id']));
                        ?>
                        <div class="batch-card">
                            <div class="batch-card__name"><?= htmlspecialchars($b['name'] ?? '') ?></div>
                            <div class="batch-card__meta">📚 <?= htmlspecialchars($courseTitle) ?></div>
                            <div class="batch-card__meta">
                                📅 <?= $b['start_date'] ? date('d M Y', strtotime($b['start_date'])) : '—' ?>
                                → <?= $b['end_date'] ? date('d M Y', strtotime($b['end_date'])) : '—' ?>
                            </div>
                            <div class="batch-card__meta">👥 <?= $studentCount ?> students · 📝 <?= $assignCount ?> assignments</div>
                            <div class="batch-card__footer">
                                <span class="badge <?= $statusClass[$status] ?? 'badge--gray' ?>">
                                    <?= $statusLabel[$status] ?? ucfirst($status) ?>
                                </span>
                                <a href="/E_Learning/trainer/batch.php?id=<?= urlencode($b['id']) ?>"
                                   class="btn btn--primary btn--sm">Open →</a>
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
