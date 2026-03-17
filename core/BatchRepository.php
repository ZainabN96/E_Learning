<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

class BatchRepository {

    private string $batchDir;
    private string $attendanceDir;
    private string $assignmentDir;

    public function __construct() {
        $this->batchDir      = data_dir() . '/batches';
        $this->attendanceDir = data_dir() . '/attendance';
        $this->assignmentDir = data_dir() . '/assignments';
        foreach ([$this->batchDir, $this->attendanceDir, $this->assignmentDir] as $d) {
            if (!is_dir($d)) mkdir($d, 0755, true);
        }
    }

    // ── Batches ──────────────────────────────────────────────────────────────

    /** List all batches, optionally filtered by trainer_id. */
    public function listBatches(?string $trainerId = null): array {
        $batches = [];
        foreach (glob($this->batchDir . '/*.json') as $file) {
            $b = json_read($file);
            if (!empty($b)) {
                if ($trainerId === null || ($b['trainer_id'] ?? '') === $trainerId) {
                    $batches[] = $b;
                }
            }
        }
        usort($batches, fn($a, $b) => strcmp($b['start_date'] ?? '', $a['start_date'] ?? ''));
        return $batches;
    }

    /** Get a batch by student enrollment. */
    public function listBatchesForStudent(string $studentId): array {
        $result = [];
        foreach (glob($this->batchDir . '/*.json') as $file) {
            $b = json_read($file);
            if (!empty($b) && in_array($studentId, $b['student_ids'] ?? [], true)) {
                $result[] = $b;
            }
        }
        usort($result, fn($a, $b) => strcmp($b['start_date'] ?? '', $a['start_date'] ?? ''));
        return $result;
    }

    /** Get one batch by ID. */
    public function getBatch(string $id): array {
        $path = $this->batchPath($id);
        if (!file_exists($path)) return [];
        return json_read($path);
    }

    /** Create or update a batch. */
    public function saveBatch(array $data): array {
        if (empty($data['id'])) {
            $data['id']         = 'batch-' . substr(generate_uuid(), 0, 8);
            $data['created_at'] = date('c');
        }
        $data['student_ids'] = array_values(array_unique($data['student_ids'] ?? []));
        $data['updated_at']  = date('c');
        json_write($this->batchPath($data['id']), $data);
        return $data;
    }

    /** Delete a batch and all its attendance + assignments. */
    public function deleteBatch(string $id): bool {
        $path = $this->batchPath($id);
        if (!file_exists($path)) return false;
        unlink($path);
        // Remove attendance folder
        $attDir = $this->attendanceDir . '/' . $id;
        if (is_dir($attDir)) $this->removeDir($attDir);
        // Remove assignment folder
        $assDir = $this->assignmentDir . '/' . $id;
        if (is_dir($assDir)) $this->removeDir($assDir);
        return true;
    }

    // ── Attendance ───────────────────────────────────────────────────────────

    /** Get attendance for a batch on a specific date (YYYY-MM-DD). */
    public function getAttendance(string $batchId, string $date): array {
        $path = $this->attendancePath($batchId, $date);
        if (!file_exists($path)) return ['batch_id' => $batchId, 'date' => $date, 'records' => []];
        return json_read($path);
    }

    /** Get all attendance dates for a batch. Returns [date => records]. */
    public function getAllAttendance(string $batchId): array {
        $dir = $this->attendanceDir . '/' . $batchId;
        if (!is_dir($dir)) return [];
        $result = [];
        foreach (glob($dir . '/*.json') as $file) {
            $rec = json_read($file);
            if (!empty($rec)) {
                $result[$rec['date']] = $rec['records'] ?? [];
            }
        }
        ksort($result);
        return $result;
    }

    /** Save attendance for a batch on a date. */
    public function saveAttendance(string $batchId, string $date, array $records): void {
        $dir = $this->attendanceDir . '/' . $batchId;
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        json_write($this->attendancePath($batchId, $date), [
            'batch_id' => $batchId,
            'date'     => $date,
            'records'  => $records,
        ]);
    }

    // ── Assignments ──────────────────────────────────────────────────────────

    /** List all assignments for a batch. */
    public function listAssignments(string $batchId): array {
        $dir = $this->assignmentDir . '/' . $batchId;
        if (!is_dir($dir)) return [];
        $result = [];
        foreach (glob($dir . '/*.json') as $file) {
            if (str_contains($file, '/submissions/')) continue;
            $a = json_read($file);
            if (!empty($a)) $result[] = $a;
        }
        usort($result, fn($a, $b) => strcmp($a['due_date'] ?? '', $b['due_date'] ?? ''));
        return $result;
    }

    /** Get one assignment. */
    public function getAssignment(string $batchId, string $assignId): array {
        $path = $this->assignmentPath($batchId, $assignId);
        if (!file_exists($path)) return [];
        return json_read($path);
    }

    /** Create or update an assignment. */
    public function saveAssignment(string $batchId, array $data): array {
        if (empty($data['id'])) {
            $data['id']         = 'assign-' . substr(generate_uuid(), 0, 8);
            $data['created_at'] = date('c');
        }
        $data['batch_id']   = $batchId;
        $data['updated_at'] = date('c');
        $dir = $this->assignmentDir . '/' . $batchId;
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        json_write($this->assignmentPath($batchId, $data['id']), $data);
        return $data;
    }

    /** Delete an assignment and all its submissions. */
    public function deleteAssignment(string $batchId, string $assignId): bool {
        $path = $this->assignmentPath($batchId, $assignId);
        if (!file_exists($path)) return false;
        unlink($path);
        $subDir = $this->assignmentDir . '/' . $batchId . '/submissions/' . $assignId;
        if (is_dir($subDir)) $this->removeDir($subDir);
        return true;
    }

    // ── Submissions ──────────────────────────────────────────────────────────

    /** Get all submissions for an assignment. Returns [studentId => submission]. */
    public function listSubmissions(string $batchId, string $assignId): array {
        $dir = $this->assignmentDir . '/' . $batchId . '/submissions/' . $assignId;
        if (!is_dir($dir)) return [];
        $result = [];
        foreach (glob($dir . '/*.json') as $file) {
            $s = json_read($file);
            if (!empty($s)) $result[$s['student_id']] = $s;
        }
        return $result;
    }

    /** Get one student's submission for an assignment. */
    public function getSubmission(string $batchId, string $assignId, string $studentId): array {
        $path = $this->submissionPath($batchId, $assignId, $studentId);
        if (!file_exists($path)) return [];
        return json_read($path);
    }

    /** Save a submission (student or grader). */
    public function saveSubmission(string $batchId, string $assignId, array $data): array {
        $studentId = $data['student_id'];
        $dir = $this->assignmentDir . '/' . $batchId . '/submissions/' . $assignId;
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $existing = $this->getSubmission($batchId, $assignId, $studentId);
        $data = array_merge($existing, $data);
        if (empty($data['submitted_at']) && !empty($data['text'] ?? $data['file_path'] ?? '')) {
            $data['submitted_at'] = date('c');
        }
        json_write($this->submissionPath($batchId, $assignId, $studentId), $data);
        return $data;
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function batchPath(string $id): string {
        return $this->batchDir . '/' . preg_replace('/[^a-zA-Z0-9_\-]/', '', $id) . '.json';
    }

    private function attendancePath(string $batchId, string $date): string {
        $safeBatch = preg_replace('/[^a-zA-Z0-9_\-]/', '', $batchId);
        $safeDate  = preg_replace('/[^0-9\-]/', '', $date);
        return $this->attendanceDir . '/' . $safeBatch . '/' . $safeDate . '.json';
    }

    private function assignmentPath(string $batchId, string $assignId): string {
        $safeBatch  = preg_replace('/[^a-zA-Z0-9_\-]/', '', $batchId);
        $safeAssign = preg_replace('/[^a-zA-Z0-9_\-]/', '', $assignId);
        return $this->assignmentDir . '/' . $safeBatch . '/' . $safeAssign . '.json';
    }

    private function submissionPath(string $batchId, string $assignId, string $studentId): string {
        $safeBatch   = preg_replace('/[^a-zA-Z0-9_\-]/', '', $batchId);
        $safeAssign  = preg_replace('/[^a-zA-Z0-9_\-]/', '', $assignId);
        $safeStudent = preg_replace('/[^a-zA-Z0-9_\-]/', '', $studentId);
        return $this->assignmentDir . '/' . $safeBatch . '/submissions/' . $safeAssign . '/' . $safeStudent . '.json';
    }

    private function removeDir(string $dir): void {
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $p = $dir . '/' . $item;
            is_dir($p) ? $this->removeDir($p) : unlink($p);
        }
        rmdir($dir);
    }
}
