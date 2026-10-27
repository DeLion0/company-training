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

if (($user['role'] ?? '') !== 'hr') {
    http_response_code(403);
    exit('Access denied. HR only.');
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
        'hr'
    );
} catch (Throwable $ex) {
    error_log(
        'Certificate preview failed: '
        . $ex->getMessage()
    );

    http_response_code(404);
    exit('Certificate not found or access denied.');
}

$savedTemplateNumber = (int) substr(
    (string) $certificate['template_key'],
    -1
);

if ($savedTemplateNumber < 1 || $savedTemplateNumber > 4) {
    $savedTemplateNumber = 1;
}

$templateNumber = filter_input(
    INPUT_GET,
    'design',
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'min_range' => 1,
            'max_range' => 4,
        ],
    ]
);

if (!is_int($templateNumber)) {
    $templateNumber = $savedTemplateNumber;
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
        Certificate Design Preview
        | Grow/Flow
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

<body class="certificate-render-page certificate-preview-page">

<header class="certificate-render-toolbar certificate-preview-toolbar">

    <div class="certificate-render-summary">
        <strong>
            HR Certificate Preview
        </strong>

        <span>
            <?= e($employeeName) ?>
            ·
            <?= e($trainingTitle) ?>
        </span>
    </div>


    <nav
        class="certificate-design-switcher"
        aria-label="Preview certificate designs"
    >
        <span>Preview design</span>

        <?php for ($design = 1; $design <= 4; $design++): ?>
            <a
                href="?id=<?= $certificateId ?>&design=<?= $design ?>"
                class="<?= $templateNumber === $design ? 'active' : '' ?>"
                <?= $templateNumber === $design
                    ? 'aria-current="page"'
                    : ''
                ?>
            >
                <?= $design ?>
            </a>
        <?php endfor; ?>
    </nav>


    <div class="certificate-render-actions">

        <span class="certificate-preview-badge">
            Saved design <?= $savedTemplateNumber ?>
        </span>

        <a
            class="certificate-toolbar-link primary"
            href="certificate-view.php?id=<?= $certificateId ?>"
        >
            Open Final Certificate
        </a>

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
