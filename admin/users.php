<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/Auth.php';
require_once dirname(__DIR__) . '/core/UserRepository.php';

Auth::requireLogin();
$lang          = load_lang();
$langCode      = get_lang_code();
$platformTitle = $lang['platform_title'];
$repo          = new UserRepository();
$admins        = $repo->listUsers('admin');
$trainers      = $repo->listUsers('trainer');
$students      = $repo->listUsers('student');
$tab           = $_GET['tab'] ?? 'trainers';
$msg           = $_GET['msg'] ?? '';

$currentAdminId = $_SESSION['user_id'] ?? '';
?>
<!DOCTYPE html>
<html lang="<?= $langCode ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Users — <?= htmlspecialchars($platformTitle) ?></title>
    <link rel="stylesheet" href="/E_Learning/assets/css/main.css">
    <link rel="stylesheet" href="/E_Learning/assets/css/admin.css">
</head>
<body class="admin-body">

<header class="admin-topbar">
    <span class="admin-topbar__brand"><?= htmlspecialchars($platformTitle) ?></span>
    <div class="admin-topbar__actions">
        <a href="/E_Learning/admin/">Courses</a>
        <a href="/E_Learning/admin/batches.php">Batches</a>
        <a href="/E_Learning/admin/reports.php">Reports</a>
        <a href="/E_Learning/admin/logout.php"><?= $lang['admin_logout'] ?></a>
    </div>
</header>

<div class="admin-container">
    <?php if ($msg === 'saved'): ?>
        <div class="alert alert--success">User saved successfully.</div>
    <?php elseif ($msg === 'deleted'): ?>
        <div class="alert alert--success">User deleted.</div>
    <?php endif; ?>

    <div class="admin-page-header">
        <h1>Users</h1>
        <?php
        $addRole = match($tab) { 'students' => 'student', 'admins' => 'admin', default => 'trainer' };
        $addLabel = match($tab) { 'students' => 'Student', 'admins' => 'Admin', default => 'Trainer' };
        ?>
        <a href="/E_Learning/admin/user-edit.php?role=<?= $addRole ?>"
           class="btn btn--primary">+ Add <?= $addLabel ?></a>
    </div>

    <!-- Tabs -->
    <div style="display:flex;gap:.5rem;margin-bottom:1.5rem;">
        <a href="?tab=trainers" class="btn <?= $tab === 'trainers' ? 'btn--primary' : 'btn--secondary' ?>">
            Trainers (<?= count($trainers) ?>)
        </a>
        <a href="?tab=students" class="btn <?= $tab === 'students' ? 'btn--primary' : 'btn--secondary' ?>">
            Students (<?= count($students) ?>)
        </a>
        <a href="?tab=admins" class="btn <?= $tab === 'admins' ? 'btn--primary' : 'btn--secondary' ?>">
            Admins (<?= count($admins) ?>)
        </a>
    </div>

    <?php
    $users     = match($tab) { 'students' => $students, 'admins' => $admins, default => $trainers };
    $roleLabel = match($tab) { 'students' => 'Student', 'admins' => 'Admin', default => 'Trainer' };
    ?>

    <div class="card">
        <?php if (empty($users)): ?>
            <div class="empty-state">
                <div class="empty-state__icon">👤</div>
                <p>No <?= $roleLabel ?>s yet.</p>
                <a href="/E_Learning/admin/user-edit.php?role=<?= $addRole ?>"
                   class="btn btn--primary">+ Add <?= $roleLabel ?></a>
            </div>
        <?php else: ?>
            <table class="course-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($u['name'] ?? '') ?></strong>
                            <?php if ($u['id'] === $currentAdminId): ?>
                                <span style="font-size:.75rem;color:var(--color-primary);margin-left:.4rem">(you)</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($u['email'] ?? '') ?></td>
                        <td><?= htmlspecialchars($u['phone'] ?? '—') ?></td>
                        <td><?= $u['created_at'] ? date('d M Y', strtotime($u['created_at'])) : '—' ?></td>
                        <td>
                            <div class="course-table__actions">
                                <a href="/E_Learning/admin/user-edit.php?id=<?= urlencode($u['id']) ?>"
                                   class="btn btn--sm btn--secondary">Edit</a>
                                <?php if ($u['id'] !== $currentAdminId): ?>
                                    <button type="button" class="btn btn--sm btn--danger"
                                            onclick="deleteUser('<?= addslashes($u['id']) ?>', '<?= addslashes($u['name'] ?? '') ?>')">
                                        Delete
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<form id="del-form" method="post" action="/E_Learning/admin/api/delete-user.php" style="display:none">
    <input type="hidden" name="id" id="del-id">
    <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
</form>

<script>
function deleteUser(id, name) {
    if (confirm('Delete user "' + name + '"? This cannot be undone.')) {
        document.getElementById('del-id').value = id;
        document.getElementById('del-form').submit();
    }
}
</script>
</body>
</html>
