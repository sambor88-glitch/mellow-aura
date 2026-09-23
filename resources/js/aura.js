/*
 * Aura motion (mellowaura-design, „Efekty — katalog”). Every effect looks for its own element and does nothing
 * without it, so one script serves every page. Nothing here hides content: without GSAP, with reduced motion
 * or before this runs, each page is complete and still.
 */
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import Lenis from 'lenis';

const INTRO_SEEN = 'ma-intro';
// The page names the phases in its own language (data-phases); these Polish ones are only the fallback.
const INTRO_PHASES = [[0, 'surowa glina'], [28, 'suszenie'], [55, 'pierwszy wypał'], [82, 'szkliwo i drugi wypał']];

export default function aura() {
    const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
    const fine = matchMedia('(pointer: fine)').matches;
    const root = document.documentElement;

    if (reduce) {
        return;
    }

    root.classList.add('motion');
    if (fine) {
        root.classList.add('fine');
    }

    gsap.registerPlugin(ScrollTrigger);

    // Smooth scrolling, but a modal dialog (the cart, a zoomed photo) scrolls on its own.
    const lenis = new Lenis({ lerp: 0.085, prevent: (node) => !!node.closest?.('dialog, [data-lenis-prevent]') });
    lenis.on('scroll', ScrollTrigger.update);
    gsap.ticker.add((time) => lenis.raf(time * 1000));
    gsap.ticker.lagSmoothing(0);
    window.auraLenis = lenis;

    intro(lenis);
    header();
    hero(lenis);
    words();
    rows();
    shelf(lenis);
    parallax();
    rise();
    giant();
    flyToCart();

    if (fine) {
        pointer();
        zoomFollow();
    }

    addEventListener('load', () => ScrollTrigger.refresh());
}

/* Firing intro: only on the first visit, only on a page that has it, never when arriving at an anchor. */
function intro(lenis) {
    const el = document.getElementById('aura-intro');
    if (! el) {
        return;
    }

    let seen = false;
    try {
        seen = localStorage.getItem(INTRO_SEEN) === '1';
    } catch {
        // Private windows may refuse storage; then the intro shows once per visit.
    }
    if (seen || location.hash) {
        return;
    }

    const root = document.documentElement;
    const num = el.querySelector('[data-intro-number]');
    const phase = el.querySelector('[data-intro-phase]');
    const bar = el.querySelector('[data-intro-bar]');
    const counter = { value: 0 };
    let phases = INTRO_PHASES;
    try {
        phases = JSON.parse(el.dataset.phases) || INTRO_PHASES;
    } catch {
        // No or broken data-phases: keep the Polish ones.
    }

    const finish = () => {
        el.hidden = true;
        root.classList.remove('intro-on');
        lenis.start();
        try {
            localStorage.setItem(INTRO_SEEN, '1');
        } catch {
            // See above.
        }
    };

    el.hidden = false;
    root.classList.add('intro-on');
    lenis.stop();

    const timeline = gsap.timeline({ onComplete: finish });
    timeline
        .to(el.querySelector('.aura-heat'), { opacity: 1, scale: 1.15, duration: 1.5, ease: 'power2.in' }, 0)
        .to(counter, {
            value: 100,
            duration: 1.6,
            ease: 'power2.inOut',
            onUpdate() {
                const v = Math.round(counter.value);
                num.textContent = v;
                gsap.set(bar, { scaleX: counter.value / 100 });
                const current = [...phases].reverse().find(([from]) => v >= from)[1];
                if (phase.textContent !== current) {
                    phase.textContent = current;
                }
            },
        }, 0)
        .to(el.querySelector('.aura-heat'), { opacity: 0, scale: 1.6, duration: 0.6, ease: 'power2.out' }, 1.65)
        .to(el.querySelector('[data-intro-core]'), { opacity: 0, filter: 'blur(14px)', scale: 1.06, duration: 0.55, ease: 'power2.in' }, 1.7)
        .to(el, { clipPath: 'inset(0 0 100% 0)', duration: 0.8, ease: 'power4.inOut' }, 2.05);

    el.querySelector('[data-intro-skip]').addEventListener('click', () => timeline.progress(1));
}

/* The header pill slides away while scrolling down and comes back on the way up or with keyboard focus. */
function header() {
    const el = document.querySelector('[data-aura-header]');
    if (! el) {
        return;
    }

    ScrollTrigger.create({
        start: 0,
        end: 'max',
        onUpdate: (self) => el.classList.toggle('is-away', self.direction === 1 && self.scroll() > 240),
    });
    el.addEventListener('focusin', () => el.classList.remove('is-away'));
}

/* Home hero: the framed photo grows to the whole screen, the word mark blurs apart, then the copy comes in. */
function hero(lenis) {
    const el = document.getElementById('aura-hero');
    if (! el) {
        return;
    }

    const small = matchMedia('(max-width: 760px)').matches;
    // The copy fits over the photo only on a screen big enough; elsewhere it stays under the scene, always readable.
    const overlay = matchMedia('(min-width: 761px) and (min-height: 700px)').matches;
    const frame = el.querySelector('.aura-frame');
    const scene = el.querySelector('.aura-scene');
    const copy = el.querySelector('[data-hero-copy]');
    const copyParts = copy.querySelectorAll(':scope > *');

    document.documentElement.classList.toggle('hero-overlay', overlay);
    if (overlay) {
        gsap.set(copyParts, { opacity: 0, y: 50, filter: 'blur(12px)' });
    }

    const timeline = gsap.timeline({
        defaults: { ease: 'none' },
        scrollTrigger: overlay
            ? { trigger: el, start: 'top top', end: 'bottom bottom', scrub: 1.1 }
            : { trigger: scene, start: 'top top', end: 'bottom top', scrub: 0.8 },
    });

    timeline
        .fromTo(frame, { clipPath: small ? 'inset(24% 14% 26% 14% round 18px)' : 'inset(22% 37% 22% 37% round 18px)' },
            { clipPath: 'inset(0% 0% 0% 0% round 0px)', duration: 0.55, ease: 'power2.inOut' }, 0)
        .fromTo(frame.querySelector('img'), { scale: 1.28 }, { scale: 1, duration: 0.6 }, 0)
        .to(el.querySelector('.aura-mark-1 > span'), { yPercent: -120, xPercent: -18, opacity: 0, filter: 'blur(16px)', letterSpacing: '.08em', duration: 0.38 }, 0)
        .to(el.querySelector('.aura-mark-2 > span'), { yPercent: 120, xPercent: 18, opacity: 0, filter: 'blur(16px)', letterSpacing: '.08em', duration: 0.38 }, 0)
        .to(el.querySelector('[data-hero-meta]'), { opacity: 0, y: 20, duration: 0.12 }, 0)
        .to(el.querySelector('.aura-scrim'), { opacity: overlay ? 1 : 0.35, duration: 0.25 }, 0.4);

    el.querySelectorAll('.aura-float').forEach((float) => {
        const speed = Number(float.dataset.speed || 1);
        const left = float.getBoundingClientRect().left < innerWidth / 2;
        timeline.to(float, { y: `${-speed * 38}vh`, x: `${(left ? -1 : 1) * speed * 8}vw`, scale: 0.7, opacity: 0, filter: 'blur(8px)', duration: 0.5 }, 0);
    });

    if (! overlay) {
        return;
    }

    timeline.to(copyParts, { opacity: 1, y: 0, filter: 'blur(0px)', stagger: 0.035, duration: 0.18 }, 0.62).to({}, { duration: 0.12 });

    // A keyboard user lands on the copy: jump to the end of the scene where it is visible.
    copy.addEventListener('focusin', () => {
        const trigger = timeline.scrollTrigger;
        if (trigger.progress < 0.85) {
            lenis.scrollTo(trigger.start + (trigger.end - trigger.start) * 0.92, { immediate: true });
        }
    });
}

/* A paragraph whose words sharpen one by one as it scrolls in. */
function words() {
    document.querySelectorAll('[data-aura-words]').forEach((el) => {
        // A screen reader gets the whole sentence; the words that sharpen are only the picture of it.
        const text = el.textContent.trim();
        const spoken = document.createElement('span');
        const shown = document.createElement('span');
        spoken.className = 'sr-only';
        spoken.textContent = text;
        shown.setAttribute('aria-hidden', 'true');
        text.split(/\s+/).forEach((word, i, all) => {
            const span = document.createElement('span');
            span.className = 'inline-block';
            span.textContent = word;
            shown.append(span, i < all.length - 1 ? ' ' : '');
        });
        el.replaceChildren(spoken, shown);

        gsap.fromTo(shown.querySelectorAll('span'), { opacity: 0.14, filter: 'blur(5px)' }, {
            opacity: 1, filter: 'blur(0px)', stagger: 0.05, ease: 'none',
            scrollTrigger: { trigger: el, start: 'top 82%', end: 'bottom 45%', scrub: true },
        });
    });
}

function rows() {
    document.querySelectorAll('[data-aura-row]').forEach((row) => {
        const toLeft = row.dataset.auraRow === 'left';
        gsap.fromTo(row, { xPercent: toLeft ? 0 : -33.33 }, {
            xPercent: toLeft ? -33.33 : 0, ease: 'none',
            scrollTrigger: { trigger: row.parentElement, start: 'top bottom', end: 'bottom top', scrub: 0.6 },
        });
    });
}

/* The shop shelf on a wide screen: the section stays put and the cards move sideways, leaning with the speed. */
function shelf(lenis) {
    const section = document.querySelector('[data-aura-shelf]');
    if (! section) {
        return;
    }

    const track = section.querySelector('[data-shelf-track]');
    const wrap = section.querySelector('.aura-track-wrap');
    const progress = section.querySelector('[data-shelf-progress]');

    gsap.matchMedia().add('(min-width: 768px)', () => {
        const distance = () => Math.max(0, track.scrollWidth - wrap.clientWidth);
        if (! distance()) {
            return;
        }

        const lean = gsap.quickTo(track.querySelectorAll('[data-shelf-card]'), 'skewX', { duration: 0.5, ease: 'power3' });
        const tween = gsap.to(track, {
            x: () => -distance(),
            ease: 'none',
            scrollTrigger: {
                trigger: section,
                start: 'top top',
                end: () => `+=${distance()}`,
                pin: true,
                scrub: 1,
                invalidateOnRefresh: true,
                onUpdate: (self) => {
                    progress && gsap.set(progress, { scaleX: self.progress });
                    lean(gsap.utils.clamp(-6, 6, self.getVelocity() / -260));
                },
                onScrubComplete: () => lean(0),
            },
        });

        // Tabbing to a card that is still off to the side scrolls the page until it comes into view.
        const onFocus = (event) => {
            const card = event.target.closest('[data-shelf-card]');
            if (! card) {
                return;
            }
            const trigger = tween.scrollTrigger;
            const share = gsap.utils.clamp(0, 1, (card.offsetLeft - innerWidth * 0.3) / distance());
            wrap.scrollLeft = 0;
            lenis.scrollTo(trigger.start + (trigger.end - trigger.start) * share, { immediate: true });
        };
        track.addEventListener('focusin', onFocus);

        return () => {
            track.removeEventListener('focusin', onFocus);
            gsap.set(track, { x: 0 });
        };
    });
}

/* data-aura-parallax="8": the photo drifts by that many percent and settles from a slight zoom. */
function parallax() {
    document.querySelectorAll('[data-aura-parallax]').forEach((img) => {
        const shift = Number(img.dataset.auraParallax || 8);
        gsap.fromTo(img, { yPercent: -shift, scale: 1.12 }, {
            yPercent: shift, scale: 1, ease: 'none',
            scrollTrigger: { trigger: img.parentElement, start: 'top bottom', end: 'bottom top', scrub: true },
        });
    });
}

/* data-aura-rise: children come up out of a blur once, the first time the block scrolls into view. */
function rise() {
    document.querySelectorAll('[data-aura-rise]').forEach((group) => {
        gsap.from(group.children, {
            y: 60, opacity: 0, filter: 'blur(10px)', stagger: 0.08, duration: 1.1, ease: 'power3.out', clearProps: 'filter,opacity,transform',
            scrollTrigger: { trigger: group, start: 'top 88%', once: true },
        });
    });
}

function giant() {
    const el = document.querySelector('[data-aura-giant]');
    if (! el) {
        return;
    }

    gsap.from(el.querySelectorAll('span'), {
        yPercent: 100, opacity: 0, stagger: 0.04, ease: 'power3.out',
        scrollTrigger: { trigger: el, start: 'top bottom', end: 'bottom bottom', scrub: 1 },
    });
}

/* Everything that follows a mouse: the floating hero photos, the rose glow, magnets, liquid fill, wet clay. */
function pointer() {
    const floats = [...document.querySelectorAll('.aura-float')].map((float) => {
        const inner = float.firstElementChild;
        return {
            depth: Number(float.dataset.depth || 30),
            x: gsap.quickTo(inner, 'x', { duration: 1.2, ease: 'power3' }),
            y: gsap.quickTo(inner, 'y', { duration: 1.2, ease: 'power3' }),
        };
    });

    const glow = document.querySelector('.aura-cursor');
    const glowX = glow && gsap.quickTo(glow, 'x', { duration: 0.9, ease: 'power3' });
    const glowY = glow && gsap.quickTo(glow, 'y', { duration: 0.9, ease: 'power3' });

    addEventListener('pointermove', (event) => {
        const nx = event.clientX / innerWidth - 0.5;
        const ny = event.clientY / innerHeight - 0.5;
        floats.forEach((f) => {
            f.x(-nx * f.depth);
            f.y(-ny * f.depth);
        });
        if (glow) {
            glowX(event.clientX);
            glowY(event.clientY);
            glow.style.opacity = 1;
        }
    }, { passive: true });
    document.addEventListener('pointerleave', () => glow && (glow.style.opacity = 0));

    document.querySelectorAll('[data-magnet]').forEach((el) => {
        const x = gsap.quickTo(el, 'x', { duration: 0.6, ease: 'power3' });
        const y = gsap.quickTo(el, 'y', { duration: 0.6, ease: 'power3' });
        el.addEventListener('pointermove', (event) => {
            const box = el.getBoundingClientRect();
            x((event.clientX - box.left - box.width / 2) * 0.25);
            y((event.clientY - box.top - box.height / 2) * 0.35);
        });
        el.addEventListener('pointerleave', () => {
            x(0);
            y(0);
        });
    });

    // The fill of .fill-btn starts where the mouse enters and drains where it leaves.
    const origin = (event) => {
        const button = event.target.closest?.('.fill-btn');
        if (! button || button.contains(event.relatedTarget)) {
            return;
        }
        const box = button.getBoundingClientRect();
        button.style.setProperty('--mx', `${event.clientX - box.left}px`);
        button.style.setProperty('--my', `${event.clientY - box.top}px`);
    };
    document.addEventListener('pointerover', origin);
    document.addEventListener('pointerout', origin);

    wetClay();
}

/* [data-clay] marks a photo host; its images ripple while the mouse moves and settle when it stops. */
function wetClay() {
    const turbulence = document.getElementById('clay-noise');
    const displacement = document.getElementById('clay-map');
    if (! turbulence || ! displacement) {
        return;
    }

    let energy = 0;
    let running = false;
    let active = [];
    let last = null;

    const tick = (time) => {
        energy *= 0.93;
        displacement.setAttribute('scale', (energy * 34).toFixed(2));
        turbulence.setAttribute('baseFrequency', `${(0.009 + Math.sin(time / 900) * 0.003).toFixed(4)} ${(0.014 + Math.cos(time / 1100) * 0.004).toFixed(4)}`);
        if (energy < 0.02) {
            running = false;
            displacement.setAttribute('scale', '0');
            active.forEach((img) => img.classList.remove('clay-on'));
            active = [];
            return;
        }
        requestAnimationFrame(tick);
    };

    document.addEventListener('pointermove', (event) => {
        const host = event.target.closest?.('[data-clay]');
        if (! host) {
            last = null;
            return;
        }
        host.querySelectorAll('img').forEach((img) => {
            if (! active.includes(img)) {
                active.push(img);
                img.classList.add('clay-on');
            }
        });
        const speed = last ? Math.hypot(event.clientX - last[0], event.clientY - last[1]) : 0;
        last = [event.clientX, event.clientY];
        energy = Math.min(1.3, energy + 0.12 + speed * 0.012);
        if (! running) {
            running = true;
            requestAnimationFrame(tick);
        }
    }, { passive: true });
}

/* „Dodaj do koszyka”: a copy of the photo flies into the cart button and blurs away (mellowaura-design, „Lot do koszyka”). */
function flyToCart() {
    document.querySelectorAll('form[data-fly-to-cart]').forEach((form) => {
        form.addEventListener('submit', () => {
            const photo = [...document.querySelectorAll('[data-zoom-follow] img')].find((img) => img.offsetParent !== null && getComputedStyle(img).display !== 'none');
            const cart = document.querySelector('button[aria-controls="cart"]');
            if (! photo || ! cart) {
                return;
            }

            const from = photo.getBoundingClientRect();
            const to = cart.getBoundingClientRect();
            const flyer = photo.cloneNode();
            flyer.removeAttribute('srcset');
            flyer.removeAttribute('x-show');
            flyer.alt = '';
            Object.assign(flyer.style, { position: 'fixed', zIndex: 99, margin: 0, objectFit: 'cover', pointerEvents: 'none', borderRadius: '26px' });
            document.body.append(flyer);
            gsap.fromTo(flyer, { left: from.left, top: from.top, width: from.width, height: from.height }, {
                left: to.right - 30, top: to.top + 8, width: 28, height: 28, borderRadius: '50%', opacity: 0.2, filter: 'blur(3px)',
                duration: 0.9, ease: 'power3.inOut', onComplete: () => flyer.remove(),
            });
        });
    });
}

/* A product photo zooms in slightly and follows the mouse, so the texture of the glaze can be looked at. */
function zoomFollow() {
    document.querySelectorAll('[data-zoom-follow]').forEach((stage) => {
        stage.addEventListener('pointermove', (event) => {
            const box = stage.getBoundingClientRect();
            gsap.to(stage.querySelectorAll('img'), {
                scale: 1.12,
                xPercent: -((event.clientX - box.left) / box.width - 0.5) * 8,
                yPercent: -((event.clientY - box.top) / box.height - 0.5) * 8,
                duration: 0.8, ease: 'power3', overwrite: 'auto',
            });
        });
        stage.addEventListener('pointerleave', () => gsap.to(stage.querySelectorAll('img'), { scale: 1, xPercent: 0, yPercent: 0, duration: 1, ease: 'power3', overwrite: 'auto' }));
    });
}
