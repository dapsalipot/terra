import './bootstrap';

// Numbers that count up the first time they scroll into view. Without JS the real figure is already in the markup.
const countUp = (el) => {
    const target = Number(el.dataset.count);
    if (! Number.isFinite(target) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const start = performance.now();
    const tick = (now) => {
        const t = Math.min((now - start) / 1300, 1);
        el.textContent = Math.round(target * (1 - Math.pow(1 - t, 4))).toLocaleString();
        if (t < 1) requestAnimationFrame(tick);
    };

    el.textContent = '0';
    requestAnimationFrame(tick);
};

const countObserver = new IntersectionObserver(
    (entries) => entries.forEach((e) => {
        if (e.isIntersecting) {
            countUp(e.target);
            countObserver.unobserve(e.target);
        }
    }),
    { threshold: 0.6 },
);

const observeCounts = () => document.querySelectorAll('[data-count]').forEach((el) => countObserver.observe(el));

observeCounts();
document.addEventListener('livewire:navigated', observeCounts);
