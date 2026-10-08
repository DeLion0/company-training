document.addEventListener('DOMContentLoaded', () => {
  const search = document.getElementById('hrEmployeeSearch');
  const department = document.getElementById('hrDepartmentFilter');
  const rows = Array.from(document.querySelectorAll('#hrEmployeeRows tr'));
  const empty = document.getElementById('hrEmptyResults');
  const count = document.getElementById('hrVisibleCount');

  const updateDirectory = () => {
    if (!search || !count) return;
    const term = search.value.trim().toLocaleLowerCase();
    const dept = department?.value || '';
    let visible = 0;
    rows.forEach(row => {
      const matches = (!term || (row.dataset.search || '').includes(term)) && (!dept || row.dataset.department === dept);
      row.hidden = !matches;
      if (matches) visible++;
    });
    count.textContent = visible.toString();
    if (empty) {
      empty.hidden = visible > 0;
      if (rows.length) empty.textContent = 'No employees match your search.';
    }
  };

  search?.addEventListener('input', updateDirectory);
  department?.addEventListener('change', updateDirectory);

  const performanceFilter = document.getElementById('hrPerformanceDepartmentFilter');
  const performanceViews = Array.from(document.querySelectorAll('.hr-dept-performance-view'));

  const updateDepartmentPerformance = () => {
    if (!performanceFilter || !performanceViews.length) return;
    const selected = performanceFilter.value;
    performanceViews.forEach(view => {
      view.hidden = view.dataset.performanceDepartment !== selected;
    });
  };

  performanceFilter?.addEventListener('change', updateDepartmentPerformance);
  updateDepartmentPerformance();

  const payloadNode = document.getElementById('hrPerformanceData');
  const dialog = document.getElementById('hrEmployeePerformanceDialog');
  if (!payloadNode || !dialog) return;

  let payload = { months: [], employees: [] };
  try {
    payload = JSON.parse(payloadNode.textContent || '{}');
  } catch {
    return;
  }

  const employees = new Map((payload.employees || []).map(employee => [String(employee.id), employee]));
  const months = Array.isArray(payload.months) ? payload.months : [];
  const name = document.getElementById('hrDialogEmployeeName');
  const meta = document.getElementById('hrDialogEmployeeMeta');
  const latest = document.getElementById('hrDialogLatest');
  const date = document.getElementById('hrDialogDate');
  const bars = document.getElementById('hrEmployeeMonthlyBars');
  const close = document.getElementById('hrDialogClose');

  const escapeHTML = value => String(value ?? '').replace(/[&<>"']/g, character => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  })[character]);

  const formatDate = value => {
    if (!value) return 'No review yet';
    const parts = String(value).split('-').map(Number);
    if (parts.length !== 3 || parts.some(Number.isNaN)) return String(value);
    return new Date(parts[0], parts[1] - 1, parts[2], 12).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  };

  const renderEmployee = employee => {
    if (!employee || !bars) return;
    name.textContent = employee.name || 'Employee Performance';
    meta.textContent = [employee.employee_code, employee.department, employee.job_title].filter(Boolean).join(' · ');
    latest.textContent = employee.latest_score == null ? 'Not evaluated' : `${Number(employee.latest_score).toFixed(1)}%`;
    date.textContent = formatDate(employee.latest_review_date);

    bars.innerHTML = months.map(month => {
      const raw = employee.monthly?.[month.key];
      const hasScore = raw !== null && raw !== undefined && raw !== '';
      const score = hasScore ? Math.max(0, Math.min(100, Number(raw))) : null;
      return `
        <div class="hr-employee-month ${hasScore ? '' : 'no-review'}">
          <div class="hr-employee-month-bar-area" title="${escapeHTML(month.long_label)} · ${hasScore ? `${score.toFixed(1)}%` : 'No review'}">
            ${hasScore
              ? `<span class="hr-employee-score">${score.toFixed(1)}%</span><span class="hr-employee-bar" style="height:${Math.max(2, score)}%"></span>`
              : '<span class="hr-employee-score">No review</span><span class="hr-employee-missing"></span>'}
          </div>
          <strong>${escapeHTML(month.label)}</strong>
        </div>`;
    }).join('');

    if (window.lucide) window.lucide.createIcons();
  };

  document.querySelectorAll('.hr-employee-open').forEach(button => {
    button.addEventListener('click', () => {
      const employee = employees.get(button.dataset.employeeId || '');
      if (!employee) return;
      renderEmployee(employee);
      dialog.showModal();
    });
  });

  close?.addEventListener('click', () => dialog.close());
  dialog.addEventListener('click', event => {
    if (event.target === dialog) dialog.close();
  });
});
