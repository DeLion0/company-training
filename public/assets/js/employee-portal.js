document.addEventListener('DOMContentLoaded', () => {
  if (window.lucide) window.lucide.createIcons();

  const isPreview = document.body.dataset.employeePreview === '1';
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
    toastTimer = setTimeout(() => toast.classList.remove('show'), 3600);
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

  if (today) {
    today.textContent = new Intl.DateTimeFormat('en-US', {
      month: 'long', day: 'numeric', year: 'numeric'
    }).format(new Date());
  }

  previewSelect?.addEventListener('change', () => {
    const url = new URL(window.location.href);
    url.searchParams.set('employee_id', previewSelect.value);
    window.location.href = url.toString();
  });

  // Available Training search/filter.
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

  // My Training tabs.
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

  // Training details modal.
  let details = {};
  const detailsData = document.getElementById('employeeTrainingDetailsData');
  try {
    if (detailsData) details = JSON.parse(detailsData.textContent || '{}');
  } catch {
    details = {};
  }

  const dialog = document.getElementById('employeeTrainingDialog');
  const title = document.getElementById('employeeTrainingDialogTitle');
  const purpose = document.getElementById('employeeTrainingDialogPurpose');
  const period = document.getElementById('employeeTrainingDialogPeriod');
  const instructor = document.getElementById('employeeTrainingDialogInstructor');
  const venue = document.getElementById('employeeTrainingDialogVenue');
  const capacity = document.getElementById('employeeTrainingDialogCapacity');
  const objectives = document.getElementById('employeeTrainingDialogObjectives');

  const formatDate = value => {
    if (!value) return 'Not specified';
    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat('en-US', {
      month: 'short', day: 'numeric', year: 'numeric'
    }).format(date);
  };

  document.querySelectorAll('[data-training-details]').forEach(button => {
    button.addEventListener('click', () => {
      const item = details[String(button.dataset.trainingDetails || '')];
      if (!item || !dialog) {
        showToast('Training details are unavailable.');
        return;
      }

      if (title) title.textContent = item.title || 'Training Details';
      if (purpose) purpose.textContent = item.purpose || 'No purpose provided.';
      if (period) period.textContent = `${formatDate(item.range_start)} – ${formatDate(item.range_end)}`;
      if (instructor) instructor.textContent = item.instructors || 'Not specified';
      if (venue) venue.textContent = item.venue || 'Not specified';
      if (capacity) capacity.textContent = `${Number(item.joined_count || 0)} / ${Number(item.capacity || 0)} joined`;
      if (objectives) objectives.textContent = item.objectives || 'No learning objectives provided.';

      dialog.showModal();
      if (window.lucide) window.lucide.createIcons();
    });
  });

  document.querySelectorAll('[data-employee-dialog-close]').forEach(button => {
    button.addEventListener('click', () => dialog?.close());
  });

  dialog?.addEventListener('click', event => {
    if (event.target === dialog) dialog.close();
  });

  // Real Join Training action.
  const joinForm = document.getElementById('employeeJoinTrainingForm');
  const joinProgramId = document.getElementById('employeeJoinTrainingProgramId');

  document.querySelectorAll('[data-join-training]').forEach(button => {
    button.addEventListener('click', () => {
      if (button.disabled) return;

      if (isPreview) {
        showToast('HR Employee Preview is read-only. Sign in as the employee to join training.');
        return;
      }

      const programId = String(button.dataset.joinTraining || '');
      if (!programId || !joinForm || !joinProgramId) {
        showToast('Unable to start the training join action.');
        return;
      }

      if (!window.confirm('Join this training program? Your enrollment will immediately use one available seat.')) {
        return;
      }

      joinProgramId.value = programId;
      joinForm.submit();
    });
  });

  // Notifications dropdown.
  const notificationButton = document.getElementById('employeeNotificationButton');
  const notificationPanel = document.getElementById('employeeNotificationPanel');

  const closeNotifications = () => {
    if (!notificationPanel) return;
    notificationPanel.hidden = true;
    notificationButton?.setAttribute('aria-expanded', 'false');
  };

  notificationButton?.addEventListener('click', event => {
    event.stopPropagation();
    if (!notificationPanel) return;
    const opening = notificationPanel.hidden;
    notificationPanel.hidden = !opening;
    notificationButton.setAttribute('aria-expanded', opening ? 'true' : 'false');
  });

  notificationPanel?.addEventListener('click', event => event.stopPropagation());
  document.addEventListener('click', closeNotifications);

  document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    closeSidebar();
    closeNotifications();
    if (dialog?.open) dialog.close();
  });

  document.querySelectorAll('.employee-nav-link.is-disabled').forEach(link => {
    link.addEventListener('click', event => {
      event.preventDefault();
      showToast('This module is not enabled yet.');
    });
  });
});
