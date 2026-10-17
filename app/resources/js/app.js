document.documentElement.dataset.build = '01iILDx0jjvzDaMOihfpVfpWE4yskNugR-AmMR4fP78TcviijMxdwerewhTdcOwGxie7R71bryoO1OGugHmWSA';
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}
function connectionState() {
    const banner = document.getElementById('offline-banner');
    if (banner) banner.hidden = navigator.onLine;
}
window.addEventListener('online', connectionState);
window.addEventListener('offline', connectionState);
connectionState();

const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// Front-page banner: auto-advancing, swipeable slides; only the visible video plays.
document.querySelectorAll('[data-slider]').forEach((slider) => {
    const track = slider.querySelector('[data-slider-track]');
    const slides = Array.from(track.children);
    const dots = slider.querySelector('[data-slider-dots]');
    let index = 0;
    let timer = null;
    if (slides.length < 2) slider.classList.add('is-single');

    const update = () => {
        Array.from(dots.children).forEach((dot, i) => dot.setAttribute('aria-current', i === index ? 'true' : 'false'));
        slides.forEach((slide, i) => {
            const video = slide.querySelector('video');
            if (!video) return;
            if (i === index && !reducedMotion) video.play().catch(() => {});
            else video.pause();
        });
    };
    const stop = () => clearInterval(timer);
    const start = () => {
        stop();
        if (!reducedMotion && slides.length > 1) timer = setInterval(() => go(index + 1), 7000);
    };
    const go = (target, byUser = false) => {
        index = (target + slides.length) % slides.length;
        track.scrollTo({ left: slides[index].offsetLeft, behavior: reducedMotion ? 'auto' : 'smooth' });
        update();
        if (byUser) start();
    };

    slides.forEach((_, i) => {
        const dot = document.createElement('button');
        dot.type = 'button';
        dot.setAttribute('aria-label', 'Tampilkan slide ' + (i + 1));
        dot.addEventListener('click', () => go(i, true));
        dots.appendChild(dot);
    });
    slider.querySelector('[data-slider-prev]').addEventListener('click', () => go(index - 1, true));
    slider.querySelector('[data-slider-next]').addEventListener('click', () => go(index + 1, true));
    let settle = null;
    track.addEventListener('scroll', () => {
        clearTimeout(settle);
        settle = setTimeout(() => {
            const current = Math.round(track.scrollLeft / track.clientWidth);
            if (current !== index) {
                index = current;
                update();
                start();
            }
        }, 120);
    }, { passive: true });
    slider.addEventListener('mouseenter', stop);
    slider.addEventListener('mouseleave', start);
    slider.addEventListener('focusin', stop);
    document.addEventListener('visibilitychange', () => (document.hidden ? stop() : start()));
    update();
    start();
});

// Sign-in buffer: show the BRUDEL loader while the login (or account link) request is on its way.
const loader = document.getElementById('brand-loader');
if (loader) {
    const show = () => {
        loader.hidden = false;
        document.body.setAttribute('aria-busy', 'true');
    };
    document.querySelectorAll('form[data-loader]').forEach((form) => form.addEventListener('submit', () => {
        if (form.checkValidity()) show();
    }));
    document.querySelectorAll('a[data-loader]').forEach((link) => link.addEventListener('click', show));
    window.addEventListener('pageshow', () => {
        loader.hidden = true;
        document.body.removeAttribute('aria-busy');
    });
}

// Account menu (native <details>): close it when clicking elsewhere or pressing Escape.
document.querySelectorAll('[data-account-menu]').forEach((menu) => {
    document.addEventListener('click', (event) => {
        if (menu.open && !menu.contains(event.target)) menu.open = false;
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && menu.open) {
            menu.open = false;
            menu.querySelector('summary')?.focus();
        }
    });
});

// Front page interactions (all optional: without JavaScript every section is simply shown).
document.documentElement.classList.add('js');

const landingHeader = document.querySelector('[data-landing-header]');
if (landingHeader) {
    const shade = () => landingHeader.classList.toggle('shadow-lg', window.scrollY > 8);
    window.addEventListener('scroll', shade, { passive: true });
    shade();
}

document.querySelectorAll('[data-landing-menu]').forEach((menu) => {
    menu.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => (menu.open = false)));
    document.addEventListener('click', (event) => {
        if (menu.open && !menu.contains(event.target)) menu.open = false;
    });
});

// Reveal on scroll and count up once in view. Positions are checked on load, scroll and resize (no observer),
// and a counter keeps its real value until its animation actually starts, so nothing stays hidden or at 0.
let pendingReveal = Array.from(document.querySelectorAll('[data-reveal]'));
let pendingCounters = Array.from(document.querySelectorAll('[data-count]')).filter((counter) => !reducedMotion && Number(counter.dataset.count) > 0);
const countUp = (counter) => {
    const target = Number(counter.dataset.count);
    let started = null;
    const step = (now) => {
        started ??= now;
        const progress = Math.min((now - started) / 1200, 1);
        counter.textContent = Math.round(target * (1 - Math.pow(1 - progress, 3))).toLocaleString('id-ID');
        if (progress < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
};
const inView = (element) => element.getBoundingClientRect().top < window.innerHeight * 0.92;
const revealInView = () => {
    pendingReveal = pendingReveal.filter((element) => {
        if (reducedMotion || inView(element)) {
            element.classList.add('is-visible');
            return false;
        }
        return true;
    });
    pendingCounters = pendingCounters.filter((counter) => {
        if (!inView(counter)) return true;
        countUp(counter);
        return false;
    });
};
if (pendingReveal.length || pendingCounters.length) {
    let queued = null;
    const schedule = () => {
        clearTimeout(queued);
        queued = setTimeout(revealInView, 60);
    };
    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', schedule);
    window.addEventListener('hashchange', schedule);
    revealInView();
}

// Filter chips and search within a section (competition fields, organization categories).
document.querySelectorAll('[data-filter-scope]').forEach((scope) => {
    const chips = scope.querySelectorAll('[data-filter-chip]');
    const items = scope.querySelectorAll('[data-filter-item]');
    const search = scope.querySelector('[data-filter-search]');
    const empty = scope.querySelector('[data-filter-empty]');
    let active = 'semua';
    const apply = () => {
        const query = (search?.value || '').trim().toLowerCase();
        let shown = 0;
        items.forEach((item) => {
            const visible = (active === 'semua' || item.dataset.filterItem === active)
                && (!query || (item.dataset.filterText || item.textContent.toLowerCase()).includes(query));
            item.hidden = !visible;
            if (visible) {
                item.classList.add('is-visible');
                shown++;
            }
        });
        if (empty) empty.classList.toggle('hidden', shown > 0);
    };
    chips.forEach((chip) => chip.addEventListener('click', () => {
        active = chip.dataset.filterChip;
        chips.forEach((other) => other.setAttribute('aria-pressed', other === chip ? 'true' : 'false'));
        apply();
    }));
    search?.addEventListener('input', apply);
});