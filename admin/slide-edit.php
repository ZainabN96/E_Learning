<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/Auth.php';
require_once dirname(__DIR__) . '/core/CourseRepository.php';

Auth::requireLogin();
$lang = load_lang();
$langCode = get_lang_code();
$repo = new CourseRepository();

$courseId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['course'] ?? '');
$unitId   = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['unit'] ?? '');
$slideId  = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['slide'] ?? '');

$course = $repo->getCourse($courseId);
if (empty($course)) { http_response_code(404); echo '<p>' . ['error_occurred'] . ' — Course not found.</p>'; exit; }

// Find unit
$unit = null;
foreach ($course['units'] as $u) {
    if ($u['id'] === $unitId) { $unit = $u; break; }
}
if (!$unit) { http_response_code(404); echo '<p>' . ['error_occurred'] . ' — Section not found.</p>'; exit; }

// Find slide or init new
$slide = null;
if ($slideId) {
    foreach ($unit['slides'] as $s) {
        if ($s['id'] === $slideId) { $slide = $s; break; }
    }
}
$isNew = $slide === null;
if ($isNew) {
    $slide = ['id' => '', 'title' => '', 'type' => 'html', 'order' => count($unit['slides']) + 1, 'content' => []];
}

$slideJson = json_encode($slide, JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="<?= $langCode ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isNew ? $lang['admin_new_slide'] : $lang['admin_edit_slide'] ?> — <?= $lang['admin_title'] ?></title>
    <link rel="stylesheet" href="/E_Learning/assets/css/main.css">
    <link rel="stylesheet" href="/E_Learning/assets/css/admin.css">
</head>
<body class="admin-body">

<header class="admin-topbar">
    <span class="admin-topbar__brand"><?= $lang['admin_title'] ?></span>
    <div class="admin-topbar__actions">
        <a href="/E_Learning/admin/course-edit.php?id=<?= urlencode($courseId) ?>"><?= $lang['admin_units'] ?></a>
        <a href="/E_Learning/admin/reports.php">Reports</a>
        <a href="/E_Learning/admin/logout.php"><?= $lang['admin_logout'] ?></a>
    </div>
</header>

<div class="admin-container">
    <div class="admin-page-header">
        <h1><?= $isNew ? $lang['admin_new_slide'] : $lang['admin_edit_slide'] ?></h1>
        <a href="/E_Learning/admin/course-edit.php?id=<?= urlencode($courseId) ?>"
           class="btn btn--secondary"><?= $lang['admin_cancel'] ?></a>
    </div>

    <div id="alert-container"></div>

    <div class="card">
        <form id="slide-form" novalidate>
            <input type="hidden" id="slide-id" value="<?= sanitize_string($slide['id']) ?>">
            <input type="hidden" id="slide-order" value="<?= (int)($slide['order'] ?? 1) ?>">

            <div class="form-row">
                <div class="form-group">
                    <label for="slide-title"><?= $lang['admin_slide_title'] ?> *</label>
                    <input type="text" id="slide-title" class="form-control"
                           value="<?= sanitize_string($slide['title']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="slide-type"><?= $lang['admin_slide_type'] ?></label>
                    <select id="slide-type" class="form-control">
                        <option value="html"        <?= $slide['type']==='html'        ? 'selected' : '' ?>><?= $lang['slide_type_html'] ?></option>
                        <option value="video"       <?= $slide['type']==='video'       ? 'selected' : '' ?>><?= $lang['slide_type_video'] ?></option>
                        <option value="quiz"        <?= $slide['type']==='quiz'        ? 'selected' : '' ?>><?= $lang['slide_type_quiz'] ?></option>
                        <option value="interactive" <?= $slide['type']==='interactive' ? 'selected' : '' ?>><?= $lang['slide_type_interactive'] ?></option>
                    </select>
                </div>
            </div>

            <!-- Dynamic fields rendered by JS -->
            <div id="slide-type-fields"></div>

            <div class="form-actions">
                <button type="button" id="save-slide-btn" class="btn btn--primary"><?= $lang['admin_save'] ?></button>
                <a href="/E_Learning/admin/course-edit.php?id=<?= urlencode($courseId) ?>"
                   class="btn btn--secondary"><?= $lang['admin_cancel'] ?></a>
            </div>
        </form>
    </div>
</div>

<script src="/E_Learning/assets/js/utils/dom.js"></script>
<script>
const COURSE_ID = '<?= addslashes($courseId) ?>';
const UNIT_ID   = '<?= addslashes($unitId) ?>';
const LANG      = <?= json_encode($lang, JSON_UNESCAPED_UNICODE) ?>;
const SLIDE_DATA = <?= $slideJson ?>;
</script>
<script src="/E_Learning/assets/js/admin/slide-form.js"></script>
</body>
</html>
