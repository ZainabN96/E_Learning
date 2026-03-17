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

$batchRepo  = new BatchRepository();
$courseRepo = new CourseRepository();
$userRepo   = new UserRepository();

// All batches with open_enrollment=true that are upcoming or active
$allBatches    = $batchRepo->listBatches();
$enrolledIds   = array_map(fn($b) => $b['id'], $batchRepo->listBatchesForStudent($studentId));
$courses       = array_column($courseRepo->listCourses(), null, 'id');
$trainers      = array_column($userRepo->listUsers('trainer'), null, 'id');

$openBatches = array_filter($allBatches, fn($b) =>
    !empty($b['open_enrollment']) &&
    in_array($b['status'] ?? 'upcoming', ['upcoming', 'active'], true)
);

$msg   = $_GET['msg']     ?? '';
$welcome = !empty($_GET['welcome']);
$statusLabel = ['active' => 'Active', 'upcoming' => 'Upcoming'];
$statusClass = ['active' => 'badge--success', 'upcoming' => 'badge--primary'];
?>
<!DOCTYPE html>
<html lang="<?= $langCode ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Browse Courses — <?= htmlspecialchars($platformTitle) ?></title>
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
                <a href="/E_Learning/student/browse.php" class="active"><span class="nav-icon">🔍</span> Browse &amp; Enroll</a>
            </div>
        </nav>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1>Browse Courses</h1>
                    <p class="subtitle">Select a batch below to enroll</p>
                </div>
            </div>

            <?php if ($welcome): ?>
                <div class="alert alert--success">
                    🎉 Welcome! Your account is ready. Browse the courses below and enroll in one to get started.
                </div>
            <?php elseif ($msg === 'enrolled'): ?>
                <div class="alert alert--success">You have been enrolled successfully!</div>
            <?php elseif ($msg === 'already'): ?>
                <div class="alert alert--info">You are already enrolled in this batch.</div>
            <?php elseif ($msg === 'closed'): ?>
                <div class="alert alert--error">This batch is no longer accepting new enrollments.</div>
            <?php endif; ?>

            <?php if (empty($openBatches)): ?>
                <div class="empty-state">
                    <div style="font-size:3rem;margin-bottom:.75rem">📭</div>
                    <p>No courses are open for enrollment right now.</p>
                    <p style="font-size:.875rem;margin-top:.5rem">Check back later or contact your admin.</p>
                </div>
            <?php else: ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.25rem">
                    <?php foreach ($openBatches as $b): ?>
                        <?php
                        $courseData   = $courses[$b['course_id'] ?? ''] ?? [];
                        $trainerData  = $trainers[$b['trainer_id'] ?? ''] ?? [];
                        $alreadyIn    = in_array($b['id'], $enrolledIds, true);
                        $status       = $b['status'] ?? 'upcoming';
                        $slideCount   = $courseData['slide_count'] ?? 0;
                        $unitCount    = 0;
                        $fullCourse   = $courseRepo->getCourse($b['course_id'] ?? '');
                        $unitCount    = count($fullCourse['units'] ?? []);
                        ?>
                        <div class="card" style="display:flex;flex-direction:column">
                            <div class="card__header" style="background:var(--color-primary);border-radius:var(--radius-md) var(--radius-md) 0 0">
                                <h2 style="color:#fff;font-size:1rem"><?= htmlspecialchars($b['name'] ?? '') ?></h2>
                                <span class="badge <?= $statusClass[$status] ?? 'badge--gray' ?>"
                                      style="background:rgba(255,255,255,.25);color:#fff">
                                    <?= $statusLabel[$status] ?? ucfirst($status) ?>
                                </span>
                            </div>
                            <div class="card__body" style="flex:1;display:flex;flex-direction:column;gap:.6rem">

                                <?php if (!empty($courseData['title'])): ?>
                                    <div style="font-size:1.05rem;font-weight:600;color:var(--color-gray-900)">
                                        📚 <?= htmlspecialchars($courseData['title']) ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($courseData['description'])): ?>
                                    <p style="font-size:.875rem;color:var(--color-gray-600);line-height:1.5">
                                        <?= htmlspecialchars(mb_substr($courseData['description'], 0, 120)) ?>
                                        <?= mb_strlen($courseData['description']) > 120 ? '…' : '' ?>
                                    </p>
                                <?php endif; ?>

                                <div style="display:flex;flex-wrap:wrap;gap:.5rem;font-size:.82rem;color:var(--color-gray-500)">
                                    <?php if (!empty($trainerData['name'])): ?>
                                        <span>👨‍🏫 <?= htmlspecialchars($trainerData['name']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($unitCount > 0): ?>
                                        <span>📦 <?= $unitCount ?> module<?= $unitCount !== 1 ? 's' : '' ?></span>
                                    <?php endif; ?>
                                    <?php if ($slideCount > 0): ?>
                                        <span>🎞 <?= $slideCount ?> slides</span>
                                    <?php endif; ?>
                                </div>

                                <div style="font-size:.82rem;color:var(--color-gray-500)">
                                    📅 <?= $b['start_date'] ? date('d M Y', strtotime($b['start_date'])) : '—' ?>
                                    → <?= $b['end_date'] ? date('d M Y', strtotime($b['end_date'])) : '—' ?>
                                </div>

                                <?php if ($b['start_date'] && $b['end_date']): ?>
                                    <?php
                                    $days = round((strtotime($b['end_date']) - strtotime($b['start_date'])) / 86400);
                                    $weeks = round($days / 7);
                                    ?>
                                    <div style="font-size:.82rem;color:var(--color-gray-500)">
                                        ⏱ <?= $weeks > 0 ? "$weeks week" . ($weeks !== 1 ? 's' : '') : "$days days" ?>
                                    </div>
                                <?php endif; ?>

                                <div style="margin-top:auto;padding-top:.75rem">
                                    <?php if ($alreadyIn): ?>
                                        <div style="display:flex;gap:.5rem;align-items:center">
                                            <span class="badge badge--success">✓ Enrolled</span>
                                            <a href="/E_Learning/student/batch.php?id=<?= urlencode($b['id']) ?>"
                                               class="btn btn--secondary btn--sm">Open →</a>
                                        </div>
                                    <?php else: ?>
                                        <form method="post" action="/E_Learning/student/api/enroll.php">
                                            <input type="hidden" name="batch_id" value="<?= htmlspecialchars($b['id']) ?>">
                                            <button type="submit" class="btn btn--primary" style="width:100%;justify-content:center">
                                                Enroll in This Batch
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
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
