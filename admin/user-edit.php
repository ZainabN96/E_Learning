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

$id   = $_GET['id'] ?? '';
$user = $id ? $repo->getUser($id) : [];
unset($user['password_hash']);

if (empty($user['role'])) {
    $user['role'] = $_GET['role'] ?? 'student';
}

$isNew  = empty($user['id']);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'id'             => $_POST['id'] ?? '',
        'name'           => trim($_POST['name'] ?? ''),
        'email'          => strtolower(trim($_POST['email'] ?? '')),
        'phone'          => trim($_POST['phone'] ?? ''),
        'role'           => in_array($_POST['role'] ?? '', ['admin','trainer','student']) ? $_POST['role'] : 'student',
        'password_plain' => trim($_POST['password_plain'] ?? ''),
    ];

    if ($data['name'] === '')  $errors[] = 'Name is required.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if ($isNew && $data['password_plain'] === '') $errors[] = 'Password is required for new users.';
    if ($data['password_plain'] !== '' && strlen($data['password_plain']) < 6)
        $errors[] = 'Password must be at least 6 characters.';
    if ($repo->emailExists($data['email'], $data['id']))
        $errors[] = 'This email is already in use.';

    if (empty($errors)) {
        $saved    = $repo->saveUser($data);
        $backTab  = match($saved['role']) { 'admin' => 'admins', 'trainer' => 'trainers', default => 'students' };
        header('Location: /E_Learning/admin/users.php?tab=' . $backTab . '&msg=saved');
        exit;
    }
    $user = $data;
}
?>
<!DOCTYPE html>
<html lang="<?= $langCode ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $isNew ? 'Add User' : 'Edit User' ?> — <?= htmlspecialchars($platformTitle) ?></title>
    <link rel="stylesheet" href="/E_Learning/assets/css/main.css">
    <link rel="stylesheet" href="/E_Learning/assets/css/admin.css">
</head>
<body class="admin-body">

<header class="admin-topbar">
    <span class="admin-topbar__brand"><?= htmlspecialchars($platformTitle) ?></span>
    <div class="admin-topbar__actions">
        <a href="/E_Learning/admin/users.php">Users</a>
        <a href="/E_Learning/admin/reports.php">Reports</a>
        <a href="/E_Learning/admin/logout.php"><?= $lang['admin_logout'] ?></a>
    </div>
</header>

<div class="admin-container" style="max-width:600px">
    <div class="admin-page-header">
        <h1><?= $isNew ? 'Add User' : 'Edit User' ?></h1>
        <a href="/E_Learning/admin/users.php" class="btn btn--secondary">← Back</a>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert--error"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body" style="padding:1.5rem">
            <form method="post" action="">
                <input type="hidden" name="id" value="<?= htmlspecialchars($user['id'] ?? '') ?>">

                <div class="form-group">
                    <label>Role <span style="color:red">*</span></label>
                    <select name="role" class="form-control">
                        <option value="trainer" <?= ($user['role'] ?? '') === 'trainer' ? 'selected' : '' ?>>Trainer</option>
                        <option value="student" <?= ($user['role'] ?? '') === 'student' ? 'selected' : '' ?>>Student</option>
                        <option value="admin"   <?= ($user['role'] ?? '') === 'admin'   ? 'selected' : '' ?>>Admin</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Full Name <span style="color:red">*</span></label>
                    <input type="text" name="name" class="form-control"
                           value="<?= htmlspecialchars($user['name'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label>Email Address <span style="color:red">*</span></label>
                    <input type="email" name="email" class="form-control"
                           value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" class="form-control"
                           value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                           placeholder="+92 300 0000000">
                </div>

                <div class="form-group">
                    <label><?= $isNew ? 'Password' : 'New Password' ?> <?= $isNew ? '<span style="color:red">*</span>' : '' ?></label>
                    <input type="password" name="password_plain" class="form-control"
                           placeholder="<?= $isNew ? 'Set password' : 'Leave blank to keep current' ?>"
                           <?= $isNew ? 'required' : '' ?> autocomplete="new-password">
                    <?php if (!$isNew): ?>
                        <small style="color:var(--color-gray-500)">Leave blank to keep the existing password.</small>
                    <?php endif; ?>
                </div>

                <div style="display:flex;gap:.75rem;margin-top:1.5rem">
                    <button type="submit" class="btn btn--primary">Save User</button>
                    <a href="/E_Learning/admin/users.php" class="btn btn--secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
