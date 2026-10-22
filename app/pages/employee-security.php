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
            <p>Review your account access and change your password securely.</p>
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
                    <span class="employee-section-kicker">PASSWORD SECURITY</span>
                    <h2>Change Password</h2>
                    <p>Use your current password to create a new private password for future sign-ins.</p>
                </div>
            </div>

            <?php $employeePortalPreview = isset($employeePortalPreview) && $employeePortalPreview === true; ?>

            <?php if ($employeePortalPreview): ?>
                <div class="employee-security-preview-note">
                    <i data-lucide="eye"></i>
                    Password changes are disabled in HR Employee Preview.
                </div>
            <?php else: ?>
                <form method="post" action="employee.php?module=security" class="employee-password-form" autocomplete="off">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="employee_action" value="change_password">
                    <input type="hidden" name="return_module" value="security">

                    <label>
                        Current Password
                        <input type="password" name="current_password" maxlength="128" autocomplete="current-password" required>
                    </label>

                    <div class="employee-password-grid">
                        <label>
                            New Password
                            <input type="password" name="new_password" minlength="12" maxlength="128" autocomplete="new-password" required>
                        </label>
                        <label>
                            Confirm New Password
                            <input type="password" name="confirm_password" minlength="12" maxlength="128" autocomplete="new-password" required>
                        </label>
                    </div>

                    <p>Minimum 12 characters with uppercase, lowercase, a number, and a symbol.</p>

                    <button type="submit" class="employee-button is-primary full employee-security-action">
                        <i data-lucide="key-round"></i>
                        Change Password
                    </button>
                </form>
            <?php endif; ?>
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
