<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

class UserRepository {

    private string $usersDir;

    public function __construct() {
        $this->usersDir = data_dir() . '/users';
        if (!is_dir($this->usersDir)) {
            mkdir($this->usersDir, 0755, true);
        }
    }

    /** Return all users, optionally filtered by role. */
    public function listUsers(?string $role = null): array {
        $users = [];
        foreach (glob($this->usersDir . '/*.json') as $file) {
            $u = json_read($file);
            if (!empty($u)) {
                if ($role === null || ($u['role'] ?? '') === $role) {
                    $users[] = $this->safe($u);
                }
            }
        }
        usort($users, fn($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));
        return $users;
    }

    /** Get a user by ID. Returns [] if not found. */
    public function getUser(string $id): array {
        $path = $this->path($id);
        if (!file_exists($path)) return [];
        return json_read($path);
    }

    /** Get a user by email (for login). Returns [] if not found. */
    public function getUserByEmail(string $email): array {
        $email = strtolower(trim($email));
        foreach (glob($this->usersDir . '/*.json') as $file) {
            $u = json_read($file);
            if (strtolower($u['email'] ?? '') === $email) {
                return $u;
            }
        }
        return [];
    }

    /** Create or update a user. Generates ID on create. */
    public function saveUser(array $data): array {
        if (empty($data['id'])) {
            $data['id']         = 'user-' . substr(generate_uuid(), 0, 8);
            $data['created_at'] = date('c');
        }
        // Only hash password if a plain-text one is provided
        if (!empty($data['password_plain'])) {
            $data['password_hash'] = password_hash($data['password_plain'], PASSWORD_BCRYPT);
        }
        unset($data['password_plain']);
        $data['updated_at'] = date('c');
        json_write($this->path($data['id']), $data);
        return $this->safe($data);
    }

    /** Delete a user by ID. */
    public function deleteUser(string $id): bool {
        $path = $this->path($id);
        if (!file_exists($path)) return false;
        unlink($path);
        return true;
    }

    /** Check if an email is already taken (optionally excluding a user ID). */
    public function emailExists(string $email, string $excludeId = ''): bool {
        $email = strtolower(trim($email));
        foreach (glob($this->usersDir . '/*.json') as $file) {
            $u = json_read($file);
            if (strtolower($u['email'] ?? '') === $email && ($u['id'] ?? '') !== $excludeId) {
                return true;
            }
        }
        return false;
    }

    // --- Helpers ---

    private function path(string $id): string {
        $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '', $id);
        return $this->usersDir . '/' . $safe . '.json';
    }

    /** Strip password_hash before returning to views. */
    private function safe(array $u): array {
        unset($u['password_hash']);
        return $u;
    }
}
