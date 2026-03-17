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

// Must arrive with a batch context so we can verify ownership
$batchId   = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['batch'] ?? '');
$batchRepo = new BatchRepository();
$batch     = $batchId ? $batchRepo->getBatch($batchId) : [];

if (empty($batch) || $batch['trainer_id'] !== $trainerId) {
    header('Location: /E_Learning/trainer/dashboard.php'); exit;
}

$courseId   = preg_replace('/[^a-zA-Z0-9_\-]/', '', $batch['course_id'] ?? '');
$courseRepo = new CourseRepository();
$course     = $courseRepo->getCourse($courseId);

if (empty($course)) {
    header('Location: /E_Learning/trainer/batch.php?id=' . urlencode($batchId)); exit;
}
?>
<!DOCTYPE html>
<html lang="<?= $langCode ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Course Outline — <?= htmlspecialchars($course['metadata']['title'] ?? '') ?></title>
    <link rel="stylesheet" href="/E_Learning/assets/css/portal.css">
    <style>
        /* ── Reuse admin course-builder styles inside portal layout ── */
        .unit-card {
            border: 1px solid var(--color-gray-200);
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
            background: #fff;
        }
        .unit-card__header {
            display: flex;
            align-items: center;
            gap: .6rem;
            padding: .75rem 1rem;
            background: var(--color-gray-50);
            border-bottom: 1px solid var(--color-gray-200);
            border-radius: var(--radius-md) var(--radius-md) 0 0;
        }
        .unit-card__title { font-weight: 600; flex: 1; }
        .unit-card__body  { padding: .75rem 1rem; }
        .slide-list       { list-style: none; padding: 0; margin: 0 0 .75rem; }
        .slide-list__item {
            display: flex;
            align-items: center;
            gap: .6rem;
            padding: .5rem .6rem;
            border: 1px solid var(--color-gray-200);
            border-radius: var(--radius-sm);
            margin-bottom: .4rem;
            background: #fff;
        }
        .slide-list__item:hover { background: var(--color-gray-50); }
        .slide-list__handle { cursor: grab; color: var(--color-gray-400); font-size: 1.1rem; }
        .slide-list__title  { flex: 1; font-size: .9rem; }
        .slide-list__actions { display: flex; gap: .3rem; }
        .slide-list__type-badge {
            font-size: .7rem;
            padding: .15rem .45rem;
            border-radius: 999px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
            background: var(--color-gray-200);
            color: var(--color-gray-600);
        }
        .slide-list__type-badge--html        { background:#dbeafe;color:#1e40af }
        .slide-list__type-badge--video       { background:#fce7f3;color:#9d174d }
        .slide-list__type-badge--quiz        { background:#dcfce7;color:#166534 }
        .slide-list__type-badge--interactive { background:#fef3c7;color:#92400e }
        .sortable-ghost   { opacity:.4; background: var(--color-primary-light); }
        .sortable-chosen  { box-shadow: var(--shadow-md); }
    </style>
</head>
<body>
<div class="portal-shell">

    <header class="topbar">
        <span class="topbar__brand">🎓 <?= htmlspecialchars($platformTitle) ?></span>
        <div class="topbar__user">
            <span>👤 <?= htmlspecialchars(UserAuth::userName()) ?></span>
            <a href="/E_Learning/trainer/batch.php?id=<?= urlencode($batchId) ?>">← Batch</a>
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
                    <h1>📚 Course Outline</h1>
                    <p class="subtitle"><?= htmlspecialchars($course['metadata']['title'] ?? '') ?> — <?= htmlspecialchars($batch['name'] ?? '') ?></p>
                </div>
                <a href="/E_Learning/player/?course=<?= urlencode($courseId) ?>" target="_blank"
                   class="btn btn--secondary">▶ Preview Course</a>
            </div>

            <div id="alert-container"></div>

            <!-- Course Settings -->
            <div class="card">
                <div class="card__header"><h2>Course Settings</h2></div>
                <div class="card__body">
                    <form id="course-form">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($courseId) ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Course Title <span class="required-mark">*</span></label>
                                <input type="text" name="metadata[title]" class="form-control" required
                                       value="<?= htmlspecialchars($course['metadata']['title'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Author</label>
                                <input type="text" name="metadata[author]" class="form-control"
                                       value="<?= htmlspecialchars($course['metadata']['author'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="metadata[description]" class="form-control"
                                      rows="3"><?= htmlspecialchars($course['metadata']['description'] ?? '') ?></textarea>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Duration (minutes)</label>
                                <input type="number" name="metadata[duration_minutes]" class="form-control"
                                       min="1" max="600"
                                       value="<?= (int)($course['metadata']['duration_minutes'] ?? 30) ?>">
                            </div>
                            <div class="form-group">
                                <label>Passing Score (%)</label>
                                <input type="number" name="scorm[masteryScore]" class="form-control"
                                       min="0" max="100"
                                       value="<?= (int)($course['scorm']['masteryScore'] ?? 80) ?>">
                                <p class="form-hint">Minimum % required to pass the course.</p>
                            </div>
                        </div>
                        <button type="button" id="save-course-btn" class="btn btn--primary">Save Settings</button>
                    </form>
                </div>
            </div>

            <!-- Course Structure -->
            <div class="card" style="margin-top:1.25rem">
                <div class="card__header">
                    <h2>Course Structure</h2>
                    <button type="button" class="btn btn--primary btn--sm" id="add-unit-btn">
                        + Add Module
                    </button>
                </div>
                <div class="card__body">
                    <div id="units-container">
                        <?php foreach ($course['units'] as $unit): ?>
                        <div class="unit-card" data-unit-id="<?= htmlspecialchars($unit['id']) ?>">
                            <div class="unit-card__header">
                                <span class="slide-list__handle">⠿</span>
                                <span class="unit-card__title"><?= htmlspecialchars($unit['title']) ?></span>
                                <div style="display:flex;gap:.3rem">
                                    <button type="button" class="btn btn--sm btn--secondary edit-unit-btn"
                                            data-unit='<?= json_encode(['id'=>$unit['id'],'title'=>$unit['title'],'order'=>$unit['order']], JSON_HEX_APOS) ?>'>
                                        Rename
                                    </button>
                                    <button type="button" class="btn btn--sm btn--danger delete-unit-btn"
                                            data-unit-id="<?= htmlspecialchars($unit['id']) ?>">
                                        Delete
                                    </button>
                                </div>
                            </div>
                            <div class="unit-card__body">
                                <ul class="slide-list" data-unit-id="<?= htmlspecialchars($unit['id']) ?>">
                                    <?php foreach ($unit['slides'] as $slide): ?>
                                    <li class="slide-list__item" data-slide-id="<?= htmlspecialchars($slide['id']) ?>">
                                        <span class="slide-list__handle">⠿</span>
                                        <span class="slide-list__type-badge slide-list__type-badge--<?= htmlspecialchars($slide['type']) ?>">
                                            <?= htmlspecialchars($slide['type']) ?>
                                        </span>
                                        <span class="slide-list__title"><?= htmlspecialchars($slide['title']) ?></span>
                                        <div class="slide-list__actions">
                                            <a href="/E_Learning/trainer/slide-edit.php?batch=<?= urlencode($batchId) ?>&course=<?= urlencode($courseId) ?>&unit=<?= urlencode($unit['id']) ?>&slide=<?= urlencode($slide['id']) ?>"
                                               class="btn btn--sm btn--secondary">Edit</a>
                                            <button type="button" class="btn btn--sm btn--danger delete-slide-btn"
                                                    data-slide-id="<?= htmlspecialchars($slide['id']) ?>"
                                                    data-unit-id="<?= htmlspecialchars($unit['id']) ?>">
                                                Delete
                                            </button>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                                <a href="/E_Learning/trainer/slide-edit.php?batch=<?= urlencode($batchId) ?>&course=<?= urlencode($courseId) ?>&unit=<?= urlencode($unit['id']) ?>"
                                   class="btn btn--sm btn--primary">+ Add Slide</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (empty($course['units'])): ?>
                        <p style="color:var(--color-gray-400);text-align:center;padding:1rem">
                            No modules yet. Click "Add Module" to start building the course.
                        </p>
                    <?php endif; ?>
                </div>
            </div>

        </main>
    </div>
</div>

<script src="/E_Learning/assets/js/utils/dom.js"></script>
<script src="/E_Learning/assets/vendor/sortable.min.js"></script>
<script>
const COURSE_ID = '<?= addslashes($courseId) ?>';
const BATCH_ID  = '<?= addslashes($batchId) ?>';
const LANG      = <?= json_encode($lang, JSON_UNESCAPED_UNICODE) ?>;
const API_BASE  = '/E_Learning/trainer/api/';

function showAlert(type, msg) {
    const c = document.getElementById('alert-container');
    const el = document.createElement('div');
    el.className = 'alert alert--' + type;
    el.textContent = msg;
    c.innerHTML = '';
    c.appendChild(el);
    setTimeout(() => el.remove(), 4000);
}

async function apiPost(endpoint, body) {
    const res = await fetch(API_BASE + endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    });
    return res.json();
}

// Save course settings
on($('#save-course-btn'), 'click', async function() {
    const f = document.getElementById('course-form');
    const fd = new FormData(f);
    const data = await apiPost('save-course.php', {
        id: fd.get('id'),
        metadata: {
            title:            fd.get('metadata[title]'),
            description:      fd.get('metadata[description]'),
            author:           fd.get('metadata[author]'),
            duration_minutes: parseInt(fd.get('metadata[duration_minutes]')) || 30,
        },
        scorm: { masteryScore: parseInt(fd.get('scorm[masteryScore]')) || 80 },
        batch_id: BATCH_ID,
    });
    if (data.success) showAlert('success', 'Settings saved.');
    else showAlert('error', data.error || 'Error saving.');
});

// Add module
on($('#add-unit-btn'), 'click', async function() {
    const title = prompt('Module title:');
    if (!title) return;
    const data = await apiPost('save-unit.php', { course_id: COURSE_ID, title, order: 999, batch_id: BATCH_ID });
    if (data.success) location.reload();
    else showAlert('error', data.error || 'Error.');
});

// Rename module
delegate(document, '.edit-unit-btn', 'click', async function(e, el) {
    const unit = JSON.parse(el.dataset.unit);
    const newTitle = prompt('Module title:', unit.title);
    if (!newTitle || newTitle === unit.title) return;
    const data = await apiPost('save-unit.php', { course_id: COURSE_ID, id: unit.id, title: newTitle, order: unit.order, batch_id: BATCH_ID });
    if (data.success) location.reload();
    else showAlert('error', data.error || 'Error.');
});

// Delete module
delegate(document, '.delete-unit-btn', 'click', async function(e, el) {
    if (!confirm('Delete this module and all its slides?')) return;
    const data = await apiPost('delete-item.php', { type: 'unit', course_id: COURSE_ID, unit_id: el.dataset.unitId, batch_id: BATCH_ID });
    if (data.success) location.reload();
    else showAlert('error', data.error || 'Error.');
});

// Delete slide
delegate(document, '.delete-slide-btn', 'click', async function(e, el) {
    if (!confirm('Delete this slide?')) return;
    const data = await apiPost('delete-item.php', { type: 'slide', course_id: COURSE_ID, unit_id: el.dataset.unitId, slide_id: el.dataset.slideId, batch_id: BATCH_ID });
    if (data.success) location.reload();
    else showAlert('error', data.error || 'Error.');
});

// Drag-drop reorder: units
new Sortable(document.getElementById('units-container'), {
    handle: '.unit-card__header .slide-list__handle',
    animation: 150,
    ghostClass: 'sortable-ghost',
    chosenClass: 'sortable-chosen',
    onEnd: async function() {
        const order = [...document.querySelectorAll('#units-container .unit-card')].map(el => el.dataset.unitId);
        await apiPost('reorder.php', { course_id: COURSE_ID, type: 'units', order, batch_id: BATCH_ID });
    }
});

// Drag-drop reorder: slides within each unit
document.querySelectorAll('.slide-list[data-unit-id]').forEach(list => {
    new Sortable(list, {
        handle: '.slide-list__handle',
        animation: 150,
        ghostClass: 'sortable-ghost',
        onEnd: async function() {
            const unitId = list.dataset.unitId;
            const order  = [...list.querySelectorAll('.slide-list__item')].map(el => el.dataset.slideId);
            await apiPost('reorder.php', { course_id: COURSE_ID, type: 'slides', unit_id: unitId, order, batch_id: BATCH_ID });
        }
    });
});
</script>
</body>
</html>
