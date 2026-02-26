(function ($) {
    $(document).ready(() => {
        initializeArena();
        if (typeof gameScheduleInitializers !== 'undefined') {
            gameScheduleInitializers.push(initializeArena);
        }
    });
})(jQuery);

function initializeArena() {
    // Promo expand/collapse toggles
    document.querySelectorAll('.arena-promo-toggle:not([data-promo-init])').forEach(btn => {
        btn.setAttribute('data-promo-init', '1');
        btn.addEventListener('click', () => {
            const panel = btn.closest('.arena-row').querySelector('.arena-promo-panel');
            const isOpen = panel.classList.toggle('is-open');
            btn.classList.toggle('is-open', isOpen);
            btn.setAttribute('aria-expanded', String(isOpen));
        });
    });

    // Guard against double-init after AJAX reloads
    document.querySelectorAll('.arena-countdown[data-timestamp]:not([data-arena-cd-init])').forEach(function (el) {
        const raw = el.getAttribute('data-timestamp');
        if (!raw) return;

        const target = new Date(raw).getTime();
        if (isNaN(target)) return;

        el.setAttribute('data-arena-cd-init', '1');

        function tick() {
            const diff = target - Date.now();

            if (diff <= 0) {
                arenaSetUnit(el, 'days',  '0');
                arenaSetUnit(el, 'hours', '00');
                arenaSetUnit(el, 'mins',  '00');
                arenaSetUnit(el, 'secs',  '00');
                return;
            }

            const days  = Math.floor(diff / 86400000);
            const hours = Math.floor((diff % 86400000) / 3600000);
            const mins  = Math.floor((diff % 3600000)  / 60000);
            const secs  = Math.floor((diff % 60000)    / 1000);

            arenaSetUnit(el, 'days',  String(days));
            arenaSetUnit(el, 'hours', arenapad(hours));
            arenaSetUnit(el, 'mins',  arenapad(mins));
            arenaSetUnit(el, 'secs',  arenapad(secs));
        }

        tick();
        setInterval(tick, 1000);
    });
}

function arenaSetUnit(container, key, val) {
    const el = container.querySelector('[data-cd="' + key + '"]');
    if (el) el.textContent = val;
}

function arenapad(n) {
    return n < 10 ? '0' + n : String(n);
}
