document.addEventListener('DOMContentLoaded', () => {
  const search = document.getElementById('avSearch');

  if (search) {
    search.addEventListener('input', () => {
      let visible = 0;
      document.querySelectorAll('.av-program').forEach((card) => {
        const match = card.dataset.filter.includes(search.value.trim().toLowerCase());
        card.hidden = !match;
        if (match) visible += 1;
      });

      const noMatch = document.getElementById('avNoMatch');
      if (noMatch) {
        noMatch.hidden = visible !== 0 || document.querySelectorAll('.av-program').length === 0;
      }
    });
  }

  const lockBody = () => {
    document.body.classList.add('av-modal-open');
  };

  const unlockBody = () => {
    if (!document.querySelector('.av-dialog[open]')) {
      document.body.classList.remove('av-modal-open');
    }
  };

  document.querySelectorAll('.av-open').forEach((button) => {
    button.addEventListener('click', () => {
      const dialog = document.getElementById(`avDialog${button.dataset.id}`);
      if (!dialog) return;
      lockBody();
      dialog.showModal();
    });
  });

  document.querySelectorAll('.av-dialog').forEach((dialog) => {
    dialog.querySelectorAll('.av-close').forEach((button) => {
      button.addEventListener('click', () => dialog.close());
    });

    dialog.addEventListener('click', (event) => {
      if (event.target === dialog) dialog.close();
    });

    dialog.addEventListener('close', unlockBody);

    dialog.addEventListener('cancel', () => {
      window.setTimeout(unlockBody, 0);
    });
  });

  // Department-first recommendation filtering.
  document.querySelectorAll('[data-recommend-form]').forEach((form) => {
    const department = form.querySelector('[data-recommend-department]');
    const employee = form.querySelector('[data-recommend-employee]');
    const button = form.querySelector('button[type="submit"]');

    if (!department || !employee) return;

    const updateEmployees = () => {
      const departmentId = department.value;
      let visibleOptions = 0;

      Array.from(employee.options).forEach((option, index) => {
        if (index === 0) return;
        const matches = departmentId !== '' && option.dataset.department === departmentId;
        option.hidden = !matches;
        option.disabled = !matches;
        if (matches) visibleOptions += 1;
      });

      employee.value = '';
      employee.disabled = departmentId === '' || visibleOptions === 0;
      if (button) button.disabled = employee.disabled || form.dataset.full === '1';
    };

    department.addEventListener('change', updateEmployees);
    employee.addEventListener('change', () => {
      if (button) {
        button.disabled = employee.value === '' || form.dataset.full === '1';
      }
    });

    updateEmployees();
  });
});
