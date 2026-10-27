<?php

$hrUser = authenticated_user();

if (
    !$hrUser
    || ($hrUser['role'] ?? '') !== 'hr'
) {
    http_response_code(403);
    exit('Access denied. HR only.');
}

$hrAccount = hr_account_data(
    (int)$hrUser['id']
);
?>

<main class="hr-account-page">

    <header class="hr-account-heading">
        <div>
            <span class="hr-account-kicker">
                ACCOUNT / HR PROFILE
            </span>

            <h1>My Profile</h1>

            <p>
                Review your Grow/Flow HR account
                information.
            </p>
        </div>

        <div class="hr-account-status">
            <span>Account Status</span>
            <strong>Active</strong>
        </div>
    </header>


    <section class="hr-profile-grid">

        <article class="hr-account-card hr-profile-identity">

            <div class="hr-profile-avatar">
                <?= e(
                    strtoupper(
                        substr(
                            (string)$hrAccount['full_name'],
                            0,
                            1
                        )
                    )
                ) ?>
            </div>

            <span class="hr-active-pill">
                Active HR Account
            </span>

            <h2>
                <?= e(
                    (string)$hrAccount['full_name']
                ) ?>
            </h2>

            <p>
                <?= e(
                    (string)$hrAccount['role_label']
                ) ?>
            </p>

            <div class="hr-profile-line">
                <i data-lucide="building-2"></i>

                <?= e(
                    (string)$hrAccount['company_name']
                ) ?>
            </div>

            <div class="hr-profile-line">
                <i data-lucide="mail"></i>

                <?= e(
                    (string)$hrAccount['email']
                ) ?>
            </div>

        </article>


        <article class="hr-account-card">

            <div class="hr-account-card-head">
                <div>
                    <span>
                        ACCOUNT INFORMATION
                    </span>

                    <h2>
                        HR Administrator Details
                    </h2>

                    <p>
                        Information associated with
                        your Grow/Flow account.
                    </p>
                </div>
            </div>

            <div class="hr-account-details">

                <div>
                    <span>Full Name</span>
                    <strong>
                        <?= e(
                            (string)$hrAccount['full_name']
                        ) ?>
                    </strong>
                </div>

                <div>
                    <span>Email Address</span>
                    <strong>
                        <?= e(
                            (string)$hrAccount['email']
                        ) ?>
                    </strong>
                </div>

                <div>
                    <span>Role</span>
                    <strong>
                        <?= e(
                            (string)$hrAccount['role_label']
                        ) ?>
                    </strong>
                </div>

                <div>
                    <span>Company</span>
                    <strong>
                        <?= e(
                            (string)$hrAccount['company_name']
                        ) ?>
                    </strong>
                </div>

                <div>
                    <span>Account Created</span>
                    <strong>
                        <?= e(
                            date(
                                'F j, Y',
                                strtotime(
                                    (string)$hrAccount['created_at']
                                )
                            )
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

    </section>

</main>