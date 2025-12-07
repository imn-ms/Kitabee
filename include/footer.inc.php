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


