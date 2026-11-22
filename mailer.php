<?php

declare(strict_types=1);

/**
 * Loads SMTP settings from config.smtp.php, or null if that file doesn't exist
 * (e.g. local dev without mail configured). See config.smtp.php.example.
 */
function smtp_config(): ?array
{
    static $config = null;
    static $loaded = false;
    if (!$loaded) {
        $loaded = true;
        $path = __DIR__ . '/config.smtp.php';
        if (is_file($path)) {
            $config = require $path;
        }
    }
    return $config;
}

/**
 * Sends a UTF-8 email over SMTP using raw sockets (no external
 * dependencies). Sends plain-text only, or a multipart/alternative
 * (plain-text + HTML) message when $htmlBody is given. Supports implicit
 * TLS ('ssl', typically port 465) and STARTTLS ('tls', typically port 587).
 * Returns false (and logs) on any failure instead of throwing, so a broken
 * mail server never breaks the feature that triggered the notification.
 */
function smtp_send(array $smtp, string $to, string $subject, string $body, ?string $htmlBody = null): bool
{
    $host = $smtp['host'];
    $port = (int) $smtp['port'];
    $encryption = $smtp['encryption'] ?? 'tls';
    $from = $smtp['from'];
    $fromName = $smtp['from_name'] ?? '';

    $transport = $encryption === 'ssl' ? 'ssl://' : 'tcp://';
    $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $socket = @stream_socket_client(
        $transport . $host . ':' . $port,
        $errno,
        $errstr,
        10,
        STREAM_CLIENT_CONNECT,
        $context
    );
    if ($socket === false) {
        error_log("smtp_send: connect to $host:$port failed: $errstr ($errno)");
        return false;
    }
    stream_set_timeout($socket, 10);

    try {
        smtp_read($socket, 220);
        smtp_command($socket, 'EHLO ' . smtp_client_hostname(), 250);

        if ($encryption === 'tls') {
            smtp_command($socket, 'STARTTLS', 220);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('STARTTLS negotiation failed');
            }
            smtp_command($socket, 'EHLO ' . smtp_client_hostname(), 250);
        }

        if (($smtp['username'] ?? '') !== '') {
            smtp_command($socket, 'AUTH LOGIN', 334);
            smtp_command($socket, base64_encode($smtp['username']), 334);
            smtp_command($socket, base64_encode($smtp['password']), 235);
        }

        smtp_command($socket, 'MAIL FROM:<' . $from . '>', 250);
        smtp_command($socket, 'RCPT TO:<' . $to . '>', 250);
        smtp_command($socket, 'DATA', 354);

        $fromHeader = $fromName !== '' ? mb_encode_mimeheader($fromName, 'UTF-8') . " <$from>" : $from;
        $headers = [
            'From: ' . $fromHeader,
            'To: <' . $to . '>',
            'Subject: ' . mb_encode_mimeheader($subject, 'UTF-8'),
            'Date: ' . date('r'),
            'MIME-Version: 1.0',
        ];

        if ($htmlBody === null) {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: base64';
            $message = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($body));
        } else {
            $boundary = 'bnd_' . bin2hex(random_bytes(16));
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
            $message = implode("\r\n", $headers) . "\r\n\r\n"
                . "--{$boundary}\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: base64\r\n\r\n"
                . chunk_split(base64_encode($body))
                . "--{$boundary}\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: base64\r\n\r\n"
                . chunk_split(base64_encode($htmlBody))
                . "--{$boundary}--";
        }
        $message = smtp_dot_stuff($message);

        smtp_command($socket, $message . "\r\n.", 250);
        smtp_command($socket, 'QUIT', 221);
    } catch (Throwable $e) {
        error_log('smtp_send: ' . $e->getMessage());
        fclose($socket);
        return false;
    }

    fclose($socket);
    return true;
}

/**
 * @param resource $socket
 */
function smtp_read($socket, int $expectedCode): string
{
    $response = '';
    do {
        $line = fgets($socket, 515);
        if ($line === false) {
            throw new RuntimeException('connection closed unexpectedly while waiting for ' . $expectedCode);
        }
        $response .= $line;
    } while (isset($line[3]) && $line[3] === '-');

    $code = (int) substr($response, 0, 3);
    if ($code !== $expectedCode) {
        throw new RuntimeException("expected $expectedCode, got: " . trim($response));
    }
    return $response;
}

/**
 * @param resource $socket
 */
function smtp_command($socket, string $command, int $expectedCode): string
{
    fwrite($socket, $command . "\r\n");
    return smtp_read($socket, $expectedCode);
}

function smtp_dot_stuff(string $message): string
{
    return preg_replace('/\r\n\./', "\r\n..", $message) ?? $message;
}

function smtp_client_hostname(): string
{
    return $_SERVER['SERVER_NAME'] ?? 'localhost';
}

/**
 * Renders a plain-text mail template (a .php file using <?= ?> like any
 * other template in this project) with the given variables and returns the
 * resulting body as a string.
 */
function render_mail_template(string $templatePath, array $vars): string
{
    extract($vars, EXTR_SKIP);
    ob_start();
    require $templatePath;
    return trim((string) ob_get_clean());
}

/**
 * Builds and sends the "new feedback" notification. Silently does nothing if
 * config.smtp.php isn't present (SMTP not configured). Never throws.
 */
function notify_feedback_submitted(array $feedback): void
{
    $config = smtp_config();
    if ($config === null) {
        return;
    }

    $relationshipLabel = RELATIONSHIPS[$feedback['relationship']] ?? RELATIONSHIPS['sonstiges'];
    $stars = str_repeat('★', $feedback['rating']) . str_repeat('☆', 5 - $feedback['rating']);

    $vars = [
        'name' => $feedback['name'],
        'relationshipLabel' => $relationshipLabel,
        'rating' => $feedback['rating'],
        'stars' => $stars,
        'comment' => $feedback['comment'],
        'submittedAt' => date('d.m.Y H:i'),
    ];

    $body = render_mail_template(__DIR__ . '/mail/feedback-notification.php', $vars);
    $htmlBody = render_mail_template(__DIR__ . '/mail/feedback-notification.html.php', $vars);

    $subject = 'Neues Feedback von ' . $feedback['name'];
    smtp_send($config, $config['to'], $subject, $body, $htmlBody);
}
