<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/UserAuth.php';
UserAuth::logout();
header('Location: /E_Learning/login.php');
exit;
