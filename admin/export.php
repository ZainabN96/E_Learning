<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/Auth.php';
require_once dirname(__DIR__) . '/scorm-export/ScormBuilder.php';

Auth::requireLogin();

$lang     = load_lang();
$langCode = get_lang_code();

$courseId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['id'] ?? '');
if (!$courseId) {
    http_response_code(400);
    echo $lang['error_occurred'];
    exit;
}

try {
    $builder = new ScormBuilder();
    $zipPath = $builder->build($courseId);

    $filename = $courseId . '_scorm12_' . date('Ymd') . '.zip';

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($zipPath));
    header('Cache-Control: no-store');
    header('Pragma: no-cache');

    readfile($zipPath);
    exit;

} catch (RuntimeException $e) {
    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html lang="<?= $langCode ?>">
    <head>
        <meta charset="UTF-8">
        <title><?= $lang['admin_export_error'] ?></title>
        <link rel="stylesheet" href="/E_Learning/assets/css/main.css">
        <link rel="stylesheet" href="/E_Learning/assets/css/admin.css">
    </head>
    <body class="admin-body">
    <header class="admin-topbar">
        <span class="admin-topbar__brand"><?= $lang['admin_title'] ?></span>
        <div class="admin-topbar__actions">
            <a href="/E_Learning/admin/"><?= $lang['admin_dashboard'] ?></a>
        </div>
    </header>
    <div class="admin-container">
        <div class="alert alert--error" style="margin-top:2rem">
            <strong><?= $lang['admin_export_error'] ?></strong><br>
            <?= sanitize_string($e->getMessage()) ?>
        </div>
        <a href="/E_Learning/admin/" class="btn btn--secondary" style="margin-top:1rem"><?= $lang['admin_dashboard'] ?></a>
    </div>
    </body>
    </html>
    <?php
}
