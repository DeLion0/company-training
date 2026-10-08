(() => {
  const modal = document.querySelector("#gf-modal");
  if (!modal) return;
  const forms = {
    manual: document.querySelector("#gf-manual"),
    excel: document.querySelector("#gf-excel"),
    position: document.querySelector("#gf-position"),
  };
  const heading = document.querySelector("#gf-modal-title");
  let opener = null;
  function close() {
    modal.hidden = true;
    opener?.focus();
  }
  function open(which) {
    opener = document.activeElement;
    Object.entries(forms).forEach(([k, f]) => {
      f.hidden = k !== which;
    });
    heading.textContent =
      which === "manual"
        ? "Add Department"
        : which === "excel"
          ? "Import Excel"
          : "Add Job Title";
    modal.hidden = false;
    modal.querySelector("form:not([hidden]) input:not([type=hidden])")?.focus();
  }
  document
    .querySelectorAll("[data-open]")
    .forEach((b) => b.addEventListener("click", () => open(b.dataset.open)));
  document.querySelectorAll("[data-add-position]").forEach((b) =>
    b.addEventListener("click", () => {
      forms.position.elements.department_id.value = b.dataset.addPosition;
      open("position");
      heading.textContent = "Add Job Title — " + b.dataset.deptName;
    }),
  );
  document
    .querySelectorAll("[data-close]")
    .forEach((b) => b.addEventListener("click", close));
  modal.addEventListener("click", (e) => {
    if (e.target === modal) close();
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && !modal.hidden) close();
  });
  const search = document.querySelector("#dept-search");
  search?.addEventListener("input", () => {
    let shown = 0;
    document.querySelectorAll("#dept-table tr").forEach((row) => {
      const matches = (row.dataset.search || "").includes(
        search.value.trim().toLowerCase(),
      );
      row.hidden = !matches;
      if (matches) shown++;
    });
    document.querySelector("#dept-no-match").hidden = shown > 0;
  });
})();

// Job Titles viewer — reads escaped, database-backed titles from each table row.
(() => {
  const modal = document.getElementById("gf-titles-modal");
  if (!modal) return;
  const heading = document.getElementById("gf-titles-title");
  const summary = document.getElementById("gf-titles-summary");
  const list = document.getElementById("gf-titles-list");
  const dialog = modal.querySelector('[role="dialog"]');
  let opener = null;
  function close() {
    modal.hidden = true;
    opener?.focus();
  }
  document.querySelectorAll("[data-view-titles]").forEach((btn) =>
    btn.addEventListener("click", () => {
      opener = btn;
      let titles = [];
      try {
        const parsed = JSON.parse(btn.dataset.titles || "[]");
        if (Array.isArray(parsed)) titles = parsed;
      } catch (_) {}
      heading.textContent = btn.dataset.deptName + " — Job Titles";
      summary.textContent =
        titles.length +
        " " +
        (titles.length === 1 ? "job title" : "job titles") +
        " registered";
      list.replaceChildren();
      if (titles.length === 0) {
        const li = document.createElement("li");
        li.className = "gf-title-empty";
        li.textContent = "No job titles registered yet.";
        list.append(li);
      } else
        titles.forEach((title, i) => {
          const li = document.createElement("li");
          const n = document.createElement("span");
          n.className = "gf-title-number";
          n.textContent = String(i + 1).padStart(2, "0");
          const label = document.createElement("span");
          label.textContent = String(title);
          li.append(n, label);
          list.append(li);
        });
      modal.hidden = false;
      dialog.focus();
    }),
  );
  modal
    .querySelectorAll("[data-close-titles]")
    .forEach((b) => b.addEventListener("click", close));
  modal.addEventListener("click", (e) => {
    if (e.target === modal) close();
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && !modal.hidden) {
      e.preventDefault();
      close();
    }
    if (e.key === "Tab" && !modal.hidden) {
      const buttons = [...modal.querySelectorAll("button")];
      const first = buttons[0],
        last = buttons[buttons.length - 1];
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }
  });
})();
