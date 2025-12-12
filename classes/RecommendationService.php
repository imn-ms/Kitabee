<?php

/**
 * Class RecommendationService
 *
 * Service de recommandation de livres pour le projet Kitabee.
 *
 * Cette classe génère des recommandations personnalisées à partir :
 * - de la bibliothèque personnelle de l’utilisateur,
 * - des genres des livres lus,
 * - des auteurs consultés,
 * - de l’état émotionnel choisi par l’utilisateur.
 *
 * Les recommandations s’appuient sur l’API Google Books
 * et appliquent des filtres spécifiques pour privilégier
 * les ouvrages en langue française.
 *
 * Auteur : Odessa TRIOLLET-PEREIRA
 * Projet : Kitabee
 */
class RecommendationService
{
    /**
     * Connexion PDO à la base de données.
     *
     * @var PDO
     */
    private $pdo;

    /**
     * Identifiant de l’utilisateur courant.
     *
     * @var int
     */
    private $userId;

    /**
     * Clé API Google Books (optionnelle).
     *
     * @var string|null
     */
    private $apiKey;

    /**
     * URL de base de l’API Google Books.
     *
     * @var string
     */
    private $googleApiBase = 'https://www.googleapis.com/books/v1/volumes';

    /**
     * Constructeur du service de recommandation.
     *
     * @param PDO        $pdo    Connexion PDO à la base de données.
     * @param int        $userId Identifiant de l’utilisateur.
     * @param string|null $apiKey Clé API Google Books (optionnelle).
     */
    public function __construct($pdo, $userId, $apiKey = null)
    {
        $this->pdo    = $pdo;
        $this->userId = (int)$userId;
        $this->apiKey = $apiKey;
    }

    /**
     * Récupère les identifiants Google Books des livres
     * présents dans la bibliothèque de l’utilisateur.
     *
     * @return array Liste des google_book_id.
     */
    private function getUserGoogleBooksIds()
    {
        $sql = "SELECT google_book_id
                FROM user_library
                WHERE user_id = :uid
                ORDER BY added_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['uid' => $this->userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * Récupère et décode une réponse JSON depuis une URL.
     *
     * Tente d’abord via file_get_contents,
     * puis via cURL si disponible.
     *
     * @param string $url URL à interroger.
     * @return array|null Données JSON décodées ou null en cas d’échec.
     */
    private function fetchJson($url)
    {
        $json = @file_get_contents($url);
        if ($json !== false) return json_decode($json, true);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 5,
            ]);
            $json = curl_exec($ch);
            curl_close($ch);
            if ($json !== false) return json_decode($json, true);
        }
        return null;
    }

    /**
     * Récupère les informations complètes d’un livre
     * à partir de son identifiant Google Books.
     *
     * @param string $googleId Identifiant Google Books.
     * @return array|null Données du volume ou null.
     */
    private function fetchVolume($googleId)
    {
        $url = $this->googleApiBase . '/' . urlencode($googleId);
        if ($this->apiKey) $url .= '?key=' . urlencode($this->apiKey);
        return $this->fetchJson($url) ?: null;
    }

    /**
     * Détermine si un titre peut être considéré comme français.
     *
     * Le filtrage se base sur :
     * - la langue déclarée par l’API,
     * - la présence d’accents typiquement français,
     * - l’exclusion de mots-clés majoritairement anglais.
     *
     * @param string $title Titre du livre.
     * @param string $lang  Code langue du livre.
     * @return bool
     */
    private function isFrenchTitle($title, $lang)
    {
        $titleLower = mb_strtolower($title, 'UTF-8');

        if (in_array($lang, ['fr', 'fr-fr', 'fr-FR'])) {
            return true;
        }

        if (preg_match('/[éèêëàâîïôöûüç]/u', $titleLower)) {
            return true;
        }

        $englishWords = [' the ', ' of ', ' and ', ' love ', ' girl ', ' man ', ' war ', 'life', 'story'];
        foreach ($englishWords as $w) {
            if (str_contains($titleLower, $w)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Recherche des livres via Google Books
     * en appliquant un filtre renforcé pour le français.
     *
     * @param string $query       Requête de recherche.
     * @param int    $maxResults  Nombre maximum de résultats.
     * @param int    $startIndex  Index de départ.
     *
     * @return array Liste de volumes filtrés.
     */
    private function searchVolumesFr($query, $maxResults = 6, $startIndex = 0)
    {
        $url = $this->googleApiBase
            . '?q=' . urlencode($query)
            . '&maxResults=' . (int)$maxResults
            . '&startIndex=' . (int)$startIndex
            . '&langRestrict=fr';

        if ($this->apiKey) $url .= '&key=' . urlencode($this->apiKey);

        $data = $this->fetchJson($url);
        if (!$data || empty($data['items'])) return [];

        $frItems = [];
        foreach ($data['items'] as $item) {
            $info = $item['volumeInfo'] ?? [];
            $title = $info['title'] ?? '';
            $lang  = $info['language'] ?? '';
            if ($title && $this->isFrenchTitle($title, $lang)) {
                $frItems[] = $item;
            }
        }

        return $frItems;
    }

    /**
     * Recherche des livres français avec plusieurs tentatives
     * afin de garantir un nombre suffisant de résultats uniques.
     *
     * @param string $query   Requête de recherche.
     * @param int    $needed Nombre de résultats souhaités.
     * @param int    $variant Variante aléatoire.
     *
     * @return array
     */
    private function searchVolumesFrWithRetries($query, $needed = 6, $variant = 0)
    {
        $results = [];
        for ($attempt = 0; $attempt < 4 && count($results) < $needed; $attempt++) {
            $startIndex = ($variant * 7 + $attempt * 10) % 40;
            $chunk = $this->searchVolumesFr($query, $needed, $startIndex);
            foreach ($chunk as $item) {
                $id = $item['id'] ?? null;
                if ($id && !isset($results[$id])) {
                    $results[$id] = $item;
                }
                if (count($results) >= $needed) break;
            }
        }
        return array_values($results);
    }

    /* ===================== RECOMMANDATIONS PAR GENRES ===================== */

    /**
     * Génère des recommandations basées sur les genres
     * des livres lus par l’utilisateur.
     *
     * @param int $limit   Nombre de recommandations.
     * @param int $variant Variante aléatoire.
     *
     * @return array
     */
    public function getByUserGenres($limit = 6, $variant = 0)
    {
        $userBookIds = $this->getUserGoogleBooksIds();
        if (empty($userBookIds)) return [];

        if ($variant) {
            $copy = $userBookIds;
            mt_srand($variant);
            shuffle($copy);
            $userBookIds = $copy;
        } else {
            shuffle($userBookIds);
        }

        $userIdSet = array_flip($userBookIds);
        $results   = [];
        $sample    = array_slice($userBookIds, 0, 6);
        $i = 0;

        foreach ($sample as $gId) {
            $volume = $this->fetchVolume($gId);
            if (!$volume) continue;

            $info = $volume['volumeInfo'] ?? [];
            if (empty($info['categories'])) continue;

            $category = $info['categories'][0];
            $books = $this->searchVolumesFrWithRetries('subject:"' . $category . '"', $limit, $variant + $i);

            foreach ($books as $item) {
                $itemId = $item['id'];
                if (isset($userIdSet[$itemId])) continue;
                $results[$itemId] = $item;
                if (count($results) >= $limit) break 2;
            }
            $i++;
        }
        return array_values($results);
    }

    /* ===================== RECOMMANDATIONS PAR AUTEURS ===================== */

    /**
     * Génère des recommandations basées sur les auteurs
     * des livres lus par l’utilisateur.
     *
     * @param int $limit   Nombre de recommandations.
     * @param int $variant Variante aléatoire.
     *
     * @return array
     */
    public function getByUserAuthors($limit = 6, $variant = 0)
    {
        $userBookIds = $this->getUserGoogleBooksIds();
        if (empty($userBookIds)) return [];

        if ($variant) {
            $copy = $userBookIds;
            mt_srand($variant);
            shuffle($copy);
            $userBookIds = $copy;
        } else {
            shuffle($userBookIds);
        }

        $userIdSet = array_flip($userBookIds);
        $results   = [];
        $sample    = array_slice($userBookIds, 0, 6);
        $i = 0;

        foreach ($sample as $gId) {
            $volume = $this->fetchVolume($gId);
            if (!$volume) continue;

            $info = $volume['volumeInfo'] ?? [];
            if (empty($info['authors'])) continue;

            $author = $info['authors'][0];
            $books = $this->searchVolumesFrWithRetries('inauthor:"' . $author . '"', $limit, $variant + $i);

            foreach ($books as $item) {
                $itemId = $item['id'];
                if (isset($userIdSet[$itemId])) continue;
                $results[$itemId] = $item;
                if (count($results) >= $limit) break 2;
            }
            $i++;
        }
        return array_values($results);
    }

    /* ===================== RECOMMANDATIONS PAR ÉMOTION ===================== */

    /**
     * Génère des recommandations basées sur l’émotion choisie.
     *
     * @param string $emotion Émotion sélectionnée.
     * @param int    $limit   Nombre de recommandations.
     * @param int    $variant Variante aléatoire.
     *
     * @return array
     */
    public function getByEmotion($emotion, $limit = 6, $variant = 0)
    {
        $emotionMap = [
            'heureux'     => ['feel good', 'romance', 'bien-être'],
            'triste'      => ['poésie', 'drame', 'littérature contemporaine'],
            'stresse'     => ['fantasy', 'aventure', 'science fiction'],
            'nostalgique' => ['historique', 'biographie', 'souvenirs'],
            'motivé'      => ['développement personnel', 'succès', 'productivité'],
        ];

        if (!isset($emotionMap[$emotion])) return [];

        $userBookIds = $this->getUserGoogleBooksIds();
        $userIdSet   = array_flip($userBookIds);
        $results     = [];

        $subjects = $emotionMap[$emotion];
        if ($variant) {
            mt_srand($variant);
            shuffle($subjects);
        }

        $i = 0;
        foreach ($subjects as $subject) {
            $books = $this->searchVolumesFrWithRetries('subject:"' . $subject . '"', $limit, $variant + $i);
            foreach ($books as $item) {
                $itemId = $item['id'];
                if (isset($userIdSet[$itemId])) continue;
                $results[$itemId] = $item;
                if (count($results) >= $limit) break 2;
            }
            $i++;
        }
        return array_values($results);
    }
}
