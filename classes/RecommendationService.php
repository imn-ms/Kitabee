<?php
class RecommendationService
{
    private $pdo;
    private $userId;
    private $apiKey;
    private $googleApiBase = 'https://www.googleapis.com/books/v1/volumes';

    public function __construct($pdo, $userId, $apiKey = null)
    {
        $this->pdo    = $pdo;
        $this->userId = (int)$userId;
        $this->apiKey = $apiKey;
    }

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

    private function fetchVolume($googleId)
    {
        $url = $this->googleApiBase . '/' . urlencode($googleId);
        if ($this->apiKey) $url .= '?key=' . urlencode($this->apiKey);
        return $this->fetchJson($url) ?: null;
    }

    /** ===== FILTRE TITRE FR ===== */
    private function isFrenchTitle($title, $lang)
    {
        $titleLower = mb_strtolower($title, 'UTF-8');
        // Si la langue est FR, c’est bon
        if (in_array($lang, ['fr', 'fr-fr', 'fr-FR'])) {
            return true;
        }

        // S’il y a des accents typiques du français
        if (preg_match('/[éèêëàâîïôöûüç]/u', $titleLower)) {
            return true;
        }

        // On exclut les titres trop anglais
        $englishWords = [' the ', ' of ', ' and ', ' love ', ' girl ', ' man ', ' war ', 'life', 'story'];
        foreach ($englishWords as $w) {
            if (str_contains($titleLower, $w)) {
                return false;
            }
        }

        // S’il n’y a pas de mot anglais suspect → on garde
        return true;
    }

    /** ===== Recherche filtrée FR ===== */
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

    /* ===================== RECO GENRES ===================== */
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

    /* ===================== RECO AUTEURS ===================== */
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

    /* ===================== RECO EMOTION ===================== */
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
