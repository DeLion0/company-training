document.addEventListener('DOMContentLoaded', () => {
  const $ = id => document.getElementById(id);
  const form = $('trainingForm');
  const dialog = $('reviewDialog');

  if (!form || !dialog) {
    return;
  }

  const fields = [
    'title',
    'purpose',
    'objectives',
    'startDate',
    'endDate',
    'weekday',
    'meetingCount',
    'startTime',
    'endTime',
    'instructors',
    'venue',
    'capacity'
  ];

  const weekdays = [
    'Sunday',
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday'
  ];

  const departmentInputs = Array.from(
    form.querySelectorAll(
      'input[name="department_ids[]"]'
    )
  );

  const localDate = s => {
    const [y, m, d] = s
      .split('-')
      .map(Number);

    return new Date(
      y,
      m - 1,
      d,
      12
    );
  };

  const iso = d => [
    d.getFullYear(),
    String(d.getMonth() + 1)
      .padStart(2, '0'),
    String(d.getDate())
      .padStart(2, '0')
  ].join('-');

  const pretty = s =>
    localDate(s).toLocaleDateString(
      'en-US',
      {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
      }
    );

  const clock = t => {
    if (!/^\d{2}:\d{2}$/.test(t)) {
      return t;
    }

    let [h, m] = t
      .split(':')
      .map(Number);

    return `${h % 12 || 12}:${String(m).padStart(2, '0')} ${h >= 12 ? 'PM' : 'AM'}`;
  };

  const escapeHTML = s =>
    String(s).replace(
      /[&<>"']/g,
      c => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
      })[c]
    );

  let sessions = [];

  function selectedDepartments() {
    return departmentInputs
      .filter(input => input.checked)
      .map(input => ({
        id: input.value,
        name:
          input.dataset.departmentName
          || 'Department',
        priorityCount:
          Number(
            input.dataset.priorityCount
            || 0
          )
      }));
  }

  function renderDepartmentSelection() {
    const selected =
      selectedDepartments();

    departmentInputs.forEach(input => {
      const card =
        input.closest(
          '[data-department-card]'
        );

      if (card) {
        card.classList.toggle(
          'is-selected',
          input.checked
        );
      }
    });

    const countRoot =
      $('departmentSelectionCount');

    if (countRoot) {
      countRoot.textContent =
        `${selected.length} department${selected.length === 1 ? '' : 's'} selected`;
    }

    const priorityCount =
      selected.reduce(
        (sum, department) =>
          sum
          + department.priorityCount,
        0
      );

    const estimateRoot =
      $('priorityEstimate');

    if (estimateRoot) {
      estimateRoot.textContent =
        `${priorityCount} employee${priorityCount === 1 ? '' : 's'}`;
    }

    const summaryRoot =
      $('summaryDepartments');

    if (summaryRoot) {
      summaryRoot.textContent =
        selected.length
          ? `${selected.length} selected`
          : 'None selected';
    }

    return {
      selected,
      priorityCount
    };
  }

  function calculate() {
    const start = $('startDate').value;
    const end = $('endDate').value;
    const day = Number(
      $('weekday').value
    );
    const meetingCount = Number(
      $('meetingCount').value
    );
    const startTime =
      $('startTime').value;
    const endTime =
      $('endTime').value;

    if (!start || !end) {
      return {
        error:
          'Select the start and end dates to generate sessions.'
      };
    }

    if (
      localDate(end)
      < localDate(start)
    ) {
      return {
        error:
          'End date must be on or after the start date.'
      };
    }

    const totalDays =
      Math.round(
        (
          localDate(end)
          - localDate(start)
        )
        / 86400000
      );

    if (totalDays > 366) {
      return {
        error:
          'Select a date range no longer than 366 days.'
      };
    }

    if (
      !Number.isInteger(meetingCount)
      || meetingCount < 1
      || meetingCount > 100
    ) {
      return {
        error:
          'Number of meetings must be between 1 and 100.'
      };
    }

    if (
      !startTime
      || !endTime
      || startTime >= endTime
    ) {
      return {
        error:
          'End time must be later than start time.'
      };
    }

    const dates = [];
    let date = localDate(start);

    for (
      let index = 0;
      index <= totalDays;
      index++
    ) {
      if (date.getDay() === day) {
        dates.push(
          iso(date)
        );
      }

      date.setDate(
        date.getDate() + 1
      );
    }

    if (
      meetingCount
      > dates.length
    ) {
      return {
        error:
          `Only ${dates.length} ${weekdays[day]} meeting date(s) are available in the selected range.`
      };
    }

    return {
      dates:
        dates.slice(
          0,
          meetingCount
        ),
      available:
        dates.length
    };
  }

  function render() {
    const result =
      calculate();

    const list =
      $('sessionsList');

    sessions =
      result.dates
      || [];

    if (result.error) {
      list.innerHTML =
        `<div class="session-error">${escapeHTML(result.error)}</div>`;

      $('dateHint').textContent =
        'Adjust the settings above to see your sessions.';
    } else {
      list.innerHTML =
        sessions
          .map(
            (date, index) =>
              `<div class="session-row">
                <strong>
                  <span class="session-number">${index + 1}</span>
                  ${escapeHTML(pretty(date))}
                </strong>
                <span>
                  ${escapeHTML(clock($('startTime').value))}
                  –
                  ${escapeHTML(clock($('endTime').value))}
                </span>
              </div>`
          )
          .join('');

      $('dateHint').textContent =
        `${result.available} matching weekdays in this date range. Showing the first ${sessions.length}.`;
    }

    $('summaryFrequency').textContent =
      `Every ${weekdays[Number($('weekday').value)]}`;

    $('summaryMeetings').textContent =
      `${sessions.length} session${sessions.length === 1 ? '' : 's'}`;

    const start =
      $('startTime')
        .value
        .split(':')
        .map(Number);

    const end =
      $('endTime')
        .value
        .split(':')
        .map(Number);

    const minutes =
      (end[0] * 60 + end[1])
      - (start[0] * 60 + start[1]);

    $('summaryDuration').textContent =
      minutes > 0
        ? `${Math.floor(minutes / 60)}h${minutes % 60 ? ` ${minutes % 60}m` : ''} per session`
        : 'Invalid time range';

    renderDepartmentSelection();

    return result;
  }

  fields.forEach(id => {
    const field = $(id);

    if (!field) {
      return;
    }

    field.addEventListener(
      'input',
      () => {
        $('formMessage').textContent = '';
        render();
      }
    );

    field.addEventListener(
      'change',
      () => {
        $('formMessage').textContent = '';
        render();
      }
    );
  });

  departmentInputs.forEach(input => {
    input.addEventListener(
      'change',
      () => {
        $('formMessage').textContent = '';
        renderDepartmentSelection();
      }
    );
  });

  $('newProgram')?.addEventListener(
    'click',
    () => {
      $('formPanel').scrollIntoView({
        behavior: 'smooth',
        block: 'start'
      });

      $('title').focus({
        preventScroll: true
      });
    }
  );

  $('resetForm')?.addEventListener(
    'click',
    () => {
      form.reset();
      $('formMessage').textContent = '';
      render();
    }
  );

  function value(id) {
    return $(id).value.trim();
  }

  form.addEventListener(
    'submit',
    event => {
      event.preventDefault();

      const result =
        render();

      if (result.error) {
        $('formMessage').textContent =
          result.error;

        return;
      }

      if (!form.reportValidity()) {
        return;
      }

      const targeting =
        renderDepartmentSelection();

      if (
        targeting.selected.length
        < 1
      ) {
        $('formMessage').textContent =
          'Select at least one target department.';

        document
          .getElementById(
            'departmentSelector'
          )
          ?.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
          });

        return;
      }

      const values = [
        [
          'PROGRAM TITLE',
          value('title')
        ],
        [
          'PURPOSE',
          value('purpose')
        ],
        [
          'OBJECTIVES',
          value('objectives')
        ],
        [
          'SCHEDULE',
          `${pretty(sessions[0])} to ${pretty(sessions[sessions.length - 1])} · Every ${weekdays[Number(value('weekday'))]} · ${clock(value('startTime'))}–${clock(value('endTime'))}`
        ],
        [
          'TARGET DEPARTMENTS',
          targeting.selected
            .map(
              department =>
                department.name
            )
            .join('\n')
        ],
        [
          'PRIORITY RECOMMENDATION',
          `${targeting.priorityCount} current employee${targeting.priorityCount === 1 ? '' : 's'} estimated below 50%. The server will recalculate at publish time. Recommendations do not auto-join employees.`
        ],
        [
          'INSTRUCTORS',
          value('instructors')
        ],
        [
          'LOCATION',
          value('venue')
        ],
        [
          'CAPACITY',
          `${value('capacity')} participants`
        ],
        [
          'MEETINGS',
          sessions
            .map(
              (date, index) =>
                `Meeting ${index + 1}: ${pretty(date)}`
            )
            .join('\n')
        ]
      ];

      $('reviewContents').innerHTML =
        values
          .map(
            ([label, content]) =>
              `<div class="review-section">
                <span>${escapeHTML(label)}</span>
                <p>${escapeHTML(content)}</p>
              </div>`
          )
          .join('');

      dialog.showModal();
    }
  );

  $('closeReview')?.addEventListener(
    'click',
    () => dialog.close()
  );

  $('backToEdit')?.addEventListener(
    'click',
    () => dialog.close()
  );

  $('publishButton')?.addEventListener(
    'click',
    () => {
      const result =
        calculate();

      if (result.error) {
        dialog.close();

        $('formMessage').textContent =
          result.error;

        return;
      }

      if (!form.reportValidity()) {
        dialog.close();
        return;
      }

      const targeting =
        renderDepartmentSelection();

      if (
        targeting.selected.length
        < 1
      ) {
        dialog.close();

        $('formMessage').textContent =
          'Select at least one target department.';

        return;
      }

      const button =
        $('publishButton');

      button.disabled = true;
      button.textContent =
        'Publishing…';

      // Native POST to authenticated PHP router.
      // Preview itself performs no database writes.
      HTMLFormElement.prototype.submit.call(
        form
      );
    }
  );

  render();
});
