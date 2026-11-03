/* Local-only page finder and presentation preferences. No requests or dependencies. */
(() => {
    'use strict';
    const body = document.body;
    const dialog = document.getElementById('wxCommandDialog');
    const input = document.getElementById('wxCommandInput');
    if (!body || !dialog || !input || body.classList.contains('wx-ready')) return;

    const routes = Array.from(dialog.querySelectorAll('[data-wx-route]'));
    const groups = Array.from(dialog.querySelectorAll('.wx-route-group'));
    const status = document.getElementById('wxResultStatus');
    const noResults = document.getElementById('wxNoResults');
    const motionNote = document.getElementById('wxMotionNote');
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    const nativeDialog = typeof dialog.showModal === 'function';
    let selected = null;
    let invokingElement = null;
    let opened = false;
    let scrollPosition = null;
    let previousOverflow = '';
    let fallbackState = [];

    function readPreference(key, fallback) {
        try { return window.localStorage.getItem(key) || fallback; }
        catch (_) { return fallback; }
    }
    function savePreference(key, value) {
        try { window.localStorage.setItem(key, value); }
        catch (_) { /* Private/blocked storage must not disable controls. */ }
    }
    let motion = readPreference('farms.motion', 'auto') === 'paused' ? 'paused' : 'auto';
    let ambience = readPreference('farms.ambience', 'pearl');
    if (!['pearl', 'lilac', 'solar'].includes(ambience)) ambience = 'pearl';

    function applyPreferences() {
        const paused = reduced.matches || motion === 'paused';
        body.dataset.wxMotion = paused ? 'paused' : 'auto';
        body.dataset.wxAmbience = ambience;
        body.classList.toggle('wx-motion-paused', paused);
        body.classList.toggle('wx-document-hidden', document.hidden);
        dialog.querySelectorAll('[data-wx-motion]').forEach(button => {
            button.setAttribute('aria-pressed', String(paused));
            button.disabled = reduced.matches;
            button.querySelector('[data-wx-motion-label]').textContent = paused ? 'Enable motion' : 'Pause motion';
            if (reduced.matches) button.setAttribute('aria-describedby', 'wxMotionNote');
            else button.removeAttribute('aria-describedby');
        });
        motionNote.hidden = !reduced.matches;
        dialog.querySelectorAll('[data-wx-ambience]').forEach(button => {
            button.setAttribute('aria-pressed', String(button.dataset.wxAmbience === ambience));
        });
        window.dispatchEvent(new CustomEvent('workspace:preferences', {
            detail: { motion: !paused && !document.hidden, ambience }
        }));
    }

    // navigator.platform is sufficient for a visual hint; no device fingerprinting.
    const isMac = /Mac|iPhone|iPad|iPod/i.test(navigator.platform || '');
    document.querySelectorAll('[data-wx-shortcut]').forEach(hint => {
        hint.textContent = isMac ? '⌘ K' : 'Ctrl K';
    });
    document.querySelectorAll('[data-wx-open]').forEach(button => {
        button.setAttribute('aria-haspopup', 'dialog');
        button.setAttribute('aria-controls', dialog.id);
        button.setAttribute('aria-keyshortcuts', isMac ? 'Meta+K' : 'Control+K');
    });

    const normalize = value => value.normalize('NFKD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    const searchText = new Map(routes.map(route => [route, normalize(route.dataset.wxSearch || route.textContent)]));
    const visibleRoutes = () => routes.filter(route => !route.closest('.wx-route-item').hidden);
    function selectRoute(route, focus = false) {
        if (selected) selected.removeAttribute('data-wx-selected');
        selected = route || null;
        if (selected) {
            selected.setAttribute('data-wx-selected', 'true');
            if (focus) {
                selected.focus({ preventScroll: true });
                selected.scrollIntoView({ block: 'nearest', behavior: 'instant' });
            }
        }
    }
    function filterRoutes() {
        const terms = normalize(input.value.trim()).split(/\s+/).filter(Boolean);
        routes.forEach(route => {
            route.closest('.wx-route-item').hidden = !terms.every(term => searchText.get(route).includes(term));
        });
        groups.forEach(group => {
            group.hidden = !group.querySelector('.wx-route-item:not([hidden])');
        });
        const visible = visibleRoutes();
        selectRoute(visible[0]);
        noResults.hidden = visible.length !== 0;
        status.textContent = `${visible.length} ${visible.length === 1 ? 'page' : 'pages'} ${terms.length ? 'found' : 'available'}`;
        document.getElementById('wxCommandRoutes').scrollTop = 0;
    }

    function anotherModalOpen() {
        // :modal covers native dialogs and other modal top-layer elements where supported.
        try { if (document.querySelector(':modal')) return true; } catch (_) { /* Older browser. */ }
        return !!document.querySelector('dialog[open], .modal.show, .sidebar.active, [aria-modal="true"]:not([inert]):not([hidden]):not(#wxCommandDialog)');
    }
    function setFallbackInert() {
        fallbackState = [];
        // Walk ancestors as well, so the fallback remains safe if the partial is nested.
        let branch = dialog;
        while (branch.parentElement) {
            Array.from(branch.parentElement.children).forEach(sibling => {
                if (sibling === branch || /^(SCRIPT|STYLE|LINK)$/.test(sibling.tagName)) return;
                fallbackState.push([sibling, sibling.inert, sibling.getAttribute('aria-hidden')]);
                sibling.inert = true;
                sibling.setAttribute('aria-hidden', 'true');
            });
            branch = branch.parentElement;
            if (branch === body) break;
        }
    }
    function restoreFallbackInert() {
        fallbackState.forEach(([element, inert, ariaHidden]) => {
            element.inert = inert;
            if (ariaHidden === null) element.removeAttribute('aria-hidden');
            else element.setAttribute('aria-hidden', ariaHidden);
        });
        fallbackState = [];
    }
    function finishClose() {
        if (!opened) return;
        opened = false;
        dialog.hidden = true;
        body.classList.remove('wx-command-open');
        body.classList.remove('wx-command-fallback');
        body.style.overflow = previousOverflow;
        restoreFallbackInert();
        if (!nativeDialog) {
            dialog.removeAttribute('open');
            dialog.removeAttribute('aria-modal');
        }
        if (invokingElement && invokingElement.isConnected && typeof invokingElement.focus === 'function') {
            invokingElement.focus({ preventScroll: true });
        }
        if (scrollPosition) window.scrollTo({ left: scrollPosition.x, top: scrollPosition.y, behavior: 'instant' });
        invokingElement = null;
        scrollPosition = null;
    }
    function closeDialog() {
        if (!opened) return;
        if (nativeDialog && dialog.open) dialog.close();
        finishClose();
    }
    function openDialog(invoker) {
        if (opened || anotherModalOpen()) return;
        invokingElement = invoker || document.activeElement;
        scrollPosition = { x: window.scrollX, y: window.scrollY };
        previousOverflow = body.style.overflow;
        input.value = '';
        filterRoutes();
        dialog.hidden = false;
        if (nativeDialog) {
            try { dialog.showModal(); }
            catch (_) { dialog.hidden = true; return; }
        } else {
            dialog.setAttribute('open', '');
            dialog.setAttribute('role', 'dialog');
            dialog.setAttribute('aria-modal', 'true');
            dialog.classList.add('wx-dialog-fallback');
            body.classList.add('wx-command-fallback');
            setFallbackInert();
        }
        opened = true;
        body.style.overflow = 'hidden';
        body.classList.add('wx-command-open');
        input.focus({ preventScroll: true });
    }
    function safeRoute(route) {
        if (!route) return false;
        const href = route.getAttribute('href') || '';
        if (!/^[a-zA-Z0-9_-]+\.php$/.test(href)) return false;
        const target = new URL(href, window.location.href);
        const current = new URL('.', window.location.href);
        return target.origin === current.origin && target.pathname.startsWith(current.pathname);
    }
    input.addEventListener('input', filterRoutes);
    routes.forEach(route => {
        route.addEventListener('focus', () => selectRoute(route));
        route.addEventListener('click', event => {
            if (!safeRoute(route)) { event.preventDefault(); return; }
            if (!event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey) closeDialog();
        });
    });
    dialog.addEventListener('close', finishClose);
    dialog.addEventListener('cancel', event => { event.preventDefault(); closeDialog(); });
    dialog.addEventListener('click', event => {
        if (event.target !== dialog || !nativeDialog) return;
        const bounds = dialog.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) closeDialog();
    });
    document.addEventListener('click', event => {
        if (!(event.target instanceof Element)) return;
        const trigger = event.target.closest('[data-wx-open]');
        if (trigger) { event.preventDefault(); openDialog(trigger); return; }
        if (!opened) return;
        if (event.target.closest('[data-wx-close]')) closeDialog();
        if (event.target.closest('[data-wx-clear]')) {
            input.value = '';
            filterRoutes();
            input.focus({ preventScroll: true });
        }
        if (event.target.closest('button[data-wx-motion]') && !reduced.matches) {
            motion = motion === 'paused' ? 'auto' : 'paused';
            savePreference('farms.motion', motion);
            applyPreferences();
        }
        const ambienceButton = event.target.closest('[data-wx-ambience]');
        if (ambienceButton && ['pearl', 'lilac', 'solar'].includes(ambienceButton.dataset.wxAmbience)) {
            ambience = ambienceButton.dataset.wxAmbience;
            savePreference('farms.ambience', ambience);
            applyPreferences();
        }
    });
    document.addEventListener('keydown', event => {
        if (event.isComposing) return;
        const exactShortcut = event.key.toLowerCase() === 'k' && !event.altKey && !event.shiftKey && (event.ctrlKey !== event.metaKey);
        if (exactShortcut) {
            if (event.repeat) return;
            if (opened) { event.preventDefault(); closeDialog(); }
            else if (!anotherModalOpen()) { event.preventDefault(); openDialog(document.activeElement); }
            return;
        }
        if (!opened) return;
        if (event.key === 'Escape') { event.preventDefault(); closeDialog(); return; }
        const inSearch = event.target === input || (event.target instanceof Element && event.target.matches('[data-wx-route]'));
        if (inSearch && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
            event.preventDefault();
            const visible = visibleRoutes();
            if (!visible.length) return;
            const index = visible.indexOf(selected);
            const next = event.target === input ? (event.key === 'ArrowDown' ? 0 : visible.length - 1)
                : (index + (event.key === 'ArrowDown' ? 1 : -1) + visible.length) % visible.length;
            selectRoute(visible[next], true);
        }
        if (event.target === input && event.key === 'Enter') {
            event.preventDefault();
            if (safeRoute(selected)) selected.click();
        }
        if (!nativeDialog && event.key === 'Tab') {
            const controls = Array.from(dialog.querySelectorAll('button:not([disabled]), input, a[href], [tabindex="0"]'))
                .filter(element => !element.closest('[hidden]') && element.getClientRects().length);
            const first = controls[0], last = controls[controls.length - 1];
            if (event.shiftKey && (document.activeElement === first || !dialog.contains(document.activeElement))) {
                event.preventDefault(); last.focus();
            } else if (!event.shiftKey && (document.activeElement === last || !dialog.contains(document.activeElement))) {
                event.preventDefault(); first.focus();
            }
        }
    });
    function syncFieldFocus(target) {
        body.classList.toggle('wx-field-focused', target instanceof Element
            && target.matches('input, textarea, select, [contenteditable="true"]'));
    }
    document.addEventListener('focusin', event => {
        syncFieldFocus(event.target);
        if (opened && !nativeDialog && !dialog.contains(event.target)) input.focus({ preventScroll: true });
    });
    document.addEventListener('focusout', event => syncFieldFocus(event.relatedTarget));
    window.addEventListener('beforeprint', closeDialog);
    window.addEventListener('pagehide', closeDialog);
    document.addEventListener('visibilitychange', applyPreferences);
    if (typeof reduced.addEventListener === 'function') reduced.addEventListener('change', applyPreferences);
    else if (typeof reduced.addListener === 'function') reduced.addListener(applyPreferences);
    window.addEventListener('storage', event => {
        if (event.key === 'farms.motion' || event.key === null) motion = readPreference('farms.motion', 'auto') === 'paused' ? 'paused' : 'auto';
        if (event.key === 'farms.ambience' || event.key === null) {
            const value = readPreference('farms.ambience', 'pearl');
            ambience = ['pearl', 'lilac', 'solar'].includes(value) ? value : 'pearl';
        }
        if (event.key === null || event.key === 'farms.motion' || event.key === 'farms.ambience') applyPreferences();
    });
    applyPreferences();
    body.classList.add('wx-ready');
})();