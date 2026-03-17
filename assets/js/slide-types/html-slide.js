/**
 * HTML/Text Slide Renderer
 * Marks complete after COMPLETION_DELAY seconds of viewing.
 */
'use strict';

const HtmlSlide = (() => {
    const COMPLETION_DELAY = 3000; // ms

    function render(container, slide, onComplete) {
        const content = slide.content || {};
        const html = content.html || '<p>Kein Inhalt vorhanden.</p>';
        const layout = content.layout || 'default';

        container.innerHTML = `
            <div class="html-slide html-slide--${layout}">
                <h1 class="slide-title">${escapeHtml(slide.title)}</h1>
                <div class="html-slide__body">${html}</div>
            </div>`;

        // Apply optional background color
        if (content.background_color) {
            container.style.backgroundColor = content.background_color;
        }

        // Auto-complete after delay
        const timer = setTimeout(() => {
            onComplete(slide.id, { status: 'completed' });
        }, COMPLETION_DELAY);

        // Clean up timer if slide is navigated away from
        container.dataset.cleanupTimer = timer;
    }

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    return { render };
})();
