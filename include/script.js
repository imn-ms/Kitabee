/* /include/script.js */
(() => {
  'use strict';

  /* ========= Helpers ========= */
  const $  = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  /* ========= Index : horloge ========= */
  function initClock() {
    const el = document.getElementById('clock');
    if (!el) return;

    const updateClock = () => {
      const now = new Date();
      el.textContent = now.toLocaleString('fr-FR', {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
        hour: '2-digit', minute: '2-digit', second: '2-digit'
      });
    };

    updateClock();
    window.setInterval(updateClock, 1000);
  }

  /* ========= Index : diaporama ========= */
  function initSlideshow() {
    const root = document.getElementById('slideshow1');
    if (!root) return;

    const slides   = $$('.mySlide', root);
    const prev     = $('.prev', root);
    const next     = $('.next', root);
    const dotsWrap = $('.dots', root);
    if (!slides.length || !dotsWrap) return;

    let i = 0;

    dotsWrap.innerHTML = '';
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

      slides.forEach((s, idx) => {
        s.style.display = (idx === i ? 'block' : 'none');
      });

      $$('.dot', dotsWrap).forEach((d, idx) => {
        d.classList.toggle('active', idx === i);
      });
    }

    if (prev) prev.addEventListener('click', () => show(i - 1));
    if (next) next.addEventListener('click', () => show(i + 1));

    root.tabIndex = 0;
    root.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowLeft')  { e.preventDefault(); show(i - 1); }
      if (e.key === 'ArrowRight') { e.preventDefault(); show(i + 1); }
    });

    show(0);
  }

  /* ========= Index : bandeau cookies ========= */
  function initCookieBanner() {
    const banner = document.getElementById('cookieBanner');
    if (!banner) return;

    function getCookie(name) {
      return document.cookie
        .split('; ')
        .find(row => row.startsWith(name + '='))
        ?.split('=')[1] || null;
    }

    function setCookie(name, value, maxAgeSeconds) {
      document.cookie = `${name}=${value}; Path=/; SameSite=Lax; Max-Age=${maxAgeSeconds}`;
    }

    const consent = getCookie('cookie_consent');
    if (!consent) banner.style.display = 'block';

    const acceptBtn = document.getElementById('cookieAccept');
    const refuseBtn = document.getElementById('cookieRefuse');

    if (acceptBtn) {
      acceptBtn.addEventListener('click', () => {
        setCookie('cookie_consent', 'accepted', 180 * 24 * 60 * 60);
        banner.style.display = 'none';
      });
    }

    if (refuseBtn) {
      refuseBtn.addEventListener('click', () => {
        setCookie('cookie_consent', 'refused', 1 * 60 * 60);
        banner.style.display = 'none';
      });
    }
  }

  /* ========= Menu mobile ========= */
  function initMobileMenu() {
    const toggle = document.querySelector('.menu-toggle');
    const nav = document.querySelector('.main-nav');
    if (!toggle || !nav) return;

    toggle.addEventListener('click', () => {
      nav.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', nav.classList.contains('is-open') ? 'true' : 'false');
    });
  }

  /* ========= Back to top ========= */
  function initBackToTop() {
    const backToTop = document.getElementById('backToTop');
    if (!backToTop) return;

    const SHOW_AFTER = 250;

    const toggleBackToTop = () => {
      backToTop.style.display = (window.scrollY > SHOW_AFTER) ? 'block' : 'none';
    };

    window.addEventListener('scroll', toggleBackToTop, { passive: true });
    window.addEventListener('load', toggleBackToTop);
    toggleBackToTop();

    backToTop.addEventListener('click', (e) => {
      e.preventDefault();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  /* ========= Accessibilité : A- A A+ ========= */
  function initFontSizeControls() {
    const buttons = $$('.font-btn');
    if (!buttons.length) return;

    const body = document.body;
    const savedFont = localStorage.getItem('kitabee_font_size') || 'normal';

    applyFontClass(savedFont);
    updateActiveButton(savedFont);

    buttons.forEach(btn => {
      btn.addEventListener('click', () => {
        const size = btn.getAttribute('data-font') || 'normal';
        applyFontClass(size);
        updateActiveButton(size);
        localStorage.setItem('kitabee_font_size', size);
      });
    });

    function applyFontClass(size) {
      body.classList.remove('font-small', 'font-normal', 'font-large');
      if (size === 'small') body.classList.add('font-small');
      else if (size === 'large') body.classList.add('font-large');
      else body.classList.add('font-normal');
    }

    function updateActiveButton(size) {
      buttons.forEach(b => {
        const active = (b.getAttribute('data-font') === size);
        b.classList.toggle('is-active', active);
        b.setAttribute('aria-pressed', active ? 'true' : 'false');
      });
    }
  }

  /* ========= Friend AJAX (global si besoin) ========= */
  function sendFriendAction(action, userId, callback) {
    const form = new FormData();
    form.append('action', action);
    form.append('user_id', String(userId));

    fetch('friends_ajax.php', {
      method: 'POST',
      body: form,
      credentials: 'same-origin'
    })
      .then(r => r.json())
      .then(data => { if (callback) callback(data); })
      .catch(err => { if (callback) callback({ success: false, error: String(err) }); });
  }
  window.sendFriendAction = sendFriendAction;

  /* ========= Connexion : fade message succès ========= */
  function initSuccessMessageFade() {
    const msg = document.getElementById('success-message');
    if (!msg) return;

    window.setTimeout(() => {
      msg.style.transition = 'opacity 1s ease';
      msg.style.opacity = '0';
      window.setTimeout(() => msg.remove(), 1000);
    }, 4000);
  }

  /* ========= Google Books : autocomplétion ========= */
  function initGoogleBooksAutocomplete() {
    const input = document.getElementById('q');
    const suggestions = document.getElementById('suggestions');
    if (!input || !suggestions) return;

    let timer = null;

    input.addEventListener('input', () => {
      if (timer) clearTimeout(timer);

      const query = input.value.trim();
      if (query.length < 2) {
        suggestions.innerHTML = '';
        return;
      }

      timer = window.setTimeout(() => {
        fetch(`https://www.googleapis.com/books/v1/volumes?q=${encodeURIComponent(query)}&maxResults=5`)
          .then(res => res.json())
          .then(data => {
            suggestions.innerHTML = '';
            if (!data?.items?.length) return;

            data.items.forEach(book => {
              const title = book?.volumeInfo?.title || 'Titre inconnu';
              const li = document.createElement('li');
              li.textContent = title;
              li.addEventListener('click', () => {
                input.value = title;
                suggestions.innerHTML = '';
              });
              suggestions.appendChild(li);
            });
          })
          .catch(() => { suggestions.innerHTML = ''; });
      }, 300);
    });

    // optionnel : fermer la liste si on clique ailleurs
    document.addEventListener('click', (e) => {
      if (e.target !== input && !suggestions.contains(e.target)) {
        suggestions.innerHTML = '';
      }
    });
  }

  /* ========= Club : AJAX + tabs (club.php) ========= */
  function initClubPage() {
    // Récupération du club id depuis <body data-club-id="123">
    const clubIdStr = document.body?.dataset?.clubId;
    const CLUB_ID = clubIdStr ? Number(clubIdStr) : NaN;
    if (!Number.isFinite(CLUB_ID)) return; // pas sur club.php

    function postClubAction(action, data, cb) {
      const fd = new FormData();
      fd.append('action', action);
      fd.append('club_id', String(CLUB_ID));
      if (data?.userId) fd.append('user_id', String(data.userId));
      if (data?.googleBookId) fd.append('google_book_id', String(data.googleBookId));

      fetch('clubs_ajax.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
      })
        .then(r => r.json())
        .then(res => cb?.(res))
        .catch(err => {
          console.error('Erreur AJAX club:', err);
          alert('Une erreur est survenue.');
        });
    }

    // Inviter un ami
    const inviteSelect   = document.getElementById('invite-friend-select');
    const inviteBtn      = document.getElementById('invite-friend-btn');
    const inviteFeedback = document.getElementById('invite-friend-feedback');

    if (inviteBtn && inviteSelect) {
      inviteBtn.addEventListener('click', () => {
        const userId = inviteSelect.value;
        if (!userId) return;

        postClubAction('add_member', { userId }, (res) => {
          if (res?.ok) {
            if (inviteFeedback) {
              inviteFeedback.style.display = 'block';
              inviteFeedback.textContent = 'Invitation envoyée.';
              window.setTimeout(() => { inviteFeedback.style.display = 'none'; }, 1500);
            }
          } else {
            alert("Impossible d'inviter ce membre au club.");
          }
        });
      });
    }

    // Retirer un membre
    $$('.js-remove-member').forEach(btn => {
      btn.addEventListener('click', () => {
        const userId = btn.dataset.userId;
        if (!userId) return;
        if (!confirm('Retirer ce membre du club ?')) return;

        postClubAction('remove_member', { userId }, (res) => {
          if (res?.ok) btn.closest('.member-item')?.remove();
          else alert("Impossible de retirer ce membre.");
        });
      });
    });

    // Ajouter un livre
    $$('.js-add-book').forEach(btn => {
      btn.addEventListener('click', () => {
        const gbid = btn.dataset.googleBookId;
        if (!gbid) return;

        postClubAction('add_book', { googleBookId: gbid }, (res) => {
          if (res?.ok) {
            btn.textContent = 'Ajouté';
            btn.disabled = true;
            window.setTimeout(() => location.reload(), 800);
          } else {
            alert("Impossible d'ajouter ce livre au club.");
          }
        });
      });
    });

    // Retirer un livre
    $$('.js-remove-book').forEach(btn => {
      btn.addEventListener('click', () => {
        const gbid = btn.dataset.googleBookId;
        if (!gbid) return;
        if (!confirm('Retirer ce livre du club ?')) return;

        postClubAction('remove_book', { googleBookId: gbid }, (res) => {
          if (res?.ok) btn.closest('.book-item')?.remove();
          else alert("Impossible de retirer ce livre.");
        });
      });
    });

    // Scroll messages en bas
    const box = document.getElementById('messages-box');
    if (box) box.scrollTop = box.scrollHeight;

    // Tabs
    const tabButtons = $$('.club-nav-link[data-panel]');
    const panels = $$('.club-panel');

    function activatePanel(panelName) {
      panels.forEach(p => p.classList.toggle('is-active', p.id === 'panel-' + panelName));
      tabButtons.forEach(b => b.classList.toggle('is-active', b.dataset.panel === panelName));

      if (panelName === 'messages' && box) {
        window.setTimeout(() => { box.scrollTop = box.scrollHeight; }, 50);
      }
    }

    tabButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        const target = btn.dataset.panel;
        if (target) activatePanel(target);
      });
    });
  }

  /* ========= Boot ========= */
  document.addEventListener('DOMContentLoaded', () => {
    initClock();
    initSlideshow();
    initCookieBanner();
    initMobileMenu();
    initBackToTop();
    initFontSizeControls();

    initSuccessMessageFade();
    initGoogleBooksAutocomplete();
    initClubPage();
  });
})();
