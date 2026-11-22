<?php

declare(strict_types=1);

require __DIR__ . '/../config.php';
ensure_session();

$pdo = get_db();
$errors = [];
$old = ['name' => '', 'rating' => '', 'comment' => '', 'relationship' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['name'] = trim((string) ($_POST['name'] ?? ''));
    $old['rating'] = (string) ($_POST['rating'] ?? '');
    $old['comment'] = trim((string) ($_POST['comment'] ?? ''));
    $old['relationship'] = (string) ($_POST['relationship'] ?? '');

    $token = (string) ($_POST['csrf_token'] ?? '');
    $honeypot = (string) ($_POST['website'] ?? '');
    $formRenderedAt = (int) ($_POST['form_ts'] ?? 0);

    if (!csrf_valid($token)) {
        $errors[] = 'Deine Sitzung ist abgelaufen. Bitte lade die Seite neu und versuche es erneut.';
    } elseif ($honeypot !== '') {
        // Silently pretend success for bots filling the honeypot.
        flash_set('Danke für dein Feedback!', 'success');
        header('Location: /');
        exit;
    } elseif ($formRenderedAt <= 0 || (time() - $formRenderedAt) < MIN_FORM_FILL_SECONDS) {
        $errors[] = 'Das war schnell. Nimm dir einen Moment Zeit und versuche es erneut.';
    } else {
        $name = $old['name'];
        $rating = filter_var($old['rating'], FILTER_VALIDATE_INT);
        $comment = $old['comment'];
        $relationship = $old['relationship'];

        if ($name === '' || mb_strlen($name) > 80) {
            $errors[] = 'Der Name muss zwischen 1 und 80 Zeichen lang sein.';
        }
        if ($rating === false || $rating < 1 || $rating > 5) {
            $errors[] = 'Bitte wähle eine Bewertung zwischen 1 und 5 Sternen.';
        }
        if ($comment === '' || mb_strlen($comment) > 255) {
            $errors[] = 'Der Kommentar ist erforderlich und darf maximal 255 Zeichen lang sein.';
        }
        if (!array_key_exists($relationship, RELATIONSHIPS)) {
            $errors[] = 'Bitte wähle aus, wie du die Person kennst.';
        }

        if (empty($errors)) {
            $rateLimitError = rate_limit_check($pdo);
            if ($rateLimitError !== null) {
                $errors[] = $rateLimitError;
            }
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare(
                'INSERT INTO feedback (name, rating, comment, relationship, ip_hash, created_at) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$name, $rating, $comment, $relationship, ip_hash(), gmdate('Y-m-d H:i:s')]);

            unset($_SESSION['csrf_token']);
            flash_set('Danke für dein Feedback!', 'success');
            header('Location: /');
            exit;
        }
    }
}

$csrfToken = csrf_token();
$formTs = time();
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Feedback abgeben &mdash; Meh</title>
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
            <a href="/">&larr; Zurück</a>
        </nav>
    </header>

    <?php foreach ($errors as $error): ?>
        <div class="flash error"><?= e($error) ?></div>
    <?php endforeach; ?>

    <form class="feedback-form" method="post" action="/feedback" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="form_ts" value="<?= e((string) $formTs) ?>">

        <div class="honeypot-field" aria-hidden="true">
            <label for="website">Website</label>
            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off" value="">
        </div>

        <div class="field">
            <label for="name">Dein Name</label>
            <input type="text" id="name" name="name" maxlength="80" required value="<?= e($old['name']) ?>">
        </div>

        <div class="field">
            <label for="relationship">Wie kennst du die Person?</label>
            <select id="relationship" name="relationship" required>
                <option value="" disabled <?= $old['relationship'] === '' ? 'selected' : '' ?>>Bitte wählen</option>
                <?php foreach (RELATIONSHIPS as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $old['relationship'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label>Bewertung</label>
            <div class="star-rating">
                <?php for ($i = 5; $i >= 1; $i--): ?>
                    <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>"
                        <?= $old['rating'] === (string) $i ? 'checked' : '' ?> required>
                    <label for="star<?= $i ?>" title="<?= $i ?> Stern<?= $i > 1 ? 'e' : '' ?>">★</label>
                <?php endfor; ?>
            </div>
        </div>

        <div class="field">
            <label for="comment">Kommentar</label>
            <textarea id="comment" name="comment" rows="4" maxlength="255" required><?= e($old['comment']) ?></textarea>
            <div class="hint">max. 255 Zeichen</div>
        </div>

        <button type="submit" class="submit">Feedback abschicken</button>
    </form>

    <footer class="site">Meh &mdash; weil auch mittelmäßige Meinungen eine Bühne verdienen.</footer>
</div>
</body>
</html>
