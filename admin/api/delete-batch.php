<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/core/helpers.php';
require_once dirname(__DIR__, 2) . '/core/Auth.php';
require_once dirname(__DIR__, 2) . '/core/BatchRepository.php';

Auth::requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); exit;
}

$id = trim($_POST['id'] ?? '');
if ($id) {
    $repo = new BatchRepository();
    $repo->deleteBatch($id);
}
header('Location: /E_Learning/admin/batches.php?msg=deleted');
exit;
