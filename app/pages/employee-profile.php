<?php

if (!isset($employeePortalData) || !is_array($employeePortalData)) {
    throw new RuntimeException('Employee portal data was not loaded.');
}

$employee = $employeePortalData['employee']; ?>

<main class="employee-content employee-module-page">
    <header class="employee-page-heading">
        <div>
            <span class="employee-page-kicker">ACCOUNT / EMPLOYEE PROFILE</span>
            <h1>My Profile</h1>
            <p>Your employee and organizational information. HR controls these official records.</p>
        </div>

        <div class="employee-heading-stat">
            <span>Employee ID</span>
            <strong class="employee-heading-stat-code"><?= e((string)$employee['employee_code']) ?></strong>
            <small><?= e((string)$employee['department_name']) ?></small>
        </div>
    </header>

    <section class="employee-profile-layout">
        <article class="employee-panel employee-profile-identity">
            <div class="employee-profile-large-avatar"><?= e(strtoupper(substr((string)$employee['full_name'], 0, 1))) ?></div>
            <span class="employee-status-pill is-joined">Active Employee</span>
            <h2><?= e((string)$employee['full_name']) ?></h2>
            <p><?= e((string)$employee['job_title']) ?></p>
            <div class="employee-profile-identity-line"><i data-lucide="building-2"></i><?= e((string)$employee['department_name']) ?></div>
            <div class="employee-profile-identity-line"><i data-lucide="mail"></i><?= e((string)($employee['work_email'] ?: $employee['account_email'] ?: 'No work email')) ?></div>
        </article>

        <article class="employee-panel employee-profile-information">
            <div class="employee-panel-head">
                <div>
                    <span class="employee-section-kicker">EMPLOYMENT INFORMATION</span>
                    <h2>Official Employee Details</h2>
                    <p>These values come from the HR employee record.</p>
                </div>
                <span class="employee-read-only-pill"><i data-lucide="lock-keyhole"></i> HR controlled</span>
            </div>

            <div class="employee-profile-info-grid">
                <div><span>Full Name</span><strong><?= e((string)$employee['full_name']) ?></strong></div>
                <div><span>Employee ID</span><strong><?= e((string)$employee['employee_code']) ?></strong></div>
                <div><span>Job Position</span><strong><?= e((string)$employee['job_title']) ?></strong></div>
                <div><span>Department</span><strong><?= e((string)$employee['department_name']) ?></strong></div>
                <div><span>Department Head</span><strong><?= e((string)($employee['manager_name'] ?: 'Not assigned')) ?></strong></div>
                <div><span>Hire Date</span><strong><?= $employee['joined_at'] ? e(date('F j, Y', strtotime((string)$employee['joined_at']))) : 'Not specified' ?></strong></div>
                <div class="employee-profile-info-full"><span>Work Email</span><strong><?= e((string)($employee['work_email'] ?: $employee['account_email'] ?: 'Not available')) ?></strong></div>
            </div>

            <div class="employee-profile-note">
                <i data-lucide="circle-help"></i>
                <span>If any official employment information is incorrect, contact HR. Employee editing is intentionally disabled for these fields.</span>
            </div>
        </article>
    </section>
</main>
