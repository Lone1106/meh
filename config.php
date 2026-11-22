<?php
declare(strict_types=1);

const DB_PATH = __DIR__ . '/data/feedback.sqlite';

const SITE_URL = 'https://meh.janrei.de';

// Rate limiting: how many submissions an IP may make in each window.
const RATE_LIMIT_COOLDOWN_SECONDS = 60;   // min gap between two submissions
const RATE_LIMIT_DAILY_MAX = 5;           // max submissions per IP per day
const MIN_FORM_FILL_SECONDS = 3;          // reject submissions faster than this (bots)

const RELATIONSHIPS = [
    'freund' => 'Freund(in)',
    'bekannter' => 'Bekannte(r)',
    'kollege' => 'Kolleg(in)',
    'sonstiges' => 'Sonstiges',
];

require_once __DIR__ . '/lib/mailer.php';

function ensure_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function get_db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dir = dirname(DB_PATH);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS feedback (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                rating INTEGER NOT NULL,
                comment TEXT NOT NULL,
                relationship TEXT NOT NULL DEFAULT "sonstiges",
                ip_hash TEXT NOT NULL,
                created_at TEXT NOT NULL
            )
        ');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_feedback_ip_created ON feedback (ip_hash, created_at)');

        // Migration for databases created before the relationship column existed.
        $columns = $pdo->query('PRAGMA table_info(feedback)')->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('relationship', $columns, true)) {
            $pdo->exec('ALTER TABLE feedback ADD COLUMN relationship TEXT NOT NULL DEFAULT "sonstiges"');
        }
    }
    return $pdo;
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function ip_hash(): string
{
    // Hashed so raw IPs are never stored.
    return hash('sha256', client_ip() . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_valid(string $token): bool
{
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Returns null if the IP is allowed to post, or an error string if rate-limited.
 */
function rate_limit_check(PDO $pdo): ?string
{
    $hash = ip_hash();

    $stmt = $pdo->prepare('SELECT created_at FROM feedback WHERE ip_hash = ? ORDER BY created_at DESC LIMIT 1');
    $stmt->execute([$hash]);
    $last = $stmt->fetchColumn();
    if ($last !== false) {
        $secondsSinceLast = time() - strtotime((string) $last);
        if ($secondsSinceLast < RATE_LIMIT_COOLDOWN_SECONDS) {
            return 'Du bist zu schnell. Bitte warte einen Moment, bevor du erneut Feedback abschickst.';
        }
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM feedback WHERE ip_hash = ? AND created_at >= datetime('now', '-1 day')");
    $stmt->execute([$hash]);
    $countToday = (int) $stmt->fetchColumn();
    if ($countToday >= RATE_LIMIT_DAILY_MAX) {
        return 'Tageslimit erreicht. Bitte versuche es morgen wieder.';
    }

    return null;
}

function flash_set(string $message, string $type = 'info'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function flash_get(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
