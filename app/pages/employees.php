<?php
// HR-only route is enforced by public/index.php before this file is included.
require_once __DIR__ . '/../core/employees-data.php';

$hrUser = current_user();
$companyId = hr_company_id((int) $hrUser['id']);
$departments = hr_departments($companyId);
$managers = hr_managers($companyId);
$employees = hr_employees($companyId);

$error = $_SESSION['employee_error'] ?? '';
unset($_SESSION['employee_error']);

$success = $_SESSION['employee_created'] ?? null;
unset($_SESSION['employee_created']);
?>

<link rel="stylesheet" href="assets/css/employees.css">

<main class="content hr-employees">

    <div class="emp-title-row">
        <div>
            <div class="emp-eyebrow">
                PEOPLE MANAGEMENT / HR WORKSPACE
            </div>

            <h1>Employees</h1>

            <p>
                Register employees, assign department managers,
                and create employee accounts.
            </p>
        </div>

        <button
            type="button"
            class="emp-primary"
            id="open-employee-form"
        >
            <i data-lucide="user-round-plus"></i>
            Add Employee
        </button>
    </div>


    <?php if ($error): ?>
        <div class="emp-alert error" role="alert">
            <?= e((string) $error) ?>
        </div>
    <?php endif; ?>


    <?php if ($success && is_array($success)): ?>

        <section
            class="emp-alert success"
            aria-label="New employee account"
        >
            <h2>
                <i data-lucide="check-circle-2"></i>
                Employee account created
            </h2>

            <p>
                The account is <strong>inactive</strong> until
                the employee creates a private password from the
                activation link.
            </p>

            <div class="emp-credentials">

                <div>
                    <small>Grow/Flow Login ID</small>
                    <strong>
                        <?= e(
                            (string) $success['email']
                        ) ?>
                    </strong>
                </div>

                <div>
                    <small>Activation sent to</small>
                    <strong>
                        <?= e(
                            (string) $success[
                                'contact_email'
                            ]
                        ) ?>
                    </strong>
                </div>

            </div>

            <?php if (!empty($success['email_sent'])): ?>

                <p class="emp-warning">
                    Activation email sent successfully.
                    The employee can open the email, create a password,
                    then sign in using the generated Grow/Flow Login ID.
                </p>

            <?php else: ?>

                <p class="emp-warning">
                    Account was created, but the activation email
                    was not delivered:
                    <?= e(
                        (string) (
                            $success['email_error']
                            ?? 'Email service unavailable.'
                        )
                    ) ?>
                </p>

                <div
                    style="
                        margin-top:12px;
                        padding:12px;
                        border:1px solid #dfe9e2;
                        border-radius:10px;
                        background:#ffffff;
                    "
                >
                    <small
                        style="
                            display:block;
                            margin-bottom:6px;
                            color:#839289;
                            font-weight:700;
                        "
                    >
                        LOCAL TEST ACTIVATION LINK
                    </small>

                    <a
                        href="<?= e(
                            (string) $success[
                                'activation_url'
                            ]
                        ) ?>"
                        style="
                            color:#27884f;
                            font-size:11px;
                            overflow-wrap:anywhere;
                        "
                    >
                        <?= e(
                            (string) $success[
                                'activation_url'
                            ]
                        ) ?>
                    </a>
                </div>

            <?php endif; ?>

        </section>

    <?php endif; ?>


    <section
        class="emp-kpis"
        aria-label="Employee statistics"
    >
        <div class="emp-kpi">
            <span class="emp-kpi-icon">
                <i data-lucide="users-round"></i>
            </span>
            <small>Total Employees</small>
            <strong><?= count($employees) ?></strong>
        </div>

        <div class="emp-kpi">
            <span class="emp-kpi-icon">
                <i data-lucide="building-2"></i>
            </span>
            <small>Departments</small>
            <strong><?= count($departments) ?></strong>
        </div>

        <div class="emp-kpi">
            <span class="emp-kpi-icon">
                <i data-lucide="user-check"></i>
            </span>
            <small>Active Employees</small>
            <strong>
                <?= count(
                    array_filter(
                        $employees,
                        fn ($x) =>
                            $x['employment_status']
                            === 'active'
                    )
                ) ?>
            </strong>
        </div>

        <div class="emp-kpi">
            <span class="emp-kpi-icon">
                <i data-lucide="key-round"></i>
            </span>
            <small>Pending Activation</small>
            <strong>
                <?= count(
                    array_filter(
                        $employees,
                        fn ($x) =>
                            $x['account_status']
                            === 'inactive'
                    )
                ) ?>
            </strong>
        </div>
    </section>


    <section class="emp-panel">

        <div class="emp-panel-head">

            <div>
                <h2>Employee Directory</h2>
                <p>
                    Company employees, departments,
                    managers, and account status.
                </p>
            </div>

            <div class="emp-filters">

                <label class="emp-search">
                    <i data-lucide="search"></i>

                    <input
                        id="employee-search"
                        placeholder="Search name, ID, email..."
                        aria-label="Search employees"
                    >
                </label>

                <select
                    id="employee-department-filter"
                    aria-label="Filter department"
                >
                    <option value="">
                        All Departments
                    </option>

                    <?php foreach (
                        $departments
                        as $d
                    ): ?>
                        <option value="<?= e($d['name']) ?>">
                            <?= e($d['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

            </div>

        </div>


        <div class="emp-table-wrap">

            <table class="emp-table">

                <thead>
                    <tr>
                        <th>EMPLOYEE</th>
                        <th>EMPLOYEE ID</th>
                        <th>DEPARTMENT</th>
                        <th>POSITION</th>
                        <th>MANAGER</th>
                        <th>ACCOUNT</th>
                    </tr>
                </thead>

                <tbody id="employee-table-body">

                <?php foreach (
                    $employees
                    as $emp
                ): ?>

                    <tr
                        data-search="<?= e(
                            strtolower(
                                $emp['full_name']
                                . ' '
                                . $emp['employee_code']
                                . ' '
                                . ($emp['email'] ?? '')
                                . ' '
                                . ($emp['contact_email'] ?? '')
                            )
                        ) ?>"
                        data-department="<?= e(
                            $emp['department_name']
                        ) ?>"
                    >
                        <td>
                            <span class="emp-person">

                                <span class="emp-avatar">
                                    <?= e(
                                        strtoupper(
                                            substr(
                                                $emp['full_name'],
                                                0,
                                                1
                                            )
                                        )
                                    ) ?>
                                </span>

                                <span>
                                    <strong>
                                        <?= e(
                                            $emp['full_name']
                                        ) ?>
                                    </strong>

                                    <small>
                                        <?= e(
                                            $emp['email']
                                            ?? 'No login account'
                                        ) ?>
                                    </small>

                                    <?php if (
                                        !empty(
                                            $emp['contact_email']
                                        )
                                    ): ?>
                                        <small>
                                            Contact:
                                            <?= e(
                                                $emp[
                                                    'contact_email'
                                                ]
                                            ) ?>
                                        </small>
                                    <?php endif; ?>
                                </span>

                            </span>
                        </td>

                        <td>
                            <?= e($emp['employee_code']) ?>
                        </td>

                        <td>
                            <?= e($emp['department_name']) ?>
                        </td>

                        <td>
                            <?= e($emp['job_title']) ?>
                        </td>

                        <td>
                            <?= e(
                                $emp['manager_name']
                                ?? 'Not assigned'
                            ) ?>
                        </td>

                        <td>
                            <span
                                class="emp-pill <?= (
                                    $emp['account_status']
                                    === 'active'
                                )
                                    ? 'enabled'
                                    : 'pending'
                                ?>"
                            >
                                <?= e(
                                    $emp['account_status']
                                    ?? 'Not Created'
                                ) ?>
                            </span>
                        </td>
                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

        <p
            class="emp-empty"
            id="emp-empty"
            <?= count($employees) > 0
                ? 'hidden'
                : ''
            ?>
        >
            No employees found.
            Click <strong>Add Employee</strong>
            to register the first employee.
        </p>

    </section>


    <div
        class="emp-modal-backdrop"
        id="employee-modal"
        hidden
    >

        <section
            class="emp-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="emp-modal-title"
        >

            <div class="emp-modal-title">

                <div>
                    <h2 id="emp-modal-title">
                        Register New Employee
                    </h2>

                    <p>
                        HR employee registration,
                        account creation, and activation delivery
                    </p>
                </div>

                <button
                    type="button"
                    class="emp-close"
                    id="close-employee-form"
                    aria-label="Close"
                >
                    <i data-lucide="x"></i>
                </button>

            </div>


            <form
                id="employee-create-form"
                method="post"
                action="?page=employees"
            >

                <input
                    type="hidden"
                    name="csrf"
                    value="<?= e(csrf_token()) ?>"
                >

                <input
                    type="hidden"
                    name="employee_action"
                    value="create"
                >


                <div class="emp-form-grid">

                    <label>
                        First Name <span>*</span>

                        <input
                            name="first_name"
                            id="emp-first"
                            required
                            maxlength="80"
                            autocomplete="off"
                            placeholder="e.g. Juan"
                        >
                    </label>

                    <label>
                        Last Name <span>*</span>

                        <input
                            name="last_name"
                            id="emp-last"
                            required
                            maxlength="80"
                            autocomplete="off"
                            placeholder="e.g. Dela Cruz"
                        >
                    </label>

                    <label>
                        Employee ID <span>*</span>

                        <input
                            name="employee_code"
                            required
                            maxlength="40"
                            placeholder="e.g. EMP-0001"
                        >
                    </label>

                    <label>
                        Job Position <span>*</span>

                        <input
                            name="job_title"
                            required
                            maxlength="120"
                            placeholder="e.g. Accounting Staff"
                        >
                    </label>

                    <label>
                        Department <span>*</span>

                        <select
                            name="department_id"
                            id="emp-department"
                            required
                        >
                            <option value="">
                                Select department
                            </option>

                            <?php foreach (
                                $departments
                                as $d
                            ): ?>
                                <option value="<?= $d['id'] ?>">
                                    <?= e($d['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        Department Manager <span>*</span>

                        <select
                            name="manager_user_id"
                            id="emp-manager"
                            required
                        >
                            <option value="">
                                Select manager
                            </option>

                            <?php foreach (
                                $managers
                                as $m
                            ): ?>
                                <option
                                    data-department="<?= $m['department_id'] ?>"
                                    value="<?= $m['id'] ?>"
                                >
                                    <?= e($m['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        Hire Date

                        <input
                            type="date"
                            name="joined_at"
                        >
                    </label>

                    <label>
                        Real Contact Email <span>*</span>

                        <small>
                            Activation link will be sent here
                        </small>

                        <input
                            type="email"
                            name="contact_email"
                            required
                            maxlength="254"
                            autocomplete="email"
                            placeholder="employee@gmail.com"
                        >
                    </label>

                    <label>
                        Grow/Flow Login ID

                        <small>
                            Generated automatically
                        </small>

                        <input
                            id="emp-email-preview"
                            readonly
                            placeholder="firstname.lastname@company.com"
                        >
                    </label>

                </div>


                <div class="emp-form-note">

                    <i data-lucide="shield-check"></i>

                    <span>
                        Grow/Flow creates an inactive employee account.
                        A one-time activation link is sent to the real
                        contact email. The employee creates their own
                        password before the account becomes active.
                    </span>

                </div>


                <div class="emp-form-actions">

                    <button
                        type="button"
                        class="emp-secondary"
                        id="cancel-employee-form"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="emp-primary"
                    >
                        <i data-lucide="user-plus"></i>
                        Create &amp; Send Activation
                    </button>

                </div>

            </form>

        </section>

    </div>

</main>

<script>
window.GROWFLOW_EMPLOYEE_DOMAIN =
    <?= json_encode(
        employee_domain(),
        JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
    ) ?>;
</script>

<script defer src="assets/js/employees.js"></script>
