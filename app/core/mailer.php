<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

/**
 * Railway-ready Gmail sender using Gmail API over HTTPS.
 * Compatible with the existing growflow_send_email() call.
 */

function growflow_mail_env(string $name): string
{
    return trim(db_env($name, ''));
}

function growflow_mail_http_status(array $headers): int
{
    foreach ($headers as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', (string) $header, $m)) {
            return (int) $m[1];
        }
    }
    return 0;
}

function growflow_mail_post(string $url, array $headers, string $body): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['status' => 0, 'body' => '', 'error' => 'Unable to initialize HTTPS transport.'];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 20,
        ]);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'status' => $status,
            'body' => is_string($response) ? $response : '',
            'error' => $response === false ? ($error !== '' ? $error : 'HTTPS request failed.') : '',
        ];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'timeout' => 20,
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];

    return [
        'status' => growflow_mail_http_status($responseHeaders),
        'body' => is_string($response) ? $response : '',
        'error' => $response === false ? 'HTTPS request failed.' : '',
    ];
}

function growflow_gmail_access_token(): array
{
    $clientId = growflow_mail_env('GMAIL_CLIENT_ID');
    $clientSecret = growflow_mail_env('GMAIL_CLIENT_SECRET');
    $refreshToken = growflow_mail_env('GMAIL_REFRESH_TOKEN');

    if ($clientId === '' || $clientSecret === '' || $refreshToken === '') {
        return [
            'ok' => false,
            'access_token' => null,
            'error' => 'Gmail API OAuth credentials are not configured.',
        ];
    }

    $payload = http_build_query([
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'refresh_token' => $refreshToken,
        'grant_type' => 'refresh_token',
    ], '', '&', PHP_QUERY_RFC3986);

    $response = growflow_mail_post(
        'https://oauth2.googleapis.com/token',
        ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
        $payload
    );

    if ($response['error'] !== '') {
        return [
            'ok' => false,
            'access_token' => null,
            'error' => 'Unable to connect to Google OAuth.',
        ];
    }

    $decoded = json_decode($response['body'], true);

    if (
        $response['status'] < 200
        || $response['status'] >= 300
        || !is_array($decoded)
        || empty($decoded['access_token'])
    ) {
        $providerMessage = is_array($decoded)
            ? ($decoded['error_description'] ?? $decoded['error'] ?? null)
            : null;

        return [
            'ok' => false,
            'access_token' => null,
            'error' => 'Google OAuth rejected the refresh token'
                . ($providerMessage ? ': ' . (string) $providerMessage : '.'),
        ];
    }

    return [
        'ok' => true,
        'access_token' => (string) $decoded['access_token'],
        'error' => null,
    ];
}

function growflow_mail_safe_header(string $value): string
{
    return trim(str_replace(["\r", "\n"], '', $value));
}

function growflow_mail_encode_subject(string $subject): string
{
    $subject = growflow_mail_safe_header($subject);
    if ($subject === '') {
        $subject = 'Grow/Flow notification';
    }
    return '=?UTF-8?B?' . base64_encode($subject) . '?=';
}

function growflow_mail_base64url(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function growflow_mail_mime(
    string $to,
    string $subject,
    string $html,
    string $text,
    string $fromEmail,
    string $fromName
): string {
    $eol = "\r\n";
    $boundary = 'growflow_alt_' . bin2hex(random_bytes(16));

    $safeTo = growflow_mail_safe_header($to);
    $safeFromEmail = growflow_mail_safe_header($fromEmail);
    $safeFromName = addcslashes(growflow_mail_safe_header($fromName), "\\\"");

    $plain = $text !== ''
        ? $text
        : trim((string) preg_replace(
            '/\s+/',
            ' ',
            html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')
        ));

    $headers = [
        'MIME-Version: 1.0',
        'From: "' . $safeFromName . '" <' . $safeFromEmail . '>',
        'To: <' . $safeTo . '>',
        'Subject: ' . growflow_mail_encode_subject($subject),
        'Date: ' . gmdate('D, d M Y H:i:s O'),
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ];

    $parts = [];
    $parts[] =
        '--' . $boundary . $eol
        . 'Content-Type: text/plain; charset=UTF-8' . $eol
        . 'Content-Transfer-Encoding: quoted-printable' . $eol . $eol
        . quoted_printable_encode($plain) . $eol;

    $parts[] =
        '--' . $boundary . $eol
        . 'Content-Type: text/html; charset=UTF-8' . $eol
        . 'Content-Transfer-Encoding: quoted-printable' . $eol . $eol
        . quoted_printable_encode($html) . $eol;

    $parts[] = '--' . $boundary . '--' . $eol;

    return implode($eol, $headers) . $eol . $eol . implode('', $parts);
}

function growflow_send_email(
    string $to,
    string $subject,
    string $html,
    string $text = ''
): array {
    $to = strtolower(trim($to));

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['sent' => false, 'id' => null, 'error' => 'Recipient email is invalid.'];
    }

    $fromEmail = strtolower(growflow_mail_env('MAIL_FROM_EMAIL'));
    $fromName = growflow_mail_env('MAIL_FROM_NAME');

    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        return [
            'sent' => false,
            'id' => null,
            'error' => 'MAIL_FROM_EMAIL is not configured correctly.',
        ];
    }

    if ($fromName === '') {
        $fromName = 'Grow/Flow';
    }

    $oauth = growflow_gmail_access_token();

    if (!$oauth['ok']) {
        return ['sent' => false, 'id' => null, 'error' => (string) $oauth['error']];
    }

    $mime = growflow_mail_mime(
        $to,
        $subject,
        $html,
        $text,
        $fromEmail,
        $fromName
    );

    $json = json_encode(
        ['raw' => growflow_mail_base64url($mime)],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );

    $response = growflow_mail_post(
        'https://gmail.googleapis.com/gmail/v1/users/me/messages/send',
        [
            'Authorization: Bearer ' . $oauth['access_token'],
            'Accept: application/json',
            'Content-Type: application/json',
        ],
        $json
    );

    if ($response['error'] !== '') {
        return [
            'sent' => false,
            'id' => null,
            'error' => 'Unable to connect to the Gmail API.',
        ];
    }

    $decoded = json_decode($response['body'], true);

    if (
        $response['status'] >= 200
        && $response['status'] < 300
        && is_array($decoded)
        && !empty($decoded['id'])
    ) {
        return [
            'sent' => true,
            'id' => (string) $decoded['id'],
            'error' => null,
        ];
    }

    $providerMessage = (
        is_array($decoded)
        && isset($decoded['error']['message'])
    )
        ? (string) $decoded['error']['message']
        : null;

    return [
        'sent' => false,
        'id' => null,
        'error' => 'Gmail API rejected the email'
            . ($providerMessage ? ': ' . $providerMessage : '.'),
    ];
}
