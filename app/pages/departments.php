<?php
// Access must be checked by the router before rendering.
require_once __DIR__ . '/../core/departments-data.php';
$hr = current_user();
$companyId = dept_company_id((int)$hr['id']);
$departments = dept_list($companyId);
$positions = dept_positions($companyId);
$message = $_SESSION['dept_message'] ?? '';
unset($_SESSION['dept_message']);
$error = $_SESSION['dept_error'] ?? '';
unset($_SESSION['dept_error']);
$preview = $_SESSION['dept_preview'] ?? null;
$grouped = [];
foreach ($positions as $p) {
  $grouped[$p['department_id']][] = $p['name'];
}
$employeeTotal = array_sum(array_map(fn($d) => (int)$d['employee_count'], $departments));
?>
<link rel="stylesheet" href="assets/css/departments.css">
<style>
  .gf-departments .gf-import-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    align-items: center;
    flex-wrap: wrap;
    padding: 18px 22px;
    border-top: 1px solid #e5eee8
  }

  .gf-departments .gf-import-actions form {
    margin: 0
  }

  .gf-departments .gf-import-actions button {
    display: inline-flex;
    align-items: center;
    gap: 8px
  }

  .gf-departments .gf-import-actions svg {
    width: 17px;
    height: 17px
  }
</style>
<main class="content gf-departments">
  <header class="gf-dept-heading">
    <div><span class="gf-eyebrow">PEOPLE MANAGEMENT / HR</span>
      <h1>Departments &amp; Job Titles</h1>
      <p>Manage your company departments, positions, and employee distribution.</p>
    </div>
    <div class="gf-actions"><button type="button" class="gf-btn gf-btn-soft" data-open="excel"> <i data-lucide="file-spreadsheet"></i> Import Excel</button><button type="button" class="gf-btn gf-btn-main" data-open="manual"><i data-lucide="plus"></i> Add Department</button></div>
  </header>
  <?php if ($message): ?><div class="gf-alert gf-ok" role="status"><?= e($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="gf-alert gf-bad" role="alert"><?= e($error) ?></div><?php endif; ?>
  <div class="gf-kpis">
    <div class="gf-kpi"><i data-lucide="building-2"></i><span>Departments</span><strong><?= count($departments) ?></strong></div>
    <div class="gf-kpi"><i data-lucide="briefcase-business"></i><span>Job Titles</span><strong><?= count($positions) ?></strong></div>
    <div class="gf-kpi"><i data-lucide="users-round"></i><span>Employees</span><strong><?= $employeeTotal ?></strong></div>
  </div>
  <section class="gf-panel">
    <div class="gf-panel-header">
      <div>
        <h2>Department Directory</h2>
        <p>Employee counts are automatically calculated from registered employee records.</p>
      </div><label class="gf-search"><i data-lucide="search"></i><input id="dept-search" placeholder="Search departments or job titles" aria-label="Search departments"></label>
    </div>
    <div class="gf-table-wrap">
      <table>
        <thead>
          <tr>
            <th>DEPARTMENT</th>
            <th>JOB TITLES</th>
            <th>EMPLOYEES</th>
            <th>DEPARTMENT HEAD</th>
            <th>STATUS</th>
            <th>ACTION</th>
          </tr>
        </thead>
        <tbody id="dept-table">
          <?php foreach ($departments as $d): $titles = $grouped[$d['id']] ?? []; ?>
            <tr data-search="<?= e(strtolower($d['name'] . ' ' . $d['department_code'] . ' ' . implode(' ', $titles))) ?>">
              <td><strong><?= e($d['name']) ?></strong><small><?= e($d['department_code'] ?: 'No code') ?></small></td>
              <td><button type="button" class="gf-titles-trigger" data-view-titles data-dept-name="<?= e($d['name']) ?>" data-titles="<?= e(json_encode(array_values($titles), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>" aria-label="View job titles for <?= e($d['name']) ?>"><i data-lucide="briefcase-business"></i> <?= count($titles) ?> <?= count($titles) === 1 ? 'Job Title' : 'Job Titles' ?> <i data-lucide="chevron-right"></i></button></td>
              <td><strong><?= (int)$d['employee_count'] ?></strong></td>
              <td><?= e($d['manager_name'] ?: 'Not assigned') ?></td>
              <td><span class="gf-status <?= e($d['status']) ?>"><?= e(ucfirst($d['status'])) ?></span></td>
              <td><button type="button" class="gf-small-button" data-add-position="<?= (int)$d['id'] ?>" data-dept-name="<?= e($d['name']) ?>">+ Job Title</button></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if (!$departments): ?><p class="gf-empty">No departments yet. Choose Add Department or Import Excel to get started.</p><?php endif; ?><p class="gf-empty" id="dept-no-match" hidden>No matching departments.</p>
  </section>
  <div class="gf-modal-backdrop" id="gf-modal" hidden>
    <section class="gf-modal" role="dialog" aria-modal="true" aria-labelledby="gf-modal-title">
      <div class="gf-modal-header">
        <div><span class="gf-eyebrow">HR / COMPANY SETTINGS</span>
          <h2 id="gf-modal-title">Add Department</h2>
        </div><button type="button" data-close aria-label="Close"><i data-lucide="x"></i></button>
      </div>
      <form method="post" action="?page=departments" id="gf-manual" class="gf-form"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="department_action" value="create">
        <div class="gf-two"><label>Department Name *<input name="name" maxlength="120" required placeholder="Information Technology"></label><label>Department Code *<input name="department_code" maxlength="24" required placeholder="IT"></label></div><label>Description<textarea name="description" rows="3" maxlength="3000" placeholder="Department responsibilities"></textarea></label><label>Job Titles <small>(one per line)</small><textarea name="positions" rows="5" placeholder="Software Developer&#10;IT Support Specialist&#10;Systems Administrator"></textarea></label>
        <p class="gf-hint">Employee count is calculated automatically after HR registers employees. Department Head may be assigned separately.</p>
        <div class="gf-form-actions"><button type="button" class="gf-btn gf-btn-soft" data-close>Cancel</button><button type="submit" class="gf-btn gf-btn-main">Save Department</button></div>
      </form>
      <form method="post" action="?page=departments" id="gf-position" class="gf-form" hidden><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="department_action" value="position"><input type="hidden" name="department_id" id="gf-position-dept"><label>Job Title *<input name="name" required maxlength="120" placeholder="e.g. Accounting Assistant"></label>
        <div class="gf-form-actions"><button type="button" class="gf-btn gf-btn-soft" data-close>Cancel</button><button class="gf-btn gf-btn-main">Add Job Title</button></div>
      </form>
      <form method="post" action="?page=departments" enctype="multipart/form-data" id="gf-excel" class="gf-form" hidden><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="department_action" value="preview">
        <p>Use the four-column Excel template. One row represents one job title; repeat department details for additional titles.</p><a class="gf-template" href="assets/templates/growflow-departments-template.xlsx" download><i data-lucide="download"></i> Download Excel Template</a><label>Select Excel Workbook (.xlsx) *<input type="file" name="excel_file" accept=".xlsx" required></label>
        <p class="gf-hint">Maximum 2 MB and 500 data rows. Preview and validation happen before saving.</p>
        <div class="gf-form-actions"><button type="button" class="gf-btn gf-btn-soft" data-close>Cancel</button><button type="submit" class="gf-btn gf-btn-main">Preview Import</button></div>
      </form>
    </section>
  </div>
  <!-- Read-only list of job titles for the selected department -->
  <div class="gf-modal-backdrop" id="gf-titles-modal" hidden>
    <section class="gf-modal gf-titles-dialog" role="dialog" aria-modal="true" aria-labelledby="gf-titles-title" tabindex="-1">
      <div class="gf-modal-header">
        <div><span class="gf-eyebrow">DEPARTMENT JOB TITLES</span>
          <h2 id="gf-titles-title">Job Titles</h2>
        </div>
        <button type="button" data-close-titles aria-label="Close job titles"><i data-lucide="x"></i></button>
      </div>
      <p class="gf-titles-subtitle" id="gf-titles-summary"></p>
      <ul class="gf-titles-list" id="gf-titles-list"></ul>
      <div class="gf-form-actions"><button type="button" class="gf-btn gf-btn-soft" data-close-titles>Close</button></div>
    </section>
  </div>
  <?php if (is_array($preview)): ?>
    <section class="gf-panel gf-preview" aria-labelledby="gf-import-title">
      <div class="gf-panel-header">
        <div>
          <span class="gf-eyebrow">PREVIEW ONLY · NOT SAVED</span>
          <h2 id="gf-import-title">Review Excel Import</h2>
          <p><?= count($preview) ?> validated rows. Confirm to save, or cancel to discard this preview.</p>
        </div>
      </div>
      <div class="gf-table-wrap">
        <table>
          <thead>
            <tr>
              <th>ROW</th>
              <th>DEPARTMENT CODE</th>
              <th>DEPARTMENT</th>
              <th>JOB TITLE</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (array_slice($preview, 0, 50) as $r): ?>
              <tr>
                <td><?= (int)$r['row'] ?></td>
                <td><?= e($r['code']) ?></td>
                <td><?= e($r['name']) ?></td>
                <td><?= e($r['position']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if (count($preview) > 50): ?>
        <p class="gf-hint" style="padding:0 20px">Showing 50 of <?= count($preview) ?> rows.</p>
      <?php endif; ?>
      <div class="gf-import-actions">
        <form method="post" action="?page=departments">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="department_action" value="cancel_import">
          <button type="submit" class="gf-btn gf-btn-soft"><i data-lucide="x"></i> Cancel Import</button>
        </form>
        <form method="post" action="?page=departments" onsubmit="return confirm('Save these validated departments and job titles to the database?');">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="department_action" value="import">
          <button type="submit" class="gf-btn gf-btn-main"><i data-lucide="save"></i> Save to Database</button>
        </form>
      </div>
    </section>
  <?php endif; ?>
</main>
<script defer src="assets/js/departments.js"></script>