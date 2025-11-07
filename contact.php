<?php 
include 'header.php';
?>

<main class="container section">
  <h1 class="section-title"><?= htmlspecialchars($title) ?></h1>

  <div class="card" style="text-align:center; padding:40px;">
    <h2>Cette section est en cours de construction</h2>
    <p>Nous travaillons activement sur <strong><?= htmlspecialchars($title) ?></strong> pour vous offrir une meilleure expérience.</p>
    <p>Revenez bientôt pour découvrir les nouveautés !</p>

    <div style="margin-top:20px;">
      <a href="index.php" class="btn btn-primary">⬅ Retour à l'accueil</a>
    </div>
  </div>
</main>

<?php
include 'footer.php';
?>
