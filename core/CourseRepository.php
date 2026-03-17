<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

class CourseRepository {

    private string $coursesDir;

    public function __construct() {
        $this->coursesDir = data_dir() . '/courses';
        if (!is_dir($this->coursesDir)) {
            mkdir($this->coursesDir, 0755, true);
        }
    }

    /**
     * List all courses (metadata only, sorted by updated_at desc).
     */
    public function listCourses(): array {
        $courses = [];
        foreach (glob($this->coursesDir . '/*/course.json') as $file) {
            $data = json_read($file);
            if (!empty($data)) {
                $courses[] = [
                    'id'               => $data['id'] ?? '',
                    'title'            => $data['metadata']['title'] ?? '',
                    'description'      => $data['metadata']['description'] ?? '',
                    'language'         => $data['metadata']['language'] ?? 'de',
                    'duration_minutes' => $data['metadata']['duration_minutes'] ?? 0,
                    'updated_at'       => $data['updated_at'] ?? '',
                    'slide_count'      => $this->countSlides($data),
                ];
            }
        }
        usort($courses, fn($a, $b) => strcmp($b['updated_at'], $a['updated_at']));
        return $courses;
    }

    /**
     * Get a full course by ID.
     */
    public function getCourse(string $id): array {
        $path = $this->coursePath($id);
        if (!file_exists($path)) {
            return [];
        }
        return json_read($path);
    }

    /**
     * Save (create or update) a course. Generates ID and timestamps.
     */
    public function saveCourse(array $data): array {
        if (empty($data['id'])) {
            $data['id'] = 'course-' . substr(generate_uuid(), 0, 8);
            $data['created_at'] = date('c');
            $data['units'] = $data['units'] ?? [];
        }
        $data['updated_at'] = date('c');

        $dir = $this->coursesDir . '/' . $data['id'];
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        json_write($this->coursePath($data['id']), $data);
        return $data;
    }

    /**
     * Delete a course and all its data.
     */
    public function deleteCourse(string $id): bool {
        $dir = $this->coursesDir . '/' . $id;
        if (!is_dir($dir)) {
            return false;
        }
        $this->removeDirectory($dir);
        return true;
    }

    /**
     * Get learner progress for a course session.
     */
    public function getProgress(string $courseId, string $sessionId): array {
        $path = $this->progressPath($courseId, $sessionId);
        if (!file_exists($path)) {
            return [];
        }
        return json_read($path);
    }

    /**
     * Save learner progress for a course session.
     */
    public function saveProgress(string $courseId, string $sessionId, array $data): void {
        $path = $this->progressPath($courseId, $sessionId);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        json_write($path, $data);
    }

    /**
     * Add or update a unit within a course.
     */
    public function saveUnit(string $courseId, array $unit): array {
        $course = $this->getCourse($courseId);
        if (empty($course)) {
            throw new RuntimeException("Course not found: $courseId");
        }

        if (empty($unit['id'])) {
            $unit['id'] = 'unit-' . substr(generate_uuid(), 0, 8);
            $unit['slides'] = $unit['slides'] ?? [];
            $course['units'][] = $unit;
        } else {
            foreach ($course['units'] as &$u) {
                if ($u['id'] === $unit['id']) {
                    $u = array_merge($u, $unit);
                    break;
                }
            }
            unset($u);
        }

        // Reorder units
        usort($course['units'], fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
        $this->saveCourse($course);
        return $unit;
    }

    /**
     * Add or update a slide within a unit.
     */
    public function saveSlide(string $courseId, string $unitId, array $slide): array {
        $course = $this->getCourse($courseId);
        if (empty($course)) {
            throw new RuntimeException("Course not found: $courseId");
        }

        foreach ($course['units'] as &$unit) {
            if ($unit['id'] !== $unitId) {
                continue;
            }
            if (empty($slide['id'])) {
                $slide['id'] = 'slide-' . substr(generate_uuid(), 0, 8);
                $slide['scorm_objective_id'] = 'obj-' . substr(generate_uuid(), 0, 8);
                $unit['slides'][] = $slide;
            } else {
                foreach ($unit['slides'] as &$s) {
                    if ($s['id'] === $slide['id']) {
                        $s = array_merge($s, $slide);
                        break;
                    }
                }
                unset($s);
            }
            usort($unit['slides'], fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
            break;
        }
        unset($unit);

        $this->saveCourse($course);
        return $slide;
    }

    /**
     * Delete a slide from a unit.
     */
    public function deleteSlide(string $courseId, string $unitId, string $slideId): void {
        $course = $this->getCourse($courseId);
        foreach ($course['units'] as &$unit) {
            if ($unit['id'] === $unitId) {
                $unit['slides'] = array_values(
                    array_filter($unit['slides'], fn($s) => $s['id'] !== $slideId)
                );
                break;
            }
        }
        unset($unit);
        $this->saveCourse($course);
    }

    /**
     * Delete a unit and all its slides.
     */
    public function deleteUnit(string $courseId, string $unitId): void {
        $course = $this->getCourse($courseId);
        $course['units'] = array_values(
            array_filter($course['units'], fn($u) => $u['id'] !== $unitId)
        );
        $this->saveCourse($course);
    }

    // --- Private helpers ---

    private function coursePath(string $id): string {
        return $this->coursesDir . '/' . $id . '/course.json';
    }

    private function progressPath(string $courseId, string $sessionId): string {
        $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '', $sessionId);
        return $this->coursesDir . '/' . $courseId . '/progress/' . $safe . '.json';
    }

    private function countSlides(array $course): int {
        $count = 0;
        foreach ($course['units'] ?? [] as $unit) {
            $count += count($unit['slides'] ?? []);
        }
        return $count;
    }

    private function removeDirectory(string $dir): void {
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
