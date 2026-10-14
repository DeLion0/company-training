<?php

if (!isset($employeePortalData) || !is_array($employeePortalData)) {
    throw new RuntimeException('Employee portal data was not loaded.');
}

$employee = $employeePortalData['employee'];
$accountStatus = (string)($employee['account_status'] ?? 'inactive');
$isActive = $accountStatus === 'active';
?>

<main class="employee-content employee-module-page">
    <header class="employee-page-heading">
        <div>
            <span class="employee-page-kicker">ACCOUNT / SECURITY</span>
            <h1>Account &amp; Security</h1>
            <p>Manage account access and password security. Activation functions will be connected after this UI is approved.</p>
        </div>

        <div class="employee-heading-stat <?= $isActive ? '' : 'is-amber' ?>">
            <span>Account Status</span>
            <strong class="employee-heading-stat-status"><?= $isActive ? 'Active' : 'Pending' ?></strong>
            <small><?= $isActive ? 'Account can sign in' : 'Activation required' ?></small>
        </div>
    </header>

    <section class="employee-security-grid">
        <article class="employee-panel employee-security-status-card">
            <div class="employee-security-shield <?= $isActive ? 'is-active' : 'is-pending' ?>"><i data-lucide="shield-check"></i></div>
            <span class="employee-section-kicker">ACCOUNT ACCESS</span>
            <h2><?= $isActive ? 'Your account is active' : 'Account activation is pending' ?></h2>
            <p><?= $isActive ? 'Your Grow/Flow account is currently allowed to sign in.' : 'HR already created your employee account. The next phase will let you use your temporary credentials to create a private password and activate it.' ?></p>

            <dl class="employee-security-details">
                <div><dt>Work Email</dt><dd><?= e((string)($employee['account_email'] ?: $employee['work_email'] ?: 'Not available')) ?></dd></div>
                <div><dt>Last Login</dt><dd><?= $employee['last_login_at'] ? e(date('M j, Y g:i A', strtotime((string)$employee['last_login_at']))) : 'Never' ?></dd></div>
            </dl>
        </article>

        <article class="employee-panel">
            <div class="employee-panel-head">
                <div>
                    <span class="employee-section-kicker">FIRST ACCESS</span>
                    <h2>Activation Flow</h2>
                    <p>The final employee flow will use the same login page as HR and Department Head.</p>
                </div>
            </div>

            <div class="employee-security-steps">
                <div class="employee-security-step <?= !$isActive ? 'is-current' : 'is-done' ?>"><b>1</b><span><strong>Temporary credentials</strong><small>Use the work email and temporary password issued after HR registration.</small></span></div>
                <div class="employee-security-step <?= $isActive ? 'is-done' : '' ?>"><b>2</b><span><strong>Create a new password</strong><small>The employee must replace the temporary password before normal access.</small></span></div>
                <div class="employee-security-step <?= $isActive ? 'is-done' : '' ?>"><b>3</b><span><strong>Employee Workspace unlocked</strong><small>After activation, future logins go directly to the Employee Workspace.</small></span></div>
            </div>

            <button type="button" class="employee-button is-primary full employee-security-action" data-ui-only="security" <?= $isActive ? '' : '' ?>><?= $isActive ? 'Change Password' : 'Activate Account' ?><i data-lucide="arrow-right"></i></button>
        </article>
    </section>

    <section class="employee-panel">
        <div class="employee-panel-head">
            <div>
                <span class="employee-section-kicker">SECURITY BASICS</span>
                <h2>Protect Your Account</h2>
                <p>Simple rules for employee access.</p>
            </div>
        </div>

        <div class="employee-security-tips">
            <div><i data-lucide="key-round"></i><span><strong>Use a private password</strong><small>Do not keep using the temporary password after activation.</small></span></div>
            <div><i data-lucide="user-round-check"></i><span><strong>Use only your own account</strong><small>Do not share work credentials with another employee.</small></span></div>
            <div><i data-lucide="log-out"></i><span><strong>Sign out on shared devices</strong><small>End your session when you finish using Grow/Flow.</small></span></div>
        </div>
    </section>
</main>
