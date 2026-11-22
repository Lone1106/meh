<?php
/**
 * HTML body for the "new feedback" notification email.
 * Rendered by render_mail_template() in mailer.php.
 * Mirrors the site's black/white editorial card look, inlined for email clients.
 *
 * Table-based layout with bgcolor fallbacks and mso-* resets for Outlook's
 * Word rendering engine; gutter background/padding lives on the outer
 * table/td (not <body>, which Gmail strips); color-scheme meta tags stop
 * Apple Mail/Outlook.com dark mode from re-coloring the card. border-radius
 * and box-shadow are left as a progressive enhancement — Outlook desktop
 * ignores them and just shows square corners, which is fine here.
 *
 * @var string $name
 * @var string $relationshipLabel
 * @var int    $rating
 * @var string $comment
 * @var string $submittedAt
 */
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>Neues Feedback</title>
</head>
<body style="margin:0; padding:0; background:#f4f4f4; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f4f4f4"
         style="background:#f4f4f4; border-collapse:collapse; mso-table-lspace:0pt; mso-table-rspace:0pt;">
    <tr>
      <td align="center" style="padding:24px;">
        <table role="presentation" width="480" cellpadding="0" cellspacing="0" border="0" align="center" bgcolor="#f9f9f9"
               style="max-width:480px; width:100%; background:#f9f9f9; border:1px solid #ececec; border-radius:20px; box-shadow:0 1px 2px rgba(18,18,18,0.03); border-collapse:collapse; mso-table-lspace:0pt; mso-table-rspace:0pt;">
          <tr>
            <td style="padding:32px; font-family: Helvetica, Arial, sans-serif; color:#121212;">
              <div style="font-family: Georgia, 'Times New Roman', serif; font-size:13px; letter-spacing:0.08em; text-transform:uppercase; color:#8a8a8a; margin:0 0 8px;">
                MEH &mdash; NEUES FEEDBACK
              </div>

              <div style="font-size:20px; font-weight:600; line-height:1.3; margin:0 0 4px;">
                <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>
              </div>

              <div style="font-size:13px; color:#8a8a8a; margin:0 0 16px;">
                <?= htmlspecialchars($relationshipLabel, ENT_QUOTES, 'UTF-8') ?>
              </div>

              <div style="margin:0 0 20px;">
                <span style="font-size:20px; letter-spacing:0.12em;">
                  <?php for ($i = 1; $i <= 5; $i++): ?>
                    <span style="color: <?= $i <= $rating ? '#121212' : '#a8a8a8' ?>;">&#9733;</span>
                  <?php endfor; ?>
                </span>
                <span style="font-size:13px; color:#8a8a8a;">(<?= (int) $rating ?>/5)</span>
              </div>

              <div style="font-family: Georgia, 'Times New Roman', serif; font-style:italic; font-size:16px; line-height:1.5; color:#121212; border-left:3px solid #ececec; padding-left:16px; margin:0 0 24px;">
                &ldquo;<?= nl2br(htmlspecialchars($comment, ENT_QUOTES, 'UTF-8')) ?>&rdquo;
              </div>

              <div style="font-size:12px; color:#a8a8a8; border-top:1px solid #ececec; padding-top:16px; margin:0;">
                Eingegangen am <?= htmlspecialchars($submittedAt, ENT_QUOTES, 'UTF-8') ?>
              </div>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
