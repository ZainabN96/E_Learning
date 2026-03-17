<?php
declare(strict_types=1);

/**
 * Read and decode a JSON file. Returns array or throws RuntimeException.
 */
function json_read(string $path): array {
    if (!file_exists($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException("Cannot read file: $path");
    }
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException("Invalid JSON in $path: " . json_last_error_msg());
    }
    return $data ?? [];
}

/**
 * Encode and write data as a JSON file atomically.
 */
function json_write(string $path, array $data): void {
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $tmp = $path . '.tmp.' . getmypid();
    $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        throw new RuntimeException("JSON encode failed: " . json_last_error_msg());
    }
    if (file_put_contents($tmp, $encoded, LOCK_EX) === false) {
        throw new RuntimeException("Cannot write to: $tmp");
    }
    rename($tmp, $path);
}

/**
 * Sanitize a string for safe output in HTML.
 */
function sanitize_string(string $s): string {
    return htmlspecialchars(trim($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Generate a simple unique ID (slug-safe).
 */
function generate_uuid(): string {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

/**
 * Format seconds as SCORM 1.2 time string: HH:MM:SS.
 */
function format_scorm_time(int $seconds): string {
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    $s = $seconds % 60;
    return sprintf('%02d:%02d:%02d', $h, $m, $s);
}

/**
 * Return a JSON response and exit.
 */
function json_response(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Require a POST request or die with JSON error.
 */
function require_post(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['error' => 'Method Not Allowed'], 405);
    }
}

/**
 * Get project root path.
 */
function project_root(): string {
    return dirname(__DIR__);
}

/**
 * Get data directory path.
 */
function data_dir(): string {
    return project_root() . '/data';
}

/**
 * Get the configured language code (e.g. "en", "de").
 */
function get_lang_code(): string {
    $config = json_read(data_dir() . '/config.json');
    return preg_replace('/[^a-z]/', '', $config['language'] ?? 'en');
}

/**
 * Load the language strings based on config.json language setting.
 * Falls back to 'en' if the configured language file doesn't exist.
 */
function load_lang(): array {
    $config = json_read(data_dir() . '/config.json');
    $lang   = preg_replace('/[^a-z]/', '', $config['language'] ?? 'en');
    $file   = project_root() . "/core/lang/{$lang}.php";
    if (!file_exists($file)) {
        $file = project_root() . '/core/lang/en.php';
    }
    return require $file;
}
