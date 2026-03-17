/**
 * E-Learning Course Player — SCORM 1.2 State Machine
 * Depends on: pipwerks-scorm-api-wrapper.js, dom.js, slide-types/*.js
 */
'use strict';

const Player = (() => {

    let course = null;
    let allSlides = [];         // flat array [{unit, slide}, ...]
    let currentIndex = -1;
    let sessionStart = Date.now();
    let interactionCount = 0;
    let objectiveCount = 0;
    let progress = {};          // deserialized from cmi.suspend_data
    let isStandalone = false;

    // ── Initialization ──────────────────────────────────────────────────────

    function init(courseData) {
        course = courseData;
        allSlides = flattenSlides(course);
        isStandalone = !pipwerks.SCORM.API.isFound;

        // Init SCORM or mock
        pipwerks.debug.isActive = isStandalone;
        const connected = scorm.init();

        if (connected || isStandalone) {
            restoreProgress();
            renderSidebar();
            renderProgressBar();

            // Find start slide (bookmark or first)
            const bookmark = scorm.get('cmi.core.lesson_location');
            const startIndex = bookmark
                ? allSlides.findIndex(item => item.slide.id === bookmark)
                : 0;

            navigateTo(startIndex !== -1 ? startIndex : 0);
            bindNavButtons();

            if (isStandalone) {
                createDebugPanel();
            }
        } else {
            showError('Verbindung zum LMS konnte nicht hergestellt werden.');
        }

        // Save on page unload
        window.addEventListener('beforeunload', finish);
    }

    // ── Slide Navigation ─────────────────────────────────────────────────────

    function navigateTo(index) {
        if (index < 0 || index >= allSlides.length) return;

        currentIndex = index;
        const { unit, slide } = allSlides[index];

        // Update sidebar
        $$('.sidebar__slide-item').forEach(el => el.classList.remove('is-active'));
        const activeItem = $(`[data-slide-id="${slide.id}"]`);
        if (activeItem) {
            activeItem.classList.add('is-active');
            activeItem.scrollIntoView({ block: 'nearest' });
        }

        // Update nav buttons
        const prevBtn = $('#btn-prev');
        const nextBtn = $('#btn-next');
        if (prevBtn) prevBtn.disabled = index === 0;
        if (nextBtn) {
            nextBtn.disabled = !isSlideComplete(slide.id) && index < allSlides.length - 1;
            nextBtn.textContent = index === allSlides.length - 1
                ? (window.LANG?.nav_finish || 'Finish Course')
                : (window.LANG?.nav_next || 'Next');
        }

        // Set lesson location
        scorm.set('cmi.core.lesson_location', slide.id);
        saveProgress();

        // Render slide content
        renderSlide(slide, unit);
        updateProgressBar();
    }

    function navNext() {
        if (currentIndex < allSlides.length - 1) {
            navigateTo(currentIndex + 1);
        } else {
            finishCourse();
        }
    }

    function navPrev() {
        if (currentIndex > 0) navigateTo(currentIndex - 1);
    }

    function bindNavButtons() {
        const prevBtn = $('#btn-prev');
        const nextBtn = $('#btn-next');
        if (prevBtn) on(prevBtn, 'click', navPrev);
        if (nextBtn) on(nextBtn, 'click', navNext);

        // Sidebar click navigation
        delegate($('#sidebar'), '[data-slide-id]', 'click', (e, el) => {
            const idx = allSlides.findIndex(item => item.slide.id === el.dataset.slideId);
            if (idx !== -1) navigateTo(idx);
        });
    }

    // ── Slide Rendering ──────────────────────────────────────────────────────

    function renderSlide(slide, unit) {
        const container = $('#slide-content');
        if (!container) return;
        container.innerHTML = '';
        container.className = `slide-content slide-content--${slide.type}`;

        switch (slide.type) {
            case 'html':
                HtmlSlide.render(container, slide, onSlideComplete);
                break;
            case 'video':
                VideoSlide.render(container, slide, onSlideComplete);
                break;
            case 'quiz':
                QuizSlide.render(container, slide, onSlideComplete);
                break;
            case 'interactive':
                InteractiveSlide.render(container, slide, onSlideComplete);
                break;
            default:
                container.innerHTML = `<p>Unbekannter Folientyp: ${slide.type}</p>`;
        }
    }

    // ── Completion & Progress ────────────────────────────────────────────────

    function onSlideComplete(slideId, completionData = {}) {
        const existing = progress.slides?.[slideId] || {};

        // Don't downgrade a passed status
        if (existing.status === 'passed' && completionData.status !== 'passed') return;

        progress.slides = progress.slides || {};
        progress.slides[slideId] = {
            ...existing,
            ...completionData,
            completed_at: new Date().toISOString()
        };

        // Write SCORM objective
        const item = allSlides.find(i => i.slide.id === slideId);
        if (item?.slide.scorm_objective_id) {
            const n = objectiveCount++;
            scorm.set(`cmi.objectives.${n}.id`, item.slide.scorm_objective_id);
            scorm.set(`cmi.objectives.${n}.status`, completionData.status || 'completed');
            if (completionData.score !== undefined) {
                scorm.set(`cmi.objectives.${n}.score.raw`, String(Math.round(completionData.score)));
            }
        }

        // Record interaction if quiz data present
        if (completionData.interactions) {
            for (const ia of completionData.interactions) {
                const n = interactionCount++;
                scorm.set(`cmi.interactions.${n}.id`, ia.id);
                scorm.set(`cmi.interactions.${n}.type`, ia.type || 'choice');
                scorm.set(`cmi.interactions.${n}.student_response`, ia.response || '');
                scorm.set(`cmi.interactions.${n}.result`, ia.result || 'neutral');
                if (ia.latency) scorm.set(`cmi.interactions.${n}.latency`, ia.latency);
            }
        }

        // If it's the final quiz, set overall course score
        if (completionData.isFinalQuiz && completionData.score !== undefined) {
            progress.quiz_score = completionData.score;
            scorm.set('cmi.core.score.raw', String(Math.round(completionData.score)));
            scorm.set('cmi.core.score.min', '0');
            scorm.set('cmi.core.score.max', '100');
        }

        saveProgress();
        updateProgressBar();
        updateSidebarItemStatus(slideId, completionData.status || 'completed');

        // Enable Next button once current slide is complete
        const nextBtn = $('#btn-next');
        if (nextBtn && allSlides[currentIndex]?.slide.id === slideId) {
            nextBtn.disabled = false;
        }
    }

    function isSlideComplete(slideId) {
        const s = progress.slides?.[slideId];
        return s && (s.status === 'completed' || s.status === 'passed');
    }

    function calculateFinalStatus() {
        const masteryScore = course.scorm?.masteryScore ?? 80;
        let allComplete = true;
        let anyFailed = false;

        for (const { slide } of allSlides) {
            const s = progress.slides?.[slide.id];
            if (!s || (s.status !== 'completed' && s.status !== 'passed')) {
                allComplete = false;
            }
            if (s?.status === 'failed') anyFailed = true;
        }

        if (!allComplete) return 'incomplete';
        if (progress.quiz_score !== undefined && progress.quiz_score < masteryScore) return 'failed';
        if (anyFailed) return 'failed';
        return 'passed';
    }

    function finishCourse() {
        const status = calculateFinalStatus();
        scorm.status('set', status);
        scorm.set('cmi.core.session_time', getSessionTime());
        saveProgress();
        scorm.save();

        // Show completion screen
        const container = $('#slide-content');
        if (container) {
            const passed = status === 'passed';
            container.innerHTML = `
                <div class="completion-screen completion-screen--${passed ? 'passed' : 'failed'}">
                    <div class="completion-screen__icon">${passed ? '✓' : '✗'}</div>
                    <h2>${passed
                        ? (window.LANG?.complete_heading || 'Congratulations!')
                        : (window.LANG?.quiz_failed || 'Not passed.')
                    }</h2>
                    <p>${passed
                        ? (window.LANG?.complete_message || 'You have successfully completed the course.')
                        : (window.LANG?.incomplete_message || 'Please complete all sections.')
                    }</p>
                    ${progress.quiz_score !== undefined
                        ? `<p class="completion-screen__score">${(window.LANG?.quiz_score || 'Your score: %d%%').replace('%d', Math.round(progress.quiz_score))}</p>`
                        : ''}
                </div>`;
        }

        const nextBtn = $('#btn-next');
        if (nextBtn) nextBtn.disabled = true;
    }

    function finish() {
        if (scorm.isActive()) {
            scorm.set('cmi.core.session_time', getSessionTime());
            const status = calculateFinalStatus();
            if (status !== 'passed') scorm.status('set', status === 'incomplete' ? 'incomplete' : status);
            saveProgress();
            scorm.save();
            scorm.quit();
        }
    }

    // ── suspend_data Serialization ──────────────────────────────────────────

    function saveProgress() {
        const payload = {
            v: 1,
            slides: progress.slides || {},
            current_slide: allSlides[currentIndex]?.slide.id || '',
            quiz_score: progress.quiz_score
        };
        const json = JSON.stringify(payload);
        // SCORM 1.2 suspend_data max = 4096 chars
        if (json.length <= 4096) {
            scorm.set('cmi.suspend_data', json);
        }
        scorm.save();
    }

    function restoreProgress() {
        const raw = scorm.get('cmi.suspend_data');
        if (raw) {
            try {
                progress = JSON.parse(raw);
            } catch (e) {
                progress = {};
            }
        }
        progress.slides = progress.slides || {};
    }

    // ── UI Rendering ─────────────────────────────────────────────────────────

    function renderSidebar() {
        const sidebar = $('#sidebar');
        if (!sidebar) return;

        let html = `<nav class="sidebar__nav" aria-label="${window.LANG?.nav_menu || 'Course Menu'}">
            <div class="sidebar__course-title">${sanitizeText(course.metadata?.title || '')}</div>`;

        for (const unit of course.units) {
            html += `<div class="sidebar__unit">
                <div class="sidebar__unit-title">${sanitizeText(unit.title)}</div>
                <ul class="sidebar__slide-list">`;

            for (const slide of unit.slides) {
                const isComplete = isSlideComplete(slide.id);
                html += `<li class="sidebar__slide-item ${isComplete ? 'is-complete' : ''}"
                             data-slide-id="${slide.id}"
                             role="button"
                             tabindex="0"
                             aria-label="${sanitizeText(slide.title)}">
                    <span class="sidebar__slide-status" aria-hidden="true"></span>
                    <span class="sidebar__slide-name">${sanitizeText(slide.title)}</span>
                </li>`;
            }
            html += `</ul></div>`;
        }
        html += '</nav>';
        sidebar.innerHTML = html;

        // Keyboard navigation on sidebar items
        delegate(sidebar, '[data-slide-id]', 'keydown', (e, el) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                el.click();
            }
        });
    }

    function renderProgressBar() {
        const bar = $('#progress-bar-fill');
        if (bar) bar.style.width = '0%';
        updateProgressBar();
    }

    function updateProgressBar() {
        const total = allSlides.length;
        if (!total) return;
        const done = allSlides.filter(item => isSlideComplete(item.slide.id)).length;
        const pct = Math.round((done / total) * 100);

        const fill = $('#progress-bar-fill');
        if (fill) fill.style.width = pct + '%';

        const label = $('#progress-label');
        if (label) {
            label.textContent = `${done} ${window.LANG?.progress_of || 'von'} ${total} ${window.LANG?.progress_slides || 'Folien'}`;
        }
    }

    function updateSidebarItemStatus(slideId, status) {
        const item = $(`[data-slide-id="${slideId}"]`);
        if (!item) return;
        item.classList.remove('is-complete', 'is-passed', 'is-failed');
        if (status === 'completed' || status === 'passed') item.classList.add('is-complete');
        if (status === 'passed') item.classList.add('is-passed');
        if (status === 'failed') item.classList.add('is-failed');
    }

    function createDebugPanel() {
        const panel = document.createElement('div');
        panel.id = 'scorm-debug-panel';
        panel.innerHTML = `
            <div id="scorm-debug-header">
                <strong>SCORM Debug</strong>
                <button id="scorm-debug-clear" title="Clear">✕</button>
                <button id="scorm-debug-toggle" title="Minimize">−</button>
            </div>
            <div id="scorm-debug-log"></div>`;
        document.body.appendChild(panel);

        on($('#scorm-debug-clear'), 'click', () => { $('#scorm-debug-log').innerHTML = ''; });
        on($('#scorm-debug-toggle'), 'click', () => panel.classList.toggle('minimized'));

        window.ScormDebugPanel = {
            log: (action, key, val) => {
                const logEl = $('#scorm-debug-log');
                if (!logEl) return;
                const colors = { SET: '#4fc3f7', GET: '#aed581', INIT: '#ffb74d', FINISH: '#ef9a9a', COMMIT: '#ce93d8' };
                const color = colors[action] || '#fff';
                const line = document.createElement('div');
                line.innerHTML = `<span style="color:${color}">[${action}]</span> <span style="color:#ddd">${key}</span>${val !== '' ? ` = <span style="color:#fff9c4">${val}</span>` : ''}`;
                logEl.appendChild(line);
                logEl.scrollTop = logEl.scrollHeight;
            }
        };
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    function flattenSlides(course) {
        const flat = [];
        for (const unit of course.units || []) {
            const sorted = [...(unit.slides || [])].sort((a, b) => (a.order || 0) - (b.order || 0));
            for (const slide of sorted) {
                flat.push({ unit, slide });
            }
        }
        return flat;
    }

    function getSessionTime() {
        const elapsed = Math.round((Date.now() - sessionStart) / 1000);
        const h = Math.floor(elapsed / 3600);
        const m = Math.floor((elapsed % 3600) / 60);
        const s = elapsed % 60;
        return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    }

    function sanitizeText(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function showError(msg) {
        const container = $('#slide-content');
        if (container) container.innerHTML = `<div class="player-error">${msg}</div>`;
    }

    // ── Public API ──────────────────────────────────────────────────────────

    return { init, navigateTo, onSlideComplete, isSlideComplete, finish };

})();
