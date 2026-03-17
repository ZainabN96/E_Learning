<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/Auth.php';
require_once dirname(__DIR__) . '/core/CourseRepository.php';

Auth::requireLogin();
$lang = load_lang();
$langCode = get_lang_code();
$repo = new CourseRepository();

$courseId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['id'] ?? '');
$course   = $courseId ? $repo->getCourse($courseId) : [];
$isNew    = empty($course);

if ($isNew) {
    $course = [
        'id'       => '',
        'metadata' => ['title' => '', 'description' => '', 'language' => 'de', 'duration_minutes' => 30, 'author' => ''],
        'scorm'    => ['version' => '1.2', 'masteryScore' => 80],
        'units'    => [],
    ];
}
?>
<!DOCTYPE html>
<html lang="<?= $langCode ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isNew ? $lang['admin_new_course'] : $lang['admin_edit_course'] ?> — <?= $lang['admin_title'] ?></title>
    <link rel="stylesheet" href="/E_Learning/assets/css/main.css">
    <link rel="stylesheet" href="/E_Learning/assets/css/admin.css">
</head>
<body class="admin-body">

<header class="admin-topbar">
    <span class="admin-topbar__brand"><?= $lang['admin_title'] ?></span>
    <div class="admin-topbar__actions">
        <a href="/E_Learning/admin/"><?= $lang['admin_dashboard'] ?></a>
        <a href="/E_Learning/admin/reports.php">Reports</a>
        <a href="/E_Learning/admin/logout.php"><?= $lang['admin_logout'] ?></a>
    </div>
</header>

<div class="admin-container">
    <div class="admin-page-header">
        <h1><?= $isNew ? $lang['admin_new_course'] : $lang['admin_edit_course'] ?></h1>
        <a href="/E_Learning/admin/" class="btn btn--secondary"><?= $lang['admin_cancel'] ?></a>
    </div>

    <div id="alert-container"></div>

    <div class="card">
        <div class="card__header">
            <span class="card__title">Course Settings</span>
        </div>
        <form id="course-form">
            <input type="hidden" name="id" value="<?= sanitize_string($course['id']) ?>">

            <div class="form-row">
                <div class="form-group">
                    <label for="title"><?= $lang['admin_course_title'] ?> *</label>
                    <input type="text" id="title" name="metadata[title]"
                           class="form-control"
                           value="<?= sanitize_string($course['metadata']['title']) ?>"
                           required>
                </div>
                <div class="form-group">
                    <label for="author">Autor</label>
                    <input type="text" id="author" name="metadata[author]"
                           class="form-control"
                           value="<?= sanitize_string($course['metadata']['author'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="description"><?= $lang['admin_course_desc'] ?></label>
                <textarea id="description" name="metadata[description]"
                          class="form-control form-control--textarea"><?= sanitize_string($course['metadata']['description']) ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="duration"><?= $lang['admin_course_duration'] ?></label>
                    <input type="number" id="duration" name="metadata[duration_minutes]"
                           class="form-control" min="1" max="600"
                           value="<?= (int)($course['metadata']['duration_minutes'] ?? 30) ?>">
                </div>
                <div class="form-group">
                    <label for="mastery"><?= $lang['admin_mastery_score'] ?></label>
                    <input type="number" id="mastery" name="scorm[masteryScore]"
                           class="form-control" min="0" max="100"
                           value="<?= (int)($course['scorm']['masteryScore'] ?? 80) ?>">
                    <p class="form-hint">Minimum percentage required to pass.</p>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" id="save-course-btn" class="btn btn--primary"><?= $lang['admin_save'] ?></button>
                <a href="/E_Learning/admin/" class="btn btn--secondary"><?= $lang['admin_cancel'] ?></a>
            </div>
        </form>
    </div>

    <?php if (!$isNew): ?>
    <!-- Unit & Slide Builder -->
    <div class="card" style="margin-top:1.5rem">
        <div class="card__header">
            <span class="card__title">Course Structure</span>
            <button type="button" class="btn btn--primary btn--sm" id="add-unit-btn">
                + <?= $lang['admin_new_unit'] ?>
            </button>
        </div>
        <div id="units-container">
            <?php foreach ($course['units'] as $unit): ?>
            <div class="unit-card" data-unit-id="<?= sanitize_string($unit['id']) ?>">
                <div class="unit-card__header">
                    <span class="slide-list__handle">⠿</span>
                    <span class="unit-card__title"><?= sanitize_string($unit['title']) ?></span>
                    <div style="margin-left:auto;display:flex;gap:0.4rem">
                        <button type="button" class="btn btn--sm btn--secondary edit-unit-btn"
                                data-unit='<?= json_encode(['id'=>$unit['id'],'title'=>$unit['title'],'order'=>$unit['order']], JSON_HEX_APOS) ?>'>
                            Rename
                        </button>
                        <button type="button" class="btn btn--sm btn--danger delete-unit-btn"
                                data-unit-id="<?= sanitize_string($unit['id']) ?>">
                            <?= $lang['admin_delete_course'] ?>
                        </button>
                    </div>
                </div>
                <div class="unit-card__body">
                    <ul class="slide-list" data-unit-id="<?= sanitize_string($unit['id']) ?>">
                        <?php foreach ($unit['slides'] as $slide): ?>
                        <li class="slide-list__item" data-slide-id="<?= sanitize_string($slide['id']) ?>">
                            <span class="slide-list__handle" title="<?= $lang['admin_drag_reorder'] ?>">⠿</span>
                            <span class="slide-list__type-badge slide-list__type-badge--<?= sanitize_string($slide['type']) ?>">
                                <?= sanitize_string($slide['type']) ?>
                            </span>
                            <span class="slide-list__title"><?= sanitize_string($slide['title']) ?></span>
                            <div class="slide-list__actions">
                                <a href="/E_Learning/admin/slide-edit.php?course=<?= urlencode($courseId) ?>&unit=<?= urlencode($unit['id']) ?>&slide=<?= urlencode($slide['id']) ?>"
                                   class="btn btn--sm btn--secondary"><?= $lang['admin_edit_slide'] ?></a>
                                <button type="button" class="btn btn--sm btn--danger delete-slide-btn"
                                        data-slide-id="<?= sanitize_string($slide['id']) ?>"
                                        data-unit-id="<?= sanitize_string($unit['id']) ?>">
                                    <?= $lang['admin_delete_slide'] ?>
                                </button>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <div style="margin-top:0.75rem">
                        <a href="/E_Learning/admin/slide-edit.php?course=<?= urlencode($courseId) ?>&unit=<?= urlencode($unit['id']) ?>"
                           class="btn btn--sm btn--secondary">+ <?= $lang['admin_new_slide'] ?></a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="/E_Learning/assets/js/utils/dom.js"></script>
<script src="/E_Learning/assets/vendor/sortable.min.js"></script>
<script src="/E_Learning/assets/js/admin/course-builder.js"></script>
<script>
const COURSE_ID = '<?= addslashes($courseId) ?>';
const LANG = <?= json_encode($lang, JSON_UNESCAPED_UNICODE) ?>;

// Save course metadata
on($('#save-course-btn'), 'click', async function() {
    const form = document.getElementById('course-form');
    const fd = new FormData(form);
    const body = {
        id: fd.get('id') || '',
        metadata: {
            title:            fd.get('metadata[title]'),
            description:      fd.get('metadata[description]'),
            author:           fd.get('metadata[author]'),
            language:         'de',
            duration_minutes: parseInt(fd.get('metadata[duration_minutes]')) || 30,
        },
        scorm: {
            version:       '1.2',
            masteryScore:  parseInt(fd.get('scorm[masteryScore]')) || 80,
        }
    };

    try {
        const res = await fetch('/E_Learning/admin/api/save-course.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(body)
        });
        const data = await res.json();
        if (data.success) {
            if (!COURSE_ID && data.id) {
                window.location.href = '/E_Learning/admin/course-edit.php?id=' + data.id + '&msg=course_saved';
            } else {
                showAlert('success', LANG.saved);
            }
        } else {
            showAlert('error', data.error || LANG.error_occurred);
        }
    } catch(e) {
        showAlert('error', LANG.error_occurred);
    }
});

// Add unit
on($('#add-unit-btn'), 'click', async function() {
    const title = prompt(LANG.admin_unit_title + ':');
    if (!title) return;
    const res = await fetch('/E_Learning/admin/api/save-unit.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ course_id: COURSE_ID, title, order: 999 })
    });
    const data = await res.json();
    if (data.success) location.reload();
    else showAlert('error', data.error || LANG.error_occurred);
});

// Edit unit title
delegate(document, '.edit-unit-btn', 'click', async function(e, el) {
    const unit = JSON.parse(el.dataset.unit);
    const newTitle = prompt(LANG.admin_unit_title + ':', unit.title);
    if (!newTitle || newTitle === unit.title) return;
    const res = await fetch('/E_Learning/admin/api/save-unit.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ course_id: COURSE_ID, id: unit.id, title: newTitle, order: unit.order })
    });
    const data = await res.json();
    if (data.success) location.reload();
    else showAlert('error', data.error || LANG.error_occurred);
});

// Delete unit
delegate(document, '.delete-unit-btn', 'click', async function(e, el) {
    if (!confirm('Delete section?')) return;
    const res = await fetch('/E_Learning/admin/api/delete-item.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ type: 'unit', course_id: COURSE_ID, unit_id: el.dataset.unitId })
    });
    const data = await res.json();
    if (data.success) location.reload();
    else showAlert('error', data.error || LANG.error_occurred);
});

// Delete slide
delegate(document, '.delete-slide-btn', 'click', async function(e, el) {
    if (!confirm('Delete slide?')) return;
    const res = await fetch('/E_Learning/admin/api/delete-item.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ type: 'slide', course_id: COURSE_ID, unit_id: el.dataset.unitId, slide_id: el.dataset.slideId })
    });
    const data = await res.json();
    if (data.success) location.reload();
    else showAlert('error', data.error || LANG.error_occurred);
});

function showAlert(type, msg) {
    const container = $('#alert-container');
    const el = document.createElement('div');
    el.className = 'alert alert--' + type;
    el.textContent = msg;
    container.innerHTML = '';
    container.appendChild(el);
    setTimeout(() => el.remove(), 4000);
}
</script>
</body>
</html>
