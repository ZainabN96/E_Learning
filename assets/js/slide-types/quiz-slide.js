/**
 * Quiz/Assessment Slide Renderer
 * Supports: single_choice, multiple_choice, true_false
 * Handles scoring, feedback, retry, and SCORM interaction recording.
 */
'use strict';

const QuizSlide = (() => {

    function render(container, slide, onComplete) {
        const c = slide.content || {};
        const questions = c.questions || [];
        const passingScore = c.passing_score ?? 80;
        const maxAttempts = c.max_attempts ?? 3;
        const showFeedback = c.show_feedback !== false;

        let attempts = 0;
        let quizStartTime = Date.now();

        function buildQuiz() {
            const instructions = c.instructions || (window.LANG?.quiz_instructions || 'Beantworten Sie alle Fragen.');
            let html = `
                <div class="quiz-slide" id="quiz-${slide.id}">
                    <h1 class="slide-title">${escapeHtml(slide.title)}</h1>
                    <p class="quiz-slide__instructions">${escapeHtml(instructions)}</p>
                    <form class="quiz-slide__form" id="quiz-form-${slide.id}" novalidate>`;

            questions.forEach((q, qi) => {
                html += `<div class="quiz-question" id="question-${q.id}" data-qi="${qi}">
                    <p class="quiz-question__text">${qi + 1}. ${escapeHtml(q.text)}</p>`;

                if (q.type === 'single_choice') {
                    html += renderChoices(q, 'radio', slide.id);
                } else if (q.type === 'multiple_choice') {
                    html += `<p class="quiz-question__hint">${window.LANG?.quiz_multiple_hint || '(Multiple answers possible)'}</p>`;
                    html += renderChoices(q, 'checkbox', slide.id);
                } else if (q.type === 'true_false') {
                    html += renderTrueFalse(q, slide.id);
                }

                html += `<div class="quiz-question__feedback" id="feedback-${q.id}" aria-live="polite"></div></div>`;
            });

            html += `</form>
                <div class="quiz-slide__controls">
                    <button type="button" class="btn btn--primary" id="quiz-submit-${slide.id}">
                        ${window.LANG?.quiz_submit || 'Antworten einreichen'}
                    </button>
                </div>
                <div class="quiz-slide__result" id="quiz-result-${slide.id}" aria-live="polite"></div>
            </div>`;

            container.innerHTML = html;

            on(container.querySelector(`#quiz-submit-${slide.id}`), 'click', () => submitQuiz());
        }

        function renderChoices(q, inputType, slideId) {
            const shuffled = c.shuffle_answers ? [...q.answers].sort(() => Math.random() - 0.5) : q.answers;
            let html = `<ul class="quiz-question__options">`;
            for (const a of shuffled) {
                html += `<li class="quiz-question__option">
                    <label>
                        <input type="${inputType}" name="q_${q.id}" value="${a.id}"
                               class="quiz-input">
                        <span>${escapeHtml(a.text)}</span>
                    </label>
                </li>`;
            }
            return html + '</ul>';
        }

        function renderTrueFalse(q, slideId) {
            return `<ul class="quiz-question__options quiz-question__options--tf">
                <li class="quiz-question__option">
                    <label>
                        <input type="radio" name="q_${q.id}" value="true" class="quiz-input">
                        <span>${window.LANG?.yes || 'Richtig'}</span>
                    </label>
                </li>
                <li class="quiz-question__option">
                    <label>
                        <input type="radio" name="q_${q.id}" value="false" class="quiz-input">
                        <span>${window.LANG?.no || 'Falsch'}</span>
                    </label>
                </li>
            </ul>`;
        }

        function submitQuiz() {
            attempts++;
            const form = container.querySelector(`#quiz-form-${slide.id}`);
            const elapsedMs = Date.now() - quizStartTime;

            let totalWeight = 0;
            let earnedWeight = 0;
            let allAnswered = true;
            const interactions = [];

            for (const q of questions) {
                totalWeight += q.weight ?? 1;
                const inputs = form.querySelectorAll(`[name="q_${q.id}"]`);
                const selected = Array.from(inputs).filter(i => i.checked).map(i => i.value);

                if (selected.length === 0) {
                    allAnswered = false;
                    continue;
                }

                let isCorrect = false;
                let response = selected.join('[,]');

                if (q.type === 'single_choice' || q.type === 'multiple_choice') {
                    const correctIds = q.answers.filter(a => a.correct).map(a => a.id).sort();
                    const selectedSorted = [...selected].sort();
                    isCorrect = JSON.stringify(correctIds) === JSON.stringify(selectedSorted);
                } else if (q.type === 'true_false') {
                    isCorrect = selected[0] === String(q.correct_answer);
                }

                if (isCorrect) earnedWeight += q.weight ?? 1;

                // SCORM interaction record
                interactions.push({
                    id: q.id,
                    type: q.type === 'true_false' ? 'true-false' : 'choice',
                    response: response,
                    result: isCorrect ? 'correct' : 'wrong',
                    latency: msToScormTime(elapsedMs / questions.length)
                });

                // Show per-question feedback
                if (showFeedback) {
                    const feedbackEl = container.querySelector(`#feedback-${q.id}`);
                    if (feedbackEl) {
                        let feedbackText = '';
                        if (q.type === 'true_false') {
                            feedbackText = isCorrect ? q.feedback_correct : q.feedback_incorrect;
                        } else {
                            const pickedAnswer = q.answers.find(a => a.id === selected[0]);
                            feedbackText = pickedAnswer?.feedback || (isCorrect
                                ? (window.LANG?.quiz_correct || 'Richtig!')
                                : (window.LANG?.quiz_incorrect || 'Leider falsch.'));
                        }
                        feedbackEl.className = `quiz-question__feedback quiz-question__feedback--${isCorrect ? 'correct' : 'incorrect'}`;
                        feedbackEl.textContent = feedbackText;
                    }
                }

                // Disable inputs after submission
                inputs.forEach(i => i.disabled = true);
            }

            if (!allAnswered) {
                alert(window.LANG?.quiz_select_answer || 'Please select at least one answer.');
                return;
            }

            const rawScore = totalWeight > 0 ? (earnedWeight / totalWeight) * 100 : 0;
            const passed = rawScore >= passingScore;
            const attemptsLeft = maxAttempts - attempts;

            // Show result
            const resultEl = container.querySelector(`#quiz-result-${slide.id}`);
            if (resultEl) {
                const scoreText = (window.LANG?.quiz_score || 'Your score: %d%%').replace('%d', Math.round(rawScore));
                resultEl.className = `quiz-slide__result quiz-slide__result--${passed ? 'passed' : 'failed'}`;
                resultEl.innerHTML = `
                    <p class="quiz-result__score">${scoreText}</p>
                    <p class="quiz-result__verdict">${passed
                        ? (window.LANG?.quiz_passed || 'Passed!')
                        : (window.LANG?.quiz_failed || 'Not passed.')}</p>
                    ${!passed && attemptsLeft > 0
                        ? `<button type="button" class="btn btn--secondary" id="quiz-retry-${slide.id}">
                            ${window.LANG?.quiz_retry || 'Erneut versuchen'}
                           </button>
                           <p>${(window.LANG?.quiz_attempts_left || 'Verbleibende Versuche: %d').replace('%d', attemptsLeft)}</p>`
                        : ''}
                    ${!passed && attemptsLeft <= 0
                        ? `<p>${window.LANG?.quiz_no_attempts || 'No further attempts possible.'}</p>`
                        : ''}`;

                const retryBtn = container.querySelector(`#quiz-retry-${slide.id}`);
                if (retryBtn) {
                    on(retryBtn, 'click', () => {
                        quizStartTime = Date.now();
                        buildQuiz();
                    });
                }
            }

            // Hide submit button
            const submitBtn = container.querySelector(`#quiz-submit-${slide.id}`);
            if (submitBtn) submitBtn.remove();

            // Notify player
            onComplete(slide.id, {
                status: passed ? 'passed' : 'failed',
                score: rawScore,
                interactions,
                isFinalQuiz: true
            });
        }

        function msToScormTime(ms) {
            const s = Math.round(ms / 1000);
            const h = Math.floor(s / 3600);
            const m = Math.floor((s % 3600) / 60);
            const sec = s % 60;
            return `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(sec).padStart(2,'0')}`;
        }

        function escapeHtml(str) {
            const d = document.createElement('div');
            d.textContent = str;
            return d.innerHTML;
        }

        buildQuiz();
    }

    return { render };
})();
