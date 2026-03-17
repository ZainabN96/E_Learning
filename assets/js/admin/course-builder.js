/**
 * Admin Course Builder — drag-drop reordering for units and slides.
 * Depends on: Sortable.js (assets/vendor/sortable.min.js), dom.js
 */
'use strict';

(function () {

    const COURSE_ID = window.COURSE_ID || '';

    // ── Reorder API call ──────────────────────────────────────────────────────

    async function saveOrder(type, order, unitId = '') {
        const body = { course_id: COURSE_ID, type, order };
        if (unitId) body.unit_id = unitId;

        try {
            const res = await fetch('/E_Learning/admin/api/reorder.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            });
            const data = await res.json();
            if (!data.success) {
                console.error('[CourseBuilder] Reorder failed:', data.error);
            }
        } catch (e) {
            console.error('[CourseBuilder] Reorder error:', e);
        }
    }

    // ── Unit reordering ───────────────────────────────────────────────────────

    function initUnitSortable() {
        const container = document.getElementById('units-container');
        if (!container || typeof Sortable === 'undefined') return;

        Sortable.create(container, {
            handle: '.unit-card__header .slide-list__handle',
            animation: 150,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            onEnd() {
                const order = Array.from(container.querySelectorAll('.unit-card'))
                    .map(el => el.dataset.unitId)
                    .filter(Boolean);
                saveOrder('units', order);
            }
        });
    }

    // ── Slide reordering (per unit) ───────────────────────────────────────────

    function initSlideSortables() {
        if (typeof Sortable === 'undefined') return;

        document.querySelectorAll('.slide-list[data-unit-id]').forEach(list => {
            const unitId = list.dataset.unitId;
            Sortable.create(list, {
                handle: '.slide-list__handle',
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                onEnd() {
                    const order = Array.from(list.querySelectorAll('.slide-list__item'))
                        .map(el => el.dataset.slideId)
                        .filter(Boolean);
                    saveOrder('slides', order, unitId);
                }
            });
        });
    }

    // ── Init ──────────────────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', function () {
        initUnitSortable();
        initSlideSortables();
    });

    // Re-init after dynamic DOM updates (e.g. new unit added)
    window.CourseBuilder = { reinit: function () { initSlideSortables(); } };

})();
