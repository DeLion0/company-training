<?php

declare(strict_types=1);

if (!isset($employeePortalData) || !is_array($employeePortalData)) {
    throw new RuntimeException('Employee portal data was not loaded.');
}

if (!function_exists('employee_training_details_payload')) {
    throw new RuntimeException(
        'Employee training actions are not loaded. Check app/core/employee-actions.php.'
    );
}

$employeePortalPreview =
    isset($employeePortalPreview)
    && $employeePortalPreview === true;

$employeeTrainingDetails =
    employee_training_details_payload(
        $employeePortalData
    );
?>

<script
    type="application/json"
    id="employeeTrainingDetailsData"
><?= json_encode(
    $employeeTrainingDetails,
    JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
) ?></script>

<dialog
    class="employee-training-dialog"
    id="employeeTrainingDialog"
    aria-labelledby="employeeTrainingDialogTitle"
>
    <div class="employee-training-dialog-head">
        <div>
            <span class="employee-page-kicker">
                TRAINING PROGRAM
            </span>

            <h2 id="employeeTrainingDialogTitle">
                Training Details
            </h2>
        </div>

        <button
            type="button"
            class="employee-dialog-close"
            data-employee-dialog-close
            aria-label="Close training details"
        >
            <i data-lucide="x"></i>
        </button>
    </div>

    <div class="employee-training-dialog-body">
        <p
            class="employee-training-dialog-purpose"
            id="employeeTrainingDialogPurpose"
        ></p>

        <div class="employee-training-dialog-grid">
            <div>
                <span>Training Period</span>
                <strong id="employeeTrainingDialogPeriod"></strong>
            </div>

            <div>
                <span>Instructor</span>
                <strong id="employeeTrainingDialogInstructor"></strong>
            </div>

            <div>
                <span>Venue</span>
                <strong id="employeeTrainingDialogVenue"></strong>
            </div>

            <div>
                <span>Enrollment</span>
                <strong id="employeeTrainingDialogCapacity"></strong>
            </div>
        </div>

        <section class="employee-training-dialog-objectives">
            <span>Learning Objectives</span>

            <p id="employeeTrainingDialogObjectives"></p>
        </section>
    </div>

    <div class="employee-training-dialog-footer">
        <button
            type="button"
            class="employee-button is-secondary"
            data-employee-dialog-close
        >
            Close
        </button>
    </div>
</dialog>

<?php if (!$employeePortalPreview): ?>
<form
    method="post"
    action="employee.php"
    id="employeeJoinTrainingForm"
    hidden
>
    <input
        type="hidden"
        name="csrf"
        value="<?= e(csrf_token()) ?>"
    >

    <input
        type="hidden"
        name="employee_action"
        value="join_training"
    >

    <input
        type="hidden"
        name="program_id"
        id="employeeJoinTrainingProgramId"
        value=""
    >

    <input
        type="hidden"
        name="return_module"
        value="<?= e((string) ($employeeModule ?? 'available')) ?>"
    >
</form>
<?php endif; ?>
