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

$page = 'certifications';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }

    unset(
        $_SESSION['cert_message'],
        $_SESSION['cert_error']
    );

    $action = (string) ($_POST['cert_action'] ?? '');

    try {
        if ($action === 'preview_attendance') {
            $programId = (int) ($_POST['program_id'] ?? 0);
            $templateKey = cert_template_key(
                (string) ($_POST['template_key'] ?? '')
            );

            $file = $_FILES['attendance_file'] ?? null;

            if (
                !$file
                || ($file['error'] ?? UPLOAD_ERR_NO_FILE)
                    !== UPLOAD_ERR_OK
                || ($file['size'] ?? 0) < 1
                || ($file['size'] ?? 0) > 2097152
                || strtolower(
                    pathinfo(
                        (string) ($file['name'] ?? ''),
                        PATHINFO_EXTENSION
                    )
                ) !== 'xlsx'
                || !is_uploaded_file(
                    (string) ($file['tmp_name'] ?? '')
                )
            ) {
                throw new InvalidArgumentException(
                    'Choose a valid .xlsx attendance file (maximum 2 MB).'
                );
            }

            $parsedWorkbook = cert_parse_attendance_xlsx(
                (string) $file['tmp_name']
            );

            $validated = cert_validate_attendance_import(
                (int) $user['id'],
                $programId,
                $parsedWorkbook
            );

            $_SESSION['cert_preview'] = [
                'program_id' => $programId,
                'template_key' => $templateKey,
                'sessions' => $validated['sessions'],
                'rows' => $validated['rows'],
            ];

            $counts = cert_preview_counts(
                $validated['rows']
            );

            $_SESSION['cert_message'] =
                'Attendance workbook validated. '
                . $counts['valid']
                . ' valid row'
                . ($counts['valid'] === 1 ? '' : 's')
                . (
                    $counts['invalid'] > 0
                        ? ' and '
                            . $counts['invalid']
                            . ' invalid row'
                            . ($counts['invalid'] === 1 ? '' : 's')
                        : ''
                )
                . '. '
                . $counts['eligible']
                . ' currently certificate-eligible; '
                . $counts['blocked']
                . ' valid but not eligible because of attendance. '
                . 'Review the preview before saving.';

        } elseif ($action === 'cancel_preview') {
            unset($_SESSION['cert_preview']);

            $_SESSION['cert_message'] =
                'Attendance import cancelled. No records were saved.';

        } elseif ($action === 'save_attendance') {
            $preview = $_SESSION['cert_preview'] ?? null;

            if (
                !is_array($preview)
                || empty($preview['rows'])
            ) {
                throw new InvalidArgumentException(
                    'Preview an attendance workbook first.'
                );
            }

            $result = cert_import_commit(
                (int) $user['id'],
                (int) $preview['program_id'],
                (string) $preview['template_key'],
                (array) $preview['rows']
            );

            unset($_SESSION['cert_preview']);

            $_SESSION['cert_message'] =
                'Saved attendance for '
                . $result['employees']
                . ' employee'
                . ($result['employees'] === 1 ? '' : 's')
                . ' across '
                . $result['saved']
                . ' meeting record'
                . ($result['saved'] === 1 ? '' : 's')
                . '. '
                . $result['issued']
                . ' new certificate'
                . ($result['issued'] === 1 ? '' : 's')
                . ' issued automatically.';

        } elseif ($action === 'issue_eligible') {
            $programId = (int) ($_POST['program_id'] ?? 0);
            $templateKey = cert_template_key(
                (string) ($_POST['template_key'] ?? '')
            );

            $issued = cert_issue_eligible_manual(
                (int) $user['id'],
                $programId,
                $templateKey
            );

            $_SESSION['cert_message'] =
                $issued > 0
                    ? $issued
                        . ' eligible certificate'
                        . ($issued === 1 ? '' : 's')
                        . ' issued.'
                    : 'No new employees currently meet the certificate attendance requirement.';

        } else {
            throw new InvalidArgumentException(
                'Unknown certification action.'
            );
        }
    } catch (InvalidArgumentException $ex) {
        $_SESSION['cert_error'] = $ex->getMessage();
    } catch (Throwable $ex) {
        error_log(
            'Certification action failed: '
            . $ex->getMessage()
        );

        $_SESSION['cert_error'] =
            'Could not complete the certification request. Check the PHP error log.';
    }

    redirect_to('certifications.php');
}

try {
    $certData = cert_dashboard_data(
        (int) $user['id']
    );
} catch (Throwable $ex) {
    error_log(
        'Certification dashboard failed: '
        . $ex->getMessage()
    );

    http_response_code(500);

    exit(
        'Unable to load Certifications. Make sure migration 011 has been applied.'
    );
}

$programs = $certData['programs'];
$certificates = $certData['certificates'];
$metrics = $certData['metrics'];

$preview = $_SESSION['cert_preview'] ?? null;

if (
    is_array($preview)
    && !isset($preview['sessions'])
) {
    unset($_SESSION['cert_preview']);
    $preview = null;
}

$flash = $_SESSION['cert_message'] ?? null;
$flashError = $_SESSION['cert_error'] ?? null;

unset(
    $_SESSION['cert_message'],
    $_SESSION['cert_error']
);

$previewCounts = is_array($preview)
    ? cert_preview_counts(
        (array) ($preview['rows'] ?? [])
    )
    : null;

require __DIR__ . '/../app/partials/layout-start.php';

echo '<div class="app-layout">';

require __DIR__ . '/../app/partials/sidebar.php';

echo '<div class="main-shell">';

require __DIR__ . '/../app/partials/header.php';
?>

<link
    rel="stylesheet"
    href="assets/css/certifications.css?v=20261022-2"
>

<main class="content cert-page" id="certificationsPage">

    <div class="cert-heading">
        <div>
            <div class="cert-kicker">
                LEARNING &amp; GROWTH / HR WORKSPACE
            </div>

            <h1>
                Certifications<span>.</span>
            </h1>

            <p>
                Import training attendance, validate completion,
                and issue employee certificates automatically.
            </p>
        </div>

        <a
            class="cert-btn cert-btn-outline is-disabled"
            href="#"
            id="certTemplateDownload"
            aria-disabled="true"
        >
            <i data-lucide="file-spreadsheet"></i>
            Select Training to Download Excel
        </a>
    </div>


    <?php if ($flash): ?>
        <div
            class="cert-alert success"
            role="status"
        >
            <i data-lucide="circle-check-big"></i>
            <?= e((string) $flash) ?>
        </div>
    <?php endif; ?>


    <?php if ($flashError): ?>
        <div
            class="cert-alert error"
            role="alert"
        >
            <i data-lucide="triangle-alert"></i>
            <?= e((string) $flashError) ?>
        </div>
    <?php endif; ?>


    <section
        class="cert-metrics"
        aria-label="Certification summary"
    >
        <article>
            <span>
                <i data-lucide="award"></i>
            </span>

            <div>
                <small>Issued Certificates</small>
                <strong><?= (int) $metrics['issued'] ?></strong>
            </div>
        </article>

        <article>
            <span>
                <i data-lucide="clipboard-check"></i>
            </span>

            <div>
                <small>Attendance Records</small>
                <strong><?= (int) $metrics['attendance'] ?></strong>
            </div>
        </article>

        <article>
            <span>
                <i data-lucide="graduation-cap"></i>
            </span>

            <div>
                <small>Published Programs</small>
                <strong><?= (int) $metrics['programs'] ?></strong>
            </div>
        </article>

        <article>
            <span>
                <i data-lucide="sheet"></i>
            </span>

            <div>
                <small>Programs With Attendance</small>
                <strong><?= (int) $metrics['programs_with_attendance'] ?></strong>
            </div>
        </article>
    </section>


    <section class="cert-workflow-grid">

        <article class="cert-panel cert-import-panel">

            <div class="cert-panel-head">
                <div>
                    <span class="cert-section-kicker">
                        ATTENDANCE → CERTIFICATE
                    </span>

                    <h2>
                        Import Training Attendance
                    </h2>

                    <p>
                        Employee ID is the authoritative match.
                        Employees must already be Joined in the selected training.
                    </p>
                </div>

                <span class="cert-step-pill">
                    STEP 1
                </span>
            </div>


            <form
                method="post"
                enctype="multipart/form-data"
                action="certifications.php"
                class="cert-import-form"
                id="certAttendanceForm"
            >
                <input
                    type="hidden"
                    name="csrf"
                    value="<?= e(csrf_token()) ?>"
                >

                <input
                    type="hidden"
                    name="cert_action"
                    value="preview_attendance"
                >


                <div class="cert-field">
                    <label for="certProgram">
                        Training Program
                        <b>*</b>
                    </label>

                    <select
                        id="certProgram"
                        name="program_id"
                        required
                    >
                        <option value="">
                            Select published training
                        </option>

                        <?php foreach ($programs as $program): ?>
                            <option
                                value="<?= (int) $program['id'] ?>"
                            >
                                <?= e((string) $program['title']) ?>
                                ·
                                <?= (int) $program['joined_count'] ?>
                                joined
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>


                <div
                    class="cert-program-template-note"
                    id="certProgramTemplateNote"
                >
                    <i data-lucide="sheet"></i>

                    <div>
                        <strong>
                            One Excel file for the whole training
                        </strong>

                        <p>
                            After selecting a Training Program, download its
                            Excel template. Meeting 1, Meeting 2, Meeting 3,
                            and any additional meetings will already appear
                            as attendance columns.
                        </p>
                    </div>
                </div>


                <div class="cert-field">
                    <label>
                        Certificate Design
                        <b>*</b>
                    </label>

                    <div class="cert-template-grid">

                        <?php
                        $templateNames = [
                            'template_1' => 'Formal Green & Gold',
                            'template_2' => 'Modern Executive',
                            'template_3' => 'Botanical Premium',
                            'template_4' => 'Heritage Institutional',
                        ];
                        ?>

                        <?php foreach ($templateNames as $key => $name): ?>
                            <?php
                            $number = (int) substr($key, -1);
                            ?>
                            <label class="cert-template-option">
                                <input
                                    type="radio"
                                    name="template_key"
                                    value="<?= e($key) ?>"
                                    <?= $key === 'template_1'
                                        ? 'checked'
                                        : ''
                                    ?>
                                >

                                <span class="cert-template-card">
                                    <img
                                        src="assets/certificates/template-<?= $number ?>.png"
                                        alt="<?= e($name) ?>"
                                    >

                                    <span>
                                        <b><?= e($name) ?></b>
                                        <small>
                                            Design <?= $number ?>
                                        </small>
                                    </span>
                                </span>
                            </label>
                        <?php endforeach; ?>

                    </div>
                </div>


                <div class="cert-upload">
                    <div>
                        <span>
                            <i data-lucide="file-up"></i>
                        </span>

                        <div>
                            <strong>
                                Upload Attendance Excel
                            </strong>

                            <small>
                                .xlsx only · max 2 MB · up to 500 employees
                            </small>
                        </div>
                    </div>

                    <input
                        type="file"
                        name="attendance_file"
                        accept=".xlsx"
                        required
                    >
                </div>


                <div class="cert-rule-note">
                    <i data-lucide="shield-check"></i>

                    <div>
                        <strong>
                            Certificate eligibility rule
                        </strong>

                        <p>
                            The employee receives a certificate only when
                            every required training session is recorded as
                            <b>Present</b>. Any Absent session blocks automatic
                            issuance.
                        </p>
                    </div>
                </div>


                <button
                    type="submit"
                    class="cert-btn cert-btn-primary"
                >
                    <i data-lucide="scan-search"></i>
                    Validate &amp; Preview Attendance
                </button>
            </form>

        </article>


        <article class="cert-panel cert-guide-panel">

            <div class="cert-panel-head">
                <div>
                    <span class="cert-section-kicker">
                        REQUIRED EXCEL FORMAT
                    </span>

                    <h2>
                        Attendance Workbook
                    </h2>

                    <p>
                        One workbook contains every meeting for the selected training program.
                    </p>
                </div>
            </div>


            <div class="cert-example-table program-level">
                <div>
                    <b>Employee ID</b>
                    <b>Employee Name</b>
                    <b>Meeting 1</b>
                    <b>Meeting 2</b>
                    <b>Meeting 3</b>
                </div>

                <div>
                    <span>EMP-4</span>
                    <span>Dwayne Johnson</span>
                    <span class="present">Present</span>
                    <span class="present">Present</span>
                    <span class="present">Present</span>
                </div>

                <div>
                    <span>EMP-5</span>
                    <span>Kevin Heart</span>
                    <span class="present">Present</span>
                    <span class="absent">Absent</span>
                    <span class="present">Present</span>
                </div>
            </div>


            <div class="cert-guide-list">

                <div>
                    <span>1</span>

                    <p>
                        Select the Training Program and download its program-specific Excel template.
                    </p>
                </div>

                <div>
                    <span>2</span>

                    <p>
                        Fill Present or Absent for every meeting, then upload the workbook once.
                    </p>
                </div>

                <div>
                    <span>3</span>

                    <p>
                        Grow/Flow validates every employee and every meeting before saving.
                    </p>
                </div>

                <div>
                    <span>4</span>

                    <p>
                        Eligibility is checked per employee. One employee with an Absent meeting does not block another employee who completed all meetings.
                    </p>
                </div>

            </div>

        </article>

    </section>


    <?php if (
        is_array($preview)
        && !empty($preview['rows'])
    ): ?>

        <section
            class="cert-panel cert-preview-panel"
            id="certAttendancePreview"
        >

            <div class="cert-panel-head">
                <div>
                    <span class="cert-section-kicker">
                        VALIDATION PREVIEW
                    </span>

                    <h2>
                        Review Before Saving
                    </h2>

                    <p>
                        Nothing below has been written to the database yet.
                    </p>
                </div>

                <div class="cert-preview-counts">
                    <span class="valid">
                        <?= (int) $previewCounts['valid'] ?>
                        Valid
                    </span>

                    <span class="eligible">
                        <?= (int) $previewCounts['eligible'] ?>
                        Eligible
                    </span>

                    <span class="blocked">
                        <?= (int) $previewCounts['blocked'] ?>
                        Not Eligible
                    </span>

                    <span class="invalid">
                        <?= (int) $previewCounts['invalid'] ?>
                        Invalid
                    </span>
                </div>
            </div>


            <div class="cert-preview-table-wrap">
                <table class="cert-table cert-attendance-matrix">
                    <thead>
                        <tr>
                            <th>Excel Row</th>
                            <th>Employee</th>
                            <th>Department</th>

                            <?php foreach (
                                (array) $preview['sessions']
                                as $session
                            ): ?>
                                <th>
                                    Meeting <?= (int) $session['session_number'] ?>
                                    <small>
                                        <?= e((string) $session['session_date']) ?>
                                    </small>
                                </th>
                            <?php endforeach; ?>

                            <th>Certificate Result</th>
                            <th>Validation</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach (
                            (array) $preview['rows']
                            as $row
                        ): ?>
                            <tr class="<?= !empty($row['valid'])
                                ? 'is-valid'
                                : 'is-invalid'
                            ?>">
                                <td>
                                    <?= (int) $row['row'] ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= e(
                                            (string) $row['employee_name']
                                        ) ?>
                                    </strong>

                                    <small>
                                        <?= e(
                                            (string) $row['employee_code']
                                        ) ?>
                                    </small>
                                </td>

                                <td>
                                    <?= e(
                                        (string) $row['department_name']
                                    ) ?>
                                </td>

                                <?php foreach (
                                    (array) $row['attendance']
                                    as $meeting
                                ): ?>
                                    <td>
                                        <span class="cert-attendance-pill <?= e(
                                            (string) $meeting['status']
                                        ) ?>">
                                            <?= e(
                                                ucfirst(
                                                    (string) $meeting['status']
                                                )
                                            ) ?>
                                        </span>
                                    </td>
                                <?php endforeach; ?>

                                <td>
                                    <?php if (empty($row['valid'])): ?>
                                        <span class="cert-invalid-state">
                                            Not evaluated
                                        </span>
                                    <?php elseif (!empty($row['eligible'])): ?>
                                        <span class="cert-eligible-state">
                                            <i data-lucide="award"></i>
                                            Eligible
                                        </span>
                                    <?php else: ?>
                                        <span class="cert-blocked-state">
                                            <i data-lucide="ban"></i>
                                            No certificate
                                            ·
                                            <?= (int) $row['absent_count'] ?>
                                            absent
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if (!empty($row['valid'])): ?>
                                        <span class="cert-valid-state">
                                            <i data-lucide="circle-check"></i>
                                            Ready
                                        </span>
                                    <?php else: ?>
                                        <span class="cert-invalid-state">
                                            <i data-lucide="circle-x"></i>
                                            <?= e(
                                                (string) $row['error']
                                            ) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>


            <div class="cert-preview-actions">

                <form
                    method="post"
                    action="certifications.php"
                >
                    <input
                        type="hidden"
                        name="csrf"
                        value="<?= e(csrf_token()) ?>"
                    >

                    <input
                        type="hidden"
                        name="cert_action"
                        value="cancel_preview"
                    >

                    <button
                        type="submit"
                        class="cert-btn cert-btn-outline"
                    >
                        Cancel Import
                    </button>
                </form>


                <form
                    method="post"
                    action="certifications.php"
                >
                    <input
                        type="hidden"
                        name="csrf"
                        value="<?= e(csrf_token()) ?>"
                    >

                    <input
                        type="hidden"
                        name="cert_action"
                        value="save_attendance"
                    >

                    <button
                        type="submit"
                        class="cert-btn cert-btn-primary"
                        <?= (int) $previewCounts['valid'] < 1
                            ? 'disabled'
                            : ''
                        ?>
                    >
                        <i data-lucide="database-zap"></i>
                        Save Valid Attendance
                    </button>
                </form>

            </div>

        </section>

    <?php endif; ?>


    <section class="cert-panel cert-programs-panel">

        <div class="cert-panel-head">
            <div>
                <span class="cert-section-kicker">
                    TRAINING COMPLETION
                </span>

                <h2>
                    Programs &amp; Certificate Status
                </h2>

                <p>
                    HR can re-run eligibility after correcting attendance.
                </p>
            </div>
        </div>


        <div class="cert-program-grid">

            <?php foreach ($programs as $program): ?>
                <article class="cert-program-card">
                    <div class="cert-program-top">
                        <span>
                            <?= count($program['sessions']) ?>
                            meeting<?= count($program['sessions']) === 1
                                ? ''
                                : 's'
                            ?>
                        </span>

                        <b>
                            #<?= (int) $program['id'] ?>
                        </b>
                    </div>

                    <h3>
                        <?= e((string) $program['title']) ?>
                    </h3>

                    <div class="cert-program-stats">
                        <div>
                            <span>Joined</span>
                            <strong>
                                <?= (int) $program['joined_count'] ?>
                            </strong>
                        </div>

                        <div>
                            <span>Attendance Rows</span>
                            <strong>
                                <?= (int) $program['attendance_rows'] ?>
                            </strong>
                        </div>

                        <div>
                            <span>Certificates</span>
                            <strong>
                                <?= (int) $program['certificate_count'] ?>
                            </strong>
                        </div>
                    </div>

                    <form
                        method="post"
                        action="certifications.php"
                        class="cert-issue-form"
                    >
                        <input
                            type="hidden"
                            name="csrf"
                            value="<?= e(csrf_token()) ?>"
                        >

                        <input
                            type="hidden"
                            name="cert_action"
                            value="issue_eligible"
                        >

                        <input
                            type="hidden"
                            name="program_id"
                            value="<?= (int) $program['id'] ?>"
                        >

                        <select
                            name="template_key"
                            aria-label="Certificate design"
                        >
                            <option value="template_1">
                                Design 1 · Formal Green &amp; Gold
                            </option>

                            <option value="template_2">
                                Design 2 · Modern Executive
                            </option>

                            <option value="template_3">
                                Design 3 · Botanical Premium
                            </option>

                            <option value="template_4">
                                Design 4 · Heritage Institutional
                            </option>
                        </select>

                        <button
                            type="submit"
                            class="cert-btn cert-btn-outline"
                        >
                            <i data-lucide="award"></i>
                            Issue Eligible
                        </button>
                    </form>
                </article>
            <?php endforeach; ?>


            <?php if (!$programs): ?>
                <div class="cert-empty">
                    No published training programs are available yet.
                </div>
            <?php endif; ?>

        </div>

    </section>


    <section class="cert-panel cert-library-panel">

        <div class="cert-panel-head">
            <div>
                <span class="cert-section-kicker">
                    CERTIFICATE LIBRARY
                </span>

                <h2>
                    Issued Certificates
                </h2>

                <p>
                    Certificates shown here are also available to the
                    corresponding employee account.
                </p>
            </div>

            <label class="cert-search">
                <i data-lucide="search"></i>

                <input
                    type="search"
                    id="certLibrarySearch"
                    placeholder="Search employee, training, certificate..."
                >
            </label>
        </div>


        <div class="cert-preview-table-wrap">
            <table
                class="cert-table"
                id="certLibraryTable"
            >
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Training Program</th>
                        <th>Certificate</th>
                        <th>Design</th>
                        <th>Issued</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($certificates as $certificate): ?>
                        <?php
                        $designNumber = (int) substr(
                            (string) $certificate['template_key'],
                            -1
                        );
                        ?>

                        <tr
                            data-search="<?= e(
                                strtolower(
                                    (string) $certificate['full_name']
                                    . ' '
                                    . (string) $certificate['employee_code']
                                    . ' '
                                    . (string) $certificate['training_title']
                                    . ' '
                                    . (string) $certificate['certificate_number']
                                )
                            ) ?>"
                        >
                            <td>
                                <strong>
                                    <?= e(
                                        (string) $certificate['full_name']
                                    ) ?>
                                </strong>

                                <small>
                                    <?= e(
                                        (string) $certificate['employee_code']
                                        . ' · '
                                        . (string) $certificate['department_name']
                                    ) ?>
                                </small>
                            </td>

                            <td>
                                <?= e(
                                    (string) $certificate['training_title']
                                ) ?>
                            </td>

                            <td>
                                <strong>
                                    <?= e(
                                        (string) $certificate['certificate_number']
                                    ) ?>
                                </strong>
                            </td>

                            <td>
                                Design <?= $designNumber ?>
                            </td>

                            <td>
                                <?= e(
                                    date(
                                        'M j, Y',
                                        strtotime(
                                            (string) $certificate['issued_at']
                                        )
                                    )
                                ) ?>
                            </td>

                            <td>
                                <a
                                    class="cert-table-action"
                                    href="certificate-view.php?id=<?= (int) $certificate['id'] ?>"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    View
                                    <i data-lucide="arrow-up-right"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>


                    <?php if (!$certificates): ?>
                        <tr>
                            <td
                                colspan="6"
                                class="cert-empty-cell"
                            >
                                No certificates have been issued yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </section>

</main>

<script
    defer
    src="assets/js/certifications.js?v=20261022-2"
></script>

<?php
echo '</div>';
echo '</div>';

require __DIR__ . '/../app/partials/layout-end.php';
