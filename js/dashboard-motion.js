/* Dashboard motion: GSAP + ScrollTrigger + Lenis choreography over the server-rendered dashboard.
   Decorative only. Every figure is already in the HTML, counters finish on the exact server text,
   and the page settles to its static state when motion is paused, reduced or printed. */
(() => {
    'use strict';
    const root = document.documentElement;
    const body = document.body;
    if (!body.classList.contains('cx-dashboard')) return;
    const { gsap, ScrollTrigger, SplitText, ScrambleTextPlugin, Lenis } = window;
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    const finePointer = matchMedia('(hover: hover) and (pointer: fine)');
    const motionAllowed = () => !reduced.matches && body.dataset.wxMotion !== 'paused';
    const shared = window.cxMotion = window.cxMotion || {};
    // The head guard hides .ld-main until this script is ready; a late script must not re-hide it.
    const introAllowed = root.classList.contains('cx-motion') && motionAllowed();
    const reveal = () => root.classList.add('cx-motion-ready');

    if (!gsap || !ScrollTrigger) { root.classList.remove('cx-motion'); return; }
    gsap.registerPlugin(ScrollTrigger, ...[SplitText, ScrambleTextPlugin].filter(Boolean));

    const q = (selector, scope = document) => Array.from(scope.querySelectorAll(selector));
    const mark = elements => { elements.forEach(el => el && el.classList.add('cx-m')); return elements; };
    const restorers = [];
    let settled = false;
    let ambientOn = false;

    // Screen readers get the final text at once; only an aria-hidden copy is animated.
    // <cx-v> is an unstyled inline element, so existing "… span" rules cannot restyle the copy.
    const visualCopy = () => { const el = document.createElement('cx-v'); el.setAttribute('aria-hidden', 'true'); return el; };
    function screenReaderPair(el, visualText) {
        const text = el.textContent.replace(/\s+/g, ' ').trim();
        const sr = document.createElement('span');
        sr.className = 'ld-sr-only';
        sr.textContent = text;
        const visual = visualCopy();
        visual.textContent = visualText === undefined ? text : visualText;
        el.replaceChildren(sr, visual);
        return { text, visual };
    }

    // Count-ups: parse "Rs. 74,090.00", "75%", "6 low-stock products"; always end on the original text.
    const NUMBER = /^(\D*?)(\d[\d,]*)(\.\d+)?(.*)$/s;
    const counters = new Map();
    function countUp(el, duration = 1.6, delay = 0) {
        if (!el || counters.has(el)) return;
        const original = el.textContent;
        const match = original.trim().match(NUMBER);
        if (!match) return;
        const value = Number(match[2].replace(/,/g, '') + (match[3] || ''));
        if (!Number.isFinite(value) || value === 0) return;
        const decimals = match[3] ? match[3].length - 1 : 0;
        const format = new Intl.NumberFormat('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals, useGrouping: match[2].includes(',') });
        const { visual } = screenReaderPair(el);
        const state = { v: 0 };
        const paint = () => { visual.textContent = match[1] + format.format(state.v) + match[4]; };
        paint();
        const finish = () => { el.textContent = original; counters.delete(el); };
        const tween = gsap.to(state, { v: value, duration, delay, ease: 'power3.out', onUpdate: paint, onComplete: finish });
        counters.set(el, { tween, finish });
    }
    function finishCounters() { counters.forEach(({ tween, finish }) => { tween.kill(); finish(); }); }

    // Scramble the uppercase kickers from a blank line.
    function scramble(el, duration = 1.3, delay = 0) {
        if (!el || !ScrambleTextPlugin) return null;
        const original = el.innerHTML;
        const { text, visual } = screenReaderPair(el, ' ');
        const restore = () => { el.innerHTML = original; };
        restorers.push(restore);
        return gsap.to(visual, { duration, delay, ease: 'none', scrambleText: { text, chars: 'upperCase', revealDelay: duration * 0.35, speed: 0.55 }, onComplete: restore });
    }

    // Split headings into masked words/chars; the split is reverted once its entrance has played.
    function splitWords(el, type = 'words') {
        if (!el || !SplitText) return null;
        const original = el.innerHTML;
        const label = el.textContent.replace(/\s+/g, ' ').trim();
        const sr = document.createElement('span');
        sr.className = 'ld-sr-only';
        sr.textContent = label;
        const visual = visualCopy();
        visual.append(...el.childNodes);
        el.append(sr, visual);
        const parts = SplitText.create(visual, { type, mask: type.includes('chars') ? 'chars' : 'words', wordsClass: 'cx-word', charsClass: 'cx-char', aria: 'none' });
        let done = false;
        const restore = () => { if (done) return; done = true; parts.revert(); el.innerHTML = original; };
        restorers.push(restore);
        return { parts, visual, restore };
    }

    const entrances = [];
    const track = tween => { entrances.push(tween); return tween; };

    /* ---------- Load-in ---------- */
    function playIntro() {
        const hero = document.querySelector('.cx-hero');
        const greeting = splitWords(document.querySelector('.ld-hello h1'));
        const title = document.querySelector('.cx-hero-title');
        let titleChars = [], titleWords = [], restoreTitle = () => {};
        if (title && SplitText) {
            const original = title.innerHTML;
            const sr = document.createElement('span');
            sr.className = 'ld-sr-only';
            sr.textContent = title.textContent.replace(/\s+/g, ' ').trim();
            const spans = q(':scope > span', title);
            spans.forEach(span => span.setAttribute('aria-hidden', 'true'));
            title.prepend(sr);
            const first = spans[0] && SplitText.create(spans[0], { type: 'chars', mask: 'chars', charsClass: 'cx-char', aria: 'none' });
            const gradient = title.querySelector('.cx-gradient-text');
            const second = gradient && SplitText.create(gradient, { type: 'words', mask: 'words', wordsClass: 'cx-word', aria: 'none' });
            if (gradient) gradient.classList.add('is-split');
            titleChars = first ? first.chars : [];
            titleWords = second ? second.words : [];
            let done = false;
            restoreTitle = () => { if (done) return; done = true; first && first.revert(); second && second.revert(); title.innerHTML = original; };
            restorers.push(restoreTitle);
        }
        const ring = document.querySelector('.cx-health-ring circle:last-of-type');
        const ringTarget = ring ? parseFloat(ring.style.strokeDashoffset) : NaN;
        const kpis = mark(q('.cx-kpi-card'));
        const actions = mark(q('.cx-hero-actions > *'));
        const labels = mark(q('.cx-orbit-label'));
        mark([hero, document.querySelector('.ld-avatar'), document.querySelector('.cx-hero-description'), document.querySelector('.cx-orbit-core'), document.querySelector('.cx-orbit-caption'), document.querySelector('.cx-marquee')]);
        mark(q('.ld-date > *, .cx-kpi-icon'));

        const tl = track(gsap.timeline({ defaults: { ease: 'expo.out' }, onComplete: () => { restoreTitle(); greeting && greeting.restore(); } }));
        tl.fromTo('.ld-avatar', { scale: 0, rotation: -120 }, { scale: 1, rotation: 0, duration: 1.2, ease: 'back.out(2.2)', clearProps: 'transform' }, 0);
        if (greeting) tl.fromTo(greeting.parts.words, { yPercent: 115 }, { yPercent: 0, duration: 1.1, stagger: 0.06 }, 0.05);
        tl.fromTo('.ld-date > *', { opacity: 0, y: 12 }, { opacity: 1, y: 0, duration: 0.8, stagger: 0.08, clearProps: 'opacity,transform' }, 0.2);
        tl.fromTo(hero, { opacity: 0, y: 46, scale: 0.965 }, { opacity: 1, y: 0, scale: 1, duration: 1.5, clearProps: 'opacity,transform' }, 0.08);
        const kicker = scramble(document.querySelector('.cx-kicker'), 1.4);
        if (kicker) tl.add(kicker, 0.3);
        const index = scramble(document.querySelector('.cx-hero-index'), 1.2);
        if (index) tl.add(index, 0.6);
        if (titleChars.length) tl.fromTo(titleChars, { yPercent: 118, rotation: 7 }, { yPercent: 0, rotation: 0, duration: 1.3, stagger: 0.035 }, 0.32);
        if (titleWords.length) tl.fromTo(titleWords, { yPercent: 118 }, { yPercent: 0, duration: 1.25, stagger: 0.09 }, 0.52);
        tl.fromTo('.cx-hero-description', { opacity: 0, y: 22 }, { opacity: 1, y: 0, duration: 1.1, clearProps: 'opacity,transform' }, 0.8);
        tl.fromTo(actions, { opacity: 0, y: 26 }, { opacity: 1, y: 0, duration: 1, stagger: 0.08, clearProps: 'opacity,transform' }, 0.9);
        tl.fromTo('.cx-orbit-core', { opacity: 0, scale: 0.55 }, { opacity: 1, scale: 1, duration: 1.6, ease: 'elastic.out(1, 0.62)', clearProps: 'opacity,transform' }, 0.45);
        if (ring && Number.isFinite(ringTarget)) {
            tl.fromTo(ring, { strokeDashoffset: 427.26 }, { strokeDashoffset: ringTarget, duration: 1.9, ease: 'power3.inOut' }, 0.7);
            restorers.push(() => { ring.style.strokeDashoffset = String(ringTarget); });
        }
        tl.call(() => countUp(document.querySelector('.cx-orbit-center strong'), 1.8, 0.25), null, 0.45);
        tl.fromTo(labels, { opacity: 0, y: 30, scale: 0.88 }, { opacity: 1, y: 0, scale: 1, duration: 1.2, stagger: 0.16, ease: 'back.out(1.7)', clearProps: 'opacity,transform' }, 1);
        tl.call(() => q('.cx-floating-value').forEach((el, i) => countUp(el, 1.5, 0.05 + i * 0.16)), null, 1);
        tl.fromTo('.cx-orbit-caption', { opacity: 0 }, { opacity: 1, duration: 0.9, clearProps: 'opacity' }, 1.35);
        tl.fromTo(kpis, { opacity: 0, y: 72, rotationX: -18, transformPerspective: 1000, transformOrigin: '50% 0%' },
            { opacity: 1, y: 0, rotationX: 0, duration: 1.35, stagger: 0.09, clearProps: 'opacity,transform', onComplete: () => kpis.forEach(card => { card.dataset.cxEntered = '1'; }) }, 0.62);
        tl.fromTo('.cx-kpi-icon', { scale: 0, rotation: -90 }, { scale: 1, rotation: 0, duration: 1, stagger: 0.09, ease: 'back.out(2.4)', clearProps: 'transform' }, 0.9);
        tl.call(() => q('.cx-kpi-value').forEach((el, i) => countUp(el, 1.6, 0.13 + i * 0.09)), null, 0.62);
        tl.fromTo('.cx-marquee', { opacity: 0, y: 30 }, { opacity: 1, y: 0, duration: 1.4, clearProps: 'opacity,transform' }, 1.1);
        return tl;
    }

    /* ---------- Scroll entrances ---------- */
    function playInside(card, delay) {
        const heading = card.querySelector(':scope > .ld-card-heading h2, :scope > .ld-actions-heading h2, :scope > h2, .ld-transactions-heading h2');
        const split = card._cxSplit;
        if (split) track(gsap.fromTo(split.parts.words, { yPercent: 115 }, { yPercent: 0, duration: 1.1, stagger: 0.05, delay: delay + 0.08, ease: 'expo.out', onComplete: split.restore }));
        else if (heading) mark([heading]);
        const kicker = card.querySelector('.cx-section-kicker');
        if (kicker) { const tween = scramble(kicker, 1.2, delay); if (tween) track(tween); }
        q('.ld-balance, .ld-supplier-amount, .ld-counts dd a, .ld-stock-summary strong, .ld-facts dd, .ld-supplier-row strong, .cx-pulse-number, .ld-today strong, :scope > strong', card)
            .forEach((el, i) => countUp(el, 1.5, delay + 0.15 + i * 0.05));
        const rows = mark(q('.ld-action-row, .ld-supplier-row, .cx-insight-list li, .ld-low-stock-row, .ld-counts > div, .ld-facts > div', card));
        if (rows.length) track(gsap.fromTo(rows, { opacity: 0, x: 34 }, { opacity: 1, x: 0, duration: 0.95, stagger: 0.07, delay: delay + 0.12, ease: 'power3.out', clearProps: 'opacity,transform' }));
        const tableRows = mark(q('tbody tr[data-type]', card));
        if (tableRows.length) track(gsap.fromTo(tableRows, { opacity: 0, y: 20 }, { opacity: 1, y: 0, duration: 0.8, stagger: 0.05, delay: delay + 0.15, ease: 'power3.out', clearProps: 'opacity,transform' }));
        const pills = mark(q('.ld-pill', card));
        if (pills.length) track(gsap.fromTo(pills, { scale: 0.55, opacity: 0 }, { scale: 1, opacity: 1, duration: 0.7, stagger: 0.03, delay: delay + 0.35, ease: 'back.out(2.6)', clearProps: 'opacity,transform' }));
        const urgent = q('.ld-low-stock-row .ld-pill-coral', card);
        if (urgent.length) track(gsap.to(urgent, { keyframes: { rotation: [0, -6, 5, -3, 2, 0] }, duration: 0.9, delay: delay + 1.45, stagger: 0.08, ease: 'power1.inOut', clearProps: 'transform' }));
        const bars = mark(q('.ld-abstract span', card));
        if (bars.length) {
            track(gsap.fromTo(bars, { scaleY: 0 }, { scaleY: 1, duration: 1, delay: delay + 0.2, ease: 'back.out(2)', stagger: { each: 0.05, from: 'center' }, onComplete: () => wave(bars, card) }));
        }
    }
    function wave(bars, card) {
        if (settled) return;
        const loop = gsap.to(bars, { scaleY: 0.42, duration: 0.85, ease: 'sine.inOut', stagger: { each: 0.09, repeat: -1, yoyo: true } });
        ScrollTrigger.create({ trigger: card, start: 'top bottom', end: 'bottom top', onToggle: self => (self.isActive && ambientOn ? loop.resume() : loop.pause()) });
        restorers.push(() => { loop.kill(); gsap.set(bars, { clearProps: 'transform' }); });
    }
    function setupScrollEntrances(intro) {
        const cards = mark(q('.ld-grid > .ld-column > *, .cx-insight-tile, .ld-transactions, .ld-low-stock, .cx-dashboard-footer'));
        cards.forEach(card => {
            const heading = card.querySelector(':scope > .ld-card-heading h2, :scope > .ld-actions-heading h2, :scope > h2, .ld-transactions-heading h2');
            if (heading && !card.matches('.cx-insight-tile')) card._cxSplit = splitWords(heading);
        });
        gsap.set(cards, { opacity: 0, y: 84, rotationX: -10, scale: 0.965, transformPerspective: 1200, transformOrigin: '50% 0%' });
        const pending = new Set(cards);
        const enter = batch => {
            const delay = intro && intro.isActive() ? Math.max(0, 1.05 - intro.time()) : 0;
            batch.forEach(card => pending.delete(card));
            track(gsap.to(batch, { opacity: 1, y: 0, rotationX: 0, scale: 1, duration: 1.25, stagger: 0.12, delay, ease: 'expo.out', clearProps: 'opacity,transform',
                onComplete: () => batch.forEach(card => { card.dataset.cxEntered = '1'; }) }));
            batch.forEach((card, i) => playInside(card, delay + i * 0.12));
        };
        // clamp() lets the last cards trigger even when the page cannot scroll them to 92%.
        ScrollTrigger.batch(cards, { start: 'clamp(top 92%)', once: true, onEnter: enter });
        // Keyboard focus can land in a card before it scrolls into view; show it immediately.
        document.addEventListener('focusin', event => {
            const card = cards.find(candidate => pending.has(candidate) && candidate.contains(event.target));
            if (card) enter([card]);
        });
        restorers.push(() => { pending.clear(); });

        gsap.to('.cx-hero-copy', { yPercent: -16, ease: 'none', scrollTrigger: { trigger: '.cx-hero', start: 'top top+=96', end: 'bottom top', scrub: true } });
        mark([document.querySelector('.cx-hero-copy')]);
        q('.ld-flower, .ld-mini-orbit').forEach(el => {
            mark([el]);
            gsap.to(el, { rotation: 360, ease: 'none', scrollTrigger: { trigger: el, start: 'top bottom', end: 'bottom top', scrub: 1 } });
        });
    }

    /* ---------- Ambient: Lenis, marquee, magnetic controls, card tilt, progress ---------- */
    function startLenis() {
        if (shared.lenis || typeof Lenis !== 'function') return;
        const lenis = new Lenis({
            autoRaf: false,
            lerp: 0.09,
            anchors: true,
            allowNestedScroll: true,
            prevent: node => node.matches('dialog, .wx-agent-panel, #sidebar, .lisso-nav-group')
        });
        lenis.on('scroll', ScrollTrigger.update);
        shared.lenis = lenis;
        syncLocks();
    }
    function stopLenis() {
        if (!shared.lenis) return;
        shared.lenis.destroy();
        shared.lenis = null;
    }
    function syncLocks() {
        if (!shared.lenis) return;
        const locked = body.classList.contains('wx-command-open') || body.classList.contains('navigation-open');
        if (locked) shared.lenis.stop(); else shared.lenis.start();
    }
    new MutationObserver(syncLocks).observe(body, { attributes: true, attributeFilter: ['class'] });
    gsap.ticker.add(time => { if (shared.lenis) shared.lenis.raf(time * 1000); });
    gsap.ticker.lagSmoothing(0);

    // Capsule band: a conveyor whose capsules ride a travelling wave. Scroll speed whips the wave up,
    // hovering slows the belt and magnifies the capsule under the cursor so it can be read.
    const marquee = (() => {
        const section = document.querySelector('.cx-marquee');
        const row = section && section.querySelector('.cx-marquee-row');
        const trackEl = row && row.querySelector('.cx-marquee-track');
        const group = trackEl && trackEl.firstElementChild;
        if (!group) return { start() {}, stop() {} };
        const setX = gsap.quickSetter(trackEl, 'x', 'px');
        let items = [], width = 1, rowWidth = 1, x = 0, clock = 0, amp = 0, speed = 1, direction = 1;
        let pointerX = null, visible = true, trigger = null, observer = null;
        function measure() {
            width = Math.max(group.offsetWidth, 1);
            rowWidth = row.offsetWidth;
            const needed = Math.min(Math.ceil((rowWidth * 2) / width) + 1, 8);
            while (trackEl.children.length < needed) trackEl.appendChild(group.cloneNode(true));
            // offsetLeft ignores transforms, so these are stable belt positions within the track.
            items = q('.cx-cap, .cx-tab', trackEl).map(el => ({ el, base: el.offsetLeft + el.offsetWidth / 2, tablet: el.classList.contains('cx-tab') }));
        }
        function tick(time, deltaTime) {
            if (!visible) return;
            const dt = Math.min(deltaTime, 50) / 1000;
            clock += dt;
            const lenis = shared.lenis;
            const velocity = lenis ? lenis.velocity || 0 : 0;
            if (lenis && lenis.direction) direction = lenis.direction;   // 0 while idle: keep the last heading
            const compact = rowWidth < 600;
            amp += ((compact ? 5 : 9) + Math.min(Math.abs(velocity) * 1.3, compact ? 12 : 21) - amp) * 0.05;
            speed += ((pointerX === null ? 1 : 0.2) - speed) * 0.08;
            x = gsap.utils.wrap(-width, 0, x - (50 + Math.abs(velocity) * 16) * direction * speed * dt);
            setX(x);
            for (const item of items) {
                const cx = item.base + x;
                if (cx < -300 || cx > rowWidth + 300) continue;
                const phase = cx / 230 - clock * 1.7;
                const lift = Math.sin(phase) * amp;
                const turn = item.tablet ? cx * 0.9 : Math.cos(phase) * amp * 0.22;
                let scale = 1;
                if (pointerX !== null) { const d = (cx - pointerX) / 170; scale += (item.tablet ? 0.3 : 0.15) * Math.exp(-d * d); }
                item.el.style.transform = `translate3d(0, ${lift.toFixed(2)}px, 0) rotate(${turn.toFixed(2)}deg) scale(${scale.toFixed(3)})`;
            }
        }
        section.addEventListener('pointermove', event => {
            if (ambientOn && finePointer.matches) pointerX = event.clientX - row.getBoundingClientRect().left;
        }, { passive: true });
        section.addEventListener('pointerleave', () => { pointerX = null; }, { passive: true });
        return {
            start() {
                measure();
                amp = 34;   // arrive with a splash, then settle into a gentle ripple
                observer = new ResizeObserver(measure);
                observer.observe(section);
                observer.observe(group);   // Lucide swaps in its icons after this script runs
                trigger = ScrollTrigger.create({ trigger: section, start: 'top bottom', end: 'bottom top', onToggle: self => { visible = self.isActive; } });
                visible = trigger.isActive;
                gsap.ticker.add(tick);
            },
            stop() {
                gsap.ticker.remove(tick);
                if (trigger) trigger.kill();
                if (observer) observer.disconnect();
                pointerX = null;
                gsap.set(trackEl, { clearProps: 'transform' });
                items.forEach(item => { item.el.style.transform = ''; });
            }
        };
    })();

    const magnets = mark(q('.ld-main :is(.cx-primary-action, .cx-secondary-action, .ld-black-link, .ld-round-link, .ld-lavender-link)'));
    magnets.forEach(el => {
        el.classList.add('cx-magnetic');
        el.addEventListener('pointermove', event => {
            if (!ambientOn || !finePointer.matches) return;
            if (!el._cxMagnet) {
                gsap.killTweensOf(el, 'x,y');   // a still-running elastic return would override the new pull
                el._cxMagnet = { x: gsap.quickTo(el, 'x', { duration: 0.5, ease: 'power3.out' }), y: gsap.quickTo(el, 'y', { duration: 0.5, ease: 'power3.out' }) };
            }
            const rect = el.getBoundingClientRect();
            el._cxMagnet.x((event.clientX - rect.left - rect.width / 2) * 0.3);
            el._cxMagnet.y((event.clientY - rect.top - rect.height / 2) * 0.42);
        }, { passive: true });
        el.addEventListener('pointerleave', () => {
            if (!el._cxMagnet) return;
            el._cxMagnet = null;
            gsap.to(el, { x: 0, y: 0, duration: 0.9, ease: 'elastic.out(1, 0.4)', overwrite: true });
        }, { passive: true });
    });

    const tiltCards = mark(q('.cx-kpi-card, .cx-insight-tile'));
    tiltCards.forEach(card => {
        card.addEventListener('pointermove', event => {
            if (!ambientOn || !finePointer.matches || !card.dataset.cxEntered) return;
            if (!card._cxTilt) {
                gsap.killTweensOf(card, 'rotationX,rotationY');
                gsap.set(card, { transformPerspective: 900 });
                card._cxTilt = { x: gsap.quickTo(card, 'rotationX', { duration: 0.7, ease: 'power3.out' }), y: gsap.quickTo(card, 'rotationY', { duration: 0.7, ease: 'power3.out' }) };
            }
            const rect = card.getBoundingClientRect();
            card._cxTilt.x(-((event.clientY - rect.top) / rect.height - 0.5) * 9);
            card._cxTilt.y(((event.clientX - rect.left) / rect.width - 0.5) * 11);
        }, { passive: true });
        card.addEventListener('pointerleave', () => {
            if (!card._cxTilt) return;
            card._cxTilt = null;
            gsap.to(card, { rotationX: 0, rotationY: 0, duration: 0.9, ease: 'power3.out', overwrite: true, clearProps: 'transform' });
        }, { passive: true });
    });

    let progressBar = null, progressTween = null;
    function startProgress() {
        progressBar = document.createElement('div');
        progressBar.className = 'cx-scroll-progress';
        progressBar.setAttribute('aria-hidden', 'true');
        body.appendChild(progressBar);
        progressTween = gsap.to(progressBar, { scaleX: 1, ease: 'none', scrollTrigger: { start: 0, end: 'max', scrub: 0.3 } });
    }
    function stopProgress() {
        if (progressTween) { progressTween.scrollTrigger && progressTween.scrollTrigger.kill(); progressTween.kill(); }
        if (progressBar) progressBar.remove();
        progressBar = progressTween = null;
    }

    function startAmbient() {
        if (ambientOn || !motionAllowed()) return;
        ambientOn = true;
        startLenis();
        marquee.start();
        startProgress();
    }
    function stopAmbient() {
        if (!ambientOn) return;
        ambientOn = false;
        stopLenis();
        marquee.stop();
        stopProgress();
        magnets.concat(tiltCards).forEach(el => { el._cxMagnet = el._cxTilt = null; });
    }

    /* ---------- Settling: paused motion is permanent for this page view; print is temporary ---------- */
    function settle() {
        if (settled) return;
        settled = true;
        stopAmbient();
        finishCounters();
        ScrollTrigger.getAll().forEach(trigger => trigger.kill(true));
        entrances.forEach(tween => tween.kill());
        restorers.splice(0).forEach(restore => restore());
        gsap.set(q('.cx-m'), { clearProps: 'opacity,visibility,transform' });
        tiltCards.forEach(card => { card.dataset.cxEntered = '1'; });
        root.classList.remove('cx-motion');
        reveal();
    }
    function syncPreference() {
        if (motionAllowed()) startAmbient();
        else settle();
    }
    window.addEventListener('workspace:preferences', syncPreference);
    if (reduced.addEventListener) reduced.addEventListener('change', syncPreference);
    window.addEventListener('beforeprint', finishCounters);

    if (!motionAllowed()) { root.classList.remove('cx-motion'); reveal(); return; }
    const intro = introAllowed ? playIntro() : null;
    if (introAllowed) setupScrollEntrances(intro);
    else tiltCards.forEach(card => { card.dataset.cxEntered = '1'; });
    reveal();
    startAmbient();
    // Late layout changes (chart render, web fonts) move trigger positions.
    let refreshTimer = 0;
    new ResizeObserver(() => { clearTimeout(refreshTimer); refreshTimer = setTimeout(() => ScrollTrigger.refresh(), 160); }).observe(document.querySelector('.ld-main'));
    window.addEventListener('load', () => ScrollTrigger.refresh());
})();
