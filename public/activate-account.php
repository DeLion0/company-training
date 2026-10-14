<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/employee-activation.php';

secure_session_start();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');

$token = trim(
    (string) (
        $_POST['token']
        ?? $_GET['token']
        ?? ''
    )
);

$record =
    $token !== ''
        ? employee_activation_lookup($token)
        : null;

$error = '';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {
    if (
        !csrf_valid(
            $_POST['csrf']
            ?? null
        )
    ) {
        $error =
            'Session expired. Refresh the page and try again.';

    } else {
        try {
            $result =
                employee_activation_complete(
                    $token,
                    (string) (
                        $_POST['new_password']
                        ?? ''
                    ),
                    (string) (
                        $_POST['confirm_password']
                        ?? ''
                    )
                );

            // Auto sign-in when auth.php already allows role=employee.
            if (
                login_user(
                    $result['email'],
                    $result['password']
                )
            ) {
                redirect_to(
                    'employee.php?module=home'
                );
            }

            // Account is active even if auth.php has not been patched yet.
            redirect_to(
                'index.php?page=login&activated=1'
            );

        } catch (
            InvalidArgumentException
            $ex
        ) {
            $error =
                $ex->getMessage();

            $record =
                employee_activation_lookup(
                    $token
                );

        } catch (Throwable $ex) {
            error_log(
                'Employee activation failed: '
                . $ex->getMessage()
            );

            $error =
                'Unable to activate the account. Contact HR.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width,initial-scale=1"
    >
    <title>Activate Employee Account | Grow/Flow</title>

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
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="assets/css/employee-activation.css?v=20261014-2"
    >

    <script
        defer
        src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"
    ></script>

    <script
        defer
        src="assets/js/employee-activation.js?v=20261014-2"
    ></script>
</head>

<body>

<main class="activation-layout">

    <section class="activation-story">

        <div class="activation-brand">
            <span>
                <i data-lucide="sprout"></i>
            </span>

            Grow/<b>Flow</b>
        </div>

        <div class="activation-story-main">

            <span class="activation-eyebrow">
                EMPLOYEE ACCOUNT ACTIVATION
            </span>

            <h1>
                Your learning workspace starts here.
            </h1>

            <p>
                Create your private password to activate your
                Grow/Flow Employee Workspace.
            </p>

            <div class="activation-steps">

                <div class="done">
                    <b>1</b>
                    <span>
                        <strong>HR created your account</strong>
                        <small>
                            Your employee profile and login ID are ready.
                        </small>
                    </span>
                </div>

                <div class="active">
                    <b>2</b>
                    <span>
                        <strong>Create your password</strong>
                        <small>
                            Choose a password only you know.
                        </small>
                    </span>
                </div>

                <div>
                    <b>3</b>
                    <span>
                        <strong>Enter Employee Workspace</strong>
                        <small>
                            Your account becomes active immediately.
                        </small>
                    </span>
                </div>

            </div>

        </div>

        <small class="activation-story-foot">
            Grow/Flow · Learning Management
        </small>

    </section>


    <section class="activation-side">

        <div class="activation-card">

            <?php if (!$record): ?>

                <span class="activation-pill error">
                    <i data-lucide="link-2-off"></i>
                    ACTIVATION LINK UNAVAILABLE
                </span>

                <h2>
                    This link cannot be used
                </h2>

                <p class="activation-intro">
                    The activation link may be invalid,
                    expired, or already used.
                    Ask HR to create a new activation link.
                </p>

                <a
                    class="activation-login-link"
                    href="index.php?page=login"
                >
                    Return to Grow/Flow Login
                </a>

            <?php else: ?>

                <span class="activation-pill">
                    <i data-lucide="shield-check"></i>
                    SECURE ACTIVATION
                </span>

                <h2>
                    Create your password
                </h2>

                <p class="activation-intro">
                    Welcome,
                    <?= e(
                        (string) $record[
                            'full_name'
                        ]
                    ) ?>.
                    Complete this one-time step.
                </p>


                <div class="activation-account">

                    <div class="activation-avatar">
                        <?= e(
                            strtoupper(
                                substr(
                                    (string) $record[
                                        'full_name'
                                    ],
                                    0,
                                    1
                                )
                            )
                        ) ?>
                    </div>

                    <div>
                        <strong>
                            <?= e(
                                (string) $record[
                                    'full_name'
                                ]
                            ) ?>
                        </strong>

                        <span>
                            <?= e(
                                (string) $record[
                                    'employee_code'
                                ]
                            ) ?>
                        </span>

                        <small>
                            Login ID:
                            <?= e(
                                (string) $record[
                                    'email'
                                ]
                            ) ?>
                        </small>
                    </div>

                </div>


                <?php if ($error !== ''): ?>

                    <div
                        class="activation-error"
                        role="alert"
                    >
                        <i data-lucide="circle-alert"></i>
                        <span><?= e($error) ?></span>
                    </div>

                <?php endif; ?>


                <form
                    method="post"
                    action="activate-account.php"
                >

                    <input
                        type="hidden"
                        name="csrf"
                        value="<?= e(csrf_token()) ?>"
                    >

                    <input
                        type="hidden"
                        name="token"
                        value="<?= e($token) ?>"
                    >


                    <label for="newPassword">
                        New password
                    </label>

                    <div class="activation-control">

                        <i data-lucide="lock-keyhole"></i>

                        <input
                            required
                            type="password"
                            id="newPassword"
                            name="new_password"
                            minlength="10"
                            maxlength="128"
                            autocomplete="new-password"
                            placeholder="Create a strong password"
                        >

                        <button
                            type="button"
                            data-toggle-password="newPassword"
                        >
                            Show
                        </button>

                    </div>


                    <div class="password-rules">
                        <span data-rule="length">
                            <i></i> 10+ characters
                        </span>
                        <span data-rule="upper">
                            <i></i> Uppercase
                        </span>
                        <span data-rule="lower">
                            <i></i> Lowercase
                        </span>
                        <span data-rule="number">
                            <i></i> Number
                        </span>
                        <span data-rule="special">
                            <i></i> Special character
                        </span>
                    </div>


                    <label for="confirmPassword">
                        Confirm password
                    </label>

                    <div class="activation-control">

                        <i data-lucide="shield-check"></i>

                        <input
                            required
                            type="password"
                            id="confirmPassword"
                            name="confirm_password"
                            minlength="10"
                            maxlength="128"
                            autocomplete="new-password"
                            placeholder="Enter password again"
                        >

                        <button
                            type="button"
                            data-toggle-password="confirmPassword"
                        >
                            Show
                        </button>

                    </div>

                    <p
                        class="activation-match"
                        id="passwordMatch"
                    ></p>

                    <button
                        class="activation-submit"
                        type="submit"
                    >
                        Activate Employee Account
                        <i data-lucide="arrow-right"></i>
                    </button>

                </form>

                <p class="activation-note">
                    After activation, use the normal Grow/Flow login
                    with the Login ID shown above and your new password.
                </p>

            <?php endif; ?>

        </div>

    </section>

</main>

</body>
</html>
