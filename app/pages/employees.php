<?php
// HR-only route is enforced by public/index.php before this file is included.
require_once __DIR__.'/../core/employees-data.php';
$hrUser=current_user();
$companyId=hr_company_id((int)$hrUser['id']);
$departments=hr_departments($companyId);
$managers=hr_managers($companyId);
$employees=hr_employees($companyId);
$error=$_SESSION['employee_error']??'';unset($_SESSION['employee_error']);
$success=$_SESSION['employee_created']??null;unset($_SESSION['employee_created']);
$byDepartment=[];
foreach($employees as $emp){$n=$emp['department_name'];$byDepartment[$n]=($byDepartment[$n]??0)+1;}
?>
<link rel="stylesheet" href="assets/css/employees.css">
<main class="content hr-employees">
  <div class="emp-title-row"><div><div class="emp-eyebrow">PEOPLE MANAGEMENT / HR WORKSPACE</div><h1>Employees</h1><p>Register employees, assign department managers, and manage employee accounts.</p></div><button type="button" class="emp-primary" id="open-employee-form"><i data-lucide="user-round-plus"></i> Add Employee</button></div>
  <?php if($error): ?><div class="emp-alert error" role="alert"><?=e((string)$error)?></div><?php endif; ?>
  <?php if($success && is_array($success)): ?>
    <section class="emp-alert success" aria-label="New account credentials"><h2><i data-lucide="check-circle-2"></i> Employee account created</h2><p>Account is <strong>inactive</strong> until the secure activation process is implemented. Keep these credentials private. This handover is shown only once.</p><div class="emp-credentials"><div><small>Work email</small><strong><?=e($success['email'])?></strong></div><div><small>Temporary password</small><strong><?=e($success['temporary_password'])?></strong></div></div><p class="emp-warning">For local testing only. Never send the password and email together over ordinary email. Employee login is not yet enabled.</p></section>
  <?php endif; ?>
  <section class="emp-kpis" aria-label="Employee statistics">
    <div class="emp-kpi"><span class="emp-kpi-icon"><i data-lucide="users-round"></i></span><small>Total Employees</small><strong><?=count($employees)?></strong></div>
    <div class="emp-kpi"><span class="emp-kpi-icon"><i data-lucide="building-2"></i></span><small>Departments</small><strong><?=count($departments)?></strong></div>
    <div class="emp-kpi"><span class="emp-kpi-icon"><i data-lucide="user-check"></i></span><small>Active Employees</small><strong><?=count(array_filter($employees,fn($x)=>$x['employment_status']==='active'))?></strong></div>
    <div class="emp-kpi"><span class="emp-kpi-icon"><i data-lucide="key-round"></i></span><small>Accounts Pending Activation</small><strong><?=count(array_filter($employees,fn($x)=>$x['account_status']==='inactive'))?></strong></div>
  </section>
  <section class="emp-panel"><div class="emp-panel-head"><div><h2>Employee Directory</h2><p>Company employees, their department and assigned manager.</p></div><div class="emp-filters"><label class="emp-search"><i data-lucide="search"></i><input id="employee-search" placeholder="Search name, ID, email..." aria-label="Search employees"></label><select id="employee-department-filter" aria-label="Filter department"><option value="">All Departments</option><?php foreach($departments as $d): ?><option value="<?=e($d['name'])?>"><?=e($d['name'])?></option><?php endforeach; ?></select></div></div>
  <div class="emp-table-wrap"><table class="emp-table"><thead><tr><th>EMPLOYEE</th><th>EMPLOYEE ID</th><th>DEPARTMENT</th><th>POSITION</th><th>MANAGER</th><th>ACCOUNT</th></tr></thead><tbody id="employee-table-body">
  <?php foreach($employees as $emp): ?><tr data-search="<?=e(strtolower($emp['full_name'].' '.$emp['employee_code'].' '.$emp['email']))?>" data-department="<?=e($emp['department_name'])?>"><td><span class="emp-person"><span class="emp-avatar"><?=e(strtoupper(substr($emp['full_name'],0,1)))?></span><span><strong><?=e($emp['full_name'])?></strong><small><?=e($emp['email']??'No login account')?></small></span></span></td><td><?=e($emp['employee_code'])?></td><td><?=e($emp['department_name'])?></td><td><?=e($emp['job_title'])?></td><td><?=e($emp['manager_name']??'Not assigned')?></td><td><span class="emp-pill <?=($emp['account_status']==='active'?'enabled':'pending')?>"><?=e($emp['account_status']??'Not Created')?></span></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <p class="emp-empty" id="emp-empty" <?=count($employees)>0?'hidden':''?>>No employees found. Click <strong>Add Employee</strong> to register the first employee.</p>
  </section>
  <div class="emp-modal-backdrop" id="employee-modal" hidden><section class="emp-modal" role="dialog" aria-modal="true" aria-labelledby="emp-modal-title"><div class="emp-modal-title"><div><h2 id="emp-modal-title">Register New Employee</h2><p>HR employee registration and account creation</p></div><button type="button" class="emp-close" id="close-employee-form" aria-label="Close"><i data-lucide="x"></i></button></div>
  <form id="employee-create-form" method="post" action="?page=employees"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="employee_action" value="create"><div class="emp-form-grid">
    <label>First Name <span>*</span><input name="first_name" id="emp-first" required maxlength="80" autocomplete="off" placeholder="e.g. Juan"></label>
    <label>Last Name <span>*</span><input name="last_name" id="emp-last" required maxlength="80" autocomplete="off" placeholder="e.g. Dela Cruz"></label>
    <label>Employee ID <span>*</span><input name="employee_code" required maxlength="40" placeholder="e.g. EMP-0001"></label>
    <label>Job Position <span>*</span><input name="job_title" required maxlength="120" placeholder="e.g. Accounting Staff"></label>
    <label>Department <span>*</span><select name="department_id" id="emp-department" required><option value="">Select department</option><?php foreach($departments as $d): ?><option value="<?=$d['id']?>"><?=e($d['name'])?></option><?php endforeach; ?></select></label>
    <label>Department Manager <span>*</span><select name="manager_user_id" id="emp-manager" required><option value="">Select manager</option><?php foreach($managers as $m): ?><option data-department="<?=$m['department_id']?>" value="<?=$m['id']?>"><?=e($m['full_name'])?></option><?php endforeach; ?></select></label>
    <label>Hire Date <input type="date" name="joined_at"></label>
    <label>Work Email <small>Generated automatically; verified when saved</small><input id="emp-email-preview" readonly placeholder="firstname.lastname@company.com"></label>
  </div><div class="emp-form-note"><i data-lucide="shield-check"></i><span>A temporary password will be created automatically. Account remains inactive until employee activation is implemented.</span></div><div class="emp-form-actions"><button type="button" class="emp-secondary" id="cancel-employee-form">Cancel</button><button type="submit" class="emp-primary"><i data-lucide="user-plus"></i> Create Employee Account</button></div></form></section></div>
</main>
<script>window.GROWFLOW_EMPLOYEE_DOMAIN = <?=json_encode(employee_domain(),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;</script>
<script defer src="assets/js/employees.js"></script>
