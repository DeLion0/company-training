document.addEventListener('DOMContentLoaded', () => {
  if (window.lucide) window.lucide.createIcons();

  const sidebar = document.getElementById('employeeSidebar');
  const overlay = document.getElementById('employeeOverlay');
  const menuButton = document.getElementById('employeeMenuButton');
  const today = document.getElementById('employeeToday');
  const previewSelect = document.getElementById('employeePreviewSelect');
  const toast = document.getElementById('employeeToast');
  let toastTimer;

  const showToast = message => {
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('show'), 3400);
  };

  const closeSidebar = () => {
    sidebar?.classList.remove('open');
    if (overlay) overlay.hidden = true;
  };

  menuButton?.addEventListener('click', () => {
    if (!sidebar) return;
    const opened = sidebar.classList.toggle('open');
    if (overlay) overlay.hidden = !opened;
  });

  overlay?.addEventListener('click', closeSidebar);
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') closeSidebar();
  });

  if (today) {
    today.textContent = new Intl.DateTimeFormat('en-US', {
      month: 'long',
      day: 'numeric',
      year: 'numeric'
    }).format(new Date());
  }

  previewSelect?.addEventListener('change', () => {
    const url = new URL(window.location.href);
    url.searchParams.set('employee_id', previewSelect.value);
    window.location.href = url.toString();
  });

  document.querySelectorAll('.employee-nav-link.is-disabled').forEach(link => {
    link.addEventListener('click', event => {
      event.preventDefault();
      showToast('Competencies will be connected after the employee learning flow is complete.');
    });
  });

  const search = document.getElementById('employeeTrainingSearch');
  const statusFilter = document.getElementById('employeeTrainingStatusFilter');
  const trainingCards = Array.from(document.querySelectorAll('#employeeAvailableGrid [data-search]'));
  const noMatch = document.getElementById('employeeTrainingNoMatch');

  const filterTraining = () => {
    if (!trainingCards.length) return;

    const query = (search?.value || '').trim().toLowerCase();
    const status = statusFilter?.value || '';
    let visible = 0;

    trainingCards.forEach(card => {
      const matchesSearch = query === '' || (card.dataset.search || '').includes(query);
      const matchesStatus = status === '' || card.dataset.status === status;
      const show = matchesSearch && matchesStatus;
      card.hidden = !show;
      if (show) visible++;
    });

    if (noMatch) noMatch.hidden = visible !== 0;
  };

  search?.addEventListener('input', filterTraining);
  statusFilter?.addEventListener('change', filterTraining);

  const tabs = Array.from(document.querySelectorAll('[data-training-tab]'));
  const panels = Array.from(document.querySelectorAll('[data-training-panel]'));

  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      const target = tab.dataset.trainingTab;

      tabs.forEach(item => {
        const active = item === tab;
        item.classList.toggle('active', active);
        item.setAttribute('aria-selected', active ? 'true' : 'false');
      });

      panels.forEach(panel => {
        panel.hidden = panel.dataset.trainingPanel !== target;
      });
    });
  });

  document.querySelectorAll('[data-ui-only]').forEach(button => {
    button.addEventListener('click', () => {
      const action = button.dataset.uiOnly;

      if (action === 'join') {
        showToast('Join Training is not connected yet. We will enable this in the next employee functionality step.');
      } else if (action === 'details') {
        showToast('Training Details is not connected yet. We will enable it with the employee training flow.');
      } else if (action === 'security') {
        showToast('Account activation is enabled. Regular password change will be connected next under Account & Security.');
      } else {
        showToast('This action is UI-only for now.');
      }
    });
  });

  document.getElementById('employeeNotificationButton')?.addEventListener('click', () => {
    showToast('Employee notifications will use priority training and upcoming sessions in a later phase.');
  });
});
