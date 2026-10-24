document.addEventListener('DOMContentLoaded', () => {
  const page = document.getElementById('pbGrowthPage');
  if (!page || !page.dataset.growthStatusUrl) return;

  const artStage = document.getElementById('pbGrowthArtStage');
  const artImage = document.getElementById('pbGrowthArtImage');
  const stageLabel = document.getElementById('pbGrowthStageLabel');
  const pointsMain = document.getElementById('pbGrowthPointsMain');
  const nextText = document.getElementById('pbGrowthNextText');
  const progressStage = document.getElementById('pbGrowthProgressStage');
  const progressPercent = document.getElementById('pbGrowthProgressPercent');
  const progressBar = document.getElementById('pbGrowthProgressBar');
  const statPoints = document.getElementById('pbGrowthStatPoints');
  const statBadges = document.getElementById('pbGrowthStatBadges');
  const statRank = document.getElementById('pbGrowthStatRank');
  const rankTop = document.getElementById('pbGrowthRankTop');
  const badgeCount = document.getElementById('pbGrowthBadgeCount');
  const stageCount = document.getElementById('pbGrowthStageCount');
  const stageSummary = document.getElementById('pbGrowthStageSummary');
  const journeyLine = document.getElementById('pbGrowthJourneyLine');
  const evolutionLabel = document.getElementById('pbEvolutionLabel');
  const evolutionLabelStage = document.getElementById('pbEvolutionLabelStage');

  let currentStage = Number(page.dataset.stageIndex || 0);
  let currentPoints = Number(page.dataset.totalPoints || 0);
  let polling = false;
  let evolutionTimer = null;

  const assetBase = 'assets/images/growth/';
  const assetByStage = [
    'seed-hero.png',
    'sprout.png',
    'seedling.png',
    'sapling.png',
    'young-tree.png',
    'flourishing-tree.png',
    'full-growth.png'
  ];

  const escapeHtml = (value) => String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

  const animateNumber = (element, from, to, duration = 700) => {
    if (!element || from === to) {
      if (element) element.textContent = Number(to).toLocaleString();
      return;
    }

    const start = performance.now();
    const diff = to - from;

    const tick = (now) => {
      const t = Math.min(1, (now - start) / duration);
      const eased = 1 - Math.pow(1 - t, 3);
      const value = Math.round(from + diff * eased);
      element.textContent = value.toLocaleString();
      if (t < 1) requestAnimationFrame(tick);
    };

    requestAnimationFrame(tick);
  };

  const updateMilestones = (stageIndex, stages) => {
    const steps = document.querySelectorAll('.pb-art-step[data-stage-index]');

    steps.forEach((step) => {
      const index = Number(step.dataset.stageIndex || 0);
      const reached = index <= stageIndex;
      const current = index === stageIndex;
      const node = step.querySelector('.pb-art-step-node span');
      const state = step.querySelector('.pb-stage-state');

      step.classList.toggle('reached', reached);
      step.classList.toggle('current', current);

      if (node) node.textContent = reached ? '✓' : String(index + 1);

      if (state) {
        state.textContent = current ? 'Current' : (reached ? 'Unlocked' : 'Locked');
        state.className = 'pb-stage-state ' + (current ? 'pb-current-stage-label' : 'pb-stage-status-label');
        if (!reached && !current) state.classList.add('locked');
      }
    });

    const count = Array.isArray(stages) ? stages.length : steps.length;
    const width = count > 1 ? (stageIndex / (count - 1)) * 100 : 100;
    if (journeyLine) journeyLine.style.width = `${width}%`;
    if (stageCount) stageCount.textContent = `Stage ${stageIndex + 1} of ${count}`;
  };

  const updateCopy = (data) => {
    const stage = data.stage;
    if (!stage) return;

    if (stageLabel) stageLabel.textContent = stage.label;
    if (progressStage) progressStage.textContent = stage.label;
    if (stageSummary) stageSummary.textContent = stage.label;
    if (progressPercent) progressPercent.textContent = `${Math.round(Number(stage.progress || 0))}%`;
    if (progressBar) progressBar.style.width = `${Math.max(0, Math.min(100, Number(stage.progress || 0)))}%`;

    if (nextText) {
      if (stage.next_label) {
        const pts = Number(stage.points_to_next || 0);
        nextText.innerHTML = `${pts.toLocaleString()} more point${pts === 1 ? '' : 's'} until <strong>${escapeHtml(stage.next_label)}</strong>.`;
      } else {
        nextText.textContent = 'Full growth reached. Keep earning points to maintain your progress and company rank.';
      }
    }

    if (statBadges) statBadges.textContent = Number(data.badge_count || 0).toLocaleString();
    if (badgeCount) badgeCount.textContent = Number(data.badge_count || 0).toLocaleString();
    if (statRank) statRank.textContent = Number(data.rank || 0).toLocaleString();
    if (rankTop) rankTop.textContent = Number(data.rank || 0).toLocaleString();

    updateMilestones(Number(stage.index || 0), stage.stages || []);
  };

  const playPointPulse = () => {
    if (!artStage) return;
    artStage.classList.remove('pb-points-updated');
    void artStage.offsetWidth;
    artStage.classList.add('pb-points-updated');
    window.setTimeout(() => artStage.classList.remove('pb-points-updated'), 900);
  };

  const playEvolution = async (stageIndexValue, stageLabelValue, assetName, duration = 1350) => {
    if (!artStage || !artImage) return;

    const nextStage = Number(stageIndexValue || 0);
    const nextSrc = assetBase + assetName;

    const preload = new Image();
    preload.src = nextSrc;
    try {
      if (preload.decode) await preload.decode();
    } catch (_) {
      // Cached/local image is still safe to use if decode() is unavailable.
    }

    window.clearTimeout(evolutionTimer);
    artStage.querySelectorAll('.pb-evolution-old-image').forEach((node) => node.remove());

    const oldRect = artImage.getBoundingClientRect();
    const oldImage = artImage.cloneNode(true);
    oldImage.removeAttribute('id');
    oldImage.classList.add('pb-evolution-old-image');
    oldImage.style.width = `${oldRect.width}px`;
    oldImage.style.maxWidth = 'none';
    oldImage.style.maxHeight = 'none';
    artStage.appendChild(oldImage);

    for (let i = 0; i <= 6; i++) artStage.classList.remove(`stage-${i}`);
    artStage.classList.add(`stage-${nextStage}`, 'is-evolving');
    artStage.dataset.stageIndex = String(nextStage);
    artStage.setAttribute('aria-label', `${stageLabelValue} growth illustration`);

    artImage.src = nextSrc;
    artImage.alt = `${stageLabelValue} growth stage`;
    artImage.classList.remove('pb-evolution-new-image');
    void artImage.offsetWidth;
    artImage.classList.add('pb-evolution-new-image');

    if (stageLabel) stageLabel.textContent = stageLabelValue;
    if (progressStage) progressStage.textContent = stageLabelValue;
    if (stageSummary) stageSummary.textContent = stageLabelValue;
    if (evolutionLabelStage) evolutionLabelStage.textContent = stageLabelValue;
    if (evolutionLabel) evolutionLabel.classList.add('show');

    await new Promise((resolve) => {
      evolutionTimer = window.setTimeout(resolve, duration);
    });

    artStage.classList.remove('is-evolving');
    artImage.classList.remove('pb-evolution-new-image');
    oldImage.remove();
    if (evolutionLabel) evolutionLabel.classList.remove('show');
  };

  const applyStatus = async (data) => {
    if (!data || data.success !== true || !data.stage) return;

    const newPoints = Number(data.total_points || 0);
    const newStage = Number(data.stage.index || 0);
    const stageChanged = newStage !== currentStage;
    const pointsChanged = newPoints !== currentPoints;

    animateNumber(pointsMain, currentPoints, newPoints, stageChanged ? 1050 : 650);
    animateNumber(statPoints, currentPoints, newPoints, stageChanged ? 1050 : 650);

    if (stageChanged && newStage > currentStage) {
      const stages = Array.isArray(data.stage.stages) ? data.stage.stages : [];

      // If a single HR award crosses several thresholds, show every unlocked
      // growth form in order instead of jumping straight to the final tree.
      for (let index = currentStage + 1; index <= newStage; index++) {
        const definition = stages[index] || {};
        const label = definition.label || (index === newStage ? data.stage.label : `Stage ${index + 1}`);
        const asset = assetByStage[index] || data.stage.asset;

        updateMilestones(index, stages);
        await playEvolution(
          index,
          label,
          asset,
          index === newStage ? 1350 : 900
        );
      }
    } else if (stageChanged) {
      await playEvolution(newStage, data.stage.label, data.stage.asset, 1100);
    } else if (pointsChanged) {
      playPointPulse();
    }

    // Apply the authoritative final state returned by PHP after all visual
    // transitions are complete.
    updateCopy(data);

    currentPoints = newPoints;
    currentStage = newStage;
    page.dataset.totalPoints = String(newPoints);
    page.dataset.stageIndex = String(newStage);
  };

  const poll = async () => {
    if (polling || document.hidden) return;
    polling = true;

    try {
      const separator = page.dataset.growthStatusUrl.includes('?') ? '&' : '?';
      const response = await fetch(`${page.dataset.growthStatusUrl}${separator}_=${Date.now()}`, {
        method: 'GET',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      const contentType = response.headers.get('content-type') || '';
      if (!response.ok || !contentType.includes('application/json')) return;

      const data = await response.json();
      await applyStatus(data);
    } catch (error) {
      console.debug('Growth live update unavailable:', error);
    } finally {
      polling = false;
    }
  };

  window.setInterval(poll, 5000);
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) poll();
  });
});
