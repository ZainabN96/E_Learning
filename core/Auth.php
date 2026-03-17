<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/UserAuth.php';
require_once __DIR__ . '/UserRepository.php';

/**
 * Auth — thin wrapper around UserAuth for admin-role checks.
 * Admin is now stored as a regular user with role="admin" in data/users/.
 */
class Auth {

    public static function startSession(): void {
        UserAuth::startSession();
    }

    /** Check if current session belongs to an admin. */
    public static function isLoggedIn(): bool {
        UserAuth::startSession();
        return !empty($_SESSION['user_id']) && ($_SESSION['user_role'] ?? '') === 'admin';
    }

    /** Redirect to login page if not authenticated as admin. */
    public static function requireLogin(): void {
        if (!self::isLoggedIn()) {
            header('Location: /E_Learning/login.php');
            exit;
        }
    }

    /** Log out current session. */
    public static function logout(): void {
        UserAuth::logout();
    }

    /** Change admin password — updates the user record in data/users/. */
    public static function changePassword(string $userId, string $newPassword): void {
        $repo = new UserRepository();
        $user = $repo->getUser($userId);
        if (!empty($user)) {
            $repo->saveUser(array_merge($user, ['password_plain' => $newPassword]));
        }
    }
}
