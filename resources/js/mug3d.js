/*
 * The 3D mug on the configurator (mellowaura-design, „Kubek 3D”): matt speckled clay outside, the chosen glaze
 * inside and on the rim, the text stamped into the wall letter by letter. Loaded only on that page, and only
 * when WebGL works and the visitor has not asked for less motion; otherwise the photo preview stays.
 */
import {
    ACESFilmicToneMapping, CanvasTexture, CircleGeometry, Color, DirectionalLight, DoubleSide, Group, HemisphereLight,
    LatheGeometry, Mesh, MeshBasicMaterial, MeshStandardMaterial, PerspectiveCamera, PlaneGeometry, Scene,
    SRGBColorSpace, TorusGeometry, Vector2, WebGLRenderer,
} from 'three';

const HEIGHT = 2.1;
const WALL = 0.09;
const STEPS = 28;
const CANVAS_WIDTH = 2400;
const CANVAS_HEIGHT = 800;
const FONT = 'Newsreader, Georgia, serif';

// The mug narrows a little towards the base and swells slightly at two thirds of its height.
const radiusAt = (k) => 0.98 * (0.94 + 0.06 * Math.sin(k * Math.PI * 0.85));

export function webglWorks() {
    try {
        const canvas = document.createElement('canvas');

        return !! (canvas.getContext('webgl2') || canvas.getContext('webgl'));
    } catch {
        return false;
    }
}

export default function createMug(stage, { ink, glaze }) {
    const renderer = new WebGLRenderer({ antialias: true, alpha: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.toneMapping = ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.08;
    renderer.domElement.setAttribute('aria-hidden', 'true');
    renderer.domElement.className = 'absolute inset-0 size-full';
    stage.prepend(renderer.domElement);

    const scene = new Scene();
    const camera = new PerspectiveCamera(30, 1, 0.1, 100);
    camera.position.set(0, 1.9, 7.6);
    camera.lookAt(0, 1.02, 0);

    scene.add(new HemisphereLight(0xfff6ec, 0xb9a38a, 0.95));
    const key = new DirectionalLight(0xfff1e0, 1.15);
    key.position.set(3.5, 5, 4.5);
    scene.add(key);
    const rim = new DirectionalLight(0xd6a39c, 0.55);
    rim.position.set(-4, 2.5, -3);
    scene.add(rim);

    const outer = [];
    const inner = [];
    for (let i = 0; i <= STEPS; i++) {
        outer.push(new Vector2(radiusAt(i / STEPS), (i / STEPS) * HEIGHT));
    }
    for (let i = STEPS; i >= 2; i--) {
        inner.push(new Vector2(radiusAt(i / STEPS) - WALL, (i / STEPS) * HEIGHT));
    }

    // The wall's colour and a matching bump map: letters are pressed into the clay, so they sit lower.
    const colour = document.createElement('canvas');
    const bump = document.createElement('canvas');
    colour.width = bump.width = CANVAS_WIDTH;
    colour.height = bump.height = CANVAS_HEIGHT;
    const paint = colour.getContext('2d');
    const press = bump.getContext('2d');
    const wallTexture = new CanvasTexture(colour);
    wallTexture.colorSpace = SRGBColorSpace;
    wallTexture.anisotropy = renderer.capabilities.getMaxAnisotropy();
    const bumpTexture = new CanvasTexture(bump);

    const clay = new MeshStandardMaterial({ map: wallTexture, bumpMap: bumpTexture, bumpScale: 0.045, roughness: 0.9, metalness: 0 });
    const glazed = new MeshStandardMaterial({ color: new Color(glaze), roughness: 0.28, metalness: 0, side: DoubleSide });
    const plain = new MeshStandardMaterial({ color: 0xece2d2, roughness: 0.9 });

    const body = new Group();
    body.add(new Mesh(new LatheGeometry(outer, 128), clay));
    body.add(new Mesh(new LatheGeometry(inner, 96), glazed));

    const lip = new Mesh(new TorusGeometry(radiusAt(1) - WALL / 2, WALL / 2, 16, 128), glazed);
    lip.rotation.x = Math.PI / 2;
    lip.position.y = HEIGHT;
    body.add(lip);

    const floor = new Mesh(new CircleGeometry(radiusAt(2 / STEPS) - WALL, 64), glazed);
    floor.rotation.x = -Math.PI / 2;
    floor.position.y = (2 / STEPS) * HEIGHT;
    body.add(floor);

    const base = new Mesh(new CircleGeometry(radiusAt(0), 64), plain);
    base.rotation.x = Math.PI / 2;
    base.position.y = 0.001;
    body.add(base);

    const handle = new Mesh(new TorusGeometry(0.5, 0.115, 24, 64, Math.PI), plain);
    handle.rotation.z = Math.PI / 2;
    handle.scale.set(1, 1.18, 1);
    handle.position.set(-radiusAt(0.5) + 0.04, HEIGHT * 0.52, 0);
    body.add(handle);

    // The text is drawn around u = 0.5, which faces away from the lathe's start; half a turn brings it to the front.
    body.rotation.y = Math.PI;
    const holder = new Group();
    holder.add(body);
    scene.add(holder);

    const shade = document.createElement('canvas');
    shade.width = shade.height = 256;
    const shadeContext = shade.getContext('2d');
    const gradient = shadeContext.createRadialGradient(128, 128, 0, 128, 128, 128);
    gradient.addColorStop(0, 'rgba(47,38,32,.42)');
    gradient.addColorStop(1, 'rgba(47,38,32,0)');
    shadeContext.fillStyle = gradient;
    shadeContext.fillRect(0, 0, 256, 256);
    const shadow = new Mesh(new PlaneGeometry(3.6, 3.6), new MeshBasicMaterial({ map: new CanvasTexture(shade), transparent: true, depthWrite: false }));
    shadow.rotation.x = -Math.PI / 2;
    shadow.position.y = -0.01;
    scene.add(shadow);

    // Speckles come from a fixed seed, so the clay looks the same after every redraw.
    let seed = 7;
    const random = () => {
        seed = (seed * 16807) % 2147483647;

        return (seed - 1) / 2147483646;
    };
    const specks = Array.from({ length: 1600 }, () => [random() * CANVAS_WIDTH, random() * CANVAS_HEIGHT, 0.6 + random() * 2.4, 0.12 + random() * 0.35]);

    let lines = [];
    let shown = 0;
    let inkColour = ink;

    const draw = () => {
        paint.fillStyle = '#EFE6D8';
        paint.fillRect(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT);
        const shading = paint.createLinearGradient(0, 0, 0, CANVAS_HEIGHT);
        shading.addColorStop(0, 'rgba(114,100,86,.06)');
        shading.addColorStop(1, 'rgba(114,100,86,.14)');
        paint.fillStyle = shading;
        paint.fillRect(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT);
        press.fillStyle = '#8a8a8a';
        press.fillRect(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT);

        specks.forEach(([x, y, r, alpha]) => {
            paint.fillStyle = `rgba(114,100,86,${alpha})`;
            paint.beginPath();
            paint.arc(x, y, r, 0, Math.PI * 2);
            paint.fill();
            press.fillStyle = `rgba(60,60,60,${alpha * 0.8})`;
            press.beginPath();
            press.arc(x, y, r, 0, Math.PI * 2);
            press.fill();
        });

        if (lines.length) {
            // The front third of the wall holds the text; the size shrinks until the longest line fits.
            let size = 150;
            paint.font = `400 ${size}px ${FONT}`;
            const widest = Math.max(...lines.map((line) => paint.measureText(line).width * 1.08));
            size = Math.min(150, (size * 720) / Math.max(widest, 1), 460 / lines.length);
            const lineHeight = size * 1.08;
            const top = CANVAS_HEIGHT * 0.52 - ((lines.length - 1) * lineHeight) / 2;
            let count = 0;

            paint.font = press.font = `400 ${size}px ${FONT}`;
            paint.textBaseline = press.textBaseline = 'middle';
            paint.textAlign = press.textAlign = 'center';

            lines.forEach((line, row) => {
                const letters = Array.from(line);
                const gap = size * 0.07;
                const widths = letters.map((letter) => paint.measureText(letter).width);
                let x = CANVAS_WIDTH / 2 - (widths.reduce((sum, w) => sum + w, 0) + gap * (letters.length - 1)) / 2;

                letters.forEach((letter, column) => {
                    const centre = x + widths[column] / 2;
                    x += widths[column] + gap;
                    if (count++ >= shown || letter === ' ') {
                        return;
                    }
                    // Every stamp lands a little crooked, the same way each time for the same letter.
                    const wobble = ((letter.charCodeAt(0) * 31 + column * 17 + row * 7) % 100) / 100;
                    const y = top + row * lineHeight + (wobble - 0.5) * size * 0.05;
                    [
                        [paint, 'rgba(252,249,244,.95)', size * 0.028],
                        [paint, 'rgba(47,38,32,.5)', -size * 0.022],
                        [paint, inkColour, 0],
                        [press, '#2a2a2a', 0],
                    ].forEach(([context, fill, offset]) => {
                        context.save();
                        context.translate(centre, y + offset);
                        context.rotate((wobble - 0.5) * 0.07);
                        context.fillStyle = fill;
                        context.fillText(letter, 0, 0);
                        context.restore();
                    });
                });
            });
        }

        wallTexture.needsUpdate = true;
        bumpTexture.needsUpdate = true;
    };

    // The mug gives a little under each stamp.
    let squash = 0;
    let timer = null;
    const total = () => lines.reduce((sum, line) => sum + Array.from(line).length, 0);

    const setText = (next, animate) => {
        const before = total();
        lines = next.map((line) => line.toUpperCase()).filter((line, i, all) => line.trim() !== '' || i < all.length - 1);
        clearInterval(timer);

        // New letters at the end stamp one by one; anything else (a deletion, a preset) redraws at once.
        if (! animate || total() <= before) {
            shown = total();
            draw();

            return;
        }

        shown = before;
        timer = setInterval(() => {
            shown++;
            squash = 1;
            draw();
            if (shown >= total()) {
                clearInterval(timer);
            }
        }, 70);
    };

    const setGlaze = (hex) => glazed.color.set(hex);
    const setInk = (hex) => {
        inkColour = hex;
        draw();
    };

    const resize = () => {
        const { clientWidth: width, clientHeight: height } = stage;
        if (! width || ! height) {
            return;
        }
        renderer.setSize(width, height, false);
        camera.aspect = width / height;
        camera.position.z = width / height < 0.8 ? 9.6 : 7.6;
        camera.updateProjectionMatrix();
    };
    resize();
    new ResizeObserver(resize).observe(stage);

    // Turning: by itself, after a drag, and a little with the page scroll; it drifts back to the text after a pause.
    let dragging = false;
    let lastX = 0;
    let offset = 0;
    let speed = 0;
    let touchedAt = 0;
    let tiltTarget = 0;
    let tilt = 0;
    let visible = false;

    stage.addEventListener('pointerdown', (event) => {
        dragging = true;
        lastX = event.clientX;
        stage.setPointerCapture(event.pointerId);
    });
    stage.addEventListener('pointermove', (event) => {
        const box = stage.getBoundingClientRect();
        tiltTarget = ((event.clientY - box.top) / box.height - 0.5) * 0.25;
        if (dragging) {
            speed = (event.clientX - lastX) * 0.012;
            lastX = event.clientX;
            offset += speed;
            touchedAt = performance.now();
        }
    });
    ['pointerup', 'pointercancel'].forEach((name) => stage.addEventListener(name, () => (dragging = false)));
    stage.addEventListener('pointerleave', () => (tiltTarget = 0));

    const frame = (time) => {
        if (! visible) {
            return;
        }
        if (! dragging) {
            offset += speed;
            speed *= 0.92;
            if (performance.now() - touchedAt > 2600) {
                offset *= 0.97;
            }
        }
        const scroll = (stage.getBoundingClientRect().top / window.innerHeight - 0.3) * -0.9;
        holder.rotation.y = Math.sin(time / 1600) * 0.4 + offset + scroll;
        tilt += (tiltTarget - tilt) * 0.08;
        holder.rotation.x = tilt;
        holder.position.y = Math.sin(time / 1100) * 0.04;
        squash *= 0.82;
        body.scale.set(1 + squash * 0.012, 1 - squash * 0.025, 1 + squash * 0.012);
        renderer.render(scene, camera);
        requestAnimationFrame(frame);
    };

    new IntersectionObserver(([entry]) => {
        visible = entry.isIntersecting;
        if (visible) {
            requestAnimationFrame(frame);
        }
    }).observe(stage);

    // The canvas can draw Newsreader only once the font is in; until then the letters would be Georgia.
    document.fonts?.load(`400 120px ${FONT}`).then(draw, draw);
    draw();

    return { setText, setGlaze, setInk };
}
