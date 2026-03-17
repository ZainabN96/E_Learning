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

$id     = $_GET['id'] ?? '';
$batch  = $id ? $batchRepo->getBatch($id) : [];
$isNew  = empty($batch['id']);
$errors = [];

$courses  = $courseRepo->listCourses();
$trainers = $userRepo->listUsers('trainer');
$students = $userRepo->listUsers('student');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'id'          => $_POST['id'] ?? '',
        'name'        => trim($_POST['name'] ?? ''),
        'course_id'   => trim($_POST['course_id'] ?? ''),
        'trainer_id'  => trim($_POST['trainer_id'] ?? ''),
        'start_date'  => trim($_POST['start_date'] ?? ''),
        'end_date'    => trim($_POST['end_date'] ?? ''),
        'status'          => in_array($_POST['status'] ?? '', ['upcoming','active','completed']) ? $_POST['status'] : 'upcoming',
        'student_ids'     => $_POST['student_ids'] ?? [],
        'open_enrollment' => !empty($_POST['open_enrollment']),
    ];

    if ($data['name'] === '')      $errors[] = 'Batch name is required.';
    if ($data['course_id'] === '') $errors[] = 'Please select a course.';
    if ($data['trainer_id'] === '') $errors[] = 'Please assign a trainer.';
    if ($data['start_date'] === '') $errors[] = 'Start date is required.';
    if ($data['end_date'] === '')   $errors[] = 'End date is required.';
    if ($data['start_date'] && $data['end_date'] && $data['end_date'] < $data['start_date'])
        $errors[] = 'End date must be after start date.';

    if (empty($errors)) {
        $batchRepo->saveBatch($data);
        header('Location: /E_Learning/admin/batches.php?msg=saved');
        exit;
    }
    $batch = $data;
}

$selectedStudents = $batch['student_ids'] ?? [];
?>
<!DOCTYPE html>
<html lang="<?= $langCode ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $isNew ? 'New Batch' : 'Edit Batch' ?> — <?= htmlspecialchars($platformTitle) ?></title>
    <link rel="stylesheet" href="/E_Learning/assets/css/main.css">
    <link rel="stylesheet" href="/E_Learning/assets/css/admin.css">
    <style>
        .student-list { max-height: 280px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 4px; }
        .student-item { display: flex; align-items: center; gap: .6rem; padding: .5rem .75rem; border-bottom: 1px solid var(--border-color); }
        .student-item:last-child { border-bottom: none; }
        .student-item:hover { background: var(--color-bg-secondary); }
        .student-item label { cursor: pointer; flex: 1; }
    </style>
</head>
<body class="admin-body">

<header class="admin-topbar">
    <span class="admin-topbar__brand"><?= htmlspecialchars($platformTitle) ?></span>
    <div class="admin-topbar__actions">
        <a href="/E_Learning/admin/batches.php">Batches</a>
        <a href="/E_Learning/admin/reports.php">Reports</a>
        <a href="/E_Learning/admin/logout.php"><?= $lang['admin_logout'] ?></a>
    </div>
</header>

<div class="admin-container" style="max-width:700px">
    <div class="admin-page-header">
        <h1><?= $isNew ? 'New Batch' : 'Edit Batch' ?></h1>
        <a href="/E_Learning/admin/batches.php" class="btn btn--secondary">← Back</a>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert--error"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body" style="padding:1.5rem">
        <form method="post" action="">
            <input type="hidden" name="id" value="<?= htmlspecialchars($batch['id'] ?? '') ?>">

            <div class="form-group">
                <label>Batch Name <span style="color:red">*</span></label>
                <input type="text" name="name" class="form-control"
                       value="<?= htmlspecialchars($batch['name'] ?? '') ?>"
                       placeholder="e.g. Batch A — Jan 2026" required>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                <div class="form-group">
                    <label>Course <span style="color:red">*</span></label>
                    <select name="course_id" class="form-control" required>
                        <option value="">— Select course —</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?= htmlspecialchars($c['id']) ?>"
                                <?= ($batch['course_id'] ?? '') === $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Trainer <span style="color:red">*</span></label>
                    <select name="trainer_id" class="form-control" required>
                        <option value="">— Select trainer —</option>
                        <?php foreach ($trainers as $t): ?>
                            <option value="<?= htmlspecialchars($t['id']) ?>"
                                <?= ($batch['trainer_id'] ?? '') === $t['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem">
                <div class="form-group">
                    <label>Start Date <span style="color:red">*</span></label>
                    <input type="date" name="start_date" class="form-control"
                           value="<?= htmlspecialchars($batch['start_date'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>End Date <span style="color:red">*</span></label>
                    <input type="date" name="end_date" class="form-control"
                           value="<?= htmlspecialchars($batch['end_date'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="upcoming"  <?= ($batch['status'] ?? 'upcoming') === 'upcoming'  ? 'selected' : '' ?>>Upcoming</option>
                        <option value="active"    <?= ($batch['status'] ?? '') === 'active'    ? 'selected' : '' ?>>Active</option>
                        <option value="completed" <?= ($batch['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label style="display:flex;align-items:center;gap:.6rem;cursor:pointer;font-weight:500">
                    <input type="checkbox" name="open_enrollment" value="1"
                           <?= !empty($batch['open_enrollment']) ? 'checked' : '' ?>
                           style="width:16px;height:16px">
                    Allow students to self-enroll in this batch
                </label>
                <p class="form-hint">When enabled, students can see and join this batch from the course browsing page after registering.</p>
            </div>

            <div class="form-group">
                <label>Enroll Students
                    <span style="font-weight:400;color:var(--color-gray-500)">(<?= count($students) ?> available)</span>
                </label>
                <?php if (empty($students)): ?>
                    <p style="color:var(--color-gray-500);font-size:.875rem">
                        No students found. <a href="/E_Learning/admin/user-edit.php?role=student">Add students →</a>
                    </p>
                <?php else: ?>
                    <div style="margin-bottom:.4rem;font-size:.85rem">
                        <a href="#" onclick="toggleAll(true);return false">Select All</a> ·
                        <a href="#" onclick="toggleAll(false);return false">Deselect All</a>
                    </div>
                    <div class="student-list">
                        <?php foreach ($students as $s): ?>
                            <div class="student-item">
                                <input type="checkbox" name="student_ids[]"
                                       id="s_<?= htmlspecialchars($s['id']) ?>"
                                       value="<?= htmlspecialchars($s['id']) ?>"
                                       <?= in_array($s['id'], $selectedStudents, true) ? 'checked' : '' ?>>
                                <label for="s_<?= htmlspecialchars($s['id']) ?>">
                                    <strong><?= htmlspecialchars($s['name']) ?></strong>
                                    <span style="color:var(--color-gray-500);font-size:.85rem"> — <?= htmlspecialchars($s['email']) ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div style="display:flex;gap:.75rem;margin-top:1.5rem">
                <button type="submit" class="btn btn--primary">Save Batch</button>
                <a href="/E_Learning/admin/batches.php" class="btn btn--secondary">Cancel</a>
            </div>
        </form>
        </div>
    </div>
</div>

<script>
function toggleAll(check) {
    document.querySelectorAll('input[name="student_ids[]"]').forEach(cb => cb.checked = check);
}
</script>
</body>
</html>
