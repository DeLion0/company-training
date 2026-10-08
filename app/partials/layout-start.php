<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <meta name="theme-color" content="#f5faf6">

  <title>Grow/Flow</title>

  <!-- FONTS -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

  <link
    href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap"
    rel="stylesheet">

  <!-- GLOBAL CSS -->
  <link rel="stylesheet" href="assets/css/layout.css?v=20261009-3">
  <link rel="stylesheet" href="assets/css/sidebar.css?v=20261009-3">

  <!-- DASHBOARD -->
  <?php if (($page ?? '') === 'dashboard'): ?>
    <link rel="stylesheet" href="assets/css/dashboard.css?v=20261009-4">
    <script defer src="assets/js/dashboard.js?v=20261009-4"></script>
  <?php endif; ?>

  <!-- EMPLOYEES -->
  <?php if (($page ?? '') === 'employees'): ?>
    <link
      rel="stylesheet"
      href="assets/css/employees.css?v=20261009-3">
  <?php endif; ?>

  <!-- DEPARTMENTS -->
  <?php if (($page ?? '') === 'departments'): ?>
    <link
      rel="stylesheet"
      href="assets/css/departments.css?v=20261009-3">
  <?php endif; ?>

  <!-- TRAINING PROGRAMS -->
  <?php if (($page ?? '') === 'training'): ?>
    <link
      rel="stylesheet"
      href="assets/css/training.css?v=20261009-3">
  <?php endif; ?>

  <!-- AVAILABLE TRAINING -->
  <?php if (($page ?? '') === 'available-training'): ?>
    <link
      rel="stylesheet"
      href="assets/css/available-training.css?v=20261009-3">
  <?php endif; ?>

  <!-- LUCIDE ICONS -->
  <script
    defer
    src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>

  <!-- GLOBAL JS -->
  <script
    defer
    src="assets/js/app.js?v=20261009-3"></script>

  <!-- DASHBOARD JS -->
  <?php if (($page ?? '') === 'dashboard'): ?>
    <script
      defer
      src="assets/js/dashboard.js?v=20261009-3"></script>
  <?php endif; ?>

  <!-- EMPLOYEES JS -->
  <?php if (($page ?? '') === 'employees'): ?>
    <script
      defer
      src="assets/js/employees.js?v=20261009-3"></script>
  <?php endif; ?>

  <!-- DEPARTMENTS JS -->
  <?php if (($page ?? '') === 'departments'): ?>
    <script
      defer
      src="assets/js/departments.js?v=20261009-3"></script>
  <?php endif; ?>

  <!-- TRAINING JS -->
  <?php if (($page ?? '') === 'training'): ?>
    <script
      defer
      src="assets/js/training.js?v=20261009-3"></script>
  <?php endif; ?>

  <!-- AVAILABLE TRAINING JS -->
  <?php if (($page ?? '') === 'available-training'): ?>
    <script
      defer
      src="assets/js/available-training.js?v=20261009-3"></script>
  <?php endif; ?>
</head>

<body>

  <div class="app-layout">

    <div
      id="overlay"
      class="mobile-overlay"
      hidden></div>