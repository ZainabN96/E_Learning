/**
 * Interactive Slide Renderer — dispatcher + sub-renderers
 * Types: tabs, accordion, drag_drop, hotspot
 */
'use strict';

const InteractiveSlide = (() => {

    function render(container, slide, onComplete) {
        const c = slide.content || {};
        switch (c.interaction_type) {
            case 'tabs':       renderTabs(container, slide, onComplete);     break;
            case 'accordion':  renderAccordion(container, slide, onComplete); break;
            case 'drag_drop':  renderDragDrop(container, slide, onComplete);  break;
            case 'hotspot':    renderHotspot(container, slide, onComplete);   break;
            default:
                container.innerHTML = `<p>Unknown interaction type: ${c.interaction_type}</p>`;
        }
    }

    // ── Tabs ─────────────────────────────────────────────────────────────────

    function renderTabs(container, slide, onComplete) {
        const c = slide.content;
        const tabs = c.tabs || [];
        const visitedTabs = new Set();

        let html = `
            <div class="interactive-slide tabs-slide">
                <h1 class="slide-title">${escapeHtml(slide.title)}</h1>
                <div class="tabs" role="tablist" aria-label="${escapeHtml(slide.title)}">`;

        tabs.forEach((tab, i) => {
            html += `<button class="tabs__tab ${i === 0 ? 'is-active' : ''}"
                             role="tab"
                             aria-selected="${i === 0}"
                             aria-controls="tabpanel-${tab.id}"
                             id="tab-btn-${tab.id}"
                             data-tab-id="${tab.id}">
                        ${escapeHtml(tab.label)}
                     </button>`;
        });
        html += `</div><div class="tabs__panels">`;

        tabs.forEach((tab, i) => {
            html += `<div class="tabs__panel ${i === 0 ? 'is-active' : ''}"
                          role="tabpanel"
                          id="tabpanel-${tab.id}"
                          aria-labelledby="tab-btn-${tab.id}">
                        ${tab.html || ''}
                     </div>`;
        });
        html += `</div>
            <p class="interactive-slide__hint" id="tabs-hint-${slide.id}">
                ${window.LANG?.tabs_visit_all || 'Please open all tabs.'}
            </p>
        </div>`;
        container.innerHTML = html;

        // Mark first tab visited
        if (tabs[0]) visitedTabs.add(tabs[0].id);
        checkTabsComplete();

        delegate(container, '[data-tab-id]', 'click', (e, el) => {
            const tabId = el.dataset.tabId;
            container.querySelectorAll('.tabs__tab').forEach(t => {
                t.classList.remove('is-active');
                t.setAttribute('aria-selected', 'false');
            });
            container.querySelectorAll('.tabs__panel').forEach(p => p.classList.remove('is-active'));
            el.classList.add('is-active');
            el.setAttribute('aria-selected', 'true');
            container.querySelector(`#tabpanel-${tabId}`)?.classList.add('is-active');
            visitedTabs.add(tabId);
            checkTabsComplete();
        });

        function checkTabsComplete() {
            if (visitedTabs.size >= tabs.length) {
                container.querySelector(`#tabs-hint-${slide.id}`)?.remove();
                onComplete(slide.id, { status: 'completed', tabs_visited: [...visitedTabs] });
            }
        }
    }

    // ── Accordion ────────────────────────────────────────────────────────────

    function renderAccordion(container, slide, onComplete) {
        const c = slide.content;
        const panels = c.panels || c.tabs || [];
        const openedPanels = new Set();

        let html = `
            <div class="interactive-slide accordion-slide">
                <h1 class="slide-title">${escapeHtml(slide.title)}</h1>
                <div class="accordion">`;

        panels.forEach((panel, i) => {
            html += `<details class="accordion__item" data-panel-id="${panel.id}">
                <summary class="accordion__summary">${escapeHtml(panel.label)}</summary>
                <div class="accordion__body">${panel.html || ''}</div>
            </details>`;
        });
        html += `</div>
            <p class="interactive-slide__hint" id="accordion-hint-${slide.id}">
                ${window.LANG?.accordion_open_all || 'Please open all sections.'}
            </p>
        </div>`;
        container.innerHTML = html;

        container.querySelectorAll('.accordion__item').forEach(details => {
            details.addEventListener('toggle', () => {
                if (details.open) {
                    openedPanels.add(details.dataset.panelId);
                    if (openedPanels.size >= panels.length) {
                        container.querySelector(`#accordion-hint-${slide.id}`)?.remove();
                        onComplete(slide.id, { status: 'completed' });
                    }
                }
            });
        });
    }

    // ── Drag & Drop ──────────────────────────────────────────────────────────

    function renderDragDrop(container, slide, onComplete) {
        const c = slide.content;
        const items = c.drag_items || [];
        const zones = c.drop_zones || [];
        let completed = false;

        const shuffledItems = [...items].sort(() => Math.random() - 0.5);

        let html = `
            <div class="interactive-slide drag-drop-slide">
                <h1 class="slide-title">${escapeHtml(slide.title)}</h1>
                <p class="interactive-slide__instructions">
                    ${escapeHtml(c.instructions || window.LANG?.drag_instructions || 'Ziehen Sie die Elemente in die richtige Kategorie.')}
                </p>
                <div class="drag-drop__source" id="drag-source-${slide.id}">`;

        for (const item of shuffledItems) {
            html += `<div class="drag-item" draggable="true" data-item-id="${item.id}"
                          role="button" tabindex="0">
                        ${escapeHtml(item.text)}
                     </div>`;
        }
        html += `</div><div class="drag-drop__zones">`;

        for (const zone of zones) {
            html += `<div class="drop-zone" data-zone-id="${zone.id}" aria-label="${escapeHtml(zone.label)}">
                        <div class="drop-zone__label">${escapeHtml(zone.label)}</div>
                        <div class="drop-zone__items" data-drop-target="${zone.id}"></div>
                     </div>`;
        }
        html += `</div>
            <div class="drag-drop__controls">
                <button type="button" class="btn btn--secondary" id="drag-reset-${slide.id}">
                    ${window.LANG?.drag_drop_reset || 'Reset'}
                </button>
                <button type="button" class="btn btn--primary" id="drag-check-${slide.id}">
                    ${window.LANG?.drag_drop_check || 'Check'}
                </button>
            </div>
            <div class="drag-drop__result" id="drag-result-${slide.id}" aria-live="polite"></div>
        </div>`;
        container.innerHTML = html;

        // Drag-and-drop wiring
        let draggedId = null;

        container.querySelectorAll('.drag-item').forEach(item => {
            item.addEventListener('dragstart', e => {
                draggedId = item.dataset.itemId;
                item.classList.add('is-dragging');
                e.dataTransfer.effectAllowed = 'move';
            });
            item.addEventListener('dragend', () => item.classList.remove('is-dragging'));
        });

        container.querySelectorAll('[data-drop-target]').forEach(zone => {
            zone.addEventListener('dragover', e => {
                e.preventDefault();
                zone.classList.add('is-over');
                e.dataTransfer.dropEffect = 'move';
            });
            zone.addEventListener('dragleave', () => zone.classList.remove('is-over'));
            zone.addEventListener('drop', e => {
                e.preventDefault();
                zone.classList.remove('is-over');
                if (!draggedId) return;
                const itemEl = container.querySelector(`[data-item-id="${draggedId}"]`);
                if (itemEl) zone.appendChild(itemEl);
                draggedId = null;
            });
        });

        // Check button
        on(container.querySelector(`#drag-check-${slide.id}`), 'click', () => {
            if (completed) return;
            let allCorrect = true;
            const interactions = [];

            container.querySelectorAll('[data-drop-target]').forEach(zoneEl => {
                const zoneId = zoneEl.dataset.dropTarget;
                zoneEl.querySelectorAll('.drag-item').forEach(itemEl => {
                    const itemId = itemEl.dataset.itemId;
                    const itemDef = items.find(i => i.id === itemId);
                    const isCorrect = itemDef?.correct_zone === zoneId;
                    if (!isCorrect) allCorrect = false;
                    itemEl.classList.toggle('is-correct', isCorrect);
                    itemEl.classList.toggle('is-incorrect', !isCorrect);
                    interactions.push({ id: itemId, type: 'matching', response: zoneId, result: isCorrect ? 'correct' : 'wrong' });
                });
            });

            const resultEl = container.querySelector(`#drag-result-${slide.id}`);
            if (resultEl) {
                resultEl.textContent = allCorrect
                    ? (window.LANG?.drag_drop_correct || 'Alle Elemente richtig zugeordnet!')
                    : (window.LANG?.drag_drop_incorrect || 'Einige Elemente sind falsch. Versuchen Sie es erneut.');
                resultEl.className = `drag-drop__result drag-drop__result--${allCorrect ? 'correct' : 'incorrect'}`;
            }

            if (allCorrect) {
                completed = true;
                onComplete(slide.id, { status: 'passed', interactions });
                container.querySelector(`#drag-check-${slide.id}`)?.remove();
                container.querySelector(`#drag-reset-${slide.id}`)?.remove();
            }
        });

        // Reset button
        on(container.querySelector(`#drag-reset-${slide.id}`), 'click', () => {
            const source = container.querySelector(`#drag-source-${slide.id}`);
            container.querySelectorAll('.drag-item').forEach(item => {
                item.classList.remove('is-correct', 'is-incorrect');
                source.appendChild(item);
            });
            const resultEl = container.querySelector(`#drag-result-${slide.id}`);
            if (resultEl) resultEl.textContent = '';
        });
    }

    // ── Hotspot ──────────────────────────────────────────────────────────────

    function renderHotspot(container, slide, onComplete) {
        const c = slide.content;
        const hotspots = c.hotspots || [];
        const correctSpots = hotspots.filter(h => h.correct);
        const foundSpots = new Set();
        let completed = false;

        let html = `
            <div class="interactive-slide hotspot-slide">
                <h1 class="slide-title">${escapeHtml(slide.title)}</h1>
                <p class="interactive-slide__instructions">
                    ${escapeHtml(c.instructions || window.LANG?.hotspot_instructions || 'Click on all marked areas in the image.')}
                </p>
                <div class="hotspot-slide__wrapper" id="hotspot-wrapper-${slide.id}">
                    <img src="${c.background_image || ''}"
                         alt="${escapeHtml(slide.title)}"
                         class="hotspot-slide__image"
                         id="hotspot-img-${slide.id}">
                    <svg class="hotspot-slide__svg" id="hotspot-svg-${slide.id}"
                         viewBox="0 0 100 100" preserveAspectRatio="none"
                         style="position:absolute;top:0;left:0;width:100%;height:100%">`;

        for (const hs of hotspots) {
            html += `<circle class="hotspot-circle ${hs.correct ? 'hotspot-circle--correct' : 'hotspot-circle--incorrect'}"
                             cx="${hs.x_percent}" cy="${hs.y_percent}" r="3"
                             data-hs-id="${hs.id}"
                             role="button"
                             tabindex="0"
                             aria-label="${escapeHtml(hs.label)}">
                         <title>${escapeHtml(hs.label)}</title>
                     </circle>`;
        }
        html += `</svg>
                </div>
                <div class="hotspot-slide__feedback" id="hotspot-feedback-${slide.id}" aria-live="polite"></div>
                <div class="hotspot-slide__counter">
                    <span id="hotspot-count-${slide.id}">0</span> / ${correctSpots.length}
                    ${window.LANG?.hotspot_found || 'Gefunden'}
                </div>
            </div>`;
        container.innerHTML = html;

        const feedbackEl = container.querySelector(`#hotspot-feedback-${slide.id}`);
        const countEl = container.querySelector(`#hotspot-count-${slide.id}`);

        delegate(container, '[data-hs-id]', 'click', (e, el) => {
            if (completed) return;
            const hsId = el.dataset.hsId;
            const hsDef = hotspots.find(h => h.id === hsId);
            if (!hsDef) return;

            el.classList.add('is-clicked');

            if (feedbackEl) feedbackEl.textContent = hsDef.feedback || '';

            if (hsDef.correct && !foundSpots.has(hsId)) {
                foundSpots.add(hsId);
                el.classList.add('is-found');
                if (countEl) countEl.textContent = foundSpots.size;

                if (foundSpots.size >= correctSpots.length) {
                    completed = true;
                    if (feedbackEl) feedbackEl.textContent = window.LANG?.hotspot_all_found || 'Alle Bereiche gefunden!';
                    onComplete(slide.id, {
                        status: 'completed',
                        interactions: [{ id: slide.id, type: 'choice', response: [...foundSpots].join('[,]'), result: 'correct' }]
                    });
                }
            }
        });

        // Keyboard support
        delegate(container, '[data-hs-id]', 'keydown', (e, el) => {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); el.click(); }
        });
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    function escapeHtml(str) {
        if (!str) return '';
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    return { render };
})();
