<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/employee-preview-data.php';
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

if (($user['role'] ?? '') !== 'employee') {
    http_response_code(403);
    exit('Access denied. Employee account required.');
}

try {
    $employeePortalData = employee_portal_data_for_user(
        (int) $user['id']
    );

    $certificateData = cert_employee_certificates(
        (int) $user['id']
    );
} catch (Throwable $ex) {
    error_log(
        'Employee certificates failed: '
        . $ex->getMessage()
    );

    http_response_code(500);

    exit(
        'Unable to load My Certificates. Make sure migration 011 has been applied.'
    );
}

$employeeModule = 'certificates';
$employeePortalPreview = false;
$certificates = $certificateData['certificates'];
$employee = $certificateData['employee'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta name="theme-color" content="#f6faf7">

    <title>
        My Certificates | Grow/Flow
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
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="assets/css/employee-portal.css?v=20261015-1"
    >

    <link
        rel="stylesheet"
        href="assets/css/employee-functions-v1.css?v=20261022-1"
    >

    <link
        rel="stylesheet"
        href="assets/css/employee-certificates.css?v=20261027-1"
    >

    <script
        defer
        src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"
    ></script>

    <script
        defer
        src="assets/js/employee-portal.js?v=20261022-functions1"
    ></script>
</head>

<body class="employee-portal-page">

<div
    class="employee-mobile-overlay"
    id="employeeOverlay"
    hidden
></div>

<div class="employee-app-layout">

    <?php
    require __DIR__
        . '/../app/partials/employee-portal-sidebar.php';
    ?>

    <div class="employee-main-shell">

        <?php
        require __DIR__
            . '/../app/partials/employee-portal-header.php';
        ?>

        <main class="employee-content employee-certificates-page">

            <section class="employee-certificates-heading">
                <div>
                    <span class="employee-kicker">
                        LEARNING ACHIEVEMENTS
                    </span>

                    <h1>
                        My Certificates<span>.</span>
                    </h1>

                    <p>
                        Certificates are issued automatically after
                        HR confirms complete training attendance.
                    </p>
                </div>

                <div class="employee-certificates-total">
                    <span>
                        <i data-lucide="award"></i>
                    </span>

                    <div>
                        <small>Certificates Earned</small>
                        <strong>
                            <?= count($certificates) ?>
                        </strong>
                    </div>
                </div>
            </section>


            <?php if ($certificates): ?>

                <section class="employee-certificates-grid">

                    <?php foreach ($certificates as $certificate): ?>
                        <?php
                        $designNumber = (int) substr(
                            (string) $certificate['template_key'],
                            -1
                        );
                        ?>

                        <article class="employee-certificate-card">

                            <div
                                class="employee-certificate-preview employee-certificate-preview-design-<?= $designNumber ?>"
                            >
                                <img
                                    src="assets/certificates/template-<?= $designNumber ?>-master.png"
                                    alt=""
                                >

                                <div class="employee-certificate-preview-name">
                                    <?= e((string) $employee['full_name']) ?>
                                </div>

                                <div class="employee-certificate-preview-training">
                                    <?= e((string) $certificate['training_title']) ?>
                                </div>

                                <span class="employee-certificate-design-tag">
                                    Design <?= $designNumber ?>
                                </span>
                            </div>

                            <div class="employee-certificate-copy">

                                <span class="employee-certificate-status">
                                    <i data-lucide="badge-check"></i>
                                    Issued
                                </span>

                                <h2>
                                    <?= e(
                                        (string) $certificate['training_title']
                                    ) ?>
                                </h2>

                                <div class="employee-certificate-meta">
                                    <span>
                                        <i data-lucide="calendar"></i>
                                        <?= e(
                                            date(
                                                'M j, Y',
                                                strtotime(
                                                    (string) $certificate['issued_at']
                                                )
                                            )
                                        ) ?>
                                    </span>

                                    <span>
                                        <i data-lucide="hash"></i>
                                        <?= e(
                                            (string) $certificate['certificate_number']
                                        ) ?>
                                    </span>
                                </div>

                                <a
                                    href="certificate-view.php?id=<?= (int) $certificate['id'] ?>"
                                    target="_blank"
                                    rel="noopener"
                                    class="employee-certificate-action"
                                >
                                    View Certificate
                                    <i data-lucide="arrow-up-right"></i>
                                </a>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </section>

            <?php else: ?>

                <section class="employee-certificates-empty">

                    <span>
                        <i data-lucide="award"></i>
                    </span>

                    <div>
                        <h2>
                            No certificates yet
                        </h2>

                        <p>
                            Once you complete all required meetings of a
                            training program and HR confirms your attendance,
                            your certificate will appear here automatically.
                        </p>

                        <a href="employee.php?module=my-training">
                            Open My Training
                            <i data-lucide="arrow-right"></i>
                        </a>
                    </div>

                </section>

            <?php endif; ?>

        </main>

    </div>

</div>

</body>
</html>
