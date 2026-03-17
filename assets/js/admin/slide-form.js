/**
 * Admin Slide Form — Dynamic fields per slide type + save logic
 */
'use strict';

(function () {
    const typeSelect   = $('#slide-type');
    const fieldsTarget = $('#slide-type-fields');

    function renderFields(type, content) {
        content = content || {};
        switch (type) {
            case 'html':        renderHtmlFields(content);        break;
            case 'video':       renderVideoFields(content);       break;
            case 'quiz':        renderQuizFields(content);        break;
            case 'interactive': renderInteractiveFields(content); break;
        }
    }

    // ── HTML Fields ───────────────────────────────────────────────────────────
    function renderHtmlFields(c) {
        fieldsTarget.innerHTML = `
            <div class="form-group">
                <label>HTML Content</label>
                <textarea id="html-content" class="form-control form-control--textarea form-control--code"
                          style="min-height:200px">${escHtml(c.html || '')}</textarea>
                <p class="form-hint">HTML allowed: &lt;p&gt;, &lt;h2&gt;, &lt;ul&gt;, &lt;img&gt;, &lt;strong&gt;, etc.</p>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Background Color</label>
                    <input type="color" id="bg-color" class="form-control"
                           value="${escAttr(c.background_color || '#ffffff')}" style="height:42px">
                </div>
                <div class="form-group">
                    <label>Layout</label>
                    <select id="html-layout" class="form-control">
                        <option value="default"  ${c.layout==='default'  ? 'selected':''}>Default</option>
                        <option value="centered" ${c.layout==='centered' ? 'selected':''}>Centered</option>
                    </select>
                </div>
            </div>`;
    }

    function getHtmlContent() {
        return {
            html:             $('#html-content')?.value || '',
            background_color: $('#bg-color')?.value || '#ffffff',
            layout:           $('#html-layout')?.value || 'default',
        };
    }

    // ── Video Fields ──────────────────────────────────────────────────────────
    function renderVideoFields(c) {
        fieldsTarget.innerHTML = `
            <div class="form-group">
                <label>Video URL or file path</label>
                <input type="text" id="video-src" class="form-control"
                       value="${escAttr(c.src || '')}"
                       placeholder="media/course-id/videos/video.mp4">
                <p class="form-hint">Relative path from project root or absolute URL.</p>
            </div>
            <div class="form-group">
                <label>Poster image (optional)</label>
                <input type="text" id="video-poster" class="form-control"
                       value="${escAttr(c.poster || '')}"
                       placeholder="media/course-id/images/poster.jpg">
            </div>
            <div class="form-group">
                <label>Subtitle file .vtt (optional)</label>
                <input type="text" id="video-caption" class="form-control"
                       value="${escAttr(c.caption_file || '')}"
                       placeholder="media/course-id/videos/subtitles.vtt">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Completion threshold (%)</label>
                    <input type="number" id="video-threshold" class="form-control"
                           min="0" max="100" value="${Math.round((c.completion_threshold ?? 0.8) * 100)}">
                    <p class="form-hint">Percentage of video watched to mark as complete.</p>
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select id="video-audio-only" class="form-control">
                        <option value="0" ${!c.audio_only ? 'selected':''}>Video</option>
                        <option value="1" ${c.audio_only  ? 'selected':''}>Audio only</option>
                    </select>
                </div>
            </div>`;
    }

    function getVideoContent() {
        return {
            src:                  $('#video-src')?.value || '',
            poster:               $('#video-poster')?.value || '',
            caption_file:         $('#video-caption')?.value || '',
            completion_threshold: (parseInt($('#video-threshold')?.value || '80') / 100),
            audio_only:           $('#video-audio-only')?.value === '1',
        };
    }

    // ── Quiz Fields ───────────────────────────────────────────────────────────
    let quizQuestions = [];

    function renderQuizFields(c) {
        quizQuestions = c.questions ? JSON.parse(JSON.stringify(c.questions)) : [];
        fieldsTarget.innerHTML = `
            <div class="form-group">
                <label>Instructions</label>
                <input type="text" id="quiz-instructions" class="form-control"
                       value="${escAttr(c.instructions || '')}">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Passing score (%)</label>
                    <input type="number" id="quiz-passing" class="form-control"
                           min="0" max="100" value="${c.passing_score ?? 80}">
                </div>
                <div class="form-group">
                    <label>Max. attempts</label>
                    <input type="number" id="quiz-attempts" class="form-control"
                           min="1" max="99" value="${c.max_attempts ?? 3}">
                </div>
            </div>
            <div style="margin-bottom:0.75rem">
                <label class="form-group">Options</label>
                <label><input type="checkbox" id="quiz-shuffle" ${c.shuffle_answers ? 'checked':''}> Shuffle answers</label>
                &nbsp;&nbsp;
                <label><input type="checkbox" id="quiz-feedback" ${c.show_feedback !== false ? 'checked':''}> Show feedback</label>
            </div>
            <div id="question-builder" class="question-builder"></div>
            <button type="button" class="btn btn--secondary btn--sm" id="add-question-btn" style="margin-top:0.75rem">
                + Add Question
            </button>`;

        renderAllQuestions();
        on($('#add-question-btn'), 'click', addQuestion);
    }

    function renderAllQuestions() {
        const builder = $('#question-builder');
        if (!builder) return;
        builder.innerHTML = '';
        quizQuestions.forEach((q, qi) => renderQuestion(q, qi, builder));
    }

    function renderQuestion(q, qi, container) {
        const div = document.createElement('div');
        div.className = 'question-item';
        div.dataset.qi = qi;
        div.innerHTML = `
            <div class="question-item__header">
                <span>Question ${qi + 1}</span>
                <select class="form-control" style="width:auto;margin-left:0.5rem" onchange="updateQType(${qi}, this.value)">
                    <option value="single_choice"  ${q.type==='single_choice'  ? 'selected':''}>Single choice</option>
                    <option value="multiple_choice" ${q.type==='multiple_choice'? 'selected':''}>Multiple choice</option>
                    <option value="true_false"       ${q.type==='true_false'     ? 'selected':''}>True / False</option>
                </select>
                <input type="number" value="${q.weight ?? 1}" min="1" max="10"
                       style="width:60px;margin-left:0.5rem" class="form-control"
                       onchange="updateQWeight(${qi}, this.value)" placeholder="Weight">
                <button type="button" class="btn btn--sm btn--danger" style="margin-left:auto"
                        onclick="removeQuestion(${qi})">Delete</button>
            </div>
            <div class="form-group">
                <input type="text" class="form-control" placeholder="Question text..."
                       value="${escAttr(q.text || '')}"
                       oninput="quizQuestions[${qi}].text = this.value">
            </div>
            ${renderAnswerEditor(q, qi)}`;
        container.appendChild(div);
    }

    function renderAnswerEditor(q, qi) {
        if (q.type === 'true_false') {
            return `<div class="form-row">
                <div class="form-group">
                    <label>Correct answer:</label>
                    <select class="form-control" onchange="quizQuestions[${qi}].correct_answer = this.value === 'true'">
                        <option value="true"  ${q.correct_answer === true  ? 'selected':''}>True</option>
                        <option value="false" ${q.correct_answer === false ? 'selected':''}>False</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Feedback (Correct)</label>
                    <input type="text" class="form-control" value="${escAttr(q.feedback_correct||'')}"
                           oninput="quizQuestions[${qi}].feedback_correct = this.value">
                </div>
                <div class="form-group">
                    <label>Feedback (Incorrect)</label>
                    <input type="text" class="form-control" value="${escAttr(q.feedback_incorrect||'')}"
                           oninput="quizQuestions[${qi}].feedback_incorrect = this.value">
                </div>
            </div>`;
        }
        const answers = q.answers || [];
        let html = '<ul class="answer-list" id="answers-' + qi + '">';
        answers.forEach((a, ai) => {
            html += `<li class="answer-item">
                <input type="checkbox" title="Correct" ${a.correct ? 'checked':''}
                       onchange="quizQuestions[${qi}].answers[${ai}].correct = this.checked">
                <input type="text" class="form-control" value="${escAttr(a.text||'')}"
                       placeholder="Answer text" oninput="quizQuestions[${qi}].answers[${ai}].text = this.value">
                <input type="text" class="form-control" value="${escAttr(a.feedback||'')}"
                       placeholder="Feedback" oninput="quizQuestions[${qi}].answers[${ai}].feedback = this.value">
                <button type="button" class="btn btn--sm btn--danger"
                        onclick="removeAnswer(${qi}, ${ai})">✕</button>
            </li>`;
        });
        html += `</ul>
            <button type="button" class="btn btn--sm btn--secondary" style="margin-top:0.4rem"
                    onclick="addAnswer(${qi})">+ Answer</button>`;
        return html;
    }

    window.updateQType = (qi, type) => { quizQuestions[qi].type = type; renderAllQuestions(); };
    window.updateQWeight = (qi, val) => { quizQuestions[qi].weight = parseInt(val) || 1; };
    window.removeQuestion = (qi) => { quizQuestions.splice(qi, 1); renderAllQuestions(); };
    window.addAnswer = (qi) => {
        quizQuestions[qi].answers = quizQuestions[qi].answers || [];
        quizQuestions[qi].answers.push({ id: 'a-' + Date.now(), text: '', correct: false, feedback: '' });
        renderAllQuestions();
    };
    window.removeAnswer = (qi, ai) => {
        quizQuestions[qi].answers.splice(ai, 1);
        renderAllQuestions();
    };
    window.addQuestion = () => {
        quizQuestions.push({
            id: 'q-' + Date.now(),
            type: 'single_choice',
            text: '',
            weight: 1,
            answers: [
                { id: 'a-' + Date.now() + '-0', text: '', correct: true,  feedback: '' },
                { id: 'a-' + Date.now() + '-1', text: '', correct: false, feedback: '' },
            ]
        });
        renderAllQuestions();
    };

    function getQuizContent() {
        return {
            instructions:    $('#quiz-instructions')?.value || '',
            passing_score:   parseInt($('#quiz-passing')?.value || '80'),
            max_attempts:    parseInt($('#quiz-attempts')?.value || '3'),
            shuffle_answers: $('#quiz-shuffle')?.checked || false,
            show_feedback:   $('#quiz-feedback')?.checked !== false,
            questions:       quizQuestions,
        };
    }

    // ── Interactive Fields ────────────────────────────────────────────────────
    function renderInteractiveFields(c) {
        const subType = c.interaction_type || 'tabs';
        fieldsTarget.innerHTML = `
            <div class="form-group">
                <label>Interaction Type</label>
                <select id="interaction-type" class="form-control">
                    <option value="tabs"      ${subType==='tabs'      ? 'selected':''}>Tabs</option>
                    <option value="accordion" ${subType==='accordion' ? 'selected':''}>Accordion</option>
                    <option value="drag_drop" ${subType==='drag_drop' ? 'selected':''}>Drag &amp; Drop</option>
                    <option value="hotspot"   ${subType==='hotspot'   ? 'selected':''}>Image Hotspot</option>
                </select>
            </div>
            <div class="form-group">
                <label>Instructions</label>
                <input type="text" id="interaction-instructions" class="form-control"
                       value="${escAttr(c.instructions || '')}">
            </div>
            <div id="interaction-sub-fields"></div>`;

        renderInteractionSubFields(subType, c);
        on($('#interaction-type'), 'change', function() {
            renderInteractionSubFields(this.value, {});
        });
    }

    function renderInteractionSubFields(subType, c) {
        const target = $('#interaction-sub-fields');
        if (!target) return;
        if (subType === 'tabs' || subType === 'accordion') {
            const items = c.tabs || c.panels || [];
            let html = `<label style="display:block;margin-bottom:0.5rem;font-weight:600">Items</label>
                <div id="tab-items">`;
            items.forEach((item, i) => {
                html += tabItemHtml(item, i);
            });
            html += `</div>
                <button type="button" class="btn btn--secondary btn--sm" id="add-tab-item-btn">+ Add Item</button>`;
            target.innerHTML = html;
            on($('#add-tab-item-btn'), 'click', () => {
                const container = $('#tab-items');
                const i = container.children.length;
                const div = document.createElement('div');
                div.innerHTML = tabItemHtml({ id: 'tab-' + Date.now(), label: '', html: '' }, i);
                container.appendChild(div.firstElementChild);
            });
        } else if (subType === 'drag_drop') {
            target.innerHTML = `<p class="form-hint">Define drag items and drop zones as JSON arrays.</p>
                <div class="form-group">
                    <label>Drag Items (JSON)</label>
                    <textarea id="dd-items" class="form-control form-control--textarea form-control--code"
                              style="min-height:100px">${escHtml(JSON.stringify(c.drag_items||[], null, 2))}</textarea>
                    <p class="form-hint">Array: [{id, text, correct_zone}]</p>
                </div>
                <div class="form-group">
                    <label>Drop Zones (JSON)</label>
                    <textarea id="dd-zones" class="form-control form-control--textarea form-control--code"
                              style="min-height:80px">${escHtml(JSON.stringify(c.drop_zones||[], null, 2))}</textarea>
                    <p class="form-hint">Array: [{id, label}]</p>
                </div>`;
        } else if (subType === 'hotspot') {
            target.innerHTML = `<div class="form-group">
                    <label>Background image path</label>
                    <input type="text" id="hs-bg" class="form-control"
                           value="${escAttr(c.background_image||'')}"
                           placeholder="media/course-id/images/image.jpg">
                </div>
                <div class="form-group">
                    <label>Hotspots (JSON)</label>
                    <textarea id="hs-spots" class="form-control form-control--textarea form-control--code"
                              style="min-height:140px">${escHtml(JSON.stringify(c.hotspots||[], null, 2))}</textarea>
                    <p class="form-hint">Array: [{id, x_percent, y_percent, radius_px, label, feedback, correct}]</p>
                </div>`;
        }
    }

    function tabItemHtml(item, i) {
        return `<div class="question-item" style="margin-bottom:0.5rem">
            <div class="form-row">
                <div class="form-group">
                    <label>Label</label>
                    <input type="text" class="form-control tab-label"
                           value="${escAttr(item.label||'')}" data-idx="${i}">
                </div>
            </div>
            <div class="form-group">
                <label>HTML Content</label>
                <textarea class="form-control form-control--textarea tab-html" data-idx="${i}"
                          style="min-height:80px">${escHtml(item.html||'')}</textarea>
            </div>
        </div>`;
    }

    function getInteractiveContent() {
        const subType = $('#interaction-type')?.value || 'tabs';
        const base = {
            interaction_type: subType,
            instructions: $('#interaction-instructions')?.value || '',
        };
        if (subType === 'tabs' || subType === 'accordion') {
            const labels = $$('.tab-label');
            const htmls  = $$('.tab-html');
            const key    = subType === 'accordion' ? 'panels' : 'tabs';
            base[key] = labels.map((l, i) => ({
                id:    'item-' + (i + 1),
                label: l.value,
                html:  htmls[i]?.value || ''
            }));
        } else if (subType === 'drag_drop') {
            try { base.drag_items = JSON.parse($('#dd-items')?.value || '[]'); } catch(e) { base.drag_items = []; }
            try { base.drop_zones = JSON.parse($('#dd-zones')?.value || '[]'); } catch(e) { base.drop_zones = []; }
        } else if (subType === 'hotspot') {
            base.background_image = $('#hs-bg')?.value || '';
            try { base.hotspots = JSON.parse($('#hs-spots')?.value || '[]'); } catch(e) { base.hotspots = []; }
        }
        return base;
    }

    // ── Content Extractor ─────────────────────────────────────────────────────
    function getCurrentContent() {
        const type = $('#slide-type').value;
        switch (type) {
            case 'html':        return getHtmlContent();
            case 'video':       return getVideoContent();
            case 'quiz':        return getQuizContent();
            case 'interactive': return getInteractiveContent();
        }
        return {};
    }

    // ── Save Slide ────────────────────────────────────────────────────────────
    on($('#save-slide-btn'), 'click', async function () {
        const title = $('#slide-title')?.value?.trim();
        if (!title) { alert('Please enter a slide title.'); return; }

        const payload = {
            course_id: COURSE_ID,
            unit_id:   UNIT_ID,
            slide: {
                id:      $('#slide-id')?.value || '',
                title,
                type:    $('#slide-type')?.value || 'html',
                order:   parseInt($('#slide-order')?.value || '1'),
                content: getCurrentContent()
            }
        };

        try {
            const res = await fetch('/E_Learning/admin/api/save-slide.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.success) {
                window.location.href = '/E_Learning/admin/course-edit.php?id=' + COURSE_ID;
            } else {
                showAlert('error', data.error || LANG.error_occurred);
            }
        } catch (e) {
            showAlert('error', LANG.error_occurred);
        }
    });

    // ── Type Change ───────────────────────────────────────────────────────────
    on(typeSelect, 'change', function () {
        renderFields(this.value, {});
    });

    // ── Init ──────────────────────────────────────────────────────────────────
    renderFields(SLIDE_DATA.type || 'html', SLIDE_DATA.content || {});

    // ── Alert ─────────────────────────────────────────────────────────────────
    function showAlert(type, msg) {
        const container = $('#alert-container');
        if (!container) return;
        container.innerHTML = `<div class="alert alert--${type}">${escHtml(msg)}</div>`;
        setTimeout(() => container.innerHTML = '', 5000);
    }

    // ── Escape helpers ────────────────────────────────────────────────────────
    function escHtml(s) {
        const d = document.createElement('div'); d.textContent = s; return d.innerHTML;
    }
    function escAttr(s) {
        return String(s).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

})();
