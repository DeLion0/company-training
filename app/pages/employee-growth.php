<?php
require_once __DIR__ . '/../core/points-badges-data.php';

$employeePortalPreview = isset($employeePortalPreview) && $employeePortalPreview === true;
$employeePortalData = isset($employeePortalData) && is_array($employeePortalData)
    ? $employeePortalData
    : [];

$growth = null;

try {
    if ($employeePortalPreview) {
        $previewEmployee = $employeePortalData['employee'] ?? null;
        if (!$previewEmployee) {
            throw new RuntimeException('No employee is available for growth preview.');
        }
        $growth = pb_employee_growth_by_employee(
            (int)$employeePortalData['company_id'],
            (int)$previewEmployee['id']
        );
    } else {
        $growth = pb_employee_growth_for_user((int)(current_user()['id'] ?? 0));
    }
} catch (Throwable $ex) {
    error_log('Employee growth page failed: ' . $ex->getMessage());
    $growth = null;
}

$growthAssets = [
    0 => 'seed-hero.png',
    1 => 'sprout.png',
    2 => 'seedling.png',
    3 => 'sapling.png',
    4 => 'young-tree.png',
    5 => 'flourishing-tree.png',
    6 => 'full-growth.png',
];

$growthMilestoneAssets = [
    0 => 'seed.png',
    1 => 'sprout.png',
    2 => 'seedling.png',
    3 => 'sapling.png',
    4 => 'young-tree.png',
    5 => 'flourishing-tree.png',
    6 => 'full-growth.png',
];

$tree = null;
$stageIndex = 0;
$currentAsset = $growthAssets[0];
$journeyProgress = 0.0;

if ($growth !== null) {
    $tree = $growth['tree'];
    $stageIndex = (int)$tree['index'];
    $currentAsset = $growthAssets[$stageIndex] ?? $growthAssets[0];
    $journeyProgress = count($tree['stages']) > 1
        ? ($stageIndex / (count($tree['stages']) - 1)) * 100
        : 100;
}
?>
<main
  class="employee-content pb-page pb-employee-page"
  id="pbGrowthPage"
  <?php if ($growth !== null): ?>
    data-stage-index="<?= $stageIndex ?>"
    data-total-points="<?= (int)$growth['total_points'] ?>"
    data-growth-status-url="employee.php?module=growth&amp;growth_status=1"
  <?php endif; ?>
>
  <section class="pb-employee-hero">
    <div>
      <span class="pb-kicker"><i data-lucide="sprout"></i> MY RECOGNITION / GROWTH</span>
      <h1>My Growth</h1>
      <p>Your progress grows with every point and badge posted by HR from your instructors.</p>
    </div>
    <?php if ($growth !== null): ?>
      <div class="pb-rank-chip">
        <i data-lucide="trophy"></i>
        <div>
          <span>COMPANY RANK</span>
          <strong>#<span id="pbGrowthRankTop"><?= (int)($growth['rank'] ?? 0) ?></span> <small>/<?= (int)$growth['employee_count'] ?></small></strong>
        </div>
      </div>
    <?php endif; ?>
  </section>

  <?php if ($growth === null): ?>
    <section class="pb-empty-state">
      <i data-lucide="sprout"></i>
      <div><strong>Your growth data is not ready</strong><p>Ask HR to confirm that the Points &amp; Badges database migration is installed.</p></div>
    </section>
  <?php else: ?>
    <section class="pb-growth-grid">
      <article class="pb-tree-panel pb-tree-panel-art">
        <div class="pb-tree-copy">
          <span class="pb-section-kicker">YOUR GROWTH TREE</span>
          <h2 id="pbGrowthStageLabel"><?= e((string)$tree['label']) ?></h2>
          <div class="pb-tree-points"><span id="pbGrowthPointsMain"><?= number_format((int)$growth['total_points']) ?></span> <small>points</small></div>

          <p id="pbGrowthNextText">
            <?php if ($tree['next'] !== null): ?>
              <?= number_format((int)$tree['points_to_next']) ?> more point<?= (int)$tree['points_to_next'] === 1 ? '' : 's' ?> until <strong><?= e((string)$tree['next']['label']) ?></strong>.
            <?php else: ?>
              Full growth reached. Keep earning points to maintain your progress and company rank.
            <?php endif; ?>
          </p>

          <div class="pb-tree-progress">
            <div><span id="pbGrowthProgressStage"><?= e((string)$tree['label']) ?></span><strong id="pbGrowthProgressPercent"><?= number_format((float)$tree['progress'], 0) ?>%</strong></div>
            <div class="pb-tree-progress-track"><span id="pbGrowthProgressBar" style="width:<?= e((string)$tree['progress']) ?>%"></span></div>
          </div>
        </div>

        <div
          class="pb-growth-art-stage stage-<?= $stageIndex ?>"
          id="pbGrowthArtStage"
          data-stage-index="<?= $stageIndex ?>"
          role="img"
          aria-label="<?= e((string)$tree['label']) ?> growth illustration"
        >
          <div class="pb-growth-art-glow" aria-hidden="true"></div>
          <div class="pb-evolution-bloom" aria-hidden="true"></div>

          <img
            class="pb-growth-art-image"
            id="pbGrowthArtImage"
            src="assets/images/growth/<?= e($currentAsset) ?>"
            alt="<?= e((string)$tree['label']) ?> growth stage"
            loading="eager"
            decoding="async"
          >

          <div class="pb-growth-ambient" aria-hidden="true">

            <span class="pb-falling-leaf leaf-1"></span>
            <span class="pb-falling-leaf leaf-2"></span>
            <span class="pb-falling-leaf leaf-3"></span>
            <span class="pb-falling-leaf leaf-4"></span>
            <span class="pb-falling-leaf leaf-5"></span>

            <span class="pb-art-sparkle sparkle-1"></span>
            <span class="pb-art-sparkle sparkle-2"></span>
            <span class="pb-art-sparkle sparkle-3"></span>
          </div>

          <div class="pb-evolution-label" id="pbEvolutionLabel" aria-live="polite">
            <span>Growth unlocked</span>
            <strong id="pbEvolutionLabelStage"><?= e((string)$tree['label']) ?></strong>
          </div>
        </div>
      </article>

      <aside class="pb-growth-stats">
        <article><span class="pb-stat-icon is-green"><i data-lucide="sparkles"></i></span><small>Total Points</small><strong id="pbGrowthStatPoints"><?= number_format((int)$growth['total_points']) ?></strong></article>
        <article><span class="pb-stat-icon is-purple"><i data-lucide="badge-check"></i></span><small>Badges Earned</small><strong id="pbGrowthStatBadges"><?= count($growth['badges']) ?></strong></article>
        <article><span class="pb-stat-icon is-amber"><i data-lucide="trophy"></i></span><small>Company Rank</small><strong>#<span id="pbGrowthStatRank"><?= (int)($growth['rank'] ?? 0) ?></span></strong></article>
      </aside>
    </section>

    <section class="pb-panel pb-milestones-panel pb-milestones-art">
      <div class="pb-panel-head">
        <div>
          <span class="pb-section-kicker">GROWTH JOURNEY</span>
          <h2>Growth Milestones</h2>
          <p>Each stage unlocks automatically as your total points increase.</p>
        </div>
        <div class="pb-milestone-summary">
          <span id="pbGrowthStageCount">Stage <?= $stageIndex + 1 ?> of <?= count($tree['stages']) ?></span>
          <strong id="pbGrowthStageSummary"><?= e((string)$tree['label']) ?></strong>
        </div>
      </div>

      <div class="pb-art-journey-wrap">
        <div class="pb-art-journey-line" aria-hidden="true">
          <span id="pbGrowthJourneyLine" style="width:<?= e((string)$journeyProgress) ?>%"></span>
        </div>

        <div class="pb-art-journey">
          <?php foreach ($tree['stages'] as $index => $stage): ?>
            <?php
              $reached = $stageIndex >= $index;
              $current = $stageIndex === $index;
              $asset = $growthMilestoneAssets[$index] ?? $growthMilestoneAssets[0];
            ?>
            <article
              class="pb-art-step <?= $reached ? 'reached' : '' ?> <?= $current ? 'current' : '' ?> stage-<?= $index ?>"
              data-stage-index="<?= $index ?>"
            >
              <div class="pb-art-step-node"><span><?= $reached ? '✓' : ($index + 1) ?></span></div>

              <div class="pb-art-step-visual" aria-hidden="true">
                <img src="assets/images/growth/<?= e($asset) ?>" alt="" loading="lazy" decoding="async">
                <span class="pb-step-glint"></span>
              </div>

              <strong><?= e((string)$stage['label']) ?></strong>
              <small><?= (int)$stage['min'] ?>+ pts</small>

              <span class="pb-stage-state <?= $current ? 'pb-current-stage-label' : 'pb-stage-status-label' ?> <?= (!$reached && !$current) ? 'locked' : '' ?>">
                <?= $current ? 'Current' : ($reached ? 'Unlocked' : 'Locked') ?>
              </span>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="pb-employee-bottom-grid">
      <article class="pb-panel">
        <div class="pb-panel-head"><div><span class="pb-section-kicker">YOUR COLLECTION</span><h2>My Badges</h2><p>Recognition badges posted to your profile.</p></div><span class="pb-count-pill" id="pbGrowthBadgeCount"><?= count($growth['badges']) ?></span></div>
        <?php if ($growth['badges']): ?>
          <div class="pb-earned-badges">
            <?php foreach ($growth['badges'] as $badge): ?>
              <div class="pb-earned-badge"><span><?= e((string)$badge['icon']) ?></span><div><strong><?= e((string)$badge['badge_name']) ?></strong><p><?= e((string)($badge['description'] ?? 'Recognition badge')) ?></p><small>From <?= e((string)$badge['source_name']) ?> · <?= e(date('M j, Y', strtotime((string)$badge['awarded_on']))) ?></small></div></div>
            <?php endforeach; ?>
          </div>
        <?php else: ?><div class="pb-inline-empty">No badges yet. Earned badges will appear here.</div><?php endif; ?>
      </article>

      <article class="pb-panel">
        <div class="pb-panel-head"><div><span class="pb-section-kicker">POINT HISTORY</span><h2>Recent Points</h2><p>Latest point awards posted by HR.</p></div></div>
        <?php if ($growth['point_history']): ?>
          <div class="pb-point-history">
            <?php foreach ($growth['point_history'] as $entry): ?>
              <div class="pb-point-entry"><span>+<?= (int)$entry['points'] ?></span><div><strong><?= e((string)($entry['reason'] ?? 'Point award')) ?></strong><small><?= e((string)$entry['source_name']) ?> · <?= e(date('M j, Y', strtotime((string)$entry['awarded_on']))) ?></small></div></div>
            <?php endforeach; ?>
          </div>
        <?php else: ?><div class="pb-inline-empty">No points have been posted yet.</div><?php endif; ?>
      </article>
    </section>
  <?php endif; ?>
</main>
