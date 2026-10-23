<?php

$error = $error ?? '';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#f2faf4">
    <title>Sign in | Grow/Flow</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="assets/css/login.css?v=20261023-v3-final">
    <script defer src="assets/js/login.js?v=20261023-v3-final"></script>
</head>

<body>
    <main class="login-shell">

        <section class="brand-panel" aria-label="Grow/Flow introduction">
            <span class="brand-circle brand-circle-one" aria-hidden="true"></span>
            <span class="brand-circle brand-circle-two" aria-hidden="true"></span>

            <div class="brand-panel-inner">

                <a class="client-lockup" href="?page=login" aria-label="Grow/Flow">
                    <img
                        id="clientLogoSource"
                        class="client-logo-source"
                        src="assets/branding/growflow-client-logo.jpg"
                        alt=""
                        aria-hidden="true"
                    >
                    <canvas
                        class="client-logo-canvas"
                        data-client-logo
                        width="900"
                        height="560"
                        aria-label="Grow/Flow Learning Management"
                        role="img"
                    ></canvas>
                </a>

                <div class="brand-content">
                    <h1>
                        Grow people.
                        <strong>Create impact.</strong>
                    </h1>

                    <p class="brand-intro">
                        A modern learning management platform that helps HR
                        and department leaders develop talent, build skills,
                        and drive organizational growth.
                    </p>

                    <div class="benefit-list">
                        <article class="benefit-item">
                            <span class="benefit-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                </svg>
                            </span>
                            <span class="benefit-copy">
                                <strong>Develop your people</strong>
                                <small>Identify skill gaps and unlock new opportunities.</small>
                            </span>
                        </article>

                        <article class="benefit-item">
                            <span class="benefit-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24">
                                    <path d="M4 20V10"/>
                                    <path d="M10 20V4"/>
                                    <path d="M16 20v-7"/>
                                    <path d="M22 20V7"/>
                                </svg>
                            </span>
                            <span class="benefit-copy">
                                <strong>Simplify training management</strong>
                                <small>Create, track, and measure learning progress.</small>
                            </span>
                        </article>

                        <article class="benefit-item">
                            <span class="benefit-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/>
                                    <path d="m9 12 2 2 4-4"/>
                                </svg>
                            </span>
                            <span class="benefit-copy">
                                <strong>Build a stronger organization</strong>
                                <small>Empower teams with the skills to grow and succeed.</small>
                            </span>
                        </article>
                    </div>
                </div>

                <p class="brand-footer">
                    Grow/Flow · Learning &amp; Development
                </p>
            </div>
        </section>

        <section class="signin-panel">
            <span class="signin-circle signin-circle-one" aria-hidden="true"></span>
            <span class="signin-circle signin-circle-two" aria-hidden="true"></span>

            <div class="mobile-lockup">
                <canvas
                    class="client-logo-canvas"
                    data-client-logo
                    width="900"
                    height="560"
                    aria-label="Grow/Flow Learning Management"
                    role="img"
                ></canvas>
            </div>

            <div class="signin-card">
                <div class="security-pill">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/>
                        <path d="m9 12 2 2 4-4"/>
                    </svg>
                    <span>SECURE WORKSPACE</span>
                </div>

                <h2>Welcome back</h2>

                <p class="signin-intro">
                    Sign in to your Grow/Flow workspace.
                </p>

                <?php if ($error !== ''): ?>
                    <div class="login-error" role="alert">
                        <?= e($error) ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="?page=login" autocomplete="on">
                    <input
                        type="hidden"
                        name="csrf"
                        value="<?= e(csrf_token()) ?>"
                    >

                    <label for="email">Work email</label>

                    <div class="field-control">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <rect x="3" y="5" width="18" height="14" rx="2"/>
                            <path d="m4 7 8 6 8-6"/>
                        </svg>

                        <input
                            required
                            type="email"
                            name="email"
                            id="email"
                            placeholder="name@growflow.com"
                            autocomplete="username"
                            maxlength="254"
                            value="<?= e((string)($_POST['email'] ?? '')) ?>"
                        >
                    </div>

                    <div class="password-label">
                        <label for="password">Password</label>
                    </div>

                    <div class="field-control password-control">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <rect x="5" y="10" width="14" height="11" rx="2"/>
                            <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                        </svg>

                        <input
                            required
                            type="password"
                            name="password"
                            id="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            maxlength="1024"
                        >

                        <span class="field-separator" aria-hidden="true"></span>

                        <button
                            type="button"
                            class="show-password"
                            id="togglePassword"
                            aria-label="Show password"
                        >
                            Show
                        </button>
                    </div>

                    <button class="signin-button" type="submit">
                        <span>Sign in to workspace</span>
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M5 12h14"/>
                            <path d="m13 6 6 6-6 6"/>
                        </svg>
                    </button>
                </form>

                <div class="signin-meta">
                    <span>For HR administrators, department heads, and employees</span>
                    <span>Protected company access</span>
                </div>
            </div>

            <p class="copyright">
                © <?= date('Y') ?> Grow/Flow. All rights reserved.
            </p>
        </section>

    </main>
</body>

</html>
