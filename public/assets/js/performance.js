document.addEventListener('DOMContentLoaded', () => {

  const previewDialog =
    document.getElementById(
      'performancePreviewDialog'
    );

  const openPreviewButton =
    document.getElementById(
      'openPerformancePreview'
    );

  const closePreviewButton =
    document.getElementById(
      'closePerformancePreview'
    );

  const backPreviewButton =
    document.getElementById(
      'backFromPerformancePreview'
    );

  const setPreviewModalState =
    isOpen => {
      document.documentElement
        .classList.toggle(
          'performance-modal-open',
          isOpen
        );
    };

  const openPreviewDialog = () => {
    if (!previewDialog) {
      return;
    }

    if (!previewDialog.open) {
      previewDialog.showModal();
    }

    setPreviewModalState(true);
  };

  const closePreviewDialog = () => {
    if (
      previewDialog
      && previewDialog.open
    ) {
      previewDialog.close();
    }

    setPreviewModalState(false);
  };

  if (previewDialog) {
    const params =
      new URLSearchParams(
        window.location.search
      );

    if (params.get('preview') === '1') {
      window.requestAnimationFrame(
        openPreviewDialog
      );
    }

    openPreviewButton?.addEventListener(
      'click',
      openPreviewDialog
    );

    closePreviewButton?.addEventListener(
      'click',
      closePreviewDialog
    );

    backPreviewButton?.addEventListener(
      'click',
      closePreviewDialog
    );

    previewDialog.addEventListener(
      'cancel',
      event => {
        event.preventDefault();
        closePreviewDialog();
      }
    );

    previewDialog.addEventListener(
      'click',
      event => {
        if (event.target === previewDialog) {
          closePreviewDialog();
        }
      }
    );

    previewDialog.addEventListener(
      'close',
      () => {
        setPreviewModalState(false);
      }
    );
  }

  const departmentSelect =
    document.getElementById('performanceDepartment');

  const employeeSearch =
    document.getElementById('performanceEmployeeSearch');

  const employeeSelect =
    document.getElementById('performanceEmployee');

  const employeeResult =
    document.getElementById('performanceEmployeeResult');

  if (
    departmentSelect
    && employeeSearch
    && employeeSelect
  ) {
    const employeeRecords = Array.from(
      employeeSelect.querySelectorAll(
        'option[data-department-id]'
      )
    ).map(option => ({
      value: option.value,
      departmentId:
        option.dataset.departmentId || '',
      code:
        option.dataset.employeeCode || '',
      name:
        option.dataset.employeeName || '',
      jobTitle:
        option.dataset.jobTitle || '',
      search:
        (option.dataset.search || '')
          .toLowerCase(),
      label: option.textContent
        .replace(/\s+/g, ' ')
        .trim()
    }));

    const renderEmployees = () => {
      const departmentId =
        departmentSelect.value;

      const query =
        employeeSearch.value
          .trim()
          .toLowerCase();

      const previousValue =
        employeeSelect.value;

      employeeSelect.innerHTML = '';

      const placeholder =
        document.createElement('option');

      placeholder.value = '';

      if (!departmentId) {
        placeholder.textContent =
          'Select a department first';

        employeeSelect.appendChild(
          placeholder
        );

        employeeSelect.disabled = true;
        employeeSearch.disabled = true;
        employeeSearch.value = '';

        if (employeeResult) {
          employeeResult.textContent =
            'Choose a department to view employees.';
        }

        return;
      }

      employeeSearch.disabled = false;

      const departmentEmployees =
        employeeRecords.filter(
          employee =>
            employee.departmentId
            === departmentId
        );

      const matches =
        departmentEmployees.filter(
          employee =>
            query === ''
            || employee.search.includes(query)
        );

      placeholder.textContent =
        matches.length
          ? 'Select employee'
          : 'No matching employees';

      employeeSelect.appendChild(
        placeholder
      );

      matches.forEach(employee => {
        const option =
          document.createElement('option');

        option.value = employee.value;
        option.textContent =
          employee.label;

        employeeSelect.appendChild(
          option
        );
      });

      employeeSelect.disabled =
        matches.length === 0;

      if (
        previousValue
        && matches.some(
          employee =>
            employee.value === previousValue
        )
      ) {
        employeeSelect.value =
          previousValue;
      }

      if (employeeResult) {
        if (query !== '') {
          employeeResult.textContent =
            `${matches.length} match${matches.length === 1 ? '' : 'es'} from ${departmentEmployees.length} employee${departmentEmployees.length === 1 ? '' : 's'} in this department.`;
        } else {
          employeeResult.textContent =
            `${departmentEmployees.length} employee${departmentEmployees.length === 1 ? '' : 's'} available in this department.`;
        }
      }
    };

    departmentSelect.addEventListener(
      'change',
      () => {
        employeeSearch.value = '';
        employeeSelect.value = '';
        renderEmployees();

        if (departmentSelect.value) {
          employeeSearch.focus();
        }
      }
    );

    employeeSearch.addEventListener(
      'input',
      renderEmployees
    );

    renderEmployees();
  }

  const fileInput = document.getElementById('performanceExcel');
  const fileName = document.getElementById('performanceFileName');

  if (fileInput && fileName) {
    fileInput.addEventListener('change', () => {
      const file = fileInput.files && fileInput.files[0];

      fileName.textContent = file
        ? `${file.name} · ${(file.size / 1024).toFixed(1)} KB`
        : 'Maximum 2 MB · up to 1000 rows';
    });
  }

  const search = document.getElementById('performanceHistorySearch');
  const rows = Array.from(
    document.querySelectorAll('[data-performance-history]')
  );

  if (search && rows.length) {
    search.addEventListener('input', () => {
      const query = search.value.trim().toLowerCase();

      rows.forEach(row => {
        const haystack = (
          row.dataset.search
          || ''
        ).toLowerCase();

        row.hidden = query !== ''
          && !haystack.includes(query);
      });
    });
  }

  const manualForm = document.getElementById('manualPerformanceForm');
  const scoreInput = document.getElementById('performanceScore');

  if (manualForm && scoreInput) {
    manualForm.addEventListener('reset', () => {
      window.setTimeout(() => {
        if (
          departmentSelect
          && employeeSearch
          && employeeSelect
        ) {
          departmentSelect.value = '';
          employeeSearch.value = '';

          employeeSelect.innerHTML =
            '<option value="">Select a department first</option>';

          employeeSearch.disabled = true;
          employeeSelect.disabled = true;

          if (employeeResult) {
            employeeResult.textContent =
              'Choose a department to view employees.';
          }
        }
      }, 0);
    });

    manualForm.addEventListener('submit', event => {
      const score = Number(scoreInput.value);

      if (
        !Number.isFinite(score)
        || score < 0
        || score > 100
      ) {
        event.preventDefault();
        scoreInput.focus();
      }
    });
  }
});
