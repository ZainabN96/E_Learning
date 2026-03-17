/**
 * Minimal DOM utility helpers.
 */
const $ = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
const on = (el, ev, fn, opts) => el && el.addEventListener(ev, fn, opts);
const off = (el, ev, fn) => el && el.removeEventListener(ev, fn);

/**
 * Event delegation: listen on parent, match descendants by selector.
 */
const delegate = (parent, selector, event, fn) => {
    on(parent, event, (e) => {
        const target = e.target.closest(selector);
        if (target && parent.contains(target)) {
            fn(e, target);
        }
    });
};

/**
 * Create an element with optional attributes and children.
 * Usage: createElement('div', { class: 'foo' }, [child1, 'text'])
 */
function createElement(tag, attrs = {}, children = []) {
    const el = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs)) {
        if (k === 'dataset') {
            Object.assign(el.dataset, v);
        } else if (k.startsWith('on')) {
            el.addEventListener(k.slice(2).toLowerCase(), v);
        } else {
            el.setAttribute(k, v);
        }
    }
    for (const child of children) {
        if (typeof child === 'string') {
            el.appendChild(document.createTextNode(child));
        } else if (child instanceof Node) {
            el.appendChild(child);
        }
    }
    return el;
}

/**
 * Show a toast notification at top-right.
 */
function showToast(message, type = 'info', duration = 3000) {
    let container = $('#toast-container');
    if (!container) {
        container = createElement('div', { id: 'toast-container', 'aria-live': 'polite' });
        document.body.appendChild(container);
    }
    const toast = createElement('div', { class: `toast toast--${type}` }, [message]);
    container.appendChild(toast);
    setTimeout(() => toast.classList.add('toast--visible'), 10);
    setTimeout(() => {
        toast.classList.remove('toast--visible');
        setTimeout(() => toast.remove(), 300);
    }, duration);
}
