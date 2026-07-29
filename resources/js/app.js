import './bootstrap';

// Scroll-triggered reveals: elements with [data-reveal] fade/rise in when they enter the viewport.
const revealObserver = new IntersectionObserver(
    (entries) => entries.forEach((e) => {
        if (e.isIntersecting) {
            e.target.classList.add('in-view');
            revealObserver.unobserve(e.target);
        }
    }),
    { threshold: 0.12, rootMargin: '0px 0px -40px 0px' },
);

const observeReveals = () => document.querySelectorAll('[data-reveal]:not(.in-view)').forEach((el) => revealObserver.observe(el));

observeReveals();
document.addEventListener('livewire:navigated', observeReveals);

// Transparent nav (photo-hero pages) turns solid once the hero starts scrolling away.
const bindNavScroll = () => {
    const nav = document.querySelector('.site-nav[data-nav="transparent"]');
    if (! nav) return;

    const sync = () => nav.classList.toggle('nav-solid', window.scrollY > 60);
    sync();
    window.addEventListener('scroll', sync, { passive: true });
};

bindNavScroll();
document.addEventListener('livewire:navigated', bindNavScroll);
