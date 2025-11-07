<?php
// reponse.php - endpoint générique de transformation/filtrage de fichiers ressources
declare(strict_types=1);

// DEBUG: passe ?debug=1 pour obtenir la trace d'erreur en JSON
$debug = (isset($_GET['debug']) && $_GET['debug'] === '1');
if ($debug) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}

try {
    // ---------- Configuration ----------
    $dataDir = __DIR__ . '/datap';
    $resourceFile = 'communes_france_2025_data.csv';
    $globalMaxLimit = 5;
    $rateLimitRequests = 5;
    $rateLimitPeriod = 60;
    $rateLimitDir = sys_get_temp_dir() . '/generic_resource_rate_limit';
    if (!is_dir($rateLimitDir)) @mkdir($rateLimitDir, 0700, true);

    // ---------- Helpers ----------
    function getRequestParam(string $name, $default = null) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return $_POST[$name] ?? ($_GET[$name] ?? $default);
        }
        return $_GET[$name] ?? $default;
    }

    function sendJson($data, int $httpCode = 200) {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    function checkRateLimit(string $ip, int $maxRequests, int $period, string $dir) {
        if (!is_dir($dir) || !is_writable($dir)) return true;
        $key = 'rl_' . preg_replace('/[^a-z0-9\._-]/i', '_', $ip);
        $file = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $key;
        $now = time();
        $times = [];
        if (file_exists($file)) {
            $content = @file_get_contents($file);
            if ($content !== false) {
                $parts = explode(',', $content);
                foreach ($parts as $t) {
                    $ti = (int)$t;
                    if ($ti > $now - $period) $times[] = $ti;
                }
            }
        }
        if (count($times) >= $maxRequests) return false;
        $times[] = $now;
        @file_put_contents($file, implode(',', $times), LOCK_EX);
        return true;
    }

    // ---------- Entrée & validation ----------
    $q = (string)getRequestParam('q', '');
    if ($q === '') sendJson(['error' => 'Paramètre q manquant'], 400);
    $q = trim($q);
    $search = mb_strtolower($q, 'UTF-8');

    $fileParam = (string)getRequestParam('file', '');
    $fileParam = $fileParam === '' ? '' : basename($fileParam);

    $try = [];
    if ($fileParam !== '') $try[] = $fileParam;
    $try[] = $resourceFile;

    $chosenPath = null;
    foreach ($try as $f) {
        $p = $dataDir . DIRECTORY_SEPARATOR . $f;
        if (is_file($p) && is_readable($p)) {
            $chosenPath = $p;
            break;
        }
    }
    if ($chosenPath === null) sendJson(['error' => "Aucun fichier ressource disponible. Vérifie que $resourceFile existe et est lisible."], 500);

    $format = strtolower((string)getRequestParam('format', 'xml'));
    if (!in_array($format, ['html','xml','json','txt','csv'], true)) $format = 'xml';

    $limit = (int)getRequestParam('limit', $globalMaxLimit);
    $limit = max(1, min($limit, $globalMaxLimit));

    $forceXmlHeader = (string)getRequestParam('force_xml_header', '0') === '1';

    // ---------- Rate-limit ----------
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!checkRateLimit($clientIp, $rateLimitRequests, $rateLimitPeriod, $rateLimitDir)) {
        $retryAfter = $rateLimitPeriod;
        header('Retry-After: ' . $retryAfter);
        sendJson(['error' => 'Trop de requêtes. Réessayer dans ' . $retryAfter . ' secondes.'], 429);
    }

    // ---------- Lecture & extraction ----------
    $content = @file_get_contents($chosenPath);
    if ($content === false) sendJson(['error' => 'Impossible de lire le fichier ressource (' . basename($chosenPath) . ')'], 500);

    $ext = strtolower(pathinfo($chosenPath, PATHINFO_EXTENSION));
    $values = [];

    function addValue(array &$arr, $v) {
        $v = trim((string)$v);
        if ($v !== '') $arr[] = $v;
    }

    switch ($ext) {
        case 'txt':
            foreach (preg_split("/\r\n|\n|\r/", $content) as $line) addValue($values, $line);
            break;
        case 'csv':
            $lines = preg_split("/\r\n|\n|\r/", trim($content));
            if (count($lines) > 0) {
                $sep = (strpos($lines[0], ';') !== false) ? ';' : ',';
                foreach ($lines as $ln) {
                    if (trim($ln) === '') continue;
                    $cols = str_getcsv($ln, $sep);
                    foreach ($cols as $c) addValue($values, $c);
                }
            }
            break;
        case 'json':
            $j = json_decode($content, true);
            if (is_array($j)) {
                $stack = [$j];
                while (!empty($stack)) {
                    $node = array_pop($stack);
                    if (is_array($node)) {
                        foreach ($node as $v) $stack[] = $v;
                    } elseif (is_string($node)) addValue($values, $node);
                }
            }
            break;
        case 'html':
            libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            if (@$dom->loadHTML('<?xml encoding="utf-8" ?>' . $content)) {
                $xpath = new DOMXPath($dom);
                $nodes = $xpath->query('//td|//th|//li|//p|//span|//a');
                foreach ($nodes as $n) addValue($values, $n->textContent);
                if (empty($values)) {
                    $body = $dom->getElementsByTagName('body')->item(0);
                    if ($body) addValue($values, $body->textContent);
                }
            } else {
                foreach (preg_split("/\r\n|\n|\r/", $content) as $line) addValue($values, strip_tags($line));
            }
            libxml_clear_errors();
            break;
        case 'xml':
        default:
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($content);
            if ($xml !== false) {
                $texts = $xml->xpath('//*[text()]');
                if ($texts !== false) foreach ($texts as $t) addValue($values, (string)$t);
            } else {
                foreach (preg_split("/\r\n|\n|\r/", $content) as $line) addValue($values, $line);
            }
            libxml_clear_errors();
            break;
    }

    // ---------- Filtrage ----------
    $results = [];
    $found = 0;
    foreach ($values as $v) {
        if ($found >= $limit) break;
        if (mb_strtolower(mb_substr($v, 0, mb_strlen($search, 'UTF-8'), 'UTF-8'), 'UTF-8') === $search) {
            $results[] = $v;
            $found++;
        }
    }

    // ---------- Réponse ----------
    if ($forceXmlHeader) header('Content-Type: application/xml; charset=UTF-8');

    switch ($format) {
        case 'json':
            if (!$forceXmlHeader) header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(array_values($results), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;
        case 'txt':
            if (!$forceXmlHeader) header('Content-Type: text/plain; charset=UTF-8');
            foreach ($results as $r) echo $r . "\n";
            break;
        case 'csv':
            if (!$forceXmlHeader) header('Content-Type: text/csv; charset=UTF-8');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['value'], ';');
            foreach ($results as $r) fputcsv($out, [$r], ';');
            fclose($out);
            break;
        case 'html':
            if (!$forceXmlHeader) header('Content-Type: text/html; charset=UTF-8');
            if (empty($results)) {
                echo '<div class="results"><p>Aucun résultat trouvé.</p></div>';
            } else {
                echo '<div class="results"><table><thead><tr><th>#</th><th>Valeur</th></tr></thead><tbody>';
                foreach ($results as $i => $r) {
                    echo '<tr><td>' . ($i+1) . '</td><td>' . htmlspecialchars($r, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td></tr>';
                }
                echo '</tbody></table></div>';
            }
            break;
        case 'xml':
        default:
            if (!$forceXmlHeader) header('Content-Type: application/xml; charset=UTF-8');
            $domOut = new DOMDocument('1.0', 'UTF-8');
            $domOut->formatOutput = true;
            $root = $domOut->createElement('results');
            $domOut->appendChild($root);
            foreach ($results as $r) {
                $el = $domOut->createElement('item', htmlspecialchars($r, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
                $root->appendChild($el);
            }
            echo $domOut->saveXML();
            break;
    }

    exit;

} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    if ($debug) {
        echo json_encode([
            'error' => 'Exception levée',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } else {
        echo json_encode(['error' => 'Erreur serveur interne'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
