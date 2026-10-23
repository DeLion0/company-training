<?php
$user = current_user();
$active = $page ?? 'dashboard';
$isHR = $user['role'] === 'hr';
?>

<aside class="sidebar" id="sidebar" aria-label="Main navigation">

<div class="brand">
  <img
    class="brand-client-logo"
    src="assets/branding/growflow-client-logo.jpg"
    alt="Grow/Flow"
  >

  <div class="brand-name">
    Grow/<span>Flow</span>
    <small>LEARNING MANAGEMENT</small>
  </div>
</div>

  <div class="workspace">
    <div class="workspace-icon">G</div>

    <div class="workspace-copy">
      <strong>Company Workspace</strong>
      <small>
        <?= e($isHR ? 'HR Workspace' : 'Department Workspace') ?>
      </small>
    </div>

    <i data-lucide="building-2" class="muted-icon"></i>
  </div>

  <nav class="navigation" aria-label="Workspace navigation">

    <p class="nav-label">WORKSPACE</p>

    <a
      href="index.php?page=dashboard"
      class="nav-link <?= $active === 'dashboard' ? 'active' : '' ?>"
      <?= $active === 'dashboard' ? 'aria-current="page"' : '' ?>
    >
      <i data-lucide="layout-dashboard"></i>
      <span>Dashboard</span>
    </a>

    <a
      href="index.php?page=employees"
      class="nav-link <?= $active === 'employees' ? 'active' : '' ?>"
      <?= $active === 'employees' ? 'aria-current="page"' : '' ?>
    >
      <i data-lucide="users-round"></i>
      <span>Employees</span>
    </a>

    <?php if ($isHR): ?>
      <a
        href="index.php?page=departments"
        class="nav-link <?= $active === 'departments' ? 'active' : '' ?>"
        <?= $active === 'departments' ? 'aria-current="page"' : '' ?>
      >
        <i data-lucide="building-2"></i>
        <span>Departments</span>
      </a>

      <a
        href="index.php?page=managers"
        class="nav-link <?= $active === 'managers' ? 'active' : '' ?>"
        <?= $active === 'managers' ? 'aria-current="page"' : '' ?>
      >
        <i data-lucide="user-round-cog"></i>
        <span>Department Heads</span>
      </a>
    <?php endif; ?>

    <p class="nav-label second-label">LEARNING &amp; GROWTH</p>

    <a
      href="index.php?page=training"
      class="nav-link <?= $active === 'training' ? 'active' : '' ?>"
      <?= $active === 'training' ? 'aria-current="page"' : '' ?>
    >
      <i data-lucide="graduation-cap"></i>
      <span>Training Programs</span>
    </a>

    <a
      href="index.php?page=available-training"
      class="nav-link <?= $active === 'available-training' ? 'active' : '' ?>"
      <?= $active === 'available-training' ? 'aria-current="page"' : '' ?>
    >
      <i data-lucide="calendar-check"></i>
      <span>Available Training</span>
    </a>

    <a
      class="nav-link <?= $active === 'performance' ? 'active' : '' ?>"
      href="index.php?page=performance"
      <?= $active === 'performance' ? 'aria-current="page"' : '' ?>
    >
      <i data-lucide="clipboard-check"></i>
      <span>Performance Evaluation</span>
    </a>

    <?php if ($isHR): ?>
      <a
        href="certifications.php"
        class="nav-link <?= $active === 'certifications' ? 'active' : '' ?>"
        <?= $active === 'certifications' ? 'aria-current="page"' : '' ?>
      >
        <i data-lucide="award"></i>
        <span>Certifications</span>
      </a>
    <?php endif; ?>

    <a
      href="#"
      class="nav-link <?= $active === 'analytics' ? 'active' : '' ?>"
      <?= $active === 'analytics' ? 'aria-current="page"' : '' ?>
    >
      <i data-lucide="chart-no-axes-column-increasing"></i>
      <span>Analytics</span>
    </a>

  </nav>

  <div class="sidebar-bottom">

    <div class="sidebar-user">
      <div class="avatar">
        <?= e(strtoupper(substr($user['name'], 0, 1))) ?>
      </div>

      <div class="sidebar-user-info">
        <strong><?= e($user['name']) ?></strong>
        <small>
          <?= e($isHR ? 'HR Administrator' : 'Department Head') ?>
        </small>
      </div>
    </div>

    <form
      method="post"
      action="index.php?page=logout"
      class="sidebar-logout-form"
    >
      <input
        type="hidden"
        name="csrf"
        value="<?= e(csrf_token()) ?>"
      >

      <button type="submit" class="sidebar-logout-btn">
        <i data-lucide="log-out"></i>
        <span>Sign out</span>
      </button>
    </form>

  </div>

</aside>
