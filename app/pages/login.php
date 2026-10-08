<?php

$error = $error ?? '';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#edf7ef">
    <title>Sign in | Grow/Flow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/login.css">
    <script defer src="assets/js/login.js"></script>
</head>

<body>
    <main class="login-layout">
        <section class="story"><a class="story-brand" href="?page=login"><span class="leaf">✳</span> Grow<span> with the flow</span></a>
            <div class="story-main">
                <div class="eyebrow"><span class="pulse"></span> WORKFORCE DEVELOPMENT PLATFORM</div>
                <h1>Grow talent.<br><em>Build potential.</em></h1>
                <p>Connect your people to the learning opportunities that matter. A smarter workspace for HR and department leaders.</p>
                <div class="story-features">
                    <div><span>↗</span><strong>Identify growth opportunities</strong><small>See skill gaps and development needs.</small></div>
                    <div><span>◎</span><strong>Support every learning journey</strong><small>Manage training sessions with confidence.</small></div>
                </div>
            </div>
            <p class="story-footer">Grow/Flow · Learning & Development</p>
        </section>
        <section class="login-side">
            <div class="login-card">
                <div class="mobile-logo">✳ Grow/Flow</div>
                <div class="small-pill">SECURE WORKSPACE</div>
                <h2>Welcome back</h2>
                <p class="intro">Sign in to your company training workspace.</p>
                <?php if ($error !== ''): ?><div class="error" role="alert"><?= e($error) ?></div><?php endif; ?>
                <form method="post" action="?page=login" autocomplete="on"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <label for="email">Work email</label>
                    <div class="control"><svg viewBox="0 0 24 24" aria-hidden="true">
                            <rect x="3" y="5" width="18" height="14" rx="2" />
                            <path d="m4 7 8 6 8-6" />
                        </svg><input required type="email" name="email" id="email" placeholder="name@company.com" autocomplete="username" maxlength="254" value="<?= e((string)($_POST['email'] ?? '')) ?>"></div>
                    <div class="password-label"><label for="password">Password</label></div>
                    <div class="control"><svg viewBox="0 0 24 24" aria-hidden="true">
                            <rect x="5" y="10" width="14" height="11" rx="2" />
                            <path d="M8 10V7a4 4 0 0 1 8 0v3" />
                        </svg><input required type="password" name="password" id="password" placeholder="Enter your password" autocomplete="current-password" minlength="8"><button type="button" class="eye" id="togglePassword" aria-label="Show password">Show</button></div>
                    <button class="submit" type="submit">Sign in to workspace <span aria-hidden="true">→</span></button>
                </form>
                <div class="login-foot"><span>For HR administrators and department heads</span><span>Protected company access</span></div>
            </div>
            <p class="copy">© <?= date('Y') ?> Grow/Flow. All rights reserved.</p>
        </section>
    </main>
</body>

</html>