<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Grow/Flow</title>

    <!-- GLOBAL CSS -->
    <link rel="stylesheet" href="assets/css/layout.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">

    <!-- DASHBOARD ONLY -->
    <?php if (($page ?? '') === 'dashboard'): ?>
        <link rel="stylesheet" href="assets/css/dashboard.css?v=20261009-2">
        <script defer src="assets/js/dashboard.js?v=20261009-2"></script>
    <?php endif; ?>

    <!-- TRAINING ONLY -->
    <?php if (($page ?? '') === 'training'): ?>
        <link rel="stylesheet" href="assets/css/training.css">
        <script defer src="assets/js/training.js"></script>
    <?php endif; ?>

    <!-- EMPLOYEES ONLY -->
    <?php if (($page ?? '') === 'employees'): ?>
        <link rel="stylesheet" href="assets/css/employees.css">
        <script defer src="assets/js/employees.js"></script>
    <?php endif; ?>

    <!-- DEPARTMENTS ONLY -->
    <?php if (($page ?? '') === 'departments'): ?>
        <link rel="stylesheet" href="assets/css/departments.css">
        <script defer src="assets/js/departments.js"></script>
    <?php endif; ?>

    <!-- AVAILABLE TRAINING ONLY -->
    <?php if (($page ?? '') === 'available-training'): ?>
        <link rel="stylesheet" href="assets/css/available-training.css">
        <script defer src="assets/js/available-training.js"></script>
    <?php endif; ?>


    <!-- PERFORMANCE EVALUATION ONLY -->
    <?php if (($page ?? '') === 'performance'): ?>
        <link
            rel="stylesheet"
            href="assets/css/performance.css?v=20261014-2"
        >
        <script
            defer
            src="assets/js/performance.js?v=20261014-2"
        ></script>
    <?php endif; ?>

    <!-- GLOBAL LIBRARIES -->
    <script defer src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>

    <!-- GLOBAL JS -->
    <script defer src="assets/js/app.js"></script>
</head>

<body>
