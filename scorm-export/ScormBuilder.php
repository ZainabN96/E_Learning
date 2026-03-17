<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/CourseRepository.php';

class ScormBuilder {

    private CourseRepository $repo;
    private string $projectRoot;
    private string $outputDir;

    public function __construct() {
        $this->repo        = new CourseRepository();
        $this->projectRoot = project_root();
        $this->outputDir   = $this->projectRoot . '/courses';
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
    }

    /**
     * Build a SCORM 1.2 package for the given course ID.
     * Returns the absolute path to the generated ZIP file.
     */
    public function build(string $courseId): string {
        $course = $this->repo->getCourse($courseId);
        if (empty($course)) {
            throw new RuntimeException("Course not found: $courseId");
        }
        if (!extension_loaded('zip')) {
            throw new RuntimeException("PHP zip extension is not loaded. Enable extension=zip in php.ini.");
        }

        // Create temporary build directory
        $tmpDir = sys_get_temp_dir() . '/scorm_build_' . $courseId . '_' . getmypid();
        if (is_dir($tmpDir)) {
            $this->removeDirectory($tmpDir);
        }
        mkdir($tmpDir, 0755, true);

        try {
            // 1. Generate imsmanifest.xml
            $this->writeManifest($tmpDir, $course);

            // 2. Generate self-contained player HTML
            $this->writePlayerHtml($tmpDir, $course);

            // 3. Copy media assets
            $this->copyMedia($tmpDir, $courseId);

            // 4. Write SCORM metadata files
            file_put_contents($tmpDir . '/adlcp_rootv1p2.xsd', $this->getAdlcpXsd());
            file_put_contents($tmpDir . '/ims_xml.xsd', $this->getImsXmlXsd());
            file_put_contents($tmpDir . '/imscp_rootv1p1p2.xsd', $this->getImscpXsd());
            file_put_contents($tmpDir . '/imsmd_rootv1p2p1.xsd', '');

            // 5. Zip everything
            $zipPath = $this->outputDir . '/' . $courseId . '.zip';
            $this->createZip($tmpDir, $zipPath);

            return $zipPath;
        } finally {
            $this->removeDirectory($tmpDir);
        }
    }

    private function writeManifest(string $dir, array $course): void {
        ob_start();
        include __DIR__ . '/templates/imsmanifest.xml.php';
        $xml = ob_get_clean();
        file_put_contents($dir . '/imsmanifest.xml', $xml);
    }

    private function writePlayerHtml(string $dir, array $course): void {
        ob_start();
        include __DIR__ . '/templates/player-scorm.html.php';
        $html = ob_get_clean();
        file_put_contents($dir . '/index.html', $html);
    }

    private function copyMedia(string $tmpDir, string $courseId): void {
        $mediaDir = $this->projectRoot . '/media/' . $courseId;
        if (!is_dir($mediaDir)) return;

        $destMedia = $tmpDir . '/media/' . $courseId;
        $this->copyDirectory($mediaDir, $destMedia);
    }

    private function createZip(string $srcDir, string $zipPath): void {
        if (file_exists($zipPath)) unlink($zipPath);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot create ZIP: $zipPath");
        }

        $srcDir = rtrim($srcDir, '/\\') . '/';
        $files  = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($srcDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $filePath   = $file->getRealPath();
            $relativePath = substr($filePath, strlen($srcDir));
            $relativePath = str_replace('\\', '/', $relativePath);

            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($filePath, $relativePath);
            }
        }
        $zip->close();
    }

    private function copyDirectory(string $src, string $dst): void {
        if (!is_dir($dst)) mkdir($dst, 0755, true);
        foreach (scandir($src) as $item) {
            if ($item === '.' || $item === '..') continue;
            $s = $src . '/' . $item;
            $d = $dst . '/' . $item;
            is_dir($s) ? $this->copyDirectory($s, $d) : copy($s, $d);
        }
    }

    private function removeDirectory(string $dir): void {
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /** Minimal SCORM 1.2 XSD stubs (required by some strict LMS validators) */
    private function getAdlcpXsd(): string {
        return '<?xml version="1.0" encoding="UTF-8"?><xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema"/>';
    }
    private function getImsXmlXsd(): string {
        return '<?xml version="1.0" encoding="UTF-8"?><xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema"/>';
    }
    private function getImscpXsd(): string {
        return '<?xml version="1.0" encoding="UTF-8"?><xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema"/>';
    }
}
