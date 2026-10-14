document.addEventListener(
  'DOMContentLoaded',
  () => {
    const modal =
      document.getElementById(
        'managerModal'
      );

    const openButton =
      document.getElementById(
        'openManagerForm'
      );

    const closeButton =
      document.getElementById(
        'closeManagerForm'
      );

    const cancelButton =
      document.getElementById(
        'cancelManagerForm'
      );

    const search =
      document.getElementById(
        'managerSearch'
      );

    const departmentFilter =
      document.getElementById(
        'managerDepartmentFilter'
      );

    const rows =
      Array.from(
        document.querySelectorAll(
          '#managerRows tr'
        )
      );

    const empty =
      document.getElementById(
        'managerEmpty'
      );

    const count =
      document.getElementById(
        'managerVisibleCount'
      );

    const openModal = () => {
      if (!modal) return;

      modal.hidden = false;

      document.documentElement
        .style
        .overflow = 'hidden';

      window.setTimeout(() => {
        modal.querySelector(
          'input[name="first_name"]'
        )?.focus();
      }, 0);
    };

    const closeModal = () => {
      if (!modal) return;

      modal.hidden = true;

      document.documentElement
        .style
        .overflow = '';
    };

    openButton?.addEventListener(
      'click',
      openModal
    );

    closeButton?.addEventListener(
      'click',
      closeModal
    );

    cancelButton?.addEventListener(
      'click',
      closeModal
    );

    modal?.addEventListener(
      'click',
      event => {
        if (event.target === modal) {
          closeModal();
        }
      }
    );

    document.addEventListener(
      'keydown',
      event => {
        if (
          event.key === 'Escape'
          && modal
          && !modal.hidden
        ) {
          closeModal();
        }
      }
    );

    const applyFilters = () => {
      const query =
        (search?.value || '')
          .trim()
          .toLowerCase();

      const departmentId =
        departmentFilter?.value
        || '';

      let visible = 0;

      rows.forEach(row => {
        const matchesSearch =
          query === ''
          || (
            row.dataset.search
            || ''
          ).includes(query);

        const matchesDepartment =
          departmentId === ''
          || row.dataset.department
            === departmentId;

        const show =
          matchesSearch
          && matchesDepartment;

        row.hidden = !show;

        if (show) {
          visible++;
        }
      });

      if (count) {
        count.textContent =
          String(visible);
      }

      if (empty) {
        empty.hidden =
          visible !== 0;
      }
    };

    search?.addEventListener(
      'input',
      applyFilters
    );

    departmentFilter?.addEventListener(
      'change',
      applyFilters
    );
  }
);
