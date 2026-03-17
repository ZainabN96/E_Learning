<?php
declare(strict_types=1);
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/UserAuth.php';
require_once __DIR__ . '/core/UserRepository.php';

$lang     = load_lang();
$langCode = get_lang_code();

// Already logged in → go to dashboard
if (UserAuth::isLoggedIn()) {
    header('Location: /E_Learning/student/dashboard.php'); exit;
}

$errors = [];
$vals   = ['name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vals = [
        'name'  => trim($_POST['name']  ?? ''),
        'email' => strtolower(trim($_POST['email'] ?? '')),
        'phone' => trim($_POST['phone'] ?? ''),
    ];
    $password  = trim($_POST['password']  ?? '');
    $password2 = trim($_POST['password2'] ?? '');

    if ($vals['name'] === '')
        $errors[] = 'Full name is required.';
    if (!filter_var($vals['email'], FILTER_VALIDATE_EMAIL))
        $errors[] = 'A valid email address is required.';
    if (strlen($password) < 6)
        $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $password2)
        $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $repo = new UserRepository();
        if ($repo->emailExists($vals['email'])) {
            $errors[] = 'This email is already registered. Please log in.';
        } else {
            $repo->saveUser([
                'name'           => $vals['name'],
                'email'          => $vals['email'],
                'phone'          => $vals['phone'],
                'role'           => 'student',
                'password_plain' => $password,
            ]);
            // Auto-login after registration
            UserAuth::login($vals['email'], $password);
            header('Location: /E_Learning/student/browse.php?welcome=1');
            exit;
        }
    }
}

$platformTitle = $lang['platform_title'] ?? 'E-Learning Platform';
?>
<!DOCTYPE html>
<html lang="<?= $langCode ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register — <?= htmlspecialchars($platformTitle) ?></title>
    <link rel="stylesheet" href="/E_Learning/assets/css/portal.css">
</head>
<body>
<div class="login-wrap">
    <div class="login-card" style="max-width:440px">
        <div class="login-card__logo">
            <div style="font-size:2.5rem">🎓</div>
            <h1><?= htmlspecialchars($platformTitle) ?></h1>
            <p>Create your student account</p>
        </div>

        <?php if ($errors): ?>
            <div class="alert alert--error">
                <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="form-group">
                <label for="name">Full Name <span class="required-mark">*</span></label>
                <input type="text" id="name" name="name" class="form-control"
                       value="<?= htmlspecialchars($vals['name']) ?>"
                       placeholder="Your full name" required autofocus>
            </div>

            <div class="form-group">
                <label for="email">Email Address <span class="required-mark">*</span></label>
                <input type="email" id="email" name="email" class="form-control"
                       value="<?= htmlspecialchars($vals['email']) ?>"
                       placeholder="you@example.com" required>
            </div>

            <div class="form-group">
                <label for="phone">Phone <span style="font-weight:400;color:var(--color-gray-400)">(optional)</span></label>
                <input type="text" id="phone" name="phone" class="form-control"
                       value="<?= htmlspecialchars($vals['phone']) ?>"
                       placeholder="+92 300 0000000">
            </div>

            <div class="form-group">
                <label for="password">Password <span class="required-mark">*</span></label>
                <input type="password" id="password" name="password" class="form-control"
                       placeholder="Min. 6 characters" required autocomplete="new-password">
            </div>

            <div class="form-group">
                <label for="password2">Confirm Password <span class="required-mark">*</span></label>
                <input type="password" id="password2" name="password2" class="form-control"
                       placeholder="Repeat password" required autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn--primary btn--lg"
                    style="width:100%;justify-content:center;margin-top:.25rem">
                Create Account
            </button>
        </form>

        <p style="text-align:center;margin-top:1.25rem;font-size:.875rem;color:var(--color-gray-500)">
            Already have an account? <a href="/E_Learning/login.php">Sign in →</a>
        </p>
    </div>
</div>
</body>
</html>
