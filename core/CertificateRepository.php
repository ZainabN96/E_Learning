<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

class CertificateRepository {

    private string $certDir;

    public function __construct() {
        $this->certDir = data_dir() . '/certificates';
        if (!is_dir($this->certDir)) {
            mkdir($this->certDir, 0755, true);
        }
    }

    /** Issue a new certificate. Returns the saved cert array. */
    public function issue(array $data): array {
        $id             = 'CERT-' . strtoupper(substr(generate_uuid(), 0, 8));
        $data['id']     = $id;
        $data['issued_at'] = date('c');
        json_write($this->path($id), $data);
        return $data;
    }

    /** Get one certificate by ID. */
    public function get(string $id): array {
        $path = $this->path($id);
        if (!file_exists($path)) return [];
        return json_read($path);
    }

    /** Get all certificates for a student. */
    public function listForStudent(string $studentId): array {
        return $this->search(fn($c) => ($c['student_id'] ?? '') === $studentId);
    }

    /** Get all certificates for a batch. */
    public function listForBatch(string $batchId): array {
        return $this->search(fn($c) => ($c['batch_id'] ?? '') === $batchId);
    }

    /** Check if a certificate already exists for student+batch. */
    public function exists(string $studentId, string $batchId): bool {
        foreach (glob($this->certDir . '/*.json') as $file) {
            $c = json_read($file);
            if (($c['student_id'] ?? '') === $studentId && ($c['batch_id'] ?? '') === $batchId) {
                return true;
            }
        }
        return false;
    }

    /** Get certificate for student+batch (or []). */
    public function findForStudentBatch(string $studentId, string $batchId): array {
        foreach (glob($this->certDir . '/*.json') as $file) {
            $c = json_read($file);
            if (($c['student_id'] ?? '') === $studentId && ($c['batch_id'] ?? '') === $batchId) {
                return $c;
            }
        }
        return [];
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function path(string $id): string {
        $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '', $id);
        return $this->certDir . '/' . $safe . '.json';
    }

    private function search(callable $fn): array {
        $result = [];
        foreach (glob($this->certDir . '/*.json') as $file) {
            $c = json_read($file);
            if (!empty($c) && $fn($c)) $result[] = $c;
        }
        usort($result, fn($a, $b) => strcmp($b['issued_at'] ?? '', $a['issued_at'] ?? ''));
        return $result;
    }
}
