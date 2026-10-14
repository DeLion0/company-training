<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/core/mailer.php';

$to = trim((string) ($argv[1] ?? ''));

if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    fwrite(
        STDERR,
        "Usage:\n"
        . "php scripts/test-gmail-api.php recipient@example.com\n"
    );
    exit(1);
}

$result = growflow_send_email(
    $to,
    'Grow/Flow Gmail API Test',
    '<div style="font-family:Arial,sans-serif">'
    . '<h2>Grow/Flow Gmail API is working.</h2>'
    . '<p>This email was sent through the Gmail API over HTTPS.</p>'
    . '</div>',
    "Grow/Flow Gmail API is working.\n"
    . "This email was sent through the Gmail API over HTTPS."
);

if ($result['sent']) {
    echo 'SENT' . PHP_EOL;
    echo 'Message ID: ' . ($result['id'] ?? 'unknown') . PHP_EOL;
    exit(0);
}

fwrite(
    STDERR,
    'FAILED: ' . ($result['error'] ?? 'Unknown error.') . PHP_EOL
);
exit(1);
