/* Dashboard hero: a WebGL cluster of capsules and tablets drawn around the stock-health core.
   Decorative only: the canvas is aria-hidden, holds no data and ignores clicks on real controls.
   It pauses off screen and in hidden tabs, and freezes into a still frame when motion is off. */
import {
    WebGLRenderer, Scene, PerspectiveCamera, Group, Mesh, LatheGeometry, Vector2, Vector3, Quaternion,
    MeshPhysicalMaterial, Color, PMREMGenerator, DirectionalLight, HemisphereLight,
    ACESFilmicToneMapping, SRGBColorSpace, RoomEnvironment
} from './vendor/three-0.186.1.dashboard.min.js';

const body = document.body;
const hero = document.querySelector('.cx-dashboard .cx-hero');
const core = hero && hero.querySelector('.cx-orbit-core');
const reduced = matchMedia('(prefers-reduced-motion: reduce)');
const motionAllowed = () => !reduced.matches && body.dataset.wxMotion !== 'paused';

// Capsule and tablet dimensions in world units; one unit is sized from the core on every resize.
const CAP_R = 0.425, BODY_R = 0.4, HALF = 0.55, LIP = 0.12;
const TAB_R = 0.62, TAB_T = 0.17, TAB_DOME = 0.085, TAB_EDGE = 0.1;
const CORE_UNITS = 4.6;          // core diameter expressed in world units
// Depth is kept shallow: pills behind the DOM core must not grow larger than the core itself.
const PULL = 15, Z_SPRING = 26, Z_LIMIT = 1.1, LIN_DAMP = 1.9, ANG_DAMP = 1.5, RESTITUTION = 0.28, SPIN = 0.9;
const POINTER_R = 1.25, BURST = 15, SCROLL_KICK = 0.11;

const PILL_COLOURS = [
    ['#8b70a8', '#fff8ef'], ['#3f2f4b', '#f1cd5b'], ['#e8907b', '#fff8ef'],
    ['#f1cd5b', '#fff8ef'], ['#9fd3b8', '#fff8ef'], ['#b79bd6', '#3f2f4b']
];
const TABLET_COLOURS = ['#fffaf2', '#fffaf2', '#f4d77a', '#fffaf2', '#cdb8e6', '#f2a493', '#3f2f4b'];

if (hero && core) {
    try { init(); }
    catch (error) { /* No WebGL: the CSS orbit rings remain as the hero decoration. */ }
}

function capsuleGeometries() {
    // The cap is a slightly wider shell that overlaps the body, like a real two-part capsule.
    const cap = [new Vector2(BODY_R * 0.97, -LIP * 0.25), new Vector2(CAP_R, -LIP * 0.25)];
    for (let i = 0; i <= 14; i++) {
        const a = (i / 14) * Math.PI / 2;
        cap.push(new Vector2(Math.max(CAP_R * Math.cos(a), 1e-4), HALF + CAP_R * Math.sin(a)));
    }
    const shell = [];
    for (let i = 0; i <= 14; i++) {
        const a = -Math.PI / 2 + (i / 14) * Math.PI / 2;
        shell.push(new Vector2(Math.max(BODY_R * Math.cos(a), 1e-4), -HALF + BODY_R * Math.sin(a)));
    }
    shell.push(new Vector2(BODY_R, LIP));
    return { cap: new LatheGeometry(cap, 40), shell: new LatheGeometry(shell, 40) };
}

function tabletGeometry() {
    const points = [];
    const flat = TAB_R - TAB_EDGE;
    for (let i = 0; i <= 8; i++) {
        const u = i / 8;
        points.push(new Vector2(Math.max(flat * u, 1e-4), -TAB_T - TAB_DOME * (1 - u * u)));
    }
    for (let i = 1; i <= 8; i++) {
        const a = -Math.PI / 2 + (i / 8) * Math.PI / 2;
        points.push(new Vector2(flat + TAB_EDGE * Math.cos(a), -TAB_T + TAB_EDGE + TAB_EDGE * Math.sin(a)));
    }
    for (let i = 0; i <= 8; i++) {
        const a = (i / 8) * Math.PI / 2;
        points.push(new Vector2(flat + TAB_EDGE * Math.cos(a), TAB_T - TAB_EDGE + TAB_EDGE * Math.sin(a)));
    }
    for (let i = 1; i <= 8; i++) {
        const u = 1 - i / 8;
        points.push(new Vector2(Math.max(flat * u, 1e-4), TAB_T + TAB_DOME * (1 - u * u)));
    }
    return new LatheGeometry(points, 44);
}

function init() {
    const canvas = document.createElement('canvas');
    canvas.className = 'cx-pills';
    canvas.setAttribute('aria-hidden', 'true');
    const renderer = new WebGLRenderer({ canvas, alpha: true, antialias: true, powerPreference: 'high-performance' });
    renderer.setClearColor(0x000000, 0);
    renderer.toneMapping = ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.05;
    renderer.outputColorSpace = SRGBColorSpace;
    hero.prepend(canvas);
    hero.classList.add('has-pills');

    const scene = new Scene();
    const pmrem = new PMREMGenerator(renderer);
    const room = new RoomEnvironment();
    scene.environment = pmrem.fromScene(room, 0.04).texture;
    scene.environmentIntensity = 0.85;
    room.dispose();
    pmrem.dispose();

    const key = new DirectionalLight('#fff2e4', 2.3); key.position.set(4, 7, 9);
    const rim = new DirectionalLight('#b48cff', 3.4); rim.position.set(-8, 3, -6);
    const warm = new DirectionalLight('#ffc98a', 1.5); warm.position.set(7, -5, -3);
    scene.add(key, rim, warm, new HemisphereLight('#fff8f2', '#e2d3ef', 0.55));

    const camera = new PerspectiveCamera(30, 1, 0.5, 400);
    const materials = new Map();
    const gloss = hex => {
        if (!materials.has('g' + hex)) materials.set('g' + hex, new MeshPhysicalMaterial({
            color: new Color(hex), roughness: 0.3, metalness: 0, clearcoat: 1, clearcoatRoughness: 0.12
        }));
        return materials.get('g' + hex);
    };
    const chalk = hex => {
        if (!materials.has('c' + hex)) materials.set('c' + hex, new MeshPhysicalMaterial({
            color: new Color(hex), roughness: 0.68, metalness: 0, clearcoat: 0.2, clearcoatRoughness: 0.55,
            sheen: 1, sheenRoughness: 0.8, sheenColor: new Color('#ffffff')
        }));
        return materials.get('c' + hex);
    };

    const capsule = capsuleGeometries();
    const tablet = tabletGeometry();
    const compact = hero.clientWidth < 700;
    const pillCount = compact ? 10 : 16;
    const tabletCount = compact ? 6 : 10;
    const bodies = [];
    for (let i = 0; i < pillCount + tabletCount; i++) {
        const isPill = i < pillCount;
        const mesh = new Group();
        if (isPill) {
            const [capHex, shellHex] = PILL_COLOURS[i % PILL_COLOURS.length];
            mesh.add(new Mesh(capsule.cap, gloss(capHex)), new Mesh(capsule.shell, gloss(shellHex)));
        } else {
            const hex = TABLET_COLOURS[(i - pillCount) % TABLET_COLOURS.length];
            mesh.add(new Mesh(tablet, hex === '#3f2f4b' || hex === '#f2a493' ? gloss(hex) : chalk(hex)));
        }
        scene.add(mesh);
        bodies.push({
            mesh,
            half: isPill ? HALF : 0,
            r: isPill ? CAP_R : TAB_R * 0.86,
            p: new Vector3(), v: new Vector3(), w: new Vector3(), q: new Quaternion(), axis: new Vector3(0, 1, 0),
            s: 0, delay: i * 0.028, age: 0,
            f1: 0.4 + Math.random() * 0.5, f2: 0.35 + Math.random() * 0.5, phase: Math.random() * 6.28,
            kick: 0.7 + Math.random() * 0.6
        });
    }

    // Layout: world units are derived from the core's rendered size, and the cluster centres on it.
    const centre = new Vector3();
    let coreRadius = 2.4, halfW = 10, halfH = 4, pxPerUnit = 40;
    function offsetWithin(el, ancestor) {
        let x = 0, y = 0;
        for (let node = el; node && node !== ancestor; node = node.offsetParent) { x += node.offsetLeft; y += node.offsetTop; }
        return { x, y };
    }
    function layout() {
        const w = Math.max(hero.clientWidth, 1), h = Math.max(hero.clientHeight, 1);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.75));
        renderer.setSize(w, h, false);
        pxPerUnit = Math.max(core.offsetWidth, 120) / CORE_UNITS;
        halfW = w / pxPerUnit / 2;
        halfH = h / pxPerUnit / 2;
        camera.aspect = w / h;
        camera.position.set(0, 0, halfH / Math.tan((camera.fov * Math.PI / 180) / 2));
        camera.updateProjectionMatrix();
        const at = offsetWithin(core, hero);
        centre.set((at.x + core.offsetWidth / 2) / pxPerUnit - halfW, halfH - (at.y + core.offsetHeight / 2) / pxPerUnit, 0);
        coreRadius = CORE_UNITS / 2 + 0.05;
    }

    // Pointer: an invisible kinematic ball on the z = 0 plane.
    const pointer = { active: false, p: new Vector3(1e3, 1e3, 0), v: new Vector3(), last: new Vector3(), t: 0 };
    function toWorld(event, target) {
        const rect = hero.getBoundingClientRect();
        const sx = rect.width / Math.max(hero.clientWidth, 1) || 1;   // the hero may be mid-intro scale
        target.set((event.clientX - rect.left) / sx / pxPerUnit - halfW, halfH - (event.clientY - rect.top) / sx / pxPerUnit, 0);
        return target;
    }
    hero.addEventListener('pointermove', event => {
        const now = performance.now() / 1000;
        toWorld(event, pointer.p);
        if (pointer.active) {
            const dt = Math.max(now - pointer.t, 1 / 240);
            pointer.v.subVectors(pointer.p, pointer.last).divideScalar(dt).clampLength(0, 45);
        }
        pointer.last.copy(pointer.p);
        pointer.t = now;
        pointer.active = true;
    }, { passive: true });
    hero.addEventListener('pointerleave', () => { pointer.active = false; pointer.p.set(1e3, 1e3, 0); pointer.v.set(0, 0, 0); }, { passive: true });
    hero.addEventListener('pointerdown', event => {
        if (!running || event.target.closest('a, button, input, select, summary, [tabindex]')) return;
        const at = toWorld(event, new Vector3());
        for (const b of bodies) {
            const d = b.p.clone().sub(at);
            const dist = Math.max(d.length(), 0.3);
            b.v.addScaledVector(d.divideScalar(dist), BURST * Math.exp(-dist / 3.2));
            b.v.z += (Math.random() - 0.5) * 3;
            b.w.add(new Vector3(Math.random() - 0.5, Math.random() - 0.5, Math.random() - 0.5).multiplyScalar(14));
        }
    });

    // Physics: segment-vs-segment capsule contacts, a cylindrical core obstacle and a pointer ball.
    const c1 = new Vector3(), c2 = new Vector3(), n = new Vector3(), tmp = new Vector3(), tmp2 = new Vector3();
    const spinQ = new Quaternion();
    const UP = new Vector3(0, 1, 0);
    const clamp = (value, min, max) => Math.min(max, Math.max(min, value));
    function ends(b, a, z) {
        const h = b.half * b.s;
        a.copy(b.p).addScaledVector(b.axis, h);
        z.copy(b.p).addScaledVector(b.axis, -h);
    }
    const A1 = new Vector3(), A2 = new Vector3(), B1 = new Vector3(), B2 = new Vector3();
    function closestSegments(p1, q1, p2, q2) {
        const d1x = q1.x - p1.x, d1y = q1.y - p1.y, d1z = q1.z - p1.z;
        const d2x = q2.x - p2.x, d2y = q2.y - p2.y, d2z = q2.z - p2.z;
        const rx = p1.x - p2.x, ry = p1.y - p2.y, rz = p1.z - p2.z;
        const a = d1x * d1x + d1y * d1y + d1z * d1z, e = d2x * d2x + d2y * d2y + d2z * d2z;
        const f = d2x * rx + d2y * ry + d2z * rz;
        let s = 0, t = 0;
        if (a > 1e-8 || e > 1e-8) {
            if (a <= 1e-8) t = clamp(f / e, 0, 1);
            else {
                const c = d1x * rx + d1y * ry + d1z * rz;
                if (e <= 1e-8) s = clamp(-c / a, 0, 1);
                else {
                    const b = d1x * d2x + d1y * d2y + d1z * d2z, denom = a * e - b * b;
                    s = denom > 1e-8 ? clamp((b * f - c * e) / denom, 0, 1) : 0;
                    t = (b * s + f) / e;
                    if (t < 0) { t = 0; s = clamp(-c / a, 0, 1); }
                    else if (t > 1) { t = 1; s = clamp((b - c) / a, 0, 1); }
                }
            }
        }
        c1.set(p1.x + d1x * s, p1.y + d1y * s, p1.z + d1z * s);
        c2.set(p2.x + d2x * t, p2.y + d2y * t, p2.z + d2z * t);
    }
    function closestOnSegment(p1, q1, point, out, planar) {
        const dx = q1.x - p1.x, dy = q1.y - p1.y, dz = planar ? 0 : q1.z - p1.z;
        const len = dx * dx + dy * dy + dz * dz;
        const t = len > 1e-8 ? clamp(((point.x - p1.x) * dx + (point.y - p1.y) * dy + (planar ? 0 : (point.z - p1.z) * dz)) / len, 0, 1) : 0;
        return out.set(p1.x + (q1.x - p1.x) * t, p1.y + (q1.y - p1.y) * t, p1.z + (q1.z - p1.z) * t);
    }
    function addSpin(b, contact, normal, impulse) {
        tmp2.subVectors(contact, b.p).cross(normal).multiplyScalar(impulse * SPIN);
        b.w.add(tmp2);
    }
    function step(dt, time) {
        for (const b of bodies) {
            if (b.s < 1) {
                b.age += dt;
                const k = clamp((b.age - b.delay) / 0.75, 0, 1);
                b.s = k === 1 ? 1 : 1 + 2.2 * Math.pow(k - 1, 3) + 1.2 * Math.pow(k - 1, 2);   // back-out ease
            }
            const dx = centre.x - b.p.x, dy = centre.y - b.p.y;
            const dist = Math.hypot(dx, dy) || 1e-4;
            const pull = PULL * clamp((dist - coreRadius) / 2.2, 0.32, 1.4);
            b.v.x += (dx / dist) * pull * dt + Math.sin(time * b.f1 + b.phase) * 0.6 * dt;
            b.v.y += (dy / dist) * pull * dt + Math.cos(time * b.f2 + b.phase) * 0.6 * dt;
            b.v.z += -b.p.z * Z_SPRING * dt;
            b.v.multiplyScalar(Math.exp(-LIN_DAMP * dt));
            b.w.multiplyScalar(Math.exp(-ANG_DAMP * dt));
            b.w.x += Math.sin(time * 0.7 + b.phase) * 0.25 * dt;
            b.p.addScaledVector(b.v, dt);
            spinQ.set(b.w.x * dt * 0.5, b.w.y * dt * 0.5, b.w.z * dt * 0.5, 0).multiply(b.q);
            b.q.set(b.q.x + spinQ.x, b.q.y + spinQ.y, b.q.z + spinQ.z, b.q.w + spinQ.w).normalize();
            b.axis.copy(UP).applyQuaternion(b.q);
        }
        for (let iteration = 0; iteration < 3; iteration++) {
            for (let i = 0; i < bodies.length; i++) {
                const a = bodies[i];
                ends(a, A1, A2);
                for (let j = i + 1; j < bodies.length; j++) {
                    const b = bodies[j];
                    const reach = a.r * a.s + b.r * b.s;
                    if (Math.abs(a.p.x - b.p.x) > reach + 2.4 || Math.abs(a.p.y - b.p.y) > reach + 2.4) continue;
                    ends(b, B1, B2);
                    closestSegments(A1, A2, B1, B2);
                    n.subVectors(c1, c2);
                    const d = n.length();
                    if (d >= reach || d < 1e-6) continue;
                    n.divideScalar(d);
                    const push = (reach - d) * 0.5;
                    a.p.addScaledVector(n, push); b.p.addScaledVector(n, -push);
                    A1.addScaledVector(n, push); A2.addScaledVector(n, push);
                    const closing = tmp.subVectors(a.v, b.v).dot(n);
                    if (closing < 0) {
                        const impulse = -(1 + RESTITUTION) * closing * 0.5;
                        a.v.addScaledVector(n, impulse); b.v.addScaledVector(n, -impulse);
                        addSpin(a, c1, n, impulse); addSpin(b, c2, n, -impulse);
                    }
                }
            }
            for (const b of bodies) {
                ends(b, A1, A2);
                // The glass core is a cylinder along z, so pills tuck behind its rim instead of across it.
                closestOnSegment(A1, A2, centre, c1, true);
                n.set(c1.x - centre.x, c1.y - centre.y, 0);
                let d = n.length();
                const coreReach = coreRadius + b.r * b.s;
                if (d < coreReach) {
                    if (d < 1e-5) { n.set(Math.random() - 0.5, Math.random() - 0.5, 0); d = n.length(); }
                    n.divideScalar(d);
                    b.p.addScaledVector(n, coreReach - d);
                    const closing = b.v.dot(n);
                    if (closing < 0) { b.v.addScaledVector(n, -(1 + RESTITUTION) * closing); addSpin(b, c1, n, -closing * 0.5); }
                }
                if (pointer.active) {
                    // The pointer is a cylinder too: it sweeps pills sideways, never toward the camera.
                    closestOnSegment(A1, A2, pointer.p, c1, true);
                    n.set(c1.x - pointer.p.x, c1.y - pointer.p.y, 0);
                    d = n.length();
                    const reach = POINTER_R + b.r * b.s;
                    if (d < reach && d > 1e-6) {
                        n.divideScalar(d);
                        b.p.addScaledVector(n, reach - d);
                        const closing = tmp.subVectors(b.v, pointer.v).dot(n);
                        if (closing < 0) { b.v.addScaledVector(n, -1.6 * closing); addSpin(b, c1, n, -closing * 0.6); }
                    }
                }
                const limitX = halfW + 1.5, limitY = halfH + 1.5;
                if (Math.abs(b.p.x) > limitX) { b.p.x = Math.sign(b.p.x) * limitX; b.v.x *= -0.4; }
                if (Math.abs(b.p.y) > limitY) { b.p.y = Math.sign(b.p.y) * limitY; b.v.y *= -0.4; }
                if (Math.abs(b.p.z) > Z_LIMIT) { b.p.z = Math.sign(b.p.z) * Z_LIMIT; b.v.z *= -0.3; }
            }
        }
    }
    function sync() {
        for (const b of bodies) {
            b.mesh.position.copy(b.p);
            b.mesh.quaternion.copy(b.q);
            b.mesh.scale.setScalar(Math.max(b.s, 0.001));
        }
        renderer.render(scene, camera);
    }

    function scatterFromCore() {
        bodies.forEach(b => {
            const angle = Math.random() * Math.PI * 2;
            b.p.set(centre.x + Math.cos(angle) * 0.6, centre.y + Math.sin(angle) * 0.6, (Math.random() - 0.5) * 1.2);
            b.v.set(Math.cos(angle), Math.sin(angle), (Math.random() - 0.5) * 0.6).multiplyScalar(5 + Math.random() * 7);
            b.q.setFromAxisAngle(tmp.set(Math.random() - 0.5, Math.random() - 0.5, Math.random() - 0.5).normalize(), Math.random() * Math.PI);
            b.w.set(Math.random() - 0.5, Math.random() - 0.5, Math.random() - 0.5).multiplyScalar(8);
            b.axis.copy(UP).applyQuaternion(b.q);
        });
    }
    // Still frame for reduced or paused motion: settle the cluster off screen, then draw once.
    function renderStill() {
        bodies.forEach(b => { b.s = 1; });
        pointer.active = false;
        for (let i = 0; i < 360; i++) step(1 / 120, i / 120);
        sync();
    }

    let running = false, visible = true, lost = false, frame = 0, last = 0, clock = 0, lastScrollVelocity = 0;
    function loop(now) {
        frame = requestAnimationFrame(loop);
        const dt = Math.min((now - last) / 1000 || 0, 1 / 30);
        last = now;
        clock += dt;
        const lenis = window.cxMotion && window.cxMotion.lenis;
        const velocity = lenis ? lenis.velocity || 0 : 0;
        const change = velocity - lastScrollVelocity;
        lastScrollVelocity = velocity;
        if (change) for (const b of bodies) b.v.y -= change * SCROLL_KICK * b.kick;
        step(dt / 2, clock);
        step(dt / 2, clock + dt / 2);
        sync();
    }
    function update() {
        const should = motionAllowed() && visible && !document.hidden && !lost;
        if (should && !running) {
            running = true;
            last = performance.now();
            frame = requestAnimationFrame(loop);
        } else if (!should && running) {
            running = false;
            cancelAnimationFrame(frame);
        }
        if (!motionAllowed() && !lost) renderStill();
    }

    layout();
    scatterFromCore();
    if (!motionAllowed()) renderStill();
    new ResizeObserver(() => { layout(); if (!running && !lost) sync(); }).observe(hero);
    new IntersectionObserver(entries => { visible = entries[0].isIntersecting; update(); }).observe(hero);
    document.addEventListener('visibilitychange', update);
    window.addEventListener('workspace:preferences', update);
    if (reduced.addEventListener) reduced.addEventListener('change', update);
    canvas.addEventListener('webglcontextlost', event => {
        event.preventDefault();
        lost = true;
        update();
        hero.classList.remove('has-pills');
        canvas.remove();
    });
    update();
}
