</main>

<footer class="site-footer">
  <div class="container footer-inner">

    <!-- Colonne 1 : Logo & Nom -->
    <div class="footer-col">
      <div class="footer-brand">
        <img src="images/logo.png" alt="Kitabee" class="logo">
        <span class="brand-name">Kitabee</span>
      </div>
      <?php if (!empty($last_visit)): ?>
        <p class="footer-info">Dernière visite : <?= htmlspecialchars($last_visit, ENT_QUOTES, 'UTF-8') ?></p>
      <?php else: ?>
        <p class="footer-info">C’est votre première visite !</p>
      <?php endif; ?>
    </div>

    <!-- Colonne 2 : Liens légaux -->
    <div class="footer-col">
      <h3>Informations</h3>
      <nav class="footer-links">
        <a href="cookie.php">Cookies</a>
        <a href="construction.php?page=confidentialite">Confidentialité</a>
      </nav>
    </div>

    <!-- Colonne 3 : Contact -->
    <div class="footer-col">
      <h3>Contact</h3>
      <ul class="footer-contact">
        <li>📍 CY Cergy Université, France</li>
        <li>📧 <a href="mailto:contact@kitabee.fr">contact@kitabee.fr</a></li>
        <li>📞 +33 6 12 34 56 78</li>
      </ul>
    </div>

    <!-- Colonne 4 : Réseaux sociaux -->
    <div class="footer-col">
      <h3>Suivez-nous</h3>
      <div class="footer-socials">
        <a href="#">📘 Facebook</a>
        <a href="#">📷 Instagram</a>
        <a href="#">🐦 Twitter</a>
      </div>
    </div>

  </div>

  <div class="footer-bottom">
    <p>© <?= date('Y') ?> — Fait par <strong>MOUSSAOUI Imane</strong> & <strong>TRIOLLET-PEREIRA Odessa</strong>.  
      Tous droits réservés.</p>
  </div>
</footer>

<!-- === Scripts === -->
<script>
  // Choix du moteur de recherche
  const ENGINES = {
    google: { action: 'https://www.google.com/search', param: 'q' },
    bing:   { action: 'https://www.bing.com/search',   param: 'q' },
    ddg:    { action: 'https://duckduckgo.com/',       param: 'q' },
    qwant:  { action: 'https://www.qwant.com/',        param: 'q' },
  };
  const form = document.getElementById('searchForm');
  const engineSelect = document.getElementById('engine');
  const queryInput = document.getElementById('q');
  function applyEngine() {
    if (!form || !engineSelect || !queryInput) return;
    const { action, param } = ENGINES[engineSelect.value];
    form.action = action;
    queryInput.name = param;
  }
  if (engineSelect) {
    engineSelect.addEventListener('change', applyEngine);
    applyEngine();
  }

  // Bouton retour en haut
  (function() {
    const backToTop = document.getElementById('backToTop');
    if (!backToTop) return;
    const SHOW_AFTER = 200; // px
    function toggleBackToTop() {
      backToTop.style.display = (window.scrollY > SHOW_AFTER) ? 'block' : 'none';
    }
    window.addEventListener('scroll', toggleBackToTop, { passive: true });
    window.addEventListener('load', toggleBackToTop);
    backToTop.addEventListener('click', function(e) {
      e.preventDefault();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  })();
</script>

<a href="#top" id="backToTop" class="back-to-top" aria-label="Revenir en haut">↑</a>

<style>
/* === Footer général === */
.site-footer {
  background: #faf7f3;
  color: #1c1c1c;
  padding: 40px 20px 20px;
  margin-top: 40px;
  font-size: 0.95rem;
}
.footer-inner {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 30px;
  max-width: 1100px;
  margin: 0 auto;
}
.footer-col h3 {
  font-size: 1.1rem;
  color: #5f7f5f;
  margin-bottom: 12px;
}
.footer-brand {
  display: flex;
  align-items: center;
  gap: 10px;
}
.footer-brand .logo {
  width: 45px;
  height: 45px;
}
.footer-brand .brand-name {
  font-weight: 700;
  font-size: 1.2rem;
  color: #1c1c1c;
}
.footer-links a,
.footer-socials a {
  display: block;
  color: #1c1c1c;
  text-decoration: none;
  margin-bottom: 6px;
  transition: color 0.2s;
}
.footer-links a:hover,
.footer-socials a:hover {
  color: #5f7f5f;
}
.footer-contact li {
  list-style: none;
  margin-bottom: 6px;
}
.footer-info {
  color: #1c1c1c;
  font-size: 0.85rem;
  margin-top: 8px;
}
.footer-bottom {
  text-align: center;
  margin-top: 30px;
  padding-top: 15px;
  border-top: 1px solid rgba(255,255,255,0.2);
  font-size: 0.85rem;
  color: #1c1c1c;
}
.back-to-top {
  position: fixed;
  right: 20px;
  bottom: 20px;
  z-index: 9999;
  background: #4b5563;
  color: #1c1c1c;
  padding: 10px 14px;
  border-radius: 50%;
  text-decoration: none;
  font-size: 16px;
  display: none;
  transition: background .3s;
}
.back-to-top:hover {
  background: #5f7f5f;
}
</style>
