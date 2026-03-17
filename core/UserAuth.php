<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/UserRepository.php';

class UserAuth {

    public static function startSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_name('elearn_session');
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'secure'   => false,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    /** Attempt login with email + password. Returns user array on success, [] on failure. */
    public static function login(string $email, string $password): array {
        self::startSession();
        $repo = new UserRepository();
        $user = $repo->getUserByEmail($email);
        if (empty($user)) return [];
        if (!password_verify($password, $user['password_hash'] ?? '')) return [];

        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['name'];
        return $user;
    }

    /** Log out the current user. */
    public static function logout(): void {
        self::startSession();
        $_SESSION = [];
        session_destroy();
    }

    /** Check if any user (trainer or student) is logged in. */
    public static function isLoggedIn(): bool {
        self::startSession();
        return !empty($_SESSION['user_id']) && !empty($_SESSION['user_role']);
    }

    /** Return current user's ID or ''. */
    public static function userId(): string {
        self::startSession();
        return $_SESSION['user_id'] ?? '';
    }

    /** Return current user's role or ''. */
    public static function userRole(): string {
        self::startSession();
        return $_SESSION['user_role'] ?? '';
    }

    /** Return current user's name or ''. */
    public static function userName(): string {
        self::startSession();
        return $_SESSION['user_name'] ?? '';
    }

    /** Redirect to login if not logged in as trainer. */
    public static function requireTrainer(): void {
        self::startSession();
        if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'trainer') {
            header('Location: /E_Learning/login.php');
            exit;
        }
    }

    /** Redirect to login if not logged in as student. */
    public static function requireStudent(): void {
        self::startSession();
        if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'student') {
            header('Location: /E_Learning/login.php');
            exit;
        }
    }

    /** Redirect to login if not logged in as trainer or student. */
    public static function requireUser(): void {
        self::startSession();
        if (empty($_SESSION['user_id'])) {
            header('Location: /E_Learning/login.php');
            exit;
        }
    }

    /** Get full user record for current session. */
    public static function getCurrentUser(): array {
        self::startSession();
        $id = $_SESSION['user_id'] ?? '';
        if (!$id) return [];
        $repo = new UserRepository();
        return $repo->getUser($id);
    }
}
