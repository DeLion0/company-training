<?php
$user = current_user();
$active = $page ?? 'dashboard';
$isHR = $user['role'] === 'hr';
?>

<aside class="sidebar" id="sidebar" aria-label="Main navigation">

  <!-- BRAND -->
  <div class="brand">
    <div class="brand-mark">
      <i data-lucide="sprout"></i>
    </div>

    <div class="brand-name">
      Grow/<span>Flow</span>
      <small>LEARNING MANAGEMENT</small>
    </div>
  </div>

  <!-- WORKSPACE -->
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

  <!-- NAVIGATION -->
  <nav class="navigation" aria-label="Workspace navigation">

    <!-- WORKSPACE MODULES -->
    <p class="nav-label">WORKSPACE</p>

    <a
      href="?page=dashboard"
      class="nav-link <?= $active === 'dashboard' ? 'active' : '' ?>"
      <?= $active === 'dashboard' ? 'aria-current="page"' : '' ?>>
      <i data-lucide="layout-dashboard"></i>
      <span>Dashboard</span>
    </a>

    <a
      href="?page=employees"
      class="nav-link <?= $active === 'employees' ? 'active' : '' ?>"
      <?= $active === 'employees' ? 'aria-current="page"' : '' ?>>
      <i data-lucide="users-round"></i>
      <span>Employees</span>
    </a>

    <?php if ($isHR): ?>
      <a
        href="?page=departments"
        class="nav-link <?= $active === 'departments' ? 'active' : '' ?>"
        <?= $active === 'departments' ? 'aria-current="page"' : '' ?>>
        <i data-lucide="building-2"></i>
        <span>Departments</span>
      </a>
    <?php endif; ?>

    <!-- LEARNING & GROWTH -->
    <p class="nav-label second-label">LEARNING & GROWTH</p>

    <a
      href="?page=training"
      class="nav-link <?= $active === 'training' ? 'active' : '' ?>"
      <?= $active === 'training' ? 'aria-current="page"' : '' ?>>
      <i data-lucide="graduation-cap"></i>
      <span>Training Programs</span>
    </a>

    <a
      href="?page=available-training"
      class="nav-link <?= $active === 'available-training' ? 'active' : '' ?>"
      <?= $active === 'available-training' ? 'aria-current="page"' : '' ?>>
      <i data-lucide="calendar-check"></i>
      <span>Available Training</span>
    </a>

    <a
      href="#"
      class="nav-link <?= $active === 'competencies' ? 'active' : '' ?>"
      <?= $active === 'competencies' ? 'aria-current="page"' : '' ?>>
      <i data-lucide="brain"></i>
      <span>Competencies</span>
    </a>

    <a
      href="#"
      class="nav-link <?= $active === 'analytics' ? 'active' : '' ?>"
      <?= $active === 'analytics' ? 'aria-current="page"' : '' ?>>
      <i data-lucide="chart-no-axes-column-increasing"></i>
      <span>Analytics</span>
    </a>

  </nav>

  <!-- BOTTOM ACCOUNT -->
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
      action="?page=logout"
      class="sidebar-logout-form">
      <input
        type="hidden"
        name="csrf"
        value="<?= e(csrf_token()) ?>">

      <button type="submit" class="sidebar-logout-btn">
        <i data-lucide="log-out"></i>
        <span>Sign out</span>
      </button>
    </form>

  </div>

</aside>