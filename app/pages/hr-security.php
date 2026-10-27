<?php

$hrAccount = hr_account_data(
    (int)$user['id']
);

$message =
    $_SESSION['hr_security_message']
    ?? null;

$error =
    $_SESSION['hr_security_error']
    ?? null;

unset(
    $_SESSION['hr_security_message'],
    $_SESSION['hr_security_error']
);
?>

<main class="hr-account-page">

    <header class="hr-account-heading">

        <div>
            <span class="hr-account-kicker">
                ACCOUNT / SECURITY
            </span>

            <h1>Account &amp; Security</h1>

            <p>
                Review your HR account access and
                change your password securely.
            </p>
        </div>

        <div class="hr-account-status">
            <span>Account Status</span>
            <strong>Active</strong>
        </div>

    </header>


    <?php if ($message): ?>
        <div class="hr-security-message success">
            <?= e((string)$message) ?>
        </div>
    <?php endif; ?>


    <?php if ($error): ?>
        <div class="hr-security-message error">
            <?= e((string)$error) ?>
        </div>
    <?php endif; ?>


    <section class="hr-security-grid">

        <article class="hr-account-card">

            <div class="hr-security-icon">
                <i data-lucide="shield-check"></i>
            </div>

            <span class="hr-account-kicker">
                ACCOUNT ACCESS
            </span>

            <h2>Your HR account is active</h2>

            <p>
                Your Grow/Flow account currently
                has HR Administrator access.
            </p>

            <div class="hr-security-details">

                <div>
                    <span>Email</span>
                    <strong>
                        <?= e(
                            (string)$hrAccount['email']
                        ) ?>
                    </strong>
                </div>

                <div>
                    <span>Last Login</span>
                    <strong>
                        <?= $hrAccount['last_login_at']
                            ? e(
                                date(
                                    'M j, Y g:i A',
                                    strtotime(
                                        (string)$hrAccount['last_login_at']
                                    )
                                )
                            )
                            : 'Never'
                        ?>
                    </strong>
                </div>

            </div>

        </article>


        <article class="hr-account-card">

            <div class="hr-account-card-head">
                <div>
                    <span>PASSWORD SECURITY</span>

                    <h2>Change Password</h2>

                    <p>
                        Confirm your current password
                        before creating a new one.
                    </p>
                </div>
            </div>

            <form
                method="post"
                action="index.php?page=hr-security"
                class="hr-password-form"
                autocomplete="off"
            >

                <input
                    type="hidden"
                    name="csrf"
                    value="<?= e(csrf_token()) ?>"
                >

                <input
                    type="hidden"
                    name="security_action"
                    value="change_password"
                >

                <label>
                    Current Password

                    <input
                        type="password"
                        name="current_password"
                        maxlength="128"
                        autocomplete="current-password"
                        required
                    >
                </label>

                <div class="hr-password-row">

                    <label>
                        New Password

                        <input
                            type="password"
                            name="new_password"
                            minlength="12"
                            maxlength="128"
                            autocomplete="new-password"
                            required
                        >
                    </label>

                    <label>
                        Confirm New Password

                        <input
                            type="password"
                            name="confirm_password"
                            minlength="12"
                            maxlength="128"
                            autocomplete="new-password"
                            required
                        >
                    </label>

                </div>

                <p>
                    Minimum 12 characters with
                    uppercase, lowercase, a number,
                    and a symbol.
                </p>

                <button
                    type="submit"
                    class="hr-password-button"
                >
                    <i data-lucide="key-round"></i>
                    Change Password
                </button>

            </form>

        </article>

    </section>

</main>