<?php
/**
 * recommandations.php — Page des recommandations
 *
 * Rôle :
 * - Page protégée.
 * - Affiche des recommandations de livres via RecommendationService :
 *   1) D’après les genres de l’utilisateur
 *   2) D’après les auteurs de l’utilisateur
 *   3) D’après une émotion sélectionnée
 *
 * Le rafraîchissement de sections utilise des "variants"  stockés en champs hidden.
 */

header('Content-Type: text/html; charset=UTF-8');
session_start();

if (empty($_SESSION['user'])) {
    header('Location: connexion.php?redirect=recommandations.php');
    exit;
}

$userId = (int)$_SESSION['user'];

require_once __DIR__ . '/secret/config.php';
require_once __DIR__ . '/secret/database.php';
require_once __DIR__ . '/classes/RecommendationService.php';
require_once __DIR__ . '/include/functions.inc.php';

$apiKey = $GOOGLE_API_KEY ?? null;

// Préparer les recommandations (variants + émotion + recos)
$data = kb_recommendations_prepare($pdo, $userId, $apiKey);

$selectedEmotion = $data['selectedEmotion'];
$variantGenres   = $data['variantGenres'];
$variantAuthors  = $data['variantAuthors'];
$variantEmotion  = $data['variantEmotion'];

$recoGenres  = $data['recoGenres'];
$recoAuthors = $data['recoAuthors'];
$recoEmotion = $data['recoEmotion'];

$pageTitle = "Recommandations – Kitabee";
include __DIR__ . '/include/header.inc.php';
?>

<section class="section container">
    <h1>Vos recommandations</h1>

    <!-- 1. Genres -->
    <section class="card" id="genres" style="margin-bottom:2rem;">
        <div class="section-head">
            <h2>D’après vos genres</h2>
            <form method="post" action="recommandations.php#genres">
                <input type="hidden" name="variant_genres" value="<?= htmlspecialchars((string)$variantGenres, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="variant_authors" value="<?= htmlspecialchars((string)$variantAuthors, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="variant_emotion" value="<?= htmlspecialchars((string)$variantEmotion, ENT_QUOTES, 'UTF-8') ?>">
                <?php if ($selectedEmotion): ?>
                    <input type="hidden" name="emotion_keep" value="<?= htmlspecialchars((string)$selectedEmotion, ENT_QUOTES, 'UTF-8') ?>">
                <?php endif; ?>
                <button type="submit" name="action" value="refresh_genres" class="btn-ghost-sm">⟳ Recharger</button>
            </form>
        </div>

        <?php if (empty($recoGenres)): ?>
            <p>Aucun livre trouvé.</p>
        <?php else: ?>
            <div class="book-grid">
                <?php foreach ($recoGenres as $item): ?>
                    <?php kb_render_google_book_card((array)$item); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- 2. Auteurs -->
    <section class="card" id="auteurs" style="margin-bottom:2rem;">
        <div class="section-head">
            <h2>D’après vos auteurs</h2>
            <form method="post" action="recommandations.php#auteurs">
                <input type="hidden" name="variant_genres" value="<?= htmlspecialchars((string)$variantGenres, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="variant_authors" value="<?= htmlspecialchars((string)$variantAuthors, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="variant_emotion" value="<?= htmlspecialchars((string)$variantEmotion, ENT_QUOTES, 'UTF-8') ?>">
                <?php if ($selectedEmotion): ?>
                    <input type="hidden" name="emotion_keep" value="<?= htmlspecialchars((string)$selectedEmotion, ENT_QUOTES, 'UTF-8') ?>">
                <?php endif; ?>
                <button type="submit" name="action" value="refresh_authors" class="btn-ghost-sm">⟳ Recharger</button>
            </form>
        </div>

        <?php if (empty($recoAuthors)): ?>
            <p>Ajoutez des livres d’auteurs que vous aimez.</p>
        <?php else: ?>
            <div class="book-grid">
                <?php foreach ($recoAuthors as $item): ?>
                    <?php kb_render_google_book_card((array)$item); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- 3. Émotion -->
    <section class="card" id="emotion">
        <div class="section-head">
            <h2>Je me sens…</h2>
            <?php if ($selectedEmotion): ?>
            <form method="post" action="recommandations.php#emotion">
                <input type="hidden" name="variant_genres" value="<?= htmlspecialchars((string)$variantGenres, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="variant_authors" value="<?= htmlspecialchars((string)$variantAuthors, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="variant_emotion" value="<?= htmlspecialchars((string)$variantEmotion, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="emotion_keep" value="<?= htmlspecialchars((string)$selectedEmotion, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" name="action" value="refresh_emotion" class="btn-ghost-sm">⟳ Recharger</button>
            </form>
            <?php endif; ?>
        </div>

        <form method="post" action="recommandations.php#emotion" class="emotion-form">
            <input type="hidden" name="variant_genres" value="<?= htmlspecialchars((string)$variantGenres, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="variant_authors" value="<?= htmlspecialchars((string)$variantAuthors, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="variant_emotion" value="<?= htmlspecialchars((string)$variantEmotion, ENT_QUOTES, 'UTF-8') ?>">

            <select name="emotion" class="emotion-select">
                <option value="">-- choisir --</option>
                <option value="heureux"     <?= $selectedEmotion === 'heureux' ? 'selected' : '' ?>>Heureux·se</option>
                <option value="triste"      <?= $selectedEmotion === 'triste' ? 'selected' : '' ?>>Triste</option>
                <option value="stresse"     <?= $selectedEmotion === 'stresse' ? 'selected' : '' ?>>Stressé·e</option>
                <option value="nostalgique" <?= $selectedEmotion === 'nostalgique' ? 'selected' : '' ?>>Nostalgique</option>
                <option value="motivé"      <?= $selectedEmotion === 'motivé' ? 'selected' : '' ?>>Motivé·e</option>
            </select>

            <button type="submit" class="btn btn-primary">Proposer</button>
        </form>

        <?php if ($selectedEmotion && empty($recoEmotion)): ?>
            <p>Aucune recommandation trouvée pour cette émotion.</p>
        <?php elseif ($selectedEmotion): ?>
            <div class="book-grid">
                <?php foreach ($recoEmotion as $item): ?>
                    <?php kb_render_google_book_card((array)$item); ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>Choisissez une émotion pour recevoir une sélection 📚</p>
        <?php endif; ?>
    </section>
</section>

<?php include __DIR__ . '/include/footer.inc.php'; ?>
