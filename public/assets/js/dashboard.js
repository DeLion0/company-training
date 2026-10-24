document.addEventListener("DOMContentLoaded", () => {
  const search = document.getElementById("hrEmployeeSearch");
  const department = document.getElementById("hrDepartmentFilter");
  const rows = Array.from(document.querySelectorAll("#hrEmployeeRows tr"));
  const empty = document.getElementById("hrEmptyResults");
  const tableBottom = document.querySelector(".hr-employees .hr-table-bottom");

  const PAGE_SIZE = 5;
  let currentPage = 1;

  if (tableBottom) {
    tableBottom.innerHTML = `
      <span class="hr-page-summary">
        <strong id="hrVisibleCount">0</strong> employees
      </span>

      <nav
        class="hr-pagination"
        id="hrEmployeePagination"
        aria-label="Employee directory pages"
      ></nav>
    `;
  }

  const count = document.getElementById("hrVisibleCount");
  const pagination = document.getElementById("hrEmployeePagination");

  const filteredRows = () => {
    const term = search?.value.trim().toLocaleLowerCase() || "";
    const dept = department?.value || "";

    return rows.filter((row) => {
      const matchesSearch = !term || (row.dataset.search || "").includes(term);

      const matchesDepartment = !dept || row.dataset.department === dept;

      return matchesSearch && matchesDepartment;
    });
  };

  const renderPagination = (totalPages) => {
    if (!pagination) return;

    if (totalPages <= 1) {
      pagination.innerHTML = "";
      pagination.hidden = true;
      return;
    }

    pagination.hidden = false;

    let html = `
      <button
        type="button"
        class="hr-page-btn hr-page-nav"
        data-page-action="prev"
        ${currentPage === 1 ? "disabled" : ""}
        aria-label="Previous page"
      >
        <i data-lucide="chevron-left"></i>
        <span>Previous</span>
      </button>
    `;

    for (let page = 1; page <= totalPages; page++) {
      html += `
        <button
          type="button"
          class="hr-page-btn hr-page-number ${page === currentPage ? "is-active" : ""}"
          data-page="${page}"
          ${page === currentPage ? 'aria-current="page"' : ""}
        >
          ${page}
        </button>
      `;
    }

    html += `
      <button
        type="button"
        class="hr-page-btn hr-page-nav"
        data-page-action="next"
        ${currentPage === totalPages ? "disabled" : ""}
        aria-label="Next page"
      >
        <span>Next</span>
        <i data-lucide="chevron-right"></i>
      </button>
    `;

    pagination.innerHTML = html;

    if (window.lucide) {
      window.lucide.createIcons();
    }
  };

  const updateDirectory = (resetPage = false) => {
    if (resetPage) {
      currentPage = 1;
    }

    const matches = filteredRows();
    const total = matches.length;
    const totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));

    if (currentPage > totalPages) {
      currentPage = totalPages;
    }

    rows.forEach((row) => {
      row.hidden = true;
    });

    const start = (currentPage - 1) * PAGE_SIZE;
    const end = Math.min(start + PAGE_SIZE, total);

    matches.slice(start, end).forEach((row) => {
      row.hidden = false;
    });

    if (count) {
      count.textContent =
        total === 0 ? "0 of 0" : `${start + 1}–${end} of ${total}`;
    }

    if (empty) {
      empty.hidden = total > 0;

      if (rows.length) {
        empty.textContent = "No employees match your search.";
      }
    }

    renderPagination(totalPages);
  };

  search?.addEventListener("input", () => {
    updateDirectory(true);
  });

  department?.addEventListener("change", () => {
    updateDirectory(true);
  });

  pagination?.addEventListener("click", (event) => {
    const button = event.target.closest("button");

    if (!button || button.disabled) {
      return;
    }

    const matches = filteredRows();
    const totalPages = Math.max(1, Math.ceil(matches.length / PAGE_SIZE));

    const page = Number(button.dataset.page || 0);
    const action = button.dataset.pageAction || "";

    if (page > 0) {
      currentPage = page;
    } else if (action === "prev") {
      currentPage = Math.max(1, currentPage - 1);
    } else if (action === "next") {
      currentPage = Math.min(totalPages, currentPage + 1);
    }

    updateDirectory();

    document.querySelector(".hr-employees")?.scrollIntoView({
      behavior: "smooth",
      block: "start",
    });
  });

  updateDirectory();

  const performanceFilter = document.getElementById(
    "hrPerformanceDepartmentFilter",
  );
  const performanceViews = Array.from(
    document.querySelectorAll(".hr-dept-performance-view"),
  );

  const updateDepartmentPerformance = () => {
    if (!performanceFilter || !performanceViews.length) return;
    const selected = performanceFilter.value;
    performanceViews.forEach((view) => {
      view.hidden = view.dataset.performanceDepartment !== selected;
    });
  };

  performanceFilter?.addEventListener("change", updateDepartmentPerformance);
  updateDepartmentPerformance();

  const payloadNode = document.getElementById("hrPerformanceData");
  const dialog = document.getElementById("hrEmployeePerformanceDialog");
  if (!payloadNode || !dialog) return;

  let payload = { months: [], employees: [] };
  try {
    payload = JSON.parse(payloadNode.textContent || "{}");
  } catch {
    return;
  }

  const employees = new Map(
    (payload.employees || []).map((employee) => [
      String(employee.id),
      employee,
    ]),
  );
  const months = Array.isArray(payload.months) ? payload.months : [];
  const name = document.getElementById("hrDialogEmployeeName");
  const meta = document.getElementById("hrDialogEmployeeMeta");
  const latest = document.getElementById("hrDialogLatest");
  const date = document.getElementById("hrDialogDate");
  const bars = document.getElementById("hrEmployeeMonthlyBars");
  const close = document.getElementById("hrDialogClose");

  const escapeHTML = (value) =>
    String(value ?? "").replace(
      /[&<>"']/g,
      (character) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        })[character],
    );

  const formatDate = (value) => {
    if (!value) return "No review yet";
    const parts = String(value).split("-").map(Number);
    if (parts.length !== 3 || parts.some(Number.isNaN)) return String(value);
    return new Date(parts[0], parts[1] - 1, parts[2], 12).toLocaleDateString(
      "en-US",
      { month: "short", day: "numeric", year: "numeric" },
    );
  };

  const renderEmployee = (employee) => {
    if (!employee || !bars) return;
    name.textContent = employee.name || "Employee Performance";
    meta.textContent = [
      employee.employee_code,
      employee.department,
      employee.job_title,
    ]
      .filter(Boolean)
      .join(" · ");
    latest.textContent =
      employee.latest_score == null
        ? "Not evaluated"
        : `${Number(employee.latest_score).toFixed(1)}%`;
    date.textContent = formatDate(employee.latest_review_date);

    bars.innerHTML = months
      .map((month) => {
        const raw = employee.monthly?.[month.key];
        const hasScore = raw !== null && raw !== undefined && raw !== "";
        const score = hasScore ? Math.max(0, Math.min(100, Number(raw))) : null;
        return `
        <div class="hr-employee-month ${hasScore ? "" : "no-review"}">
          <div class="hr-employee-month-bar-area" title="${escapeHTML(month.long_label)} · ${hasScore ? `${score.toFixed(1)}%` : "No review"}">
            ${
              hasScore
                ? `<span class="hr-employee-score">${score.toFixed(1)}%</span><span class="hr-employee-bar" style="height:${Math.max(2, score)}%"></span>`
                : '<span class="hr-employee-score">No review</span><span class="hr-employee-missing"></span>'
            }
          </div>
          <strong>${escapeHTML(month.label)}</strong>
        </div>`;
      })
      .join("");

    if (window.lucide) window.lucide.createIcons();
  };

  document.querySelectorAll(".hr-employee-open").forEach((button) => {
    button.addEventListener("click", () => {
      const employee = employees.get(button.dataset.employeeId || "");
      if (!employee) return;
      renderEmployee(employee);
      dialog.showModal();
    });
  });

  close?.addEventListener("click", () => dialog.close());
  dialog.addEventListener("click", (event) => {
    if (event.target === dialog) dialog.close();
  });
});
