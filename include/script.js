/* /include/script.js */
(() => {
  'use strict';

  /* ========= Helpers ========= */
  const $  = (sel, root=document) => root.querySelector(sel);
  const $$ = (sel, root=document) => Array.from(root.querySelectorAll(sel));

  function injectStyle(css) {
    const s = document.createElement('style');
    s.textContent = css;
    document.head.appendChild(s);
  }

  /* ========= Styles spécifiques (déplacés depuis les pages) ========= */
  injectStyle(`
    /* TD7 tables */
    #td7-table td, #td7-table th { padding:8px; border:1px solid #e6e6e6; text-align:left; }
    #td7-table th { background:#f3f4f6; font-weight:600; }
    #td7-html-fragment table { width:100%; border-collapse:collapse; }
    #td7-html-fragment td, #td7-html-fragment th { padding:8px; border:1px solid #e6e6e6; text-align:left; }
    #td7-html-fragment th { background:#f3f4f6; font-weight:600; }

    /* TD6 tables */
    #ajax-table td, #ajax-table th,
    #departement-table td, #departement-table th {
      padding:8px; border:1px solid #e6e6e6; text-align:left;
    }
    #ajax-table th, #departement-table th {
      background:#f3f4f6; font-weight:600;
    }
    .exo3-table td, .exo3-table th { padding:8px; border:1px solid #e6e6e6; }
  `);

  /* ========= Index : horloge ========= */
  function initClock() {
    const el = document.getElementById('clock');
    if (!el) return;
    function updateClock() {
      const now = new Date();
      const formatted = now.toLocaleString("fr-FR", {
        weekday: "long", year: "numeric", month: "long", day: "numeric",
        hour: "2-digit", minute: "2-digit", second: "2-digit"
      });
      el.textContent = formatted;
    }
    updateClock();
    setInterval(updateClock, 1000);
  }

  /* ========= Index : diaporama ========= */
  function initSlideshow() {
    const root = document.getElementById('slideshow1');
    if (!root) return;

    const slides  = $$('.mySlide', root);
    const prev    = $('.prev', root);
    const next    = $('.next', root);
    const dotsWrap= $('.dots', root);
    let i = 0;

    slides.forEach((_, idx) => {
      const d = document.createElement('button');
      d.className = 'dot';
      d.type = 'button';
      d.setAttribute('aria-label', 'Aller à la diapositive ' + (idx + 1));
      d.addEventListener('click', () => show(idx));
      dotsWrap.appendChild(d);
    });

    function show(n) {
      i = (n + slides.length) % slides.length;
      slides.forEach((s, idx) => s.style.display = (idx === i ? 'block' : 'none'));
      $$('.dot', dotsWrap).forEach((d, idx) => d.classList.toggle('active', idx === i));
    }
    prev?.addEventListener('click', () => show(i - 1));
    next?.addEventListener('click', () => show(i + 1));
    root.tabIndex = 0;
    root.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowLeft') { e.preventDefault(); show(i - 1); }
      if (e.key === 'ArrowRight'){ e.preventDefault(); show(i + 1); }
    });
    show(0);
  }

  /* ========= Index : bandeau cookies ========= */
  function initCookieBanner() {
    const banner = document.getElementById('cookieBanner');
    if (!banner) return;

    function getCookie(name){
      return document.cookie.split('; ').find(row => row.startsWith(name + '='))?.split('=')[1] || null;
    }
    function setCookie(name, value, maxAgeSeconds){
      document.cookie = name + '=' + value + '; Path=/; SameSite=Lax; Max-Age=' + maxAgeSeconds;
    }
    const consent = getCookie('cookie_consent');
    if (!consent) banner.style.display = 'block';

    $('#cookieAccept')?.addEventListener('click', function(){
      setCookie('cookie_consent', 'accepted', 180*24*60*60);
      banner.style.display = 'none';
    });
    $('#cookieRefuse')?.addEventListener('click', function(){
      setCookie('cookie_consent', 'refused', 1*60*60);
      banner.style.display = 'none';
    });
  }

  /* ========= Menu mobile ========= */
  function initMobileMenu() {
    const toggle = document.querySelector('.menu-toggle');
    const nav = document.querySelector('.main-nav');
    if (!toggle || !nav) return;

    toggle.addEventListener('click', () => {
      nav.classList.toggle('is-open');
    });
  }

  /* ========= Placeholders pour tes autres inits ========= */
  function initTD7() {}
  function initTD6() {}
  function initTD5() {}
  function initTD() {}

  /* ========= Boot ========= */
  document.addEventListener('DOMContentLoaded', () => {
    initClock();
    initSlideshow();
    initCookieBanner();
    initMobileMenu();

    // Pages spécifiques (tu avais ça au départ)
    initTD7();
    initTD6();
    initTD5();
    initTD(); // testTD.php
  });
})();
