<?php
/**
 * Plain-text body for the "new feedback" notification email.
 * Rendered by render_mail_template() in mailer.php.
 *
 * Note: every line ends its echo with an explicit "\n" rather than relying
 * on the newline in the template source — PHP swallows a single newline
 * immediately following "?>", which silently merges lines otherwise.
 *
 * @var string $name
 * @var string $relationshipLabel
 * @var int    $rating
 * @var string $stars
 * @var string $comment
 * @var string $submittedAt
 */
?>
Neues Feedback erhalten!

Name:        <?= $name . "\n" ?>
Beziehung:   <?= $relationshipLabel . "\n" ?>
Bewertung:   <?= $stars . " ({$rating}/5)" . "\n" ?>

Kommentar:
<?= $comment . "\n" ?>

---
Eingegangen: <?= $submittedAt . "\n" ?>
