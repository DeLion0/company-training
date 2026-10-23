document.addEventListener('DOMContentLoaded', () => {
  const payloadNode = document.getElementById('analyticsData');
  if (!payloadNode) return;

  let payload;
  try {
    payload = JSON.parse(payloadNode.textContent || '{}');
  } catch {
    return;
  }

  const months = Array.isArray(payload.months) ? payload.months : [];
  const hiddenDepartments = new Set();

  const numberOrNull = value => {
    if (value === null || value === undefined || value === '') return null;
    const number = Number(value);
    return Number.isFinite(number) ? number : null;
  };

  const escapeHTML = value => String(value ?? '').replace(/[&<>"']/g, character => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  })[character]);

  const createRenderer = ({
    canvas,
    seriesProvider,
    min = 0,
    max = 100,
    formatter = value => `${Number(value).toFixed(1)}%`,
    integerAxis = false,
  }) => {
    if (!canvas || !months.length) return null;

    const stage = canvas.closest('.analytics-chart-stage');
    const tooltip = stage?.querySelector('[data-tooltip]');
    const context = canvas.getContext('2d');
    if (!context) return null;

    let metrics = null;
    let hoverIndex = null;

    const draw = () => {
      const rect = canvas.getBoundingClientRect();
      if (!rect.width || !rect.height) return;

      const dpr = Math.min(window.devicePixelRatio || 1, 2);
      canvas.width = Math.round(rect.width * dpr);
      canvas.height = Math.round(rect.height * dpr);

      context.setTransform(dpr, 0, 0, dpr, 0, 0);
      context.clearRect(0, 0, rect.width, rect.height);

      const width = rect.width;
      const height = rect.height;
      const padding = {
        left: width < 520 ? 34 : 42,
        right: 13,
        top: 14,
        bottom: 30,
      };

      const plotWidth = Math.max(1, width - padding.left - padding.right);
      const plotHeight = Math.max(1, height - padding.top - padding.bottom);

      const xAt = index => months.length === 1
        ? padding.left + plotWidth / 2
        : padding.left + (plotWidth * index / (months.length - 1));

      const yAt = value => {
        const normalized = (Number(value) - min) / Math.max(1, max - min);
        return padding.top + plotHeight - normalized * plotHeight;
      };

      metrics = { width, height, padding, plotWidth, plotHeight, xAt, yAt };

      context.font = '8px "DM Sans", sans-serif';
      context.lineWidth = 1;

      const tickCount = 4;
      for (let i = 0; i <= tickCount; i++) {
        const value = min + ((max - min) * (tickCount - i) / tickCount);
        const y = padding.top + plotHeight * i / tickCount;

        context.beginPath();
        context.strokeStyle = '#edf2ee';
        context.moveTo(padding.left, y);
        context.lineTo(width - padding.right, y);
        context.stroke();

        context.fillStyle = '#9eaaa2';
        context.textAlign = 'right';
        context.textBaseline = 'middle';

        const label = integerAxis
          ? String(Math.round(value))
          : String(Math.round(value));

        context.fillText(label, padding.left - 7, y);
      }

      const labelStep = width < 600 && months.length > 8 ? 2 : 1;

      months.forEach((month, index) => {
        if (index % labelStep !== 0 && index !== months.length - 1) return;

        context.fillStyle = '#829389';
        context.textAlign = 'center';
        context.textBaseline = 'top';
        context.fillText(
          month.label || '',
          xAt(index),
          height - padding.bottom + 10
        );
      });

      const series = seriesProvider();

      series.forEach(item => {
        const values = Array.isArray(item.values) ? item.values : [];
        const color = item.color || '#278b52';

        context.lineWidth = 2.15;
        context.lineJoin = 'round';
        context.lineCap = 'round';
        context.strokeStyle = color;

        let segmentOpen = false;

        values.forEach((raw, index) => {
          const value = numberOrNull(raw);

          if (value === null) {
            if (segmentOpen) {
              context.stroke();
              segmentOpen = false;
            }
            return;
          }

          const x = xAt(index);
          const y = yAt(Math.max(min, Math.min(max, value)));

          if (!segmentOpen) {
            context.beginPath();
            context.moveTo(x, y);
            segmentOpen = true;
          } else {
            context.lineTo(x, y);
          }
        });

        if (segmentOpen) context.stroke();

        values.forEach((raw, index) => {
          const value = numberOrNull(raw);
          if (value === null) return;

          const x = xAt(index);
          const y = yAt(Math.max(min, Math.min(max, value)));

          context.beginPath();
          context.fillStyle = '#ffffff';
          context.arc(x, y, 4.1, 0, Math.PI * 2);
          context.fill();

          context.beginPath();
          context.fillStyle = color;
          context.arc(x, y, 2.6, 0, Math.PI * 2);
          context.fill();
        });
      });

      if (hoverIndex !== null && hoverIndex >= 0 && hoverIndex < months.length) {
        const x = xAt(hoverIndex);
        context.beginPath();
        context.strokeStyle = '#cbd8cf';
        context.lineWidth = 1;
        context.setLineDash([3, 3]);
        context.moveTo(x, padding.top);
        context.lineTo(x, padding.top + plotHeight);
        context.stroke();
        context.setLineDash([]);
      }
    };

    const updateTooltip = event => {
      if (!metrics || !tooltip) return;

      const rect = canvas.getBoundingClientRect();
      const x = event.clientX - rect.left;
      const relative = (x - metrics.padding.left) / Math.max(1, metrics.plotWidth);

      const index = Math.max(
        0,
        Math.min(
          months.length - 1,
          Math.round(relative * Math.max(0, months.length - 1))
        )
      );

      hoverIndex = index;
      draw();

      const month = months[index];
      const activeSeries = seriesProvider();
      const rows = activeSeries
        .map(item => {
          const value = numberOrNull(item.values?.[index]);
          if (value === null) return '';

          return `<div class="analytics-tooltip-row">
            <i style="background:${escapeHTML(item.color || '#278b52')}"></i>
            <span>${escapeHTML(item.label)}: <b>${escapeHTML(formatter(value))}</b></span>
          </div>`;
        })
        .filter(Boolean)
        .join('');

      if (!rows) {
        tooltip.hidden = true;
        return;
      }

      tooltip.innerHTML = `<strong>${escapeHTML(month.long_label || month.label || '')}</strong>${rows}`;
      tooltip.hidden = false;

      const stageRect = stage.getBoundingClientRect();
      const tooltipWidth = tooltip.offsetWidth || 170;
      const localX = event.clientX - stageRect.left;
      const localY = event.clientY - stageRect.top;

      tooltip.style.left = `${Math.min(
        Math.max(8, localX + 12),
        Math.max(8, stageRect.width - tooltipWidth - 8)
      )}px`;

      tooltip.style.top = `${Math.max(8, localY - 18)}px`;
    };

    const clearTooltip = () => {
      hoverIndex = null;
      if (tooltip) tooltip.hidden = true;
      draw();
    };

    canvas.addEventListener('mousemove', updateTooltip);
    canvas.addEventListener('mouseleave', clearTooltip);

    const observer = new ResizeObserver(draw);
    observer.observe(canvas);

    draw();

    return { draw };
  };

  const departmentCanvas = document.getElementById('analyticsDepartmentChart');

  const departmentRenderer = createRenderer({
    canvas: departmentCanvas,
    seriesProvider: () => (payload.departments || [])
      .filter(department => !hiddenDepartments.has(String(department.id)))
      .map(department => ({
        label: department.name,
        color: department.color,
        values: department.values,
      })),
  });

  document.querySelectorAll('[data-series-toggle]').forEach(button => {
    button.addEventListener('click', () => {
      const id = String(button.dataset.seriesToggle || '');
      const currentlyVisible = button.getAttribute('aria-pressed') !== 'false';

      if (currentlyVisible) {
        hiddenDepartments.add(id);
        button.setAttribute('aria-pressed', 'false');
      } else {
        hiddenDepartments.delete(id);
        button.setAttribute('aria-pressed', 'true');
      }

      departmentRenderer?.draw();
    });
  });

  createRenderer({
    canvas: document.getElementById('analyticsCompanyChart'),
    seriesProvider: () => [{
      label: 'Company average',
      color: '#278b52',
      values: payload.company_average || [],
    }],
  });

  createRenderer({
    canvas: document.getElementById('analyticsCoverageChart'),
    seriesProvider: () => [{
      label: 'Evaluation coverage',
      color: '#3478d4',
      values: payload.coverage || [],
    }],
  });

  const riskValues = Array.isArray(payload.below_threshold)
    ? payload.below_threshold.map(value => Number(value) || 0)
    : [];

  const riskMax = Math.max(
    4,
    Number(payload.total_employees) || 0,
    ...riskValues
  );

  const riskAxisMax = Math.max(
    4,
    Math.ceil(riskMax / 4) * 4
  );

  createRenderer({
    canvas: document.getElementById('analyticsRiskChart'),
    seriesProvider: () => [{
      label: `Below ${Number(payload.threshold || 50).toFixed(0)}%`,
      color: '#dc3f45',
      values: riskValues,
    }],
    max: riskAxisMax,
    formatter: value => `${Math.round(value)} employee${Math.round(value) === 1 ? '' : 's'}`,
    integerAxis: true,
  });
});
