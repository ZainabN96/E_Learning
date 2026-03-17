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
$batches    = $batchRepo->listBatches();
$courses    = array_column($courseRepo->listCourses(), null, 'id');
$trainers   = array_column($userRepo->listUsers('trainer'), null, 'id');
$msg        = $_GET['msg'] ?? '';

$statusLabel = ['active' => 'Active', 'upcoming' => 'Upcoming', 'completed' => 'Completed'];
$statusClass = ['active' => 'badge--success', 'upcoming' => 'badge--primary', 'completed' => 'badge--gray'];
?>
<!DOCTYPE html>
<html lang="<?= $langCode ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Batches — <?= htmlspecialchars($platformTitle) ?></title>
    <link rel="stylesheet" href="/E_Learning/assets/css/main.css">
    <link rel="stylesheet" href="/E_Learning/assets/css/admin.css">
</head>
<body class="admin-body">

<header class="admin-topbar">
    <span class="admin-topbar__brand"><?= htmlspecialchars($platformTitle) ?></span>
    <div class="admin-topbar__actions">
        <a href="/E_Learning/admin/">Courses</a>
        <a href="/E_Learning/admin/users.php">Users</a>
        <a href="/E_Learning/admin/reports.php">Reports</a>
        <a href="/E_Learning/admin/logout.php"><?= $lang['admin_logout'] ?></a>
    </div>
</header>

<div class="admin-container">
    <?php if ($msg === 'saved'): ?>
        <div class="alert alert--success">Batch saved successfully.</div>
    <?php elseif ($msg === 'deleted'): ?>
        <div class="alert alert--success">Batch deleted.</div>
    <?php endif; ?>

    <div class="admin-page-header">
        <h1>Batches</h1>
        <a href="/E_Learning/admin/batch-edit.php" class="btn btn--primary">+ New Batch</a>
    </div>

    <div class="card">
        <?php if (empty($batches)): ?>
            <div class="empty-state">
                <div class="empty-state__icon">📅</div>
                <p>No batches yet. Create one to assign trainers and students to a course.</p>
                <a href="/E_Learning/admin/batch-edit.php" class="btn btn--primary">+ New Batch</a>
            </div>
        <?php else: ?>
            <table class="course-table">
                <thead>
                    <tr>
                        <th>Batch Name</th>
                        <th>Course</th>
                        <th>Trainer</th>
                        <th>Students</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($batches as $b): ?>
                    <?php
                    $courseTitle   = $courses[$b['course_id'] ?? '']['title'] ?? '—';
                    $trainerName   = $trainers[$b['trainer_id'] ?? '']['name'] ?? '—';
                    $studentCount  = count($b['student_ids'] ?? []);
                    $status        = $b['status'] ?? 'upcoming';
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($b['name'] ?? '') ?></strong></td>
                        <td><?= htmlspecialchars($courseTitle) ?></td>
                        <td><?= htmlspecialchars($trainerName) ?></td>
                        <td><?= $studentCount ?></td>
                        <td style="white-space:nowrap;font-size:.85rem">
                            <?= $b['start_date'] ? date('d M Y', strtotime($b['start_date'])) : '—' ?>
                            → <?= $b['end_date'] ? date('d M Y', strtotime($b['end_date'])) : '—' ?>
                        </td>
                        <td>
                            <span class="badge <?= $statusClass[$status] ?? 'badge--gray' ?>">
                                <?= $statusLabel[$status] ?? ucfirst($status) ?>
                            </span>
                        </td>
                        <td>
                            <div class="course-table__actions">
                                <a href="/E_Learning/admin/batch-edit.php?id=<?= urlencode($b['id']) ?>"
                                   class="btn btn--sm btn--secondary">Edit</a>
                                <button type="button" class="btn btn--sm btn--danger"
                                        onclick="deleteBatch('<?= addslashes($b['id']) ?>', '<?= addslashes($b['name'] ?? '') ?>')">
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<form id="del-form" method="post" action="/E_Learning/admin/api/delete-batch.php" style="display:none">
    <input type="hidden" name="id" id="del-id">
</form>

<script>
function deleteBatch(id, name) {
    if (confirm('Delete batch "' + name + '" and all its attendance/assignment data?')) {
        document.getElementById('del-id').value = id;
        document.getElementById('del-form').submit();
    }
}
</script>
</body>
</html>
