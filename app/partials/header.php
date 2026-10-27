<?php

$user = current_user();

$pageLabels = [
    'dashboard' => 'Dashboard',
    'employees' => 'Employees',
    'departments' => 'Departments',
    'managers' => 'Department Heads',
    'training' => 'Training Programs',
    'available-training' => 'Available Training',
    'performance' => 'Performance Evaluation',
    'competencies' => 'Competencies',
    'analytics' => 'Analytics',
    'points-badges' => 'Points & Badges',
    'hr-profile' => 'My Profile',
    'hr-security' => 'Account & Security',
];

$pageLabel =
    $pageLabels[$page ?? 'dashboard']
    ?? 'Workspace';
?>

<header class="topbar">

    <div class="topbar-left">

        <button
            class="icon-button menu-trigger"
            id="menuToggle"
            aria-label="Toggle navigation"
        >
            <i data-lucide="panel-left"></i>
        </button>

        <span class="breadcrumb">
            Workspace

            <i data-lucide="chevron-right"></i>

            <strong>
                <?= e($pageLabel) ?>
            </strong>
        </span>

    </div>

    <div class="topbar-right">

        <span
            class="today-label"
            id="todayLabel"
        ></span>

        <span class="topbar-divider"></span>

        <div
            class="header-avatar"
            title="<?= e(
                (string) $user['name']
            ) ?>"
        >
            <?= e(
                strtoupper(
                    substr(
                        (string) $user['name'],
                        0,
                        1
                    )
                )
            ) ?>
        </div>

    </div>

</header>
