<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/certifications-data.php';

secure_session_start();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');

$user = authenticated_user();

if (!$user) {
    redirect_to('index.php?page=login');
}

$certificateId = (int) ($_GET['id'] ?? 0);

if ($certificateId < 1) {
    http_response_code(404);
    exit('Certificate not found.');
}

try {
    $certificate = cert_certificate_record_for_view(
        $certificateId,
        (int) $user['id'],
        (string) $user['role']
    );
} catch (Throwable $ex) {
    error_log(
        'Certificate view failed: '
        . $ex->getMessage()
    );

    http_response_code(404);
    exit('Certificate not found or access denied.');
}

/*
 * FINAL / ISSUED VIEW IS LOCKED.
 *
 * The only source of truth is:
 * training_certificates.template_key
 *
 * Any ?design= URL parameter is intentionally ignored.
 */
$templateNumber = (int) substr(
    (string) $certificate['template_key'],
    -1
);

if ($templateNumber < 1 || $templateNumber > 4) {
    $templateNumber = 1;
}

$certificateNumber =
    (string) $certificate['certificate_number'];

$employeeName =
    (string) $certificate['full_name'];

$trainingTitle =
    (string) $certificate['training_title'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta name="theme-color" content="#edf2ee">

    <title>
        <?= e($certificateNumber) ?>
        | Grow/Flow Certificate
    </title>

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=DM+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="assets/css/certificate-render-final.css?v=20261027-3"
    >

    <script
        defer
        src="assets/js/certificate-fit.js?v=20261022-8"
    ></script>
</head>

<body class="certificate-render-page">

<header class="certificate-render-toolbar">

    <div class="certificate-render-summary">
        <strong>
            <?= e($certificateNumber) ?>
        </strong>

        <span>
            <?= e($employeeName) ?>
            ·
            <?= e($trainingTitle) ?>
            · Design <?= $templateNumber ?>
        </span>
    </div>


    <div class="certificate-render-actions">

        <?php if (($user['role'] ?? '') === 'hr'): ?>
            <a
                class="certificate-toolbar-link"
                href="certificate-preview.php?id=<?= $certificateId ?>"
            >
                Preview Designs
            </a>
        <?php endif; ?>

        <button
            type="button"
            onclick="window.print()"
        >
            Print / Save PDF
        </button>

        <button
            type="button"
            onclick="window.close()"
            class="secondary"
        >
            Close
        </button>

    </div>

</header>


<main class="certificate-render-stage">

    <?php
    require __DIR__
        . '/../app/certificates/certificate-render.php';
    ?>

</main>

</body>
</html>
