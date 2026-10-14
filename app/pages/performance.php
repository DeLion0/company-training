<?php

$user = authenticated_user();

if (!$user || ($user['role'] ?? '') !== 'hr') {
    http_response_code(403);
    exit('Access denied. HR only.');
}

require_once __DIR__ . '/../core/performance-data.php';

$performanceData =
    performance_overview(
        (int) $user['id']
    );

$performanceEmployees =
    $performanceData['employees'];

$performanceStats =
    $performanceData['stats'];

$performanceHistory =
    $performanceData['history'];

$performanceThreshold =
    (float) $performanceData['threshold'];

$performanceMessage =
    $_SESSION['performance_message']
    ?? null;

$performanceError =
    $_SESSION['performance_error']
    ?? null;

$performancePreview =
    $_SESSION['performance_preview']
    ?? [];

$performancePreviewRequested =
    (string) ($_GET['preview'] ?? '') === '1';

unset(
    $_SESSION['performance_message'],
    $_SESSION['performance_error']
);

$currentMonth =
    (new DateTimeImmutable(
        'first day of this month'
    ))->format('Y-m');


$performanceDepartments = [];

foreach ($performanceEmployees as $employee) {
    $departmentId =
        (int) ($employee['department_id'] ?? 0);

    if ($departmentId < 1) {
        continue;
    }

    if (!isset(
        $performanceDepartments[$departmentId]
    )) {
        $performanceDepartments[$departmentId] = [
            'id' => $departmentId,
            'name' =>
                (string) (
                    $employee['department_name']
                    ?? 'Department'
                ),
            'employee_count' => 0,
        ];
    }

    $performanceDepartments[
        $departmentId
    ]['employee_count']++;
}

uasort(
    $performanceDepartments,
    static fn (array $a, array $b): int =>
        strcasecmp(
            (string) $a['name'],
            (string) $b['name']
        )
);

$validPreviewRows = 0;
$invalidPreviewRows = 0;

if (is_array($performancePreview)) {
    foreach (
        $performancePreview
        as $previewRow
    ) {
        if (!empty($previewRow['valid'])) {
            $validPreviewRows++;
        } else {
            $invalidPreviewRows++;
        }
    }
}
?>

<main class="content performance-page">

    <?php if ($performanceMessage): ?>
        <div
            class="performance-alert is-success"
            role="status"
        >
            <i data-lucide="circle-check"></i>
            <span>
                <?= e(
                    (string) $performanceMessage
                ) ?>
            </span>
        </div>
    <?php endif; ?>

    <?php if ($performanceError): ?>
        <div
            class="performance-alert is-error"
            role="alert"
        >
            <i data-lucide="circle-alert"></i>
            <span>
                <?= e(
                    (string) $performanceError
                ) ?>
            </span>
        </div>
    <?php endif; ?>


    <header class="performance-header">

        <div>
            <div class="performance-eyebrow">
                <span></span>
                PEOPLE PERFORMANCE
            </div>

            <h1>
                Employee Evaluation<span>.</span>
            </h1>

            <p>
                Record monthly employee ratings manually or import them from Excel.
                Scores below
                <?= e(
                    rtrim(
                        rtrim(
                            number_format(
                                $performanceThreshold,
                                2
                            ),
                            '0'
                        ),
                        '.'
                    )
                ) ?>%
                can automatically prioritize relevant training programs.
            </p>
        </div>

        <a
            class="performance-template-link"
            href="templates/employee-performance-template.xlsx"
            download
        >
            <i data-lucide="download"></i>
            Excel template
        </a>

    </header>


    <section
        class="performance-metrics"
        aria-label="Performance overview"
    >

        <article class="performance-metric">
            <span>Active employees</span>
            <strong>
                <?= (int) $performanceStats['total'] ?>
            </strong>
            <small>Employees available for evaluation</small>
        </article>

        <article class="performance-metric">
            <span>Evaluated</span>
            <strong>
                <?= (int) $performanceStats['evaluated'] ?>
            </strong>
            <small>Employees with at least one monthly rating</small>
        </article>

        <article class="performance-metric">
            <span>Below 50%</span>
            <strong>
                <?= (int) $performanceStats['below_threshold'] ?>
            </strong>
            <small>Latest monthly rating below priority threshold</small>
        </article>

        <article class="performance-metric">
            <span>Average latest score</span>
            <strong>
                <?= $performanceStats['average'] !== null
                    ? e(
                        number_format(
                            (float) $performanceStats['average'],
                            1
                        )
                    ) . '%'
                    : '—'
                ?>
            </strong>
            <small>Average of each employee's latest rating</small>
        </article>

    </section>


    <div class="performance-grid">

        <!-- MANUAL -->
        <section class="performance-card">

            <div class="performance-card-head">
                <div class="performance-icon">
                    <i data-lucide="user-round-check"></i>
                </div>

                <div>
                    <h2>Manual monthly rating</h2>
                    <p>
                        One official rating per employee per month.
                        Saving the same employee and month updates that record.
                    </p>
                </div>
            </div>

            <form
                method="post"
                action="?page=performance"
                class="performance-form"
                id="manualPerformanceForm"
            >
                <input
                    type="hidden"
                    name="performance_action"
                    value="manual"
                >

                <input
                    type="hidden"
                    name="csrf"
                    value="<?= e(csrf_token()) ?>"
                >

                <div class="performance-picker">

                    <div class="performance-picker-step">
                        <span class="performance-step-number">1</span>

                        <div>
                            <strong>Choose department</strong>
                            <small>
                                Select the employee's department first.
                            </small>
                        </div>
                    </div>

                    <div class="performance-field">
                        <label for="performanceDepartment">
                            Department
                            <b>*</b>
                        </label>

                        <select
                            id="performanceDepartment"
                            required
                        >
                            <option value="">
                                Select department
                            </option>

                            <?php foreach (
                                $performanceDepartments
                                as $department
                            ): ?>
                                <option
                                    value="<?= (int) $department['id'] ?>"
                                    data-employee-count="<?= (int) $department['employee_count'] ?>"
                                >
                                    <?= e(
                                        (string) $department['name']
                                    ) ?>
                                    (<?= (int) $department['employee_count'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="performance-picker-step">
                        <span class="performance-step-number">2</span>

                        <div>
                            <strong>Find employee</strong>
                            <small>
                                Search inside the selected department.
                            </small>
                        </div>
                    </div>

                    <div class="performance-field">
                        <label for="performanceEmployeeSearch">
                            Search employee
                            <span>Optional</span>
                        </label>

                        <div class="performance-search-input">
                            <i data-lucide="search"></i>

                            <input
                                type="search"
                                id="performanceEmployeeSearch"
                                placeholder="Search name, employee code, or job title..."
                                autocomplete="off"
                                disabled
                            >
                        </div>
                    </div>

                    <div class="performance-field">
                        <label for="performanceEmployee">
                            Employee
                            <b>*</b>
                        </label>

                        <select
                            id="performanceEmployee"
                            name="employee_id"
                            required
                            disabled
                        >
                            <option value="">
                                Select a department first
                            </option>

                            <?php foreach (
                                $performanceEmployees
                                as $employee
                            ): ?>
                                <option
                                    value="<?= (int) $employee['id'] ?>"
                                    data-department-id="<?= (int) $employee['department_id'] ?>"
                                    data-employee-code="<?= e(
                                        (string) $employee['employee_code']
                                    ) ?>"
                                    data-employee-name="<?= e(
                                        (string) $employee['full_name']
                                    ) ?>"
                                    data-job-title="<?= e(
                                        (string) ($employee['job_title'] ?? '')
                                    ) ?>"
                                    data-search="<?= e(
                                        strtolower(
                                            (string) $employee['employee_code']
                                            . ' '
                                            . (string) $employee['full_name']
                                            . ' '
                                            . (string) ($employee['job_title'] ?? '')
                                            . ' '
                                            . (string) $employee['department_name']
                                        )
                                    ) ?>"
                                >
                                    <?= e(
                                        (string) $employee['employee_code']
                                    ) ?>
                                    —
                                    <?= e(
                                        (string) $employee['full_name']
                                    ) ?>
                                    <?php if (
                                        trim(
                                            (string) (
                                                $employee['job_title']
                                                ?? ''
                                            )
                                        ) !== ''
                                    ): ?>
                                        ·
                                        <?= e(
                                            (string) $employee['job_title']
                                        ) ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <div
                            class="performance-employee-result"
                            id="performanceEmployeeResult"
                            aria-live="polite"
                        >
                            Choose a department to view employees.
                        </div>
                    </div>

                </div>

                <div class="performance-two-col">

                    <div class="performance-field">
                        <label for="performanceMonth">
                            Review month
                            <b>*</b>
                        </label>

                        <input
                            type="month"
                            id="performanceMonth"
                            name="review_month"
                            value="<?= e($currentMonth) ?>"
                            required
                        >
                    </div>

                    <div class="performance-field">
                        <label for="performanceScore">
                            Score
                            <b>*</b>
                        </label>

                        <div class="performance-score-input">
                            <input
                                type="number"
                                id="performanceScore"
                                name="score"
                                min="0"
                                max="100"
                                step="0.01"
                                placeholder="0 - 100"
                                required
                            >
                            <span>%</span>
                        </div>
                    </div>

                </div>

                <div class="performance-field">
                    <label for="performanceNotes">
                        Notes
                        <span>Optional</span>
                    </label>

                    <textarea
                        id="performanceNotes"
                        name="notes"
                        maxlength="1000"
                        rows="4"
                        placeholder="Short evaluation note or context for this monthly rating."
                    ></textarea>

                    <small>
                        A score below 50% is eligible for automatic
                        Priority to Take recommendations in relevant active training.
                    </small>
                </div>

                <div class="performance-actions">
                    <button
                        type="reset"
                        class="performance-button is-secondary"
                    >
                        Clear
                    </button>

                    <button
                        type="submit"
                        class="performance-button is-primary"
                    >
                        <i data-lucide="save"></i>
                        Save monthly rating
                    </button>
                </div>
            </form>

        </section>


        <!-- EXCEL -->
        <section class="performance-card">

            <div class="performance-card-head">
                <div class="performance-icon is-blue">
                    <i data-lucide="sheet"></i>
                </div>

                <div>
                    <h2>Excel bulk evaluation</h2>
                    <p>
                        Upload → Validate → Preview → Save.
                        Preview never changes the database.
                    </p>
                </div>
            </div>

            <div class="performance-import-guide">
                <strong>Required columns</strong>

                <div>
                    <span>Employee Code</span>
                    <span>Review Month</span>
                    <span>Score</span>
                    <span>Notes</span>
                </div>

                <p>
                    Review Month must use
                    <code>YYYY-MM</code>.
                    Notes are optional.
                </p>
            </div>

            <form
                method="post"
                action="?page=performance"
                enctype="multipart/form-data"
                class="performance-upload"
                id="performanceUploadForm"
            >
                <input
                    type="hidden"
                    name="performance_action"
                    value="preview"
                >

                <input
                    type="hidden"
                    name="csrf"
                    value="<?= e(csrf_token()) ?>"
                >

                <label
                    class="performance-dropzone"
                    for="performanceExcel"
                >
                    <i data-lucide="upload-cloud"></i>

                    <strong>
                        Choose an .xlsx workbook
                    </strong>

                    <span id="performanceFileName">
                        Maximum 2 MB · up to 1000 rows
                    </span>
                </label>

                <input
                    class="performance-file-input"
                    id="performanceExcel"
                    type="file"
                    name="excel_file"
                    accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                    required
                >

                <button
                    type="submit"
                    class="performance-button is-primary is-full"
                >
                    <i data-lucide="scan-search"></i>
                    Validate & preview
                </button>
            </form>

            <?php if (
                is_array($performancePreview)
                && $performancePreview
            ): ?>
                <div class="performance-preview-ready">
                    <div>
                        <i data-lucide="circle-check"></i>

                        <span>
                            <strong>
                                Validated preview ready
                            </strong>

                            <?= $validPreviewRows ?>
                            valid,
                            <?= $invalidPreviewRows ?>
                            invalid
                        </span>
                    </div>

                    <button
                        type="button"
                        class="performance-button is-secondary"
                        id="openPerformancePreview"
                    >
                        <i data-lucide="eye"></i>
                        View preview
                    </button>
                </div>
            <?php endif; ?>

        </section>

    </div>


    <?php if (
        is_array($performancePreview)
        && $performancePreview
    ): ?>

        <dialog
            class="performance-preview-dialog"
            id="performancePreviewDialog"
            aria-labelledby="performancePreviewTitle"
        >
            <div class="performance-preview-modal">

                <div class="performance-preview-modal-head">

                    <div class="performance-preview-modal-title">
                        <div class="performance-icon is-gold">
                            <i data-lucide="clipboard-check"></i>
                        </div>

                        <div>
                            <span class="performance-modal-eyebrow">
                                EXCEL IMPORT REVIEW
                            </span>

                            <h2 id="performancePreviewTitle">
                                Preview monthly ratings
                            </h2>

                            <p>
                                Nothing has been saved yet.
                                Review the validated rows before committing.
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="performance-modal-close"
                        id="closePerformancePreview"
                        aria-label="Close Excel preview"
                    >
                        <i data-lucide="x"></i>
                    </button>

                </div>


                <div class="performance-preview-summary">

                    <div class="performance-preview-summary-item is-valid">
                        <span>Valid rows</span>
                        <strong>
                            <?= $validPreviewRows ?>
                        </strong>
                    </div>

                    <div class="performance-preview-summary-item <?= $invalidPreviewRows > 0 ? 'is-invalid' : 'is-neutral' ?>">
                        <span>Invalid rows</span>
                        <strong>
                            <?= $invalidPreviewRows ?>
                        </strong>
                    </div>

                    <div class="performance-preview-summary-note">
                        <i data-lucide="info"></i>

                        <span>
                            Saving uses one official monthly rating per employee.
                            Invalid rows must be fixed before the import can be saved.
                        </span>
                    </div>

                </div>


                <div class="performance-preview-modal-body">

                    <div class="performance-table-wrap performance-preview-table-wrap">

                        <table class="performance-table performance-preview-table">

                            <thead>
                                <tr>
                                    <th>Row</th>
                                    <th>Employee</th>
                                    <th>Department</th>
                                    <th>Month</th>
                                    <th>Score</th>
                                    <th>Notes</th>
                                    <th>Status</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach (
                                    $performancePreview
                                    as $row
                                ): ?>

                                    <tr
                                        class="<?= !empty($row['valid'])
                                            ? 'is-valid'
                                            : 'is-invalid'
                                        ?>"
                                    >

                                        <td>
                                            <?= (int) $row['row_number'] ?>
                                        </td>

                                        <td>
                                            <strong>
                                                <?= e(
                                                    (string) (
                                                        $row['employee_name']
                                                        ?: $row['employee_code']
                                                    )
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

                                        <td>
                                            <?= e(
                                                (string) $row['review_month']
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= $row['score'] !== null
                                                ? e(
                                                    number_format(
                                                        (float) $row['score'],
                                                        2
                                                    )
                                                ) . '%'
                                                : '—'
                                            ?>
                                        </td>

                                        <td>
                                            <?= e(
                                                (string) (
                                                    $row['notes']
                                                    ?? ''
                                                )
                                            ) ?>
                                        </td>

                                        <td>
                                            <?php if (!empty($row['valid'])): ?>

                                                <span class="performance-status is-valid">
                                                    Ready
                                                </span>

                                            <?php else: ?>

                                                <span class="performance-status is-invalid">
                                                    Invalid
                                                </span>

                                                <ul class="performance-errors">
                                                    <?php foreach (
                                                        $row['errors']
                                                        as $rowError
                                                    ): ?>
                                                        <li>
                                                            <?= e(
                                                                (string) $rowError
                                                            ) ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>

                                            <?php endif; ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                </div>


                <div class="performance-preview-modal-actions">

                    <div class="performance-preview-modal-actions-copy">
                        <?php if ($invalidPreviewRows > 0): ?>
                            <strong>
                                Fix <?= $invalidPreviewRows ?>
                                invalid row<?= $invalidPreviewRows === 1 ? '' : 's' ?> first.
                            </strong>

                            <span>
                                Cancel this preview, correct the workbook, then upload it again.
                            </span>
                        <?php else: ?>
                            <strong>
                                <?= $validPreviewRows ?>
                                rating<?= $validPreviewRows === 1 ? '' : 's' ?>
                                ready to save.
                            </strong>

                            <span>
                                The database will only change after you click Save ratings.
                            </span>
                        <?php endif; ?>
                    </div>


                    <div class="performance-preview-modal-buttons">

                        <button
                            type="button"
                            class="performance-button is-secondary"
                            id="backFromPerformancePreview"
                        >
                            Back
                        </button>

                        <form
                            method="post"
                            action="?page=performance"
                        >
                            <input
                                type="hidden"
                                name="performance_action"
                                value="cancel_import"
                            >

                            <input
                                type="hidden"
                                name="csrf"
                                value="<?= e(csrf_token()) ?>"
                            >

                            <button
                                type="submit"
                                class="performance-button is-danger-quiet"
                            >
                                Cancel import
                            </button>
                        </form>

                        <form
                            method="post"
                            action="?page=performance"
                        >
                            <input
                                type="hidden"
                                name="performance_action"
                                value="import"
                            >

                            <input
                                type="hidden"
                                name="csrf"
                                value="<?= e(csrf_token()) ?>"
                            >

                            <button
                                type="submit"
                                class="performance-button is-primary"
                                <?= $invalidPreviewRows > 0
                                    ? 'disabled'
                                    : ''
                                ?>
                            >
                                <i data-lucide="database"></i>

                                Save
                                <?= $validPreviewRows ?>
                                rating<?= $validPreviewRows === 1 ? '' : 's' ?>
                            </button>
                        </form>

                    </div>

                </div>

            </div>
        </dialog>

    <?php endif; ?>



    <?php if (
        $performancePreviewRequested
        && (
            !is_array($performancePreview)
            || !$performancePreview
        )
    ): ?>

        <section
            class="performance-card performance-preview-card"
            id="performancePreviewMissing"
            tabindex="-1"
        >
            <div class="performance-preview-missing">
                <i data-lucide="circle-alert"></i>

                <div>
                    <strong>
                        Preview data was not available.
                    </strong>

                    <p>
                        The workbook was validated, but the preview session
                        was not preserved. Upload the workbook again.
                    </p>
                </div>
            </div>
        </section>

    <?php endif; ?>


    <section class="performance-card performance-history-card">

        <div class="performance-card-head">
            <div class="performance-icon">
                <i data-lucide="history"></i>
            </div>

            <div>
                <h2>Monthly performance history</h2>
                <p>
                    Most recent 300 official monthly ratings.
                    Dashboard charts use these records.
                </p>
            </div>

            <div class="performance-history-tools">
                <input
                    type="search"
                    id="performanceHistorySearch"
                    placeholder="Search employee..."
                    autocomplete="off"
                >
            </div>
        </div>

        <?php if (!$performanceHistory): ?>

            <div class="performance-empty">
                <i data-lucide="chart-no-axes-column"></i>
                <strong>No monthly ratings yet</strong>
                <p>
                    Add one manually or import an Excel workbook.
                </p>
            </div>

        <?php else: ?>

            <div class="performance-table-wrap">
                <table
                    class="performance-table"
                    id="performanceHistoryTable"
                >
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Month</th>
                            <th>Score</th>
                            <th>Source</th>
                            <th>Notes</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach (
                            $performanceHistory
                            as $row
                        ): ?>
                            <?php
                            $score =
                                (float) $row['score'];
                            ?>
                            <tr
                                data-performance-history
                                data-search="<?= e(
                                    strtolower(
                                        (string) $row['employee_code']
                                        . ' '
                                        . (string) $row['full_name']
                                        . ' '
                                        . (string) $row['department_name']
                                    )
                                ) ?>"
                            >
                                <td>
                                    <strong>
                                        <?= e(
                                            (string) $row['full_name']
                                        ) ?>
                                    </strong>

                                    <small>
                                        <?= e(
                                            (string) $row['employee_code']
                                        ) ?>
                                        ·
                                        <?= e(
                                            (string) $row['job_title']
                                        ) ?>
                                    </small>
                                </td>

                                <td>
                                    <?= e(
                                        (string) $row['department_name']
                                    ) ?>
                                </td>

                                <td>
                                    <?= e(
                                        (string) $row['review_month']
                                    ) ?>
                                </td>

                                <td>
                                    <span
                                        class="performance-score <?= $score < $performanceThreshold
                                            ? 'is-low'
                                            : 'is-normal'
                                        ?>"
                                    >
                                        <?= e(
                                            number_format(
                                                $score,
                                                1
                                            )
                                        ) ?>%
                                    </span>
                                </td>

                                <td>
                                    <span class="performance-source">
                                        <?= e(
                                            str_replace(
                                                '_',
                                                ' ',
                                                ucfirst(
                                                    (string) $row['source']
                                                )
                                            )
                                        ) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= e(
                                        (string) (
                                            $row['notes']
                                            ?? ''
                                        )
                                    ) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>

    </section>

</main>
