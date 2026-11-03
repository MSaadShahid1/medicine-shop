/* Static demo (GitHub Pages): this copy has no PHP or database behind it.
   Links to exported pages work, product search runs on a bundled list, and anything that would
   change data shows a short notice instead. Generated pages load demo/static-demo-data.js first. */
(() => {
    'use strict';
    const demo = window.__staticDemo || { pages: [], products: [], repository: '' };
    const pages = new Set(demo.pages);
    const KEEP = ['id', 'bill_id', 'edit_id'];   // same order as tools/build_static_demo.php
    const READ_ONLY = 'This is a read-only demo, so nothing is saved. Run the app locally to make changes.';

    // Same naming rule as the exporter: page.php?id=5 -> page-id-5.html
    function staticName(url) {
        const parsed = new URL(url, location.href);
        const match = parsed.pathname.match(/([a-z_]+)\.php$/i);
        if (!match) return null;
        let name = match[1];
        KEEP.forEach(key => {
            const value = parsed.searchParams.get(key);
            if (value && /^\d+$/.test(value)) name += '-' + key + '-' + value;
        });
        return name + '.html';
    }

    const toastEl = document.createElement('div');
    toastEl.className = 'sd-toast';
    toastEl.setAttribute('role', 'status');
    document.body.appendChild(toastEl);
    let toastTimer = 0;
    function toast(message) {
        toastEl.textContent = message;
        toastEl.classList.add('is-visible');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toastEl.classList.remove('is-visible'), 3400);
    }

    const badge = document.createElement(demo.repository ? 'a' : 'span');
    badge.className = 'sd-badge';
    badge.textContent = 'Live demo · read-only · fictional data';
    if (demo.repository) { badge.href = demo.repository; badge.target = '_blank'; badge.rel = 'noopener'; badge.title = 'View the source code on GitHub'; }
    document.body.appendChild(badge);

    // List pages delete through confirmDelete(id) -> "?delete_id=" navigation.
    if (typeof window.confirmDelete === 'function') window.confirmDelete = () => toast(READ_ONLY);

    // Capture phase runs before inline onclick/onsubmit handlers (delete confirmations and the like).
    document.addEventListener('click', event => {
        const link = event.target.closest && event.target.closest('a[href]');
        if (!link) return;
        if (link.hasAttribute('data-demo-off')) {
            event.preventDefault();
            event.stopPropagation();
            toast(READ_ONLY);
            return;
        }
        const href = link.getAttribute('href');
        if (/\.php(?:[?#]|$)/i.test(href)) {   // links built at runtime by page scripts
            const target = staticName(href);
            event.preventDefault();
            if (target && pages.has(target)) location.href = target;
            else { event.stopPropagation(); toast(READ_ONLY); }
        }
    }, true);
    document.addEventListener('submit', event => {
        event.preventDefault();
        event.stopPropagation();
        toast(READ_ONLY);
    }, true);

    // Live product search on the billing pages (jQuery $.ajax to search_product.php).
    if (window.jQuery) {
        window.jQuery.ajaxTransport('json', options => {
            if (!/(?:^|\/)search_product\.php/i.test(options.url)) return undefined;
            return {
                send(_, complete) {
                    const term = (new URL(options.url, location.href).searchParams.get('term') || '').toLowerCase();
                    const results = demo.products
                        .filter(product => product.label.toLowerCase().includes(term))
                        .sort((a, b) => a.label.localeCompare(b.label))
                        .slice(0, 10);
                    complete(200, 'success', { text: JSON.stringify(results) });
                },
                abort() {}
            };
        });
    }
})();
