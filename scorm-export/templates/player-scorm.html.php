<?php
// Variables available: $course (array)
// This template generates a fully self-contained HTML file for SCORM export.
// All JS is inlined; CSS variables and component styles are embedded.
$courseJson = json_encode($course, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
$lang       = load_lang();
$langJson   = json_encode($lang, JSON_UNESCAPED_UNICODE);
$courseTitle = htmlspecialchars($course['metadata']['title'] ?? '', ENT_HTML5);

// Read and inline all JS and CSS files
function readAsset(string $path): string {
    $full = project_root() . '/' . ltrim($path, '/');
    return file_exists($full) ? file_get_contents($full) : "/* File not found: $path */";
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($course['metadata']['language'] ?? 'de') ?>" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $courseTitle ?></title>
    <style>
/* ---- main.css ---- */
<?= readAsset('assets/css/main.css') ?>

/* ---- player.css ---- */
<?= readAsset('assets/css/player.css') ?>

/* ---- quiz.css ---- */
<?= readAsset('assets/css/components/quiz.css') ?>

/* ---- interactive.css ---- */
<?= readAsset('assets/css/components/interactive.css') ?>
    </style>
</head>
<body class="player-body">

<header class="player-header" role="banner">
    <div class="player-header__title"><?= $courseTitle ?></div>
    <div class="player-header__progress">
        <div class="progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar__fill" id="progress-bar-fill"></div>
        </div>
        <span class="progress-bar__label" id="progress-label" aria-live="polite"></span>
    </div>
</header>

<div class="player-layout">
    <aside class="player-sidebar" id="sidebar" aria-label="Course Menu"></aside>
    <main class="player-main" id="player-main" role="main">
        <div class="slide-content" id="slide-content" aria-live="polite" aria-atomic="true">
            <div class="player-loading"><?= htmlspecialchars($lang['loading'] ?? 'Loading...') ?></div>
        </div>
        <nav class="player-nav" aria-label="Course Navigation">
            <button type="button" class="btn btn--secondary btn--nav" id="btn-prev" disabled><?= htmlspecialchars($lang['nav_prev'] ?? 'Back') ?></button>
            <button type="button" class="btn btn--primary btn--nav" id="btn-next"><?= htmlspecialchars($lang['nav_next'] ?? 'Next') ?></button>
        </nav>
    </main>
</div>

<script>
window.COURSE_DATA = <?= $courseJson ?>;
window.LANG = <?= $langJson ?>;
</script>

<script>
/* ---- pipwerks-scorm-api-wrapper.js ---- */
<?= readAsset('assets/vendor/pipwerks-scorm-api-wrapper.js') ?>
</script>

<script>
/* ---- dom.js ---- */
<?= readAsset('assets/js/utils/dom.js') ?>
</script>

<script>
/* ---- html-slide.js ---- */
<?= readAsset('assets/js/slide-types/html-slide.js') ?>
</script>
<script>
/* ---- video-slide.js ---- */
<?= readAsset('assets/js/slide-types/video-slide.js') ?>
</script>
<script>
/* ---- quiz-slide.js ---- */
<?= readAsset('assets/js/slide-types/quiz-slide.js') ?>
</script>
<script>
/* ---- interactive-slide.js ---- */
<?= readAsset('assets/js/slide-types/interactive-slide.js') ?>
</script>
<script>
/* ---- player.js ---- */
<?= readAsset('assets/js/player.js') ?>
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // In SCORM export, real LMS API should be present.
    // If not (e.g., previewing locally), fall back to mock.
    if (!pipwerks.SCORM.API.get()) {
        /* ---- scorm-mock.js (fallback) ---- */
        <?= readAsset('assets/js/scorm-mock.js') ?>
    }
    Player.init(window.COURSE_DATA);
});
</script>
</body>
</html>
