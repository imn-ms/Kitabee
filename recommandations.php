<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (empty($_SESSION['user'])) {
    header('Location: connexion.php?redirect=recommandations.php');
    exit;
}
$userId = (int) $_SESSION['user'];

require_once __DIR__ . '/private/config.php';
require_once __DIR__ . '/classes/RecommendationService.php';

$apiKey  = defined('GOOGLE_BOOKS_API_KEY') ? GOOGLE_BOOKS_API_KEY : null;
$service = new RecommendationService($pdo, $userId, $apiKey);

/* on récupère ce qui vient du formulaire */
$action          = $_POST['action'] ?? null;
$selectedEmotion = $_POST['emotion'] ?? ($_POST['emotion_keep'] ?? null);

/* variants actuels ou nouveaux */
$variantGenres  = isset($_POST['variant_genres'])  ? (int)$_POST['variant_genres']  : rand(1, 999999);
$variantAuthors = isset($_POST['variant_authors']) ? (int)$_POST['variant_authors'] : rand(1, 999999);
$variantEmotion = isset($_POST['variant_emotion']) ? (int)$_POST['variant_emotion'] : rand(1, 999999);

/* si on recharge une section, on régénère juste son variant */
if ($action === 'refresh_genres') {
    $variantGenres = rand(1, 999999);
}
if ($action === 'refresh_authors') {
    $variantAuthors = rand(1, 999999);
}
if ($action === 'refresh_emotion') {
    $variantEmotion = rand(1, 999999);
}

/* récupérer les recos avec les variants */
$recoGenres  = $service->getByUserGenres(6, $variantGenres);
$recoAuthors = $service->getByUserAuthors(6, $variantAuthors);
$recoEmotion = $selectedEmotion ? $service->getByEmotion($selectedEmotion, 6, $variantEmotion) : [];

$pageTitle = "Recommandations – Kitabee";
include __DIR__ . '/include/header.inc.php';

function renderGoogleBookCard($item)
{
    $info    = $item['volumeInfo'] ?? [];
    $title   = $info['title'] ?? 'Sans titre';
    $authors = isset($info['authors']) ? implode(', ', $info['authors']) : 'Auteur inconnu';
    $thumb   = $info['imageLinks']['thumbnail'] ?? null;
    $id      = $item['id'] ?? '';
    ?>
    <article class="book-card">
        <?php if ($thumb): ?>
            <img src="<?= htmlspecialchars($thumb) ?>" alt="Couverture de <?= htmlspecialchars($title) ?>">
        <?php endif; ?>
        <h3><?= htmlspecialchars($title) ?></h3>
        <p class="muted"><?= htmlspecialchars($authors) ?></p>
        <?php if ($id): ?>
            <p><a href="detail.php?id=<?= urlencode($id) ?>" class="link-more">Voir plus</a></p>
        <?php endif; ?>
    </article>
    <?php
}
?>

<section class="section container">
    <h1>Vos recommandations</h1>

    <!-- 1. Genres -->
    <section class="card" id="genres" style="margin-bottom:2rem;">
        <div class="section-head">
            <h2>D’après vos genres</h2>
            <form method="post" action="recommandations.php#genres">
                <input type="hidden" name="variant_genres" value="<?= htmlspecialchars($variantGenres) ?>">
                <input type="hidden" name="variant_authors" value="<?= htmlspecialchars($variantAuthors) ?>">
                <input type="hidden" name="variant_emotion" value="<?= htmlspecialchars($variantEmotion) ?>">
                <?php if ($selectedEmotion): ?>
                    <input type="hidden" name="emotion_keep" value="<?= htmlspecialchars($selectedEmotion) ?>">
                <?php endif; ?>
                <button type="submit" name="action" value="refresh_genres" class="btn-ghost-sm">⟳ Recharger</button>
            </form>
        </div>

        <?php if (empty($recoGenres)): ?>
            <p>Aucun livre trouvé.</p>
        <?php else: ?>
            <div class="book-grid">
                <?php foreach ($recoGenres as $item): ?>
                    <?php renderGoogleBookCard($item); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- 2. Auteurs -->
    <section class="card" id="auteurs" style="margin-bottom:2rem;">
        <div class="section-head">
            <h2>D’après vos auteurs</h2>
            <form method="post" action="recommandations.php#auteurs">
                <input type="hidden" name="variant_genres" value="<?= htmlspecialchars($variantGenres) ?>">
                <input type="hidden" name="variant_authors" value="<?= htmlspecialchars($variantAuthors) ?>">
                <input type="hidden" name="variant_emotion" value="<?= htmlspecialchars($variantEmotion) ?>">
                <?php if ($selectedEmotion): ?>
                    <input type="hidden" name="emotion_keep" value="<?= htmlspecialchars($selectedEmotion) ?>">
                <?php endif; ?>
                <button type="submit" name="action" value="refresh_authors" class="btn-ghost-sm">⟳ Recharger</button>
            </form>
        </div>

        <?php if (empty($recoAuthors)): ?>
            <p>Ajoutez des livres d’auteurs que vous aimez.</p>
        <?php else: ?>
            <div class="book-grid">
                <?php foreach ($recoAuthors as $item): ?>
                    <?php renderGoogleBookCard($item); ?>
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
                <input type="hidden" name="variant_genres" value="<?= htmlspecialchars($variantGenres) ?>">
                <input type="hidden" name="variant_authors" value="<?= htmlspecialchars($variantAuthors) ?>">
                <input type="hidden" name="variant_emotion" value="<?= htmlspecialchars($variantEmotion) ?>">
                <input type="hidden" name="emotion_keep" value="<?= htmlspecialchars($selectedEmotion) ?>">
                <button type="submit" name="action" value="refresh_emotion" class="btn-ghost-sm">⟳ Recharger</button>
            </form>
            <?php endif; ?>
        </div>

        <form method="post" action="recommandations.php#emotion" class="emotion-form">
            <input type="hidden" name="variant_genres" value="<?= htmlspecialchars($variantGenres) ?>">
            <input type="hidden" name="variant_authors" value="<?= htmlspecialchars($variantAuthors) ?>">
            <input type="hidden" name="variant_emotion" value="<?= htmlspecialchars($variantEmotion) ?>">
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
                    <?php renderGoogleBookCard($item); ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>Choisissez une émotion pour recevoir une sélection 📚</p>
        <?php endif; ?>
    </section>
</section>

<?php include __DIR__ . '/include/footer.inc.php'; ?>
