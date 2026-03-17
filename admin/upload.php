<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/Auth.php';

Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'POST required'], 405);
}

$courseId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['course_id'] ?? '');
if (!$courseId) json_response(['error' => 'course_id required'], 400);

$config     = json_read(data_dir() . '/config.json');
$maxMb      = (int)($config['max_upload_mb'] ?? 50);
$maxBytes   = $maxMb * 1024 * 1024;

$allowed = [
    'video' => ['mp4', 'webm', 'ogv'],
    'audio' => ['mp3', 'ogg', 'wav', 'm4a'],
    'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
    'other' => ['vtt', 'pdf'],
];
$allAllowed = array_merge(...array_values($allowed));

if (empty($_FILES['file'])) {
    json_response(['error' => 'No file uploaded'], 400);
}

$file     = $_FILES['file'];
$origName = basename($file['name']);
$ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

if ($file['error'] !== UPLOAD_ERR_OK) {
    $errors = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server-side limit',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form limit',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE    => 'No file uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'No temporary directory',
        UPLOAD_ERR_CANT_WRITE => 'Write error',
    ];
    json_response(['error' => $errors[$file['error']] ?? 'Upload error'], 400);
}
if ($file['size'] > $maxBytes) {
    json_response(['error' => "File is too large (max {$maxMb} MB)"], 400);
}
if (!in_array($ext, $allAllowed)) {
    json_response(['error' => "File type '$ext' not allowed"], 400);
}

// Determine subdirectory
$subDir = 'other';
foreach ($allowed as $type => $exts) {
    if (in_array($ext, $exts)) { $subDir = $type . 's'; break; }
}
if ($subDir === 'others') $subDir = 'other';

$destDir = project_root() . "/media/$courseId/$subDir";
if (!is_dir($destDir)) mkdir($destDir, 0755, true);

// Sanitize filename
$safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $origName);
$safeName = preg_replace('/\.+/', '.', $safeName);
$destPath = $destDir . '/' . $safeName;

// Avoid overwrite
if (file_exists($destPath)) {
    $base     = pathinfo($safeName, PATHINFO_FILENAME);
    $safeName = $base . '_' . substr(generate_uuid(), 0, 6) . '.' . $ext;
    $destPath = $destDir . '/' . $safeName;
}

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    json_response(['error' => 'File could not be saved'], 500);
}

$relativePath = "media/$courseId/$subDir/$safeName";
json_response(['success' => true, 'path' => $relativePath, 'filename' => $safeName]);
