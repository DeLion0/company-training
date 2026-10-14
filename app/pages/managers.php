<?php

require_once __DIR__
    . '/../core/managers-data.php';

$hrUser = current_user();

$companyId =
    manager_company_id(
        (int) $hrUser['id']
    );

$managerDepartments =
    manager_departments(
        $companyId
    );

$departmentHeads =
    manager_list(
        $companyId
    );

$managerError =
    $_SESSION['manager_error']
    ?? '';

unset(
    $_SESSION['manager_error']
);

$managerCreated =
    $_SESSION['manager_created']
    ?? null;

unset(
    $_SESSION['manager_created']
);

$activeHeads =
    count(
        array_filter(
            $departmentHeads,
            static fn (array $head): bool =>
                ($head['status'] ?? '')
                === 'active'
        )
    );

$coveredDepartments =
    count(
        array_filter(
            $managerDepartments,
            static fn (array $department): bool =>
                (int) (
                    $department[
                        'active_head_count'
                    ]
                    ?? 0
                ) > 0
        )
    );

$openDepartments =
    count(
        array_filter(
            $managerDepartments,
            static fn (array $department): bool =>
                ($department['status'] ?? '')
                    === 'active'
                && (int) (
                    $department[
                        'active_head_count'
                    ]
                    ?? 0
                ) === 0
        )
    );

$assignedEmployees =
    array_sum(
        array_map(
            static fn (array $head): int =>
                (int) (
                    $head[
                        'assigned_employee_count'
                    ]
                    ?? 0
                ),
            $departmentHeads
        )
    );
?>

<link
    rel="stylesheet"
    href="assets/css/managers.css?v=20261014-1"
>

<main class="content manager-page">

    <div class="manager-heading">

        <div>
            <div class="manager-eyebrow">
                PEOPLE MANAGEMENT / HR WORKSPACE
            </div>

            <h1>
                Department Heads
            </h1>

            <p>
                Create Department Head accounts and assign
                one active head to each department.
            </p>
        </div>

        <button
            type="button"
            class="manager-button is-primary"
            id="openManagerForm"
            <?= $openDepartments < 1
                ? 'disabled'
                : ''
            ?>
        >
            <i data-lucide="user-round-plus"></i>
            Add Department Head
        </button>

    </div>


    <?php if ($managerError): ?>

        <div
            class="manager-alert is-error"
            role="alert"
        >
            <i data-lucide="circle-alert"></i>

            <span>
                <?= e(
                    (string) $managerError
                ) ?>
            </span>
        </div>

    <?php endif; ?>


    <?php if (
        is_array($managerCreated)
    ): ?>

        <section class="manager-credentials">

            <div class="manager-credentials-head">
                <div>
                    <span class="manager-success-icon">
                        <i data-lucide="badge-check"></i>
                    </span>

                    <div>
                        <span class="manager-eyebrow">
                            ACCOUNT CREATED
                        </span>

                        <h2>
                            Department Head account is active
                        </h2>

                        <p>
                            Give these credentials directly to the
                            Department Head. They are shown only once.
                        </p>
                    </div>
                </div>
            </div>

            <div class="manager-credential-grid">

                <div>
                    <small>Department Head</small>
                    <strong>
                        <?= e(
                            (string) $managerCreated['name']
                        ) ?>
                    </strong>
                </div>

                <div>
                    <small>Department</small>
                    <strong>
                        <?= e(
                            (string) $managerCreated['department']
                        ) ?>
                    </strong>
                </div>

                <div>
                    <small>Work Email</small>
                    <strong>
                        <?= e(
                            (string) $managerCreated['email']
                        ) ?>
                    </strong>
                </div>

                <div>
                    <small>Temporary Password</small>
                    <strong>
                        <?= e(
                            (string) $managerCreated[
                                'temporary_password'
                            ]
                        ) ?>
                    </strong>
                </div>

            </div>

            <div class="manager-security-note">
                <i data-lucide="shield-check"></i>

                <span>
                    The account can log in immediately.
                    Do not send the email and password together
                    through an unsecured channel.
                </span>
            </div>

        </section>

    <?php endif; ?>


    <section
        class="manager-kpis"
        aria-label="Department Head statistics"
    >

        <article class="manager-kpi">
            <span class="manager-kpi-icon">
                <i data-lucide="users-round"></i>
            </span>

            <small>Active Heads</small>
            <strong>
                <?= $activeHeads ?>
            </strong>
        </article>

        <article class="manager-kpi">
            <span class="manager-kpi-icon">
                <i data-lucide="building-2"></i>
            </span>

            <small>Departments Covered</small>
            <strong>
                <?= $coveredDepartments ?>
            </strong>
        </article>

        <article class="manager-kpi">
            <span class="manager-kpi-icon is-amber">
                <i data-lucide="user-round-x"></i>
            </span>

            <small>Without Head</small>
            <strong>
                <?= $openDepartments ?>
            </strong>
        </article>

        <article class="manager-kpi">
            <span class="manager-kpi-icon">
                <i data-lucide="network"></i>
            </span>

            <small>Employees Assigned</small>
            <strong>
                <?= $assignedEmployees ?>
            </strong>
        </article>

    </section>


    <section class="manager-panel">

        <div class="manager-panel-head">

            <div>
                <h2>
                    Department Head Directory
                </h2>

                <p>
                    Active and inactive Department Head accounts
                    in this company.
                </p>
            </div>

            <div class="manager-filters">

                <label class="manager-search">
                    <i data-lucide="search"></i>

                    <input
                        type="search"
                        id="managerSearch"
                        placeholder="Search name, email, department..."
                        autocomplete="off"
                    >
                </label>

                <select
                    id="managerDepartmentFilter"
                    aria-label="Filter Department Heads by department"
                >
                    <option value="">
                        All Departments
                    </option>

                    <?php foreach (
                        $managerDepartments
                        as $department
                    ): ?>
                        <option
                            value="<?= (int) $department['id'] ?>"
                        >
                            <?= e(
                                (string) $department['name']
                            ) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

            </div>

        </div>


        <div class="manager-table-wrap">

            <table class="manager-table">

                <thead>
                    <tr>
                        <th>DEPARTMENT HEAD</th>
                        <th>DEPARTMENT</th>
                        <th>EMPLOYEES</th>
                        <th>ACCOUNT</th>
                        <th>LAST LOGIN</th>
                    </tr>
                </thead>

                <tbody id="managerRows">

                    <?php foreach (
                        $departmentHeads
                        as $head
                    ): ?>

                        <?php
                        $searchValue =
                            strtolower(
                                (string) $head['full_name']
                                . ' '
                                . (string) $head['email']
                                . ' '
                                . (string) (
                                    $head['department_name']
                                    ?? ''
                                )
                                . ' '
                                . (string) (
                                    $head['department_code']
                                    ?? ''
                                )
                            );
                        ?>

                        <tr
                            data-search="<?= e($searchValue) ?>"
                            data-department="<?= (int) (
                                $head['department_id']
                                ?? 0
                            ) ?>"
                        >

                            <td>
                                <div class="manager-person">

                                    <span class="manager-avatar">
                                        <?= e(
                                            strtoupper(
                                                substr(
                                                    (string) $head['full_name'],
                                                    0,
                                                    1
                                                )
                                            )
                                        ) ?>
                                    </span>

                                    <span>
                                        <strong>
                                            <?= e(
                                                (string) $head['full_name']
                                            ) ?>
                                        </strong>

                                        <small>
                                            <?= e(
                                                (string) $head['email']
                                            ) ?>
                                        </small>
                                    </span>

                                </div>
                            </td>

                            <td>
                                <strong class="manager-department-name">
                                    <?= e(
                                        (string) (
                                            $head['department_name']
                                            ?? 'Not assigned'
                                        )
                                    ) ?>
                                </strong>

                                <small class="manager-department-code">
                                    <?= e(
                                        (string) (
                                            $head['department_code']
                                            ?? ''
                                        )
                                    ) ?>
                                </small>
                            </td>

                            <td>
                                <span class="manager-count-pill">
                                    <?= (int) $head[
                                        'assigned_employee_count'
                                    ] ?>
                                </span>
                            </td>

                            <td>
                                <span class="manager-status <?= e(
                                    (string) $head['status']
                                ) ?>">
                                    <?= e(
                                        ucfirst(
                                            (string) $head['status']
                                        )
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <?php if (
                                    !empty(
                                        $head['last_login_at']
                                    )
                                ): ?>
                                    <?= e(
                                        date(
                                            'M j, Y g:i A',
                                            strtotime(
                                                (string) $head[
                                                    'last_login_at'
                                                ]
                                            )
                                        )
                                    ) ?>
                                <?php else: ?>
                                    <span class="manager-muted">
                                        Never
                                    </span>
                                <?php endif; ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

        <div
            class="manager-empty"
            id="managerEmpty"
            <?= $departmentHeads
                ? 'hidden'
                : ''
            ?>
        >
            <i data-lucide="user-round-search"></i>

            <strong>
                No Department Heads found
            </strong>

            <span>
                Create the first account to assign a Department Head.
            </span>
        </div>

        <div class="manager-table-footer">
            <span id="managerVisibleCount">
                <?= count($departmentHeads) ?>
            </span>
            Department Head<?= count($departmentHeads) === 1 ? '' : 's' ?> shown
        </div>

    </section>


    <div
        class="manager-modal-backdrop"
        id="managerModal"
        hidden
    >

        <section
            class="manager-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="managerModalTitle"
        >

            <div class="manager-modal-head">

                <div>
                    <span class="manager-eyebrow">
                        HR / ACCOUNT CREATION
                    </span>

                    <h2 id="managerModalTitle">
                        Add Department Head
                    </h2>

                    <p>
                        Create an active manager account and
                        assign it to one department.
                    </p>
                </div>

                <button
                    type="button"
                    class="manager-close"
                    id="closeManagerForm"
                    aria-label="Close Department Head form"
                >
                    <i data-lucide="x"></i>
                </button>

            </div>


            <form
                method="post"
                action="?page=managers"
                id="managerCreateForm"
            >

                <input
                    type="hidden"
                    name="csrf"
                    value="<?= e(csrf_token()) ?>"
                >

                <input
                    type="hidden"
                    name="manager_action"
                    value="create"
                >


                <div class="manager-form-grid">

                    <label>
                        First Name
                        <b>*</b>

                        <input
                            type="text"
                            name="first_name"
                            required
                            maxlength="80"
                            placeholder="e.g. Maria"
                            autocomplete="off"
                        >
                    </label>

                    <label>
                        Last Name
                        <b>*</b>

                        <input
                            type="text"
                            name="last_name"
                            required
                            maxlength="80"
                            placeholder="e.g. Santos"
                            autocomplete="off"
                        >
                    </label>

                    <label class="manager-form-full">
                        Department
                        <b>*</b>

                        <select
                            name="department_id"
                            required
                        >
                            <option value="">
                                Select department
                            </option>

                            <?php foreach (
                                $managerDepartments
                                as $department
                            ): ?>

                                <?php
                                $occupied =
                                    (int) (
                                        $department[
                                            'active_head_count'
                                        ]
                                        ?? 0
                                    ) > 0;

                                $inactive =
                                    (
                                        $department[
                                            'status'
                                        ]
                                        ?? ''
                                    ) !== 'active';
                                ?>

                                <option
                                    value="<?= (int) $department['id'] ?>"
                                    <?= (
                                        $occupied
                                        || $inactive
                                    )
                                        ? 'disabled'
                                        : ''
                                    ?>
                                >
                                    <?= e(
                                        (string) $department['name']
                                    ) ?>

                                    <?php if ($inactive): ?>
                                        — Inactive
                                    <?php elseif ($occupied): ?>
                                        — Head assigned
                                    <?php else: ?>
                                        — Available
                                    <?php endif; ?>
                                </option>

                            <?php endforeach; ?>

                        </select>
                    </label>

                </div>


                <div class="manager-account-preview">

                    <i data-lucide="mail-check"></i>

                    <div>
                        <strong>
                            Account details are generated automatically
                        </strong>

                        <span>
                            Work email uses
                            <?= e(employee_domain()) ?>.
                            Local demo password follows the same rule
                            as employee accounts; production uses a random password.
                        </span>
                    </div>

                </div>


                <div class="manager-form-actions">

                    <button
                        type="button"
                        class="manager-button is-secondary"
                        id="cancelManagerForm"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="manager-button is-primary"
                    >
                        <i data-lucide="user-plus"></i>
                        Create Department Head
                    </button>

                </div>

            </form>

        </section>

    </div>

</main>

<script
    defer
    src="assets/js/managers.js?v=20261014-1"
></script>
