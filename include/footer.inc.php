<a href="#top" id="backToTop" class="back-to-top" aria-label="Revenir en haut">⬆</a>
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
        <li>📧 <a href="mailto:kitabee@alwaysdata.net">kitabee@alwaysdata.net</a></li>
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
    <p>© <?= date('Y') ?> — Fait par <strong>MOUSSAOUI Imane</strong> & 
      <strong>TRIOLLET-PEREIRA Odessa</strong>.  
      Tous droits réservés.</p>
  </div>
</footer>

</body>
</html>
