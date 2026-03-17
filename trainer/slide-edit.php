<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/UserAuth.php';
require_once dirname(__DIR__) . '/core/BatchRepository.php';
require_once dirname(__DIR__) . '/core/CourseRepository.php';

UserAuth::requireTrainer();
$lang          = load_lang();
$langCode      = get_lang_code();
$platformTitle = $lang['platform_title'];
$trainerId     = UserAuth::userId();

$batchId   = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['batch']  ?? '');
$courseId  = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['course'] ?? '');
$unitId    = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['unit']   ?? '');
$slideId   = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['slide']  ?? '');

// Verify trainer owns this batch/course
$batchRepo = new BatchRepository();
$batch     = $batchId ? $batchRepo->getBatch($batchId) : [];
if (empty($batch) || $batch['trainer_id'] !== $trainerId || $batch['course_id'] !== $courseId) {
    header('Location: /E_Learning/trainer/dashboard.php'); exit;
}

$repo   = new CourseRepository();
$course = $repo->getCourse($courseId);
if (empty($course)) { http_response_code(404); echo '<p>Course not found.</p>'; exit; }

// Find unit
$unit = null;
foreach ($course['units'] ?? [] as $u) {
    if ($u['id'] === $unitId) { $unit = $u; break; }
}
if (!$unit) { http_response_code(404); echo '<p>Module not found.</p>'; exit; }

// Find slide or init new
$slide = null;
if ($slideId) {
    foreach ($unit['slides'] ?? [] as $s) {
        if ($s['id'] === $slideId) { $slide = $s; break; }
    }
}
$isNew = $slide === null;
if ($isNew) {
    $slide = ['id' => '', 'title' => '', 'type' => 'html', 'order' => count($unit['slides']) + 1, 'content' => []];
}

$slideJson = json_encode($slide, JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE);
$backUrl   = '/E_Learning/trainer/course.php?batch=' . urlencode($batchId);
?>
<!DOCTYPE html>
<html lang="<?= $langCode ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $isNew ? 'New Slide' : 'Edit Slide' ?> — <?= htmlspecialchars($course['metadata']['title'] ?? '') ?></title>
    <link rel="stylesheet" href="/E_Learning/assets/css/portal.css">
    <link rel="stylesheet" href="/E_Learning/assets/css/admin.css">
    <style>
        /* Override admin card padding to work inside portal layout */
        .admin-container { max-width: 100%; padding: 0; }
        .card__body { padding: 1.25rem; }
    </style>
</head>
<body>
<div class="portal-shell">

    <header class="topbar">
        <span class="topbar__brand">🎓 <?= htmlspecialchars($platformTitle) ?></span>
        <div class="topbar__user">
            <span>👤 <?= htmlspecialchars(UserAuth::userName()) ?></span>
            <a href="<?= $backUrl ?>">← Course Outline</a>
            <a href="/E_Learning/trainer/logout.php">Sign out</a>
        </div>
    </header>

    <div class="portal-body">
        <nav class="sidebar">
            <div class="sidebar__nav">
                <a href="/E_Learning/trainer/dashboard.php"><span class="nav-icon">📋</span> My Batches</a>
                <a href="/E_Learning/trainer/reports.php"><span class="nav-icon">&#128202;</span> Reports</a>
                <a href="/E_Learning/trainer/batch.php?id=<?= urlencode($batchId) ?>"><span class="nav-icon">📦</span> Batch Overview</a>
                <a href="/E_Learning/trainer/attendance.php?batch=<?= urlencode($batchId) ?>"><span class="nav-icon">✅</span> Attendance</a>
                <a href="/E_Learning/trainer/assignments.php?batch=<?= urlencode($batchId) ?>"><span class="nav-icon">📝</span> Assignments</a>
                <a href="/E_Learning/trainer/course.php?batch=<?= urlencode($batchId) ?>" class="active">
                    <span class="nav-icon">📚</span> Course Outline
                </a>
            </div>
        </nav>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1><?= $isNew ? 'New Slide' : 'Edit Slide' ?></h1>
                    <p class="subtitle">
                        Module: <?= htmlspecialchars($unit['title']) ?> ·
                        Course: <?= htmlspecialchars($course['metadata']['title'] ?? '') ?>
                    </p>
                </div>
                <a href="<?= $backUrl ?>" class="btn btn--secondary">← Back to Outline</a>
            </div>

            <div id="alert-container"></div>

            <div class="card">
                <div class="card__body">
                    <form id="slide-form" novalidate>
                        <input type="hidden" id="slide-id"    value="<?= htmlspecialchars($slide['id']) ?>">
                        <input type="hidden" id="slide-order" value="<?= (int)($slide['order'] ?? 1) ?>">
                        <input type="hidden" id="batch-id"    value="<?= htmlspecialchars($batchId) ?>">

                        <div class="form-row">
                            <div class="form-group">
                                <label for="slide-title"><?= $lang['admin_slide_title'] ?> <span class="required-mark">*</span></label>
                                <input type="text" id="slide-title" class="form-control"
                                       value="<?= htmlspecialchars($slide['title']) ?>" required>
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

                        <!-- Dynamic fields rendered by slide-form.js -->
                        <div id="slide-type-fields"></div>

                        <div style="display:flex;gap:.75rem;margin-top:1.25rem">
                            <button type="button" id="save-slide-btn" class="btn btn--primary">
                                <?= $lang['admin_save'] ?>
                            </button>
                            <a href="<?= $backUrl ?>" class="btn btn--secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="/E_Learning/assets/js/utils/dom.js"></script>
<script>
const COURSE_ID  = '<?= addslashes($courseId) ?>';
const UNIT_ID    = '<?= addslashes($unitId) ?>';
const BATCH_ID   = '<?= addslashes($batchId) ?>';
const LANG       = <?= json_encode($lang, JSON_UNESCAPED_UNICODE) ?>;
const SLIDE_DATA = <?= $slideJson ?>;
const BACK_URL   = '<?= addslashes($backUrl) ?>';
const SAVE_API   = '/E_Learning/trainer/api/save-slide.php';

function showAlert(type, msg) {
    const c = document.getElementById('alert-container');
    const el = document.createElement('div');
    el.className = 'alert alert--' + type;
    el.textContent = msg;
    c.innerHTML = '';
    c.appendChild(el);
    setTimeout(() => el.remove(), 4000);
}
</script>
<script src="/E_Learning/assets/js/admin/slide-form.js"></script>
<script>
// Override the save handler injected by slide-form.js to use trainer API + batch_id
on($('#save-slide-btn'), 'click', async function() {
    const title = document.getElementById('slide-title').value.trim();
    if (!title) { alert('Please enter a slide title.'); return; }

    const type    = document.getElementById('slide-type').value;
    const id      = document.getElementById('slide-id').value;
    const order   = parseInt(document.getElementById('slide-order').value) || 1;
    const content = window.getSlideContent ? window.getSlideContent() : {};

    try {
        const res = await fetch(SAVE_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                course_id: COURSE_ID,
                unit_id:   UNIT_ID,
                batch_id:  BATCH_ID,
                slide: { id, title, type, order, content }
            })
        });
        const data = await res.json();
        if (data.success) {
            window.location.href = BACK_URL + '&msg=slide_saved';
        } else {
            showAlert('error', data.error || 'Error saving slide.');
        }
    } catch(e) {
        showAlert('error', 'Network error.');
    }
}, { once: false });
</script>
</body>
</html>
