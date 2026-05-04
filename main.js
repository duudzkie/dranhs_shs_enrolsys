/* ═══════════════════════════════════════════════
   DRANHS School Website — Main JavaScript
   ═══════════════════════════════════════════════ */

document.addEventListener('DOMContentLoaded', () => {

  // ── Navbar scroll effect ────────────────────
  const navbar = document.getElementById('navbar');
  if (navbar) {
    window.addEventListener('scroll', () => {
      navbar.classList.toggle('scrolled', window.scrollY > 20);
    });
  }

  // ── Mobile menu toggle ──────────────────────
  const hamburger = document.getElementById('nav-hamburger');
  const mobileMenu = document.getElementById('nav-mobile');

  if (hamburger && mobileMenu) {
    hamburger.addEventListener('click', () => {
      hamburger.classList.toggle('open');
      mobileMenu.classList.toggle('open');
      document.body.style.overflow = mobileMenu.classList.contains('open') ? 'hidden' : '';
    });

    mobileMenu.querySelectorAll('.nav-link').forEach(link => {
      link.addEventListener('click', () => {
        hamburger.classList.remove('open');
        mobileMenu.classList.remove('open');
        document.body.style.overflow = '';
      });
    });
  }

  // ── Scroll reveal animations ────────────────
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.15 });

  document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

  // ── Counter animation for stats ─────────────
  const statsObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const el = entry.target;
        const target = parseInt(el.dataset.count, 10);
        const suffix = el.dataset.suffix || '';
        let current = 0;
        const step = Math.ceil(target / 80);

        function tick() {
          current += step;
          if (current >= target) {
            el.textContent = target.toLocaleString() + suffix;
          } else {
            el.textContent = current.toLocaleString() + suffix;
            requestAnimationFrame(tick);
          }
        }
        tick();
        statsObserver.unobserve(el);
      }
    });
  }, { threshold: 0.5 });

  document.querySelectorAll('.stat-number').forEach(el => statsObserver.observe(el));

  // ── Carousel ────────────────────────────────
  const track = document.getElementById('carousel-track');
  const dotsWrap = document.getElementById('carousel-dots');
  const prevBtn = document.getElementById('carousel-prev');
  const nextBtn = document.getElementById('carousel-next');

  if (track && dotsWrap) {
    const slides = track.children;
    const count = slides.length;
    let index = 0;
    let autoTimer = null;

    // Build dots
    for (let i = 0; i < count; i++) {
      const dot = document.createElement('button');
      dot.className = 'carousel-dot' + (i === 0 ? ' active' : '');
      dot.setAttribute('aria-label', 'Slide ' + (i + 1));
      dot.addEventListener('click', () => goTo(i));
      dotsWrap.appendChild(dot);
    }

    function goTo(i) {
      index = ((i % count) + count) % count;
      track.style.transform = 'translateX(-' + (index * 100) + '%)';
      dotsWrap.querySelectorAll('.carousel-dot').forEach((d, di) => {
        d.classList.toggle('active', di === index);
      });
      resetAuto();
    }

    function resetAuto() {
      clearInterval(autoTimer);
      autoTimer = setInterval(() => goTo(index + 1), 5000);
    }

    if (prevBtn) prevBtn.addEventListener('click', () => goTo(index - 1));
    if (nextBtn) nextBtn.addEventListener('click', () => goTo(index + 1));

    // Auto-play
    resetAuto();

    // Pause on hover
    const carousel = document.getElementById('carousel');
    if (carousel) {
      carousel.addEventListener('mouseenter', () => clearInterval(autoTimer));
      carousel.addEventListener('mouseleave', () => resetAuto());
    }

    // Swipe support
    let touchX = 0;
    track.addEventListener('touchstart', e => { touchX = e.touches[0].clientX; }, { passive: true });
    track.addEventListener('touchend', e => {
      const diff = touchX - e.changedTouches[0].clientX;
      if (Math.abs(diff) > 50) goTo(index + (diff > 0 ? 1 : -1));
    });
  }

});
