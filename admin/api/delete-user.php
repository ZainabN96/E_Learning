<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/core/helpers.php';
require_once dirname(__DIR__, 2) . '/core/Auth.php';
require_once dirname(__DIR__, 2) . '/core/UserRepository.php';

Auth::requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); exit;
}

$id  = trim($_POST['id'] ?? '');
$tab = in_array($_POST['tab'] ?? '', ['trainers','students']) ? $_POST['tab'] : 'trainers';
if (!$id) {
    header('Location: /E_Learning/admin/users.php?tab=' . $tab);
    exit;
}

$repo = new UserRepository();
$repo->deleteUser($id);
header('Location: /E_Learning/admin/users.php?tab=' . $tab . '&msg=deleted');
exit;
