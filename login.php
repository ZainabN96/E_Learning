<?php
declare(strict_types=1);
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/UserAuth.php';

$lang     = load_lang();
$langCode = get_lang_code();

// Already logged in? Route by role.
UserAuth::startSession();
if (UserAuth::isLoggedIn()) {
    $role = UserAuth::userRole();
    if ($role === 'admin')        { header('Location: /E_Learning/admin/');                  exit; }
    if ($role === 'trainer')      { header('Location: /E_Learning/trainer/dashboard.php');    exit; }
    /* student */                   header('Location: /E_Learning/student/dashboard.php');    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = strtolower(trim($_POST['email']    ?? ''));
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        $user = UserAuth::login($email, $password);
        if (!empty($user)) {
            $role = $user['role'] ?? '';
            if ($role === 'admin')   { header('Location: /E_Learning/admin/');               exit; }
            if ($role === 'trainer') { header('Location: /E_Learning/trainer/dashboard.php'); exit; }
            /* student */              header('Location: /E_Learning/student/dashboard.php'); exit;
        }
        $error = 'Invalid email or password.';
    }
}

$platformTitle = $lang['platform_title'] ?? 'E-Learning Platform';
?>
<!DOCTYPE html>
<html lang="<?= $langCode ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login — <?= htmlspecialchars($platformTitle) ?></title>
    <link rel="stylesheet" href="/E_Learning/assets/css/portal.css">
    <style>
        .role-hint {
            background: var(--color-gray-50);
            border: 1px solid var(--color-gray-200);
            border-radius: var(--radius-sm);
            padding: .65rem .9rem;
            font-size: .8rem;
            color: var(--color-gray-500);
            margin-bottom: 1rem;
            line-height: 1.7;
        }
        .role-hint strong { color: var(--color-gray-700); }
        code {
            background: var(--color-gray-200);
            padding: .1rem .35rem;
            border-radius: 3px;
            font-size: .85em;
        }
        .login-divider {
            display: flex; align-items: center; gap: .75rem;
            margin: 1.25rem 0; color: var(--color-gray-400); font-size: .8rem;
        }
        .login-divider::before, .login-divider::after {
            content: ''; flex: 1; height: 1px; background: var(--color-gray-200);
        }
    </style>
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <div class="login-card__logo">
            <div style="font-size:2.5rem">🎓</div>
            <h1><?= htmlspecialchars($platformTitle) ?></h1>
            <p>Sign in to continue</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert--error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="role-hint">
            <strong>Admin:</strong> <code>admin@elearning.local</code><br>
            <strong>Trainer / Student:</strong> your registered email address
        </div>

        <form method="post" action="">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       placeholder="you@example.com"
                       autocomplete="email" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control"
                       placeholder="••••••••" autocomplete="current-password" required>
            </div>
            <button type="submit" class="btn btn--primary btn--lg"
                    style="width:100%;justify-content:center;margin-top:.25rem">
                Sign In
            </button>
        </form>

        <div class="login-divider">or</div>

        <p style="text-align:center;font-size:.875rem">
            New student? <a href="/E_Learning/register.php"><strong>Create an account →</strong></a>
        </p>
    </div>
</div>
</body>
</html>
