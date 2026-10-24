(() => {
  "use strict";

  const PAGE_SIZE = 5;

  const configs = [
    {
      rows: "#employee-table-body tr",
      search: "#employee-search",
      filter: "#employee-department-filter",
      empty: "#emp-empty",
      matches(row, query, filter) {
        return (
          (!query || (row.dataset.search || "").includes(query)) &&
          (!filter || row.dataset.department === filter)
        );
      },
    },

    {
      rows: "#dept-table tr",
      search: "#dept-search",
      empty: "#dept-no-match",
      matches(row, query) {
        return !query || (row.dataset.search || "").includes(query);
      },
    },

    {
      rows: "#managerRows tr",
      search: "#managerSearch",
      filter: "#managerDepartmentFilter",
      empty: "#managerEmpty",
      matches(row, query, filter) {
        return (
          (!query || (row.dataset.search || "").includes(query)) &&
          (!filter || row.dataset.department === filter)
        );
      },
    },

    {
      rows: "[data-performance-history]",
      search: "#performanceHistorySearch",
      matches(row, query) {
        return (
          !query || (row.dataset.search || "").toLowerCase().includes(query)
        );
      },
    },

    {
      rows: "[data-pb-search]",
      search: "#pbLeaderboardSearch",
      empty: "#pbLeaderboardEmpty",
      matches(row, query) {
        return !query || (row.dataset.pbSearch || "").includes(query);
      },
    },
  ];

  function getAnchor(rows) {
    const first = rows[0];

    if (!first) return null;

    const table = first.closest("table");

    if (table) {
      return table.parentElement || table;
    }

    return first.parentElement;
  }

  function pageItems(current, total) {
    if (total <= 7) {
      return Array.from({ length: total }, (_, index) => index + 1);
    }

    const pages = new Set([1, total, current - 1, current, current + 1]);

    const valid = [...pages]
      .filter((page) => page >= 1 && page <= total)
      .sort((a, b) => a - b);

    const output = [];

    valid.forEach((page, index) => {
      const previous = valid[index - 1];

      if (index > 0 && previous && page - previous > 1) {
        output.push("...");
      }

      output.push(page);
    });

    return output;
  }

  function setup(config) {
    const rows = Array.from(document.querySelectorAll(config.rows));

    if (!rows.length) {
      return;
    }

    const search = config.search ? document.querySelector(config.search) : null;

    const filter = config.filter ? document.querySelector(config.filter) : null;

    const empty = config.empty ? document.querySelector(config.empty) : null;

    const anchor = getAnchor(rows);

    if (!anchor) {
      return;
    }

    let currentPage = 1;

    const shell = document.createElement("div");
    shell.className = "gf-pagination-bar";

    const summary = document.createElement("span");
    summary.className = "gf-pagination-summary";

    const nav = document.createElement("nav");
    nav.className = "gf-pagination";
    nav.setAttribute("aria-label", "Pagination");

    shell.append(summary, nav);

    anchor.insertAdjacentElement("afterend", shell);

    const matchedRows = () => {
      const query = (search?.value || "").trim().toLocaleLowerCase();

      const filterValue = filter?.value || "";

      return rows.filter((row) => config.matches(row, query, filterValue));
    };

    const renderNav = (totalPages) => {
      nav.replaceChildren();

      if (totalPages <= 1) {
        nav.hidden = true;
        return;
      }

      nav.hidden = false;

      const previous = document.createElement("button");

      previous.type = "button";
      previous.className = "gf-page-button gf-page-nav";

      previous.disabled = currentPage === 1;

      previous.dataset.action = "prev";

      previous.innerHTML = `
        <i data-lucide="chevron-left"></i>
        <span>Previous</span>
      `;

      nav.append(previous);

      pageItems(currentPage, totalPages).forEach((item) => {
        if (item === "...") {
          const ellipsis = document.createElement("span");

          ellipsis.className = "gf-page-ellipsis";

          ellipsis.textContent = "…";

          nav.append(ellipsis);
          return;
        }

        const button = document.createElement("button");

        button.type = "button";
        button.className = "gf-page-button gf-page-number";

        button.textContent = String(item);

        button.dataset.page = String(item);

        if (item === currentPage) {
          button.classList.add("is-active");

          button.setAttribute("aria-current", "page");
        }

        nav.append(button);
      });

      const next = document.createElement("button");

      next.type = "button";
      next.className = "gf-page-button gf-page-nav";

      next.disabled = currentPage === totalPages;

      next.dataset.action = "next";

      next.innerHTML = `
        <span>Next</span>
        <i data-lucide="chevron-right"></i>
      `;

      nav.append(next);

      window.lucide?.createIcons();
    };

    const render = (resetPage = false) => {
      if (resetPage) {
        currentPage = 1;
      }

      const matches = matchedRows();

      const total = matches.length;

      const totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));

      currentPage = Math.min(currentPage, totalPages);

      rows.forEach((row) => {
        row.hidden = true;
      });

      const start = (currentPage - 1) * PAGE_SIZE;

      const end = Math.min(start + PAGE_SIZE, total);

      matches.slice(start, end).forEach((row) => {
        row.hidden = false;
      });

      if (total === 0) {
        summary.textContent = "0 results";

        shell.hidden = true;
      } else {
        shell.hidden = false;

        summary.textContent = `${start + 1}–${end} of ${total}`;
      }

      if (empty) {
        empty.hidden = total > 0;
      }

      renderNav(totalPages);
    };

    nav.addEventListener("click", (event) => {
      const button = event.target.closest("button");

      if (!button || button.disabled) {
        return;
      }

      const matches = matchedRows();

      const totalPages = Math.max(1, Math.ceil(matches.length / PAGE_SIZE));

      if (button.dataset.page) {
        currentPage = Number(button.dataset.page);
      }

      if (button.dataset.action === "prev") {
        currentPage = Math.max(1, currentPage - 1);
      }

      if (button.dataset.action === "next") {
        currentPage = Math.min(totalPages, currentPage + 1);
      }

      render();

      anchor.scrollIntoView({
        behavior: "smooth",
        block: "nearest",
      });
    });

    search?.addEventListener("input", () => render(true));

    filter?.addEventListener("change", () => render(true));

    render();
  }

  document.addEventListener("DOMContentLoaded", () => {
    configs.forEach(setup);
  });
})();
