<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

function http_json(string $url, array $headers = [], ?array $post = null): ?array
{
    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => 'LivroApp/1.0',
        ]);

        if ($post !== null) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($post, JSON_UNESCAPED_UNICODE));
        }

        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($body === false || $status >= 400) {
            return null;
        }

        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : null;
    }

    if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
        return null;
    }

    $context = null;
    $requestHeaders = array_merge(['User-Agent: LivroApp/1.0'], $headers);
    if ($post !== null || $requestHeaders) {
        $context = stream_context_create([
            'http' => [
                'method' => $post === null ? 'GET' : 'POST',
                'header' => implode("\r\n", $requestHeaders),
                'content' => $post === null ? '' : json_encode($post, JSON_UNESCAPED_UNICODE),
                'timeout' => 15,
            ],
        ]);
    }

    $body = @file_get_contents($url, false, $context);
    $decoded = $body ? json_decode($body, true) : null;

    return is_array($decoded) ? $decoded : null;
}

function decode_book_characters(array $row): array
{
    $decoded = json_decode($row['characters'] ?? '[]', true);
    $row['characters'] = is_array($decoded) ? $decoded : [];

    return $row;
}

function normalize_cover(?string $url): string
{
    if (!$url) {
        return '';
    }

    $url = preg_replace('/^http:/', 'https:', $url) ?? $url;
    $url = str_replace('zoom=1', 'zoom=0', $url);

    return $url;
}

function extract_json_array(string $text): array
{
    if (preg_match('/\[[\s\S]*\]/', $text, $matches)) {
        $decoded = json_decode($matches[0], true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('strval', $decoded)));
        }
    }

    return [];
}

function extract_json_object(string $text): array
{
    $decoded = json_decode($text, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
        $decoded = json_decode($matches[0], true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return [];
}

function normalize_ai_book_payload(array $data, string $fallbackTitle): array
{
    $characters = $data['characters'] ?? [];
    if (!is_array($characters)) {
        $characters = [];
    }

    return [
        'title' => trim((string) ($data['title'] ?? $fallbackTitle)),
        'author' => trim((string) ($data['author'] ?? '')),
        'release_year' => is_numeric($data['release_year'] ?? null) ? (int) $data['release_year'] : null,
        'pages' => is_numeric($data['pages'] ?? null) ? (int) $data['pages'] : null,
        'genre' => trim((string) ($data['genre'] ?? '')),
        'summary' => trim((string) ($data['summary'] ?? '')),
        'cover_url' => filter_var($data['cover_url'] ?? '', FILTER_VALIDATE_URL) ? (string) $data['cover_url'] : '',
        'characters' => array_slice(array_values(array_filter(array_map('strval', $characters))), 0, 6),
    ];
}

function merge_book_payload(array $book, array $fallback): array
{
    foreach (['release_year', 'pages', 'genre', 'summary'] as $field) {
        if (($fallback[$field] ?? null) !== null && ($fallback[$field] ?? '') !== '') {
            $book[$field] = $fallback[$field];
        }
    }

    foreach (['title', 'author', 'cover_url'] as $field) {
        if (($book[$field] ?? null) === null || ($book[$field] ?? '') === '') {
            $book[$field] = $fallback[$field] ?? $book[$field] ?? null;
        }
    }

    if (!empty($fallback['characters'])) {
        $book['characters'] = $fallback['characters'];
    }

    return $book;
}

function full_book_prompt(string $title, string $author = ''): string
{
    $authorText = $author !== ''
        ? ' Pista para identificar a edicao certa (pode ser parcial ou incompleta, use apenas para desambiguar): o nome do autor contem "' . $author . '".'
        : '';

    return 'Para o livro com titulo "' . $title . '".' . $authorText . ' Retorne estritamente um JSON valido em portugues, sem markdown e sem comentarios, com exatamente estes campos: {"title":"","author":"","release_year":null,"pages":null,"genre":"","summary":"","cover_url":"","characters":[]}. Regras: title deve ser o titulo oficial; author deve ser o nome completo e correto do(s) autor(es) do livro real -- nunca copie a pista parcial como se fosse o nome inteiro, sempre complete com o nome verdadeiro e completo; release_year deve ser o ano de publicacao como numero; pages deve ser a estimativa media de paginas de uma edicao comum quando a quantidade exata variar; genre deve ser uma string com o tema principal, por exemplo "Romance / Realismo"; summary deve ter 3 a 5 linhas, em portugues, sem spoilers excessivos; cover_url deve ser uma URL real de capa somente se voce tiver certeza, caso contrario retorne ""; characters deve ser um array JSON de strings com 3 a 6 personagens principais da trama, por exemplo ["Bentinho", "Capitu", "Escobar"]. Se for nao-ficcao, characters deve ser []. Se algum dado for incerto, use null, "" ou [] em vez de inventar.';
}

function book_with_gemini(string $title, string $author = ''): ?array
{
    if (GEMINI_API_KEY === '') {
        return null;
    }

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-lite-latest:generateContent?key=' . urlencode(GEMINI_API_KEY);
    $response = http_json($url, ['Content-Type: application/json'], [
        'contents' => [[
            'parts' => [['text' => full_book_prompt($title, $author)]],
        ]],
        'generationConfig' => ['temperature' => 0.1, 'responseMimeType' => 'application/json'],
    ]);

    $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $data = is_string($text) ? extract_json_object($text) : [];

    return $data ? normalize_ai_book_payload($data, $title) : null;
}

function book_with_openai(string $title, string $author = ''): ?array
{
    if (OPENAI_API_KEY === '') {
        return null;
    }

    $response = http_json('https://api.openai.com/v1/chat/completions', [
        'Content-Type: application/json',
        'Authorization: Bearer ' . OPENAI_API_KEY,
    ], [
        'model' => 'gpt-4o-mini',
        'messages' => [[
            'role' => 'user',
            'content' => full_book_prompt($title, $author),
        ]],
        'temperature' => 0.1,
        'response_format' => ['type' => 'json_object'],
    ]);

    $text = $response['choices'][0]['message']['content'] ?? '';
    $data = is_string($text) ? extract_json_object($text) : [];

    return $data ? normalize_ai_book_payload($data, $title) : null;
}

function first_publication_year(array $item): ?int
{
    $publishedDate = $item['publishedDate'] ?? '';
    if (is_string($publishedDate) && preg_match('/\d{4}/', $publishedDate, $matches)) {
        return (int) $matches[0];
    }

    return null;
}

function google_book_payload(array $item, string $fallbackTitle): array
{
    return [
        'title' => $item['title'] ?? $fallbackTitle,
        'author' => implode(', ', $item['authors'] ?? []),
        'release_year' => first_publication_year($item),
        'pages' => $item['pageCount'] ?? null,
        'genre' => $item['categories'][0] ?? '',
        'summary' => trim(strip_tags($item['description'] ?? '')),
        'cover_url' => normalize_cover($item['imageLinks']['extraLarge'] ?? $item['imageLinks']['large'] ?? $item['imageLinks']['medium'] ?? $item['imageLinks']['thumbnail'] ?? $item['imageLinks']['smallThumbnail'] ?? ''),
        'characters' => [],
    ];
}

function google_volume_details(string $volumeId): ?array
{
    $url = 'https://www.googleapis.com/books/v1/volumes/' . rawurlencode($volumeId) . '?projection=full';
    $response = http_json($url);
    $volumeInfo = $response['volumeInfo'] ?? null;

    return is_array($volumeInfo) ? $volumeInfo : null;
}

function google_volume_needs_details(array $volumeInfo): bool
{
    return empty($volumeInfo['description'])
        || empty($volumeInfo['pageCount'])
        || empty($volumeInfo['categories']);
}

function author_matches(string $authorsText, string $wanted): bool
{
    return $wanted !== '' && stripos($authorsText, $wanted) !== false;
}

function find_google_book(string $query, string $author = ''): ?array
{
    $queries = [];
    if ($author !== '') {
        $queries[] = 'intitle:' . $query . ' inauthor:' . $author;
    }
    $queries[] = $query;
    $queries[] = 'intitle:' . $query;
    $queries[] = $query . ' livro';
    $queries[] = $query . ' book';

    foreach (array_unique($queries) as $search) {
        $url = 'https://www.googleapis.com/books/v1/volumes?q=' . urlencode($search) . '&projection=full&maxResults=10&printType=books&orderBy=relevance';
        $response = http_json($url);
        $items = $response['items'] ?? [];

        if (!is_array($items) || !$items) {
            continue;
        }

        usort($items, function (array $a, array $b) use ($author): int {
            $bookA = $a['volumeInfo'] ?? [];
            $bookB = $b['volumeInfo'] ?? [];
            $matchA = author_matches(implode(', ', $bookA['authors'] ?? []), $author) ? 10 : 0;
            $matchB = author_matches(implode(', ', $bookB['authors'] ?? []), $author) ? 10 : 0;
            $scoreA = $matchA + (isset($bookA['title']) ? 4 : 0) + (!empty($bookA['authors']) ? 3 : 0) + (isset($bookA['description']) ? 2 : 0) + (isset($bookA['imageLinks']) ? 1 : 0);
            $scoreB = $matchB + (isset($bookB['title']) ? 4 : 0) + (!empty($bookB['authors']) ? 3 : 0) + (isset($bookB['description']) ? 2 : 0) + (isset($bookB['imageLinks']) ? 1 : 0);

            return $scoreB <=> $scoreA;
        });

        $selectedItem = $items[0];
        if ($author !== '') {
            $selectedItem = null;
            foreach ($items as $item) {
                $info = $item['volumeInfo'] ?? [];
                if (author_matches(implode(', ', $info['authors'] ?? []), $author)) {
                    $selectedItem = $item;
                    break;
                }
            }
            if ($selectedItem === null) {
                continue;
            }
        }

        $volumeInfo = $selectedItem['volumeInfo'] ?? null;
        if (is_array($volumeInfo)) {
            $volumeId = $selectedItem['id'] ?? '';
            if (google_volume_needs_details($volumeInfo) && is_string($volumeId) && $volumeId !== '') {
                $details = google_volume_details($volumeId);
                if ($details) {
                    $volumeInfo = array_replace_recursive($volumeInfo, $details);
                }
            }

            return google_book_payload($volumeInfo, $query);
        }
    }

    return null;
}

function find_open_library_book(string $query, string $author = ''): ?array
{
    $fields = 'title,author_name,first_publish_year,number_of_pages_median,subject,cover_i';
    $url = 'https://openlibrary.org/search.json?title=' . urlencode($query) . '&limit=5&fields=' . urlencode($fields);
    if ($author !== '') {
        $url .= '&author=' . urlencode($author);
    }
    $response = http_json($url);
    $docs = $response['docs'] ?? [];

    if (!is_array($docs) || !$docs) {
        return null;
    }

    $doc = $docs[0];
    if ($author !== '') {
        $doc = null;
        foreach ($docs as $candidate) {
            if (author_matches(implode(', ', $candidate['author_name'] ?? []), $author)) {
                $doc = $candidate;
                break;
            }
        }
        if ($doc === null) {
            return null;
        }
    }

    $coverUrl = '';
    if (!empty($doc['cover_i'])) {
        $coverUrl = 'https://covers.openlibrary.org/b/id/' . rawurlencode((string) $doc['cover_i']) . '-L.jpg';
    }

    return [
        'title' => $doc['title'] ?? $query,
        'author' => implode(', ', $doc['author_name'] ?? []),
        'release_year' => $doc['first_publish_year'] ?? null,
        'pages' => $doc['number_of_pages_median'] ?? null,
        'genre' => $doc['subject'][0] ?? '',
        'summary' => '',
        'cover_url' => $coverUrl,
        'characters' => [],
    ];
}

function characters_with_gemini(string $title, string $author): array
{
    if (GEMINI_API_KEY === '') {
        return [];
    }

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-lite-latest:generateContent?key=' . urlencode(GEMINI_API_KEY);
    $prompt = 'Dado o livro "' . $title . '" do autor "' . $author . '", retorne APENAS um array JSON de strings com os 3 a 6 personagens principais da trama. Exemplo: ["Bentinho", "Capitu", "Escobar"]. Se for nao-ficcao, retorne []. Sem markdown, sem comentarios.';
    $response = http_json($url, ['Content-Type: application/json'], [
        'contents' => [[
            'parts' => [['text' => $prompt]],
        ]],
        'generationConfig' => ['temperature' => 0.1, 'responseMimeType' => 'application/json'],
    ]);

    $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';

    return is_string($text) ? extract_json_array($text) : [];
}

function characters_with_openai(string $title, string $author): array
{
    if (OPENAI_API_KEY === '') {
        return [];
    }

    $response = http_json('https://api.openai.com/v1/chat/completions', [
        'Content-Type: application/json',
        'Authorization: Bearer ' . OPENAI_API_KEY,
    ], [
        'model' => 'gpt-4o-mini',
        'messages' => [[
            'role' => 'user',
            'content' => 'Dado o livro "' . $title . '" do autor "' . $author . '", retorne APENAS um array JSON de strings com os 3 a 6 personagens principais da trama. Exemplo: ["Bentinho", "Capitu", "Escobar"]. Se for nao-ficcao, retorne []. Sem markdown, sem comentarios.',
        ]],
        'temperature' => 0.1,
    ]);

    $text = $response['choices'][0]['message']['content'] ?? '';

    return is_string($text) ? extract_json_array($text) : [];
}

function characters_with_wikidata(string $title): array
{
    $query = '
        SELECT ?characterLabel WHERE {
          ?work rdfs:label ?label.
          FILTER(LCASE(STR(?label)) = LCASE("' . str_replace('"', '\\"', $title) . '"))
          ?work wdt:P674 ?character.
          SERVICE wikibase:label { bd:serviceParam wikibase:language "pt,en". }
        }
        LIMIT 7
    ';
    $url = 'https://query.wikidata.org/sparql?format=json&query=' . urlencode($query);
    $response = http_json($url, ['Accept: application/sparql-results+json', 'User-Agent: LivroApp/1.0']);
    $rows = $response['results']['bindings'] ?? [];
    $names = [];

    foreach ($rows as $row) {
        $name = $row['characterLabel']['value'] ?? '';
        if (is_string($name) && $name !== '') {
            $names[] = $name;
        }
    }

    return array_values(array_unique($names));
}

function find_local_book(string $query, string $author = ''): ?array
{
    $stmt = db()->prepare('SELECT * FROM books WHERE LOWER(title) = LOWER(:title) ORDER BY id DESC');
    $stmt->execute(['title' => $query]);
    $rows = $stmt->fetchAll();
    $isExactMatch = (bool) $rows;

    if (!$rows) {
        $stmt = db()->prepare('SELECT * FROM books WHERE LOWER(title) LIKE LOWER(:title) ORDER BY id DESC LIMIT 5');
        $stmt->execute(['title' => '%' . $query . '%']);
        $rows = $stmt->fetchAll();
    }

    if (!$rows) {
        return null;
    }

    if ($author === '') {
        return decode_book_characters($rows[0]);
    }

    foreach ($rows as $row) {
        if (author_matches($row['author'] ?? '', $author)) {
            return decode_book_characters($row);
        }
    }

    if ($isExactMatch && count($rows) === 1) {
        return decode_book_characters($rows[0]);
    }

    return null;
}

$action = $_GET['action'] ?? '';

try {
    if ($action === 'search_book') {
        require_auth();
        $query = trim($_GET['q'] ?? '');
        $authorHint = trim($_GET['author'] ?? '');
        if ($query === '') {
            json_response(['error' => 'Informe o titulo do livro.'], 422);
        }

        $localBook = find_local_book($query, $authorHint);
        if ($localBook) {
            json_response(['book' => $localBook, 'source' => 'local']);
        }

        $book = find_google_book($query, $authorHint) ?: find_open_library_book($query, $authorHint);
        $needsAiCompletion = !$book || empty($book['release_year']) || empty($book['pages']) || empty($book['summary']) || empty($book['genre']) || empty($book['characters']);

        if ($needsAiCompletion) {
            $aiAuthor = $authorHint !== '' ? $authorHint : ($book['author'] ?? '');
            $aiBook = book_with_gemini($query, $aiAuthor) ?: book_with_openai($query, $aiAuthor);
            if ($aiBook) {
                $book = $book ? merge_book_payload($book, $aiBook) : $aiBook;
            }
        }

        if (!$book) {
            json_response(['error' => 'Nao foi possivel buscar nas APIs externas pelo PHP. Habilite curl ou openssl no PHP para chamadas HTTPS.'], 503);
        }

        json_response(['book' => $book]);
    }

    if ($action === 'characters') {
        require_auth();
        $title = trim($_GET['title'] ?? '');
        $author = trim($_GET['author'] ?? '');

        if ($title === '') {
            json_response(['characters' => []]);
        }

        $characters = characters_with_gemini($title, $author);
        if (!$characters) {
            $characters = characters_with_openai($title, $author);
        }
        if (!$characters) {
            $characters = characters_with_wikidata($title);
        }

        json_response(['characters' => array_slice($characters, 0, 7)]);
    }

    if ($action === 'save_reading') {
        $user = require_auth();
        require_csrf();
        $data = request_json();
        $title = trim($data['title'] ?? '');
        $author = trim($data['author'] ?? '');
        $status = trim($data['status'] ?? 'Quero ler');
        $characters = array_values(array_filter(array_map('trim', $data['characters'] ?? [])));

        if ($title === '' || !in_array($status, ['Ja li', 'Lendo', 'Quero ler', 'Nao li'], true)) {
            json_response(['error' => 'Dados obrigatorios invalidos.'], 422);
        }

        $pdo = db();
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('INSERT INTO books (title, author, release_year, pages, genre, characters, summary, cover_url) VALUES (:title, :author, :release_year, :pages, :genre, :characters, :summary, :cover_url) ON CONFLICT(title, author) DO UPDATE SET release_year = excluded.release_year, pages = excluded.pages, genre = excluded.genre, characters = excluded.characters, summary = excluded.summary, cover_url = excluded.cover_url');
        $stmt->execute([
            'title' => $title,
            'author' => $author,
            'release_year' => $data['release_year'] !== '' ? $data['release_year'] : null,
            'pages' => $data['pages'] !== '' ? $data['pages'] : null,
            'genre' => trim($data['genre'] ?? ''),
            'characters' => json_encode($characters, JSON_UNESCAPED_UNICODE),
            'summary' => trim($data['summary'] ?? ''),
            'cover_url' => trim($data['cover_url'] ?? ''),
        ]);

        $bookStmt = $pdo->prepare('SELECT id FROM books WHERE title = :title AND author = :author');
        $bookStmt->execute(['title' => $title, 'author' => $author]);
        $bookId = (int) $bookStmt->fetchColumn();

        $readingStmt = $pdo->prepare('INSERT INTO user_books (user_id, book_id, status, rating, read_at, review) VALUES (:user_id, :book_id, :status, :rating, :read_at, :review) ON CONFLICT(user_id, book_id) DO UPDATE SET status = excluded.status, rating = excluded.rating, read_at = excluded.read_at, review = excluded.review');
        $readingStmt->execute([
            'user_id' => $user['id'],
            'book_id' => $bookId,
            'status' => $status,
            'rating' => $data['rating'] ?: null,
            'read_at' => $data['read_at'] ?: null,
            'review' => trim($data['review'] ?? ''),
        ]);

        $pdo->commit();

        json_response(['ok' => true]);
    }

    if ($action === 'delete_book') {
        require_auth();
        require_csrf();
        $data = request_json();
        $bookId = (int) ($data['book_id'] ?? 0);
        if ($bookId <= 0) {
            json_response(['error' => 'Livro invalido.'], 422);
        }

        $readCountStmt = db()->prepare("SELECT COUNT(*) FROM user_books WHERE book_id = :book_id AND status = 'Ja li'");
        $readCountStmt->execute(['book_id' => $bookId]);
        if ((int) $readCountStmt->fetchColumn() > 0) {
            json_response(['error' => 'Nao e possivel excluir: alguem ja marcou este livro como lido.'], 409);
        }

        $stmt = db()->prepare('DELETE FROM books WHERE id = :id');
        $stmt->execute(['id' => $bookId]);

        json_response(['ok' => true]);
    }

    if ($action === 'catalog') {
        $user = require_auth();
        $stmt = db()->prepare(<<<SQL
            SELECT
                b.*,
                COALESCE(ub.status, 'Quero ler') AS status,
                ub.rating,
                ub.read_at,
                ub.review,
                CASE WHEN ub.id IS NOT NULL THEN 1 ELSE 0 END AS has_reading
            FROM books b
            LEFT JOIN user_books ub ON ub.book_id = b.id AND ub.user_id = :user_id
            ORDER BY b.title ASC
        SQL);
        $stmt->execute(['user_id' => $user['id']]);
        json_response(['books' => array_map('decode_book_characters', $stmt->fetchAll())]);
    }

    if ($action === 'feed') {
        require_auth();
        $rows = db()->query("SELECT b.*, ub.status, ub.rating, ub.read_at, ub.review, u.name AS user_name, (SELECT ROUND(AVG(rating), 1) FROM user_books WHERE book_id = b.id AND rating IS NOT NULL) AS community_rating FROM user_books ub JOIN books b ON b.id = ub.book_id JOIN users u ON u.id = ub.user_id WHERE u.is_profile_public = 1 ORDER BY ub.created_at DESC LIMIT 50")->fetchAll();
        json_response(['books' => array_map('decode_book_characters', $rows)]);
    }

    if ($action === 'feed_by_person') {
        require_auth();
        $rows = db()->query(<<<SQL
            SELECT
                u.id AS user_id,
                u.name AS user_name,
                (SELECT COUNT(*) FROM user_books WHERE user_id = u.id AND status = 'Ja li') AS total_read,
                (SELECT b2.title FROM user_books ub2 JOIN books b2 ON b2.id = ub2.book_id WHERE ub2.user_id = u.id AND ub2.status IN ('Ja li', 'Lendo') ORDER BY ub2.created_at DESC LIMIT 1) AS last_book_title,
                (SELECT ub3.status FROM user_books ub3 WHERE ub3.user_id = u.id AND ub3.status IN ('Ja li', 'Lendo') ORDER BY ub3.created_at DESC LIMIT 1) AS last_book_status
            FROM users u
                        WHERE u.is_profile_public = 1
                            AND EXISTS (SELECT 1 FROM user_books WHERE user_id = u.id)
            ORDER BY total_read DESC, u.name ASC
        SQL)->fetchAll();
        json_response(['people' => $rows]);
    }

    json_response(['error' => 'Endpoint nao encontrado.'], 404);
} catch (Throwable $exception) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }

    json_response(['error' => 'Erro interno.'], 500);
}
