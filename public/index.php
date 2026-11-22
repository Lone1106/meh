<?php

declare(strict_types=1);

require __DIR__ . '/../config.php';
ensure_session();

$pdo = get_db();
$entries = $pdo->query('SELECT name, rating, comment, relationship, created_at FROM feedback ORDER BY created_at DESC LIMIT 100')
    ->fetchAll(PDO::FETCH_ASSOC);

$avgStmt = $pdo->query('SELECT COUNT(*) AS n, AVG(rating) AS avg_rating FROM feedback');
$stats = $avgStmt->fetch(PDO::FETCH_ASSOC);
$count = (int) $stats['n'];
$average = $count > 0 ? round((float) $stats['avg_rating'], 1) : null;

$flash = flash_get();

function render_stars(int $rating): string
{
    $rating = max(0, min(5, $rating));
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        $html .= $i <= $rating ? '★' : '<span class="dim">★</span>';
    }
    return $html;
}
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Meh &mdash; Feedback</title>
    <link rel="icon" type="image/png" href="/favicon/favicon-96x96.png" sizes="96x96">
    <link rel="icon" type="image/svg+xml" href="/favicon/favicon.svg">
    <link rel="shortcut icon" href="/favicon/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/favicon/apple-touch-icon.png">
    <link rel="manifest" href="/favicon/site.webmanifest">
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<div class="wrap">
    <header class="site">
        <a href="/" class="brand">
            <img src="/assets/logo-meh.svg" alt="Meh" class="logo">
        </a>
        <nav class="actions">
            <a href="/feedback">Feedback abgeben</a>
        </nav>
    </header>

    <?php if ($flash): ?>
        <div class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <?php if ($count > 0): ?>
        <div class="summary">
            <span class="score"><?= e((string) $average) ?><span class="of5">/5</span></span>
            <span class="dot">&middot;</span>
            <span class="count"><?= $count ?> <?= $count === 1 ? 'Bewertung' : 'Bewertungen' ?></span>
        </div>
    <?php endif; ?>

    <?php if (empty($entries)): ?>
        <p class="empty">Noch kein Feedback. Sei die erste Person, die etwas sagt.</p>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($entries as $entry): ?>
                <div class="card">
                    <div class="stars"><?= render_stars((int) $entry['rating']) ?></div>
                    <div class="comment"><?= e($entry['comment']) ?></div>
                    <div class="meta">
                        <div class="who">
                            <span class="name"><?= e($entry['name']) ?></span>
                            <span class="tag"><?= e(RELATIONSHIPS[$entry['relationship']] ?? RELATIONSHIPS['sonstiges']) ?></span>
                        </div>
                        <span class="date"><?= e(date('d.m.Y', strtotime((string) $entry['created_at']))) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <footer class="site">Meh &mdash; weil auch mittelmäßige Meinungen eine Bühne verdienen.</footer>
</div>
</body>
</html>
