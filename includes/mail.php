<?php
/**
 * Small SMTP mail helper for the password-reset flow.
 * Uses the project's config without adding an uninstalled dependency.
 */
require_once __DIR__ . '/config.php';

function send_smtp_mail(string $to, string $subject, string $body): void {
    $cfg = config();
    $host = trim((string) ($cfg['MAIL_HOST'] ?? ''));
    $port = (int) ($cfg['MAIL_PORT'] ?? 587);
    $username = (string) ($cfg['MAIL_USERNAME'] ?? '');
    $password = (string) ($cfg['MAIL_PASSWORD'] ?? '');
    $encryption = strtolower((string) ($cfg['MAIL_ENCRYPTION'] ?? 'tls'));
    $from = trim((string) ($cfg['MAIL_FROM_ADDRESS'] ?? $username));
    $fromName = trim((string) ($cfg['MAIL_FROM_NAME'] ?? 'Uttarakhand Ventures CRM'));

    if ($host === '' || $from === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('SMTP is not configured.');
    }
    if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
        throw new RuntimeException('Unsupported SMTP encryption.');
    }

    $transport = $encryption === 'ssl' ? 'ssl://' . $host : $host;
    $socket = @stream_socket_client($transport . ':' . $port, $errorNumber, $errorMessage, 15, STREAM_CLIENT_CONNECT);
    if (!$socket) throw new RuntimeException('SMTP connection failed: ' . $errorMessage);
    stream_set_timeout($socket, 15);

    try {
        smtp_expect($socket, '220');
        smtp_command($socket, 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), '250');
        if ($encryption === 'tls') {
            smtp_command($socket, 'STARTTLS', '220');
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('SMTP TLS negotiation failed.');
            }
            smtp_command($socket, 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), '250');
        }
        if ($username !== '') {
            smtp_command($socket, 'AUTH LOGIN', '334');
            smtp_command($socket, base64_encode($username), '334');
            smtp_command($socket, base64_encode($password), '235');
        }
        smtp_command($socket, 'MAIL FROM:<' . $from . '>', '250');
        smtp_command($socket, 'RCPT TO:<' . $to . '>', '250');
        smtp_command($socket, 'DATA', '354');

        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . ($fromName !== '' ? '=?UTF-8?B?' . base64_encode($fromName) . '?= ' : '') . '<' . $from . '>',
            'To: <' . $to . '>',
            'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];
        $message = implode("\r\n", $headers) . "\r\n\r\n" . preg_replace('/(^|\r?\n)\./', '$1..', $body) . "\r\n.";
        fwrite($socket, $message . "\r\n");
        smtp_expect($socket, '250');
        smtp_command($socket, 'QUIT', '221');
    } finally {
        fclose($socket);
    }
}

function smtp_command($socket, string $command, string $expected): void {
    fwrite($socket, $command . "\r\n");
    smtp_expect($socket, $expected);
}

function smtp_expect($socket, string $expected): void {
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === ' ') break;
    }
    if (substr($response, 0, 3) !== $expected) {
        throw new RuntimeException('SMTP server rejected the request.');
    }
}
