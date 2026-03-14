/* ============================================================
   BNS Transport — script.js
   ============================================================ */

(function () {
  'use strict';

  /* ---------- Header scroll shadow ---------- */
  const header = document.getElementById('header');

  function onScroll() {
    header.classList.toggle('scrolled', window.scrollY > 10);
    backToTop.classList.toggle('visible', window.scrollY > 400);
  }

  window.addEventListener('scroll', onScroll, { passive: true });

  /* ---------- Mobile nav toggle ---------- */
  const navToggle = document.getElementById('navToggle');
  const mainNav   = document.getElementById('mainNav');

  navToggle.addEventListener('click', function () {
    const isOpen = mainNav.classList.toggle('open');
    navToggle.classList.toggle('open', isOpen);
    navToggle.setAttribute('aria-expanded', String(isOpen));
  });

  /* Close nav when a link is clicked */
  mainNav.querySelectorAll('.nav__link').forEach(function (link) {
    link.addEventListener('click', function () {
      mainNav.classList.remove('open');
      navToggle.classList.remove('open');
      navToggle.setAttribute('aria-expanded', 'false');
    });
  });

  /* Close nav on outside click */
  document.addEventListener('click', function (e) {
    if (!header.contains(e.target)) {
      mainNav.classList.remove('open');
      navToggle.classList.remove('open');
      navToggle.setAttribute('aria-expanded', 'false');
    }
  });

  /* ---------- Active nav link on scroll ---------- */
  const sections = document.querySelectorAll('section[id]');
  const navLinks  = document.querySelectorAll('.nav__link[href^="#"]');

  const observer = new IntersectionObserver(
    function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          const id = entry.target.getAttribute('id');
          navLinks.forEach(function (link) {
            link.classList.toggle('active', link.getAttribute('href') === '#' + id);
          });
        }
      });
    },
    { rootMargin: '-40% 0px -55% 0px' }
  );

  sections.forEach(function (section) { observer.observe(section); });

  /* ---------- Back to top ---------- */
  const backToTop = document.getElementById('backToTop');

  /* ---------- Contact form ---------- */
  const form        = document.getElementById('contactForm');
  const formSuccess = document.getElementById('formSuccess');

  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();

      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }

      const submitBtn = form.querySelector('[type="submit"]');
      const originalText = submitBtn.textContent;

      submitBtn.disabled = true;
      submitBtn.textContent = 'Envoi en cours…';

      /* Simulate async send (replace with real fetch/API call) */
      setTimeout(function () {
        form.reset();
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
        formSuccess.hidden = false;
        formSuccess.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

        setTimeout(function () {
          formSuccess.hidden = true;
        }, 8000);
      }, 1200);
    });
  }

  /* ---------- Smooth reveal on scroll ---------- */
  const revealEls = document.querySelectorAll(
    '.service-card, .stat-card, .zone-item, .contact__details li'
  );

  if ('IntersectionObserver' in window) {
    const revealObserver = new IntersectionObserver(
      function (entries, obs) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.style.animationPlayState = 'running';
            entry.target.classList.add('revealed');
            obs.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.12 }
    );

    revealEls.forEach(function (el, i) {
      el.style.opacity       = '0';
      el.style.transform     = 'translateY(20px)';
      el.style.transition    = 'opacity .5s ease ' + (i * 0.07) + 's, transform .5s ease ' + (i * 0.07) + 's';
      revealObserver.observe(el);
    });
  }

  /* Trigger .revealed to animate in */
  document.addEventListener('scroll', function () {}, { passive: true });

  /* Patch: once revealed class is added, apply final state */
  const styleSheet = document.createElement('style');
  styleSheet.textContent = '.revealed { opacity: 1 !important; transform: none !important; }';
  document.head.appendChild(styleSheet);

})();
