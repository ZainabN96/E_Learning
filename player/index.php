<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/CourseRepository.php';

$lang = load_lang();

$courseId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['course'] ?? '');
$debug    = !empty($_GET['debug']);

if (!$courseId) {
    http_response_code(400);
    echo '<p>' . $lang['error_occurred'] . ' — No course ID provided (?course=ID)</p>';
    exit;
}

$repo   = new CourseRepository();
$course = $repo->getCourse($courseId);

if (empty($course)) {
    http_response_code(404);
    echo '<p>' . $lang['error_occurred'] . ' — Course not found.</p>';
    exit;
}

$courseJson = json_encode($course, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
$langJson   = json_encode($lang, JSON_UNESCAPED_UNICODE);
$baseUrl    = '/E_Learning';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($course['metadata']['language'] ?? 'de') ?>" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize_string($course['metadata']['title'] ?? 'Course') ?></title>
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/main.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/player.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/components/quiz.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/components/interactive.css">
</head>
<body class="player-body">

<!-- Progress bar -->
<header class="player-header" role="banner">
    <div class="player-header__title"><?= sanitize_string($course['metadata']['title'] ?? '') ?></div>
    <div class="player-header__progress" aria-label="<?= $lang['progress_label'] ?>">
        <div class="progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar__fill" id="progress-bar-fill"></div>
        </div>
        <span class="progress-bar__label" id="progress-label" aria-live="polite"></span>
    </div>
</header>

<div class="player-layout">
    <!-- Sidebar navigation -->
    <aside class="player-sidebar" id="sidebar" aria-label="<?= $lang['nav_menu'] ?>">
        <!-- Populated by player.js -->
    </aside>

    <!-- Main content area -->
    <main class="player-main" id="player-main" role="main">
        <div class="slide-content" id="slide-content" aria-live="polite" aria-atomic="true">
            <div class="player-loading"><?= $lang['loading'] ?></div>
        </div>

        <!-- Navigation buttons -->
        <nav class="player-nav" aria-label="Course Navigation">
            <button type="button" class="btn btn--secondary btn--nav" id="btn-prev" disabled>
                <?= $lang['nav_prev'] ?>
            </button>
            <button type="button" class="btn btn--primary btn--nav" id="btn-next">
                <?= $lang['nav_next'] ?>
            </button>
        </nav>
    </main>
</div>

<!-- Inject course data and language strings -->
<script>
window.COURSE_DATA = <?= $courseJson ?>;
window.LANG = <?= $langJson ?>;
</script>

<?php if ($debug): ?>
<!-- Dev-only SCORM mock -->
<script src="<?= $baseUrl ?>/assets/js/scorm-mock.js"></script>
<?php endif; ?>

<!-- Vendor -->
<script src="<?= $baseUrl ?>/assets/vendor/pipwerks-scorm-api-wrapper.js"></script>

<!-- Utilities -->
<script src="<?= $baseUrl ?>/assets/js/utils/dom.js"></script>

<!-- Slide type renderers -->
<script src="<?= $baseUrl ?>/assets/js/slide-types/html-slide.js"></script>
<script src="<?= $baseUrl ?>/assets/js/slide-types/video-slide.js"></script>
<script src="<?= $baseUrl ?>/assets/js/slide-types/quiz-slide.js"></script>
<script src="<?= $baseUrl ?>/assets/js/slide-types/interactive-slide.js"></script>

<!-- Core player -->
<script src="<?= $baseUrl ?>/assets/js/player.js"></script>

<script>
// Boot the player
document.addEventListener('DOMContentLoaded', function () {
    // If no real SCORM API found in parent frames, load mock automatically
    if (!pipwerks.SCORM.API.get()) {
        var s = document.createElement('script');
        s.src = '<?= $baseUrl ?>/assets/js/scorm-mock.js';
        s.onload = function () { Player.init(window.COURSE_DATA); };
        document.head.appendChild(s);
    } else {
        Player.init(window.COURSE_DATA);
    }
});
</script>
</body>
</html>
