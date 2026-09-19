<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('APP_NAME', 'Livro');
define('BASE_PATH', __DIR__);
define('DATA_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'data');
define('DB_PATH', DATA_PATH . DIRECTORY_SEPARATOR . 'livro.sqlite');

function load_env_file(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");

        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
        }
    }
}

load_env_file(BASE_PATH . '/.env');

define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: '');

function security_questions(): array
{
    return [
        'Qual o nome do seu primeiro animal de estimacao?',
        'Qual o nome da cidade onde voce nasceu?',
        'Qual o nome do seu melhor amigo de infancia?',
        'Qual o nome de solteira da sua mae?',
        'Qual foi o modelo do seu primeiro carro?',
    ];
}

function ensure_data_path(): void
{
    if (!is_dir(DATA_PATH)) {
        mkdir(DATA_PATH, 0755, true);
    }
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    ensure_data_path();

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    return $pdo;
}

function init_database(): void
{
    $pdo = db();

    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    SQL);

    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS books (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            author TEXT,
            release_year INTEGER,
            pages INTEGER,
            genre TEXT,
            characters TEXT,
            summary TEXT,
            cover_url TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(title, author)
        )
    SQL);

    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS user_books (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            book_id INTEGER NOT NULL,
            status TEXT NOT NULL CHECK(status IN ('Ja li', 'Lendo', 'Quero ler', 'Nao li')),
            rating INTEGER CHECK(rating BETWEEN 1 AND 5),
            read_at TEXT,
            review TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(user_id, book_id),
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY(book_id) REFERENCES books(id) ON DELETE CASCADE
        )
    SQL);

    migrate_users_email_verification($pdo);
    migrate_user_books_status($pdo);

    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS password_resets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            token_hash TEXT NOT NULL UNIQUE,
            expires_at TEXT NOT NULL,
            used_at TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    SQL);

    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS rate_limit_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            action TEXT NOT NULL,
            identifier TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    SQL);
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_rate_limit_lookup ON rate_limit_attempts (action, identifier, created_at)');
}

function too_many_attempts(string $action, string $identifier, int $maxAttempts, int $windowSeconds): bool
{
    $pdo = db();
    $cutoff = gmdate('Y-m-d H:i:s', time() - $windowSeconds);

    $pdo->prepare('DELETE FROM rate_limit_attempts WHERE action = :action AND created_at < :cutoff')
        ->execute(['action' => $action, 'cutoff' => $cutoff]);

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM rate_limit_attempts WHERE action = :action AND identifier = :identifier AND created_at >= :cutoff');
    $stmt->execute(['action' => $action, 'identifier' => $identifier, 'cutoff' => $cutoff]);

    return (int) $stmt->fetchColumn() >= $maxAttempts;
}

function record_attempt(string $action, string $identifier): void
{
    db()->prepare('INSERT INTO rate_limit_attempts (action, identifier) VALUES (:action, :identifier)')
        ->execute(['action' => $action, 'identifier' => $identifier]);
}

function migrate_users_email_verification(PDO $pdo): void
{
    $columns = $pdo->query('PRAGMA table_info(users)')->fetchAll();
    $columnNames = array_column($columns, 'name');

    if (!in_array('email_verified_at', $columnNames, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN email_verified_at TEXT');
    }
    if (!in_array('email_verification_token_hash', $columnNames, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN email_verification_token_hash TEXT');
    }
    if (!in_array('email_verification_expires_at', $columnNames, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN email_verification_expires_at TEXT');
    }
    if (!in_array('gender', $columnNames, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN gender TEXT');
    }
    if (!in_array('country', $columnNames, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN country TEXT');
    }
    if (!in_array('state', $columnNames, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN state TEXT');
    }
    if (!in_array('birth_date', $columnNames, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN birth_date TEXT');
    }
    if (!in_array('is_profile_public', $columnNames, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN is_profile_public INTEGER NOT NULL DEFAULT 1');
    }
    if (!in_array('security_question', $columnNames, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN security_question TEXT');
    }
    if (!in_array('security_answer_hash', $columnNames, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN security_answer_hash TEXT');
    }

    $pdo->exec("UPDATE users SET email_verified_at = created_at WHERE email_verified_at IS NULL AND email_verification_token_hash IS NULL");
}

function migrate_user_books_status(PDO $pdo): void
{
    $sql = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='user_books'")->fetchColumn();
    if (!is_string($sql) || str_contains($sql, 'Nao li')) {
        return;
    }

    $pdo->exec('PRAGMA foreign_keys = OFF');
    $pdo->exec('ALTER TABLE user_books RENAME TO user_books_old');
    $pdo->exec(<<<SQL
        CREATE TABLE user_books (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            book_id INTEGER NOT NULL,
            status TEXT NOT NULL CHECK(status IN ('Ja li', 'Lendo', 'Quero ler', 'Nao li')),
            rating INTEGER CHECK(rating BETWEEN 1 AND 5),
            read_at TEXT,
            review TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(user_id, book_id),
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY(book_id) REFERENCES books(id) ON DELETE CASCADE
        )
    SQL);
    $pdo->exec('INSERT INTO user_books SELECT * FROM user_books_old');
    $pdo->exec('DROP TABLE user_books_old');
    $pdo->exec('PRAGMA foreign_keys = ON');
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, name, email, gender, country, state, birth_date, is_profile_public, security_question, created_at FROM users WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);

    return $stmt->fetch() ?: null;
}

function require_auth(): array
{
    $user = current_user();

    if (!$user) {
        header('Location: login.php');
        exit;
    }

    return $user;
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function request_json(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);

    return is_array($data) ? $data : [];
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function valid_csrf_token(?string $token): bool
{
    return is_string($token) && hash_equals(csrf_token(), $token);
}

function require_csrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!valid_csrf_token($token)) {
        json_response(['error' => 'Token de seguranca invalido. Atualize a pagina e tente novamente.'], 403);
    }
}

function app_shell_head(string $title): void
{
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . h($title) . ' | ' . APP_NAME . '</title>';
    echo '<link rel="icon" type="image/png" href="assets/logo_livro.png">';
    echo '<script>tailwind={config:{theme:{extend:{fontFamily:{sans:["Inter","ui-sans-serif","system-ui","sans-serif"]}}}}}</script><script src="https://cdn.tailwindcss.com"></script>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">';
    echo '<style>body>main,body>div{min-height:0!important;flex:1 0 auto}</style>';
    echo '<script src="https://unpkg.com/@phosphor-icons/web"></script></head><body class="flex min-h-screen flex-col bg-zinc-50 pb-16 font-sans text-zinc-900 antialiased">';
}

function app_shell_foot(): void
{
    echo '<footer class="fixed inset-x-0 bottom-0 z-50 flex h-16 items-center justify-center border-t border-zinc-700 bg-zinc-900 px-6 text-center text-sm font-medium text-zinc-100 shadow-[0_-4px_16px_rgba(0,0,0,0.12)]">Renato Fiuza - Versão 1.03 (2026)</footer>';
    echo '</body></html>';
}

init_database();
