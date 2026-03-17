<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/Auth.php';
require_once dirname(__DIR__) . '/core/CourseRepository.php';

Auth::requireLogin();
$lang          = load_lang();
$langCode      = get_lang_code();
$platformTitle = $lang['platform_title'];
$repo          = new CourseRepository();
$courses       = $repo->listCourses();

$message = $_GET['msg'] ?? '';
$msgMap  = [
    'course_saved'   => ['type' => 'success', 'text' => $lang['saved']],
    'course_deleted' => ['type' => 'success', 'text' => $lang['admin_delete_course'] . ' — OK'],
];
?>
<!DOCTYPE html>
<html lang="<?= $langCode ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $lang['admin_dashboard'] ?> — <?= sanitize_string($platformTitle) ?></title>
    <link rel="stylesheet" href="/E_Learning/assets/css/main.css">
    <link rel="stylesheet" href="/E_Learning/assets/css/admin.css">
</head>
<body class="admin-body">

<header class="admin-topbar">
    <span class="admin-topbar__brand"><?= sanitize_string($platformTitle) ?></span>
    <div class="admin-topbar__actions">
        <a href="/E_Learning/admin/users.php">Users</a>
        <a href="/E_Learning/admin/batches.php">Batches</a>
        <a href="/E_Learning/admin/reports.php">Reports</a>
        <a href="/E_Learning/admin/logout.php"><?= $lang['admin_logout'] ?></a>
    </div>
</header>

<div class="admin-container">
    <?php if ($message && isset($msgMap[$message])): ?>
        <div class="alert alert--<?= $msgMap[$message]['type'] ?>"><?= sanitize_string($msgMap[$message]['text']) ?></div>
    <?php endif; ?>

    <div class="admin-page-header">
        <h1><?= $lang['admin_title'] ?></h1>
        <a href="/E_Learning/admin/course-edit.php" class="btn btn--primary">
            + <?= $lang['admin_new_course'] ?>
        </a>
    </div>

    <div class="card">
        <?php if (empty($courses)): ?>
            <div class="empty-state">
                <div class="empty-state__icon">📚</div>
                <p><?= $lang['admin_no_courses'] ?></p>
                <a href="/E_Learning/admin/course-edit.php" class="btn btn--primary">
                    <?= $lang['admin_new_course'] ?>
                </a>
            </div>
        <?php else: ?>
            <table class="course-table">
                <thead>
                    <tr>
                        <th><?= $lang['admin_course_title'] ?></th>
                        <th><?= $lang['admin_slides'] ?></th>
                        <th><?= $lang['admin_course_duration'] ?></th>
                        <th>Last Modified</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($courses as $course): ?>
                    <tr>
                        <td>
                            <strong><?= sanitize_string($course['title']) ?></strong>
                            <br><small class="text-muted"><?= sanitize_string($course['id']) ?></small>
                        </td>
                        <td><?= (int)$course['slide_count'] ?></td>
                        <td><?= (int)$course['duration_minutes'] ?> <?= $lang['minutes'] ?></td>
                        <td><?= $course['updated_at'] ? date('d M Y H:i', strtotime($course['updated_at'])) : '—' ?></td>
                        <td>
                            <div class="course-table__actions">
                                <a href="/E_Learning/admin/course-edit.php?id=<?= urlencode($course['id']) ?>"
                                   class="btn btn--sm btn--secondary"><?= $lang['admin_edit_course'] ?></a>
                                <a href="/E_Learning/player/?course=<?= urlencode($course['id']) ?>&debug=1"
                                   target="_blank"
                                   class="btn btn--sm btn--secondary"><?= $lang['admin_preview'] ?></a>
                                <a href="/E_Learning/admin/export.php?id=<?= urlencode($course['id']) ?>"
                                   class="btn btn--sm btn--primary"><?= $lang['admin_export'] ?></a>
                                <button type="button"
                                        class="btn btn--sm btn--danger"
                                        onclick="confirmDelete('<?= addslashes($course['id']) ?>', '<?= addslashes($course['title']) ?>')">
                                    <?= $lang['admin_delete_course'] ?>
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

<form id="delete-form" method="post" action="/E_Learning/admin/api/delete-item.php" style="display:none">
    <input type="hidden" name="type" value="course">
    <input type="hidden" name="course_id" id="delete-course-id">
</form>

<script>
function confirmDelete(id, title) {
    if (confirm('<?= addslashes($lang['admin_confirm_delete']) ?>\n"' + title + '"')) {
        document.getElementById('delete-course-id').value = id;
        document.getElementById('delete-form').submit();
    }
}
</script>
</body>
</html>
