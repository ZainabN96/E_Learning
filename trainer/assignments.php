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

$batchId   = $_GET['batch'] ?? '';
$batchRepo = new BatchRepository();
$batch     = $batchId ? $batchRepo->getBatch($batchId) : [];

if (empty($batch) || $batch['trainer_id'] !== $trainerId) {
    header('Location: /E_Learning/trainer/dashboard.php'); exit;
}

$assignments  = $batchRepo->listAssignments($batchId);
$studentCount = count($batch['student_ids'] ?? []);
$course       = (new CourseRepository())->getCourse($batch['course_id'] ?? '');
$modules      = $course['units'] ?? [];
$msg          = $_GET['msg'] ?? '';
$editId       = $_GET['edit'] ?? '';
$editData     = $editId ? $batchRepo->getAssignment($batchId, $editId) : [];
?>
<!DOCTYPE html>
<html lang="<?= $langCode ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Assignments — <?= htmlspecialchars($batch['name'] ?? '') ?></title>
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
                <a href="/E_Learning/trainer/attendance.php?batch=<?= urlencode($batchId) ?>"><span class="nav-icon">✅</span> Attendance</a>
                <a href="/E_Learning/trainer/assignments.php?batch=<?= urlencode($batchId) ?>" class="active">
                    <span class="nav-icon">📝</span> Assignments
                </a>
            </div>
        </nav>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1>Assignments — <?= htmlspecialchars($batch['name'] ?? '') ?></h1>
                </div>
                <button class="btn btn--primary" onclick="document.getElementById('add-form').style.display='block';this.style.display='none'">
                    + New Assignment
                </button>
            </div>

            <?php if ($msg === 'saved'): ?>
                <div class="alert alert--success">Assignment saved.</div>
            <?php elseif ($msg === 'deleted'): ?>
                <div class="alert alert--success">Assignment deleted.</div>
            <?php endif; ?>

            <!-- Add/Edit form -->
            <div id="add-form" class="card" style="<?= $editId ? '' : 'display:none' ?>">
                <div class="card__header">
                    <h2><?= $editId ? 'Edit Assignment' : 'New Assignment' ?></h2>
                    <button class="btn btn--secondary btn--sm"
                            onclick="document.getElementById('add-form').style.display='none'">Cancel</button>
                </div>
                <div class="card__body">
                    <form method="post" action="/E_Learning/trainer/api/save-assignment.php">
                        <input type="hidden" name="batch_id"     value="<?= htmlspecialchars($batchId) ?>">
                        <input type="hidden" name="assignment_id" value="<?= htmlspecialchars($editId) ?>">

                        <div class="form-group">
                            <label>Title <span style="color:red">*</span></label>
                            <input type="text" name="title" class="form-control" required
                                   value="<?= htmlspecialchars($editData['title'] ?? '') ?>"
                                   placeholder="e.g. Week 1 Assignment">
                        </div>
                        <div class="form-group">
                            <label>Description / Instructions</label>
                            <textarea name="description" class="form-control" rows="4"
                                      placeholder="Describe what students need to do..."><?= htmlspecialchars($editData['description'] ?? '') ?></textarea>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem">
                            <div class="form-group">
                                <label>Related Module</label>
                                <select name="module_id" class="form-control">
                                    <option value="">— General —</option>
                                    <?php foreach ($modules as $m): ?>
                                        <option value="<?= htmlspecialchars($m['id']) ?>"
                                            <?= ($editData['module_id'] ?? '') === $m['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($m['title']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Due Date</label>
                                <input type="date" name="due_date" class="form-control"
                                       value="<?= htmlspecialchars($editData['due_date'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Max Marks</label>
                                <input type="number" name="max_marks" class="form-control" min="1" max="1000"
                                       value="<?= (int)($editData['max_marks'] ?? 100) ?>">
                            </div>
                        </div>
                        <button type="submit" class="btn btn--primary">Save Assignment</button>
                    </form>
                </div>
            </div>

            <!-- Assignments list -->
            <div class="card">
                <div class="card__header"><h2>All Assignments (<?= count($assignments) ?>)</h2></div>
                <div class="card__body" style="padding:0">
                    <?php if (empty($assignments)): ?>
                        <p style="padding:1rem;color:var(--color-gray-400)">No assignments yet. Create the first one above.</p>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Module</th>
                                    <th>Due Date</th>
                                    <th>Max Marks</th>
                                    <th>Submissions</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($assignments as $a): ?>
                                <?php
                                $subs      = $batchRepo->listSubmissions($batchId, $a['id']);
                                $modTitle  = '—';
                                foreach ($modules as $m) {
                                    if ($m['id'] === ($a['module_id'] ?? '')) { $modTitle = $m['title']; break; }
                                }
                                $gradedCount = count(array_filter($subs, fn($s) => isset($s['marks'])));
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($a['title'] ?? '') ?></strong></td>
                                    <td><?= htmlspecialchars($modTitle) ?></td>
                                    <td><?= $a['due_date'] ? date('d M Y', strtotime($a['due_date'])) : '—' ?></td>
                                    <td><?= (int)($a['max_marks'] ?? 100) ?></td>
                                    <td>
                                        <span class="badge badge--primary"><?= count($subs) ?>/<?= $studentCount ?> submitted</span>
                                        <?php if ($gradedCount > 0): ?>
                                            <span class="badge badge--success"><?= $gradedCount ?> graded</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions">
                                        <a href="/E_Learning/trainer/grade.php?batch=<?= urlencode($batchId) ?>&assignment=<?= urlencode($a['id']) ?>"
                                           class="btn btn--sm btn--primary">Grade</a>
                                        <a href="?batch=<?= urlencode($batchId) ?>&edit=<?= urlencode($a['id']) ?>#add-form"
                                           class="btn btn--sm btn--secondary">Edit</a>
                                        <button type="button" class="btn btn--sm btn--danger"
                                                onclick="delAssign('<?= addslashes($a['id']) ?>', '<?= addslashes($a['title'] ?? '') ?>')">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

        </main>
    </div>
</div>

<form id="del-assign-form" method="post" action="/E_Learning/trainer/api/delete-assignment.php" style="display:none">
    <input type="hidden" name="batch_id" value="<?= htmlspecialchars($batchId) ?>">
    <input type="hidden" name="assignment_id" id="del-assign-id">
</form>

<script>
function delAssign(id, title) {
    if (confirm('Delete assignment "' + title + '"? All submissions will be lost.')) {
        document.getElementById('del-assign-id').value = id;
        document.getElementById('del-assign-form').submit();
    }
}
</script>
</body>
</html>
