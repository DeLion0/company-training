<?php
require_once __DIR__ . '/../core/points-badges-data.php';
$viewer = current_user();
try {
    $data = pb_hr_data((int)$viewer['id']);
} catch (Throwable $ex) {
    error_log('Points & Badges query failed: ' . $ex->getMessage());
    $data = null;
}
$message = $_SESSION['points_badges_message'] ?? null;
$error = $_SESSION['points_badges_error'] ?? null;
unset($_SESSION['points_badges_message'], $_SESSION['points_badges_error']);
$today = (new DateTimeImmutable('today', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
?>
<main class="content pb-page pb-hr-page">
  <section class="pb-hero">
    <div>
      <span class="pb-kicker"><i data-lucide="sparkles"></i> RECOGNITION / EMPLOYEE GROWTH</span>
      <h1>Points &amp; Badges</h1>
      <p>Post instructor-provided points and badges, monitor employee growth, and view the company leaderboard.</p>
    </div>
    <div class="pb-hero-note">
      <i data-lucide="shield-check"></i>
      <div><span>POSTING CONTROL</span><strong>HR only</strong><small>Instructor/source is recorded for every award</small></div>
    </div>
  </section>

  <?php if ($message): ?><div class="pb-flash success"><i data-lucide="circle-check-big"></i><?= e((string)$message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="pb-flash error"><i data-lucide="triangle-alert"></i><?= e((string)$error) ?></div><?php endif; ?>

  <?php if ($data === null): ?>
    <section class="pb-empty-state"><i data-lucide="database-zap"></i><div><strong>Points &amp; Badges data is unavailable</strong><p>Confirm migration 012_points_badges.sql was applied and check the PHP error log.</p></div></section>
  <?php else: ?>
    <section class="pb-metrics">
      <article class="pb-metric"><span class="pb-metric-icon is-green"><i data-lucide="sparkles"></i></span><small>Total Points Posted</small><strong><?= number_format((int)$data['summary']['total_points']) ?></strong><p>Across all active employees</p></article>
      <article class="pb-metric"><span class="pb-metric-icon is-blue"><i data-lucide="users-round-check"></i></span><small>Employees With Points</small><strong><?= number_format((int)$data['summary']['employees_with_points']) ?><em>/<?= number_format(count($data['employees'])) ?></em></strong><p>Active employees recognized</p></article>
      <article class="pb-metric"><span class="pb-metric-icon is-purple"><i data-lucide="badge-check"></i></span><small>Badges Awarded</small><strong><?= number_format((int)$data['summary']['badge_awards']) ?></strong><p>Employee badge awards posted</p></article>
      <article class="pb-metric"><span class="pb-metric-icon is-amber"><i data-lucide="trophy"></i></span><small>Current Leader</small><strong class="pb-metric-name"><?= $data['summary']['top_employee'] ? e((string)$data['summary']['top_employee']['full_name']) : '—' ?></strong><p><?= $data['summary']['top_employee'] ? number_format((int)$data['summary']['top_employee']['total_points']) . ' points' : 'No points posted yet' ?></p></article>
    </section>

    <section class="pb-management-grid">
      <article class="pb-panel pb-leaderboard-panel">
        <div class="pb-panel-head">
          <div><span class="pb-section-kicker">COMPANY RANKING</span><h2>Employee Leaderboard</h2><p>Ranked by total points posted by HR.</p></div>
          <label class="pb-search"><i data-lucide="search"></i><input id="pbLeaderboardSearch" type="search" placeholder="Search employee..." aria-label="Search leaderboard"></label>
        </div>
        <div class="pb-leaderboard-head-simple" aria-hidden="true">
          <span></span><span></span><span>Employee</span><span>Growth Stage</span><span>Total Points</span>
        </div>
        <div class="pb-leaderboard">
          <?php foreach ($data['employees'] as $employee): ?>
            <div class="pb-leader-row pb-leader-row-simple" data-pb-search="<?= e(strtolower((string)$employee['full_name'] . ' ' . (string)$employee['employee_code'] . ' ' . (string)$employee['department_name'])) ?>">
              <span class="pb-rank <?= (int)$employee['rank'] <= 3 ? 'top' : '' ?>"><?= (int)$employee['rank'] ?></span>
              <span class="pb-avatar"><?= e(strtoupper(substr((string)$employee['full_name'],0,1))) ?></span>
              <span class="pb-leader-person"><strong><?= e((string)$employee['full_name']) ?></strong><small><?= e((string)$employee['department_name']) ?> · <?= e((string)$employee['employee_code']) ?></small></span>
              <span class="pb-stage-pill"><i data-lucide="sprout"></i><?= e((string)$employee['tree']['label']) ?></span>
              <span class="pb-leader-points pb-leader-points-emphasis"><strong><?= number_format((int)$employee['total_points']) ?></strong><small>points</small></span>
            </div>
          <?php endforeach; ?>
        </div>
        <div id="pbLeaderboardEmpty" class="pb-inline-empty" hidden>No employees match your search.</div>
      </article>

      <div class="pb-action-stack">
        <article class="pb-panel">
          <div class="pb-panel-head compact"><div><span class="pb-section-kicker">POST RECOGNITION</span><h2>Award Points</h2><p>Record points supplied by an instructor.</p></div><span class="pb-action-icon is-green"><i data-lucide="plus-circle"></i></span></div>
          <form method="post" action="index.php?page=points-badges" class="pb-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="points_badges_action" value="award_points">
            <label><span>Employee</span><select name="employee_id" required><option value="">Select employee</option><?php foreach ($data['employees'] as $employee): ?><option value="<?= (int)$employee['id'] ?>"><?= e((string)$employee['full_name']) ?> · <?= e((string)$employee['employee_code']) ?></option><?php endforeach; ?></select></label>
            <div class="pb-form-row"><label><span>Points</span><input type="number" name="points" min="1" max="<?= PB_MAX_POINTS_PER_AWARD ?>" step="1" placeholder="e.g. 10" required></label><label><span>Award Date</span><input type="date" name="awarded_on" max="<?= e($today) ?>" value="<?= e($today) ?>" required></label></div>
            <label><span>Instructor / Source</span><input type="text" name="source_name" maxlength="120" placeholder="Who provided the points?" required></label>
            <label><span>Reason <small>optional</small></span><textarea name="reason" maxlength="255" rows="3" placeholder="Reason for the point award"></textarea></label>
            <button type="submit" class="pb-primary-button"><i data-lucide="sparkles"></i>Post Points</button>
          </form>
        </article>

        <article class="pb-panel">
          <div class="pb-panel-head compact"><div><span class="pb-section-kicker">POST RECOGNITION</span><h2>Award Badge</h2><p>Post an instructor-provided badge.</p></div><span class="pb-action-icon is-purple"><i data-lucide="badge-check"></i></span></div>
          <form method="post" action="index.php?page=points-badges" class="pb-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="points_badges_action" value="award_badge">
            <label><span>Employee</span><select name="employee_id" required><option value="">Select employee</option><?php foreach ($data['employees'] as $employee): ?><option value="<?= (int)$employee['id'] ?>"><?= e((string)$employee['full_name']) ?></option><?php endforeach; ?></select></label>
            <label><span>Badge</span><select name="badge_id" required><option value="">Select badge</option><?php foreach ($data['badges'] as $badge): ?><option value="<?= (int)$badge['id'] ?>"><?= e((string)$badge['icon']) ?> <?= e((string)$badge['badge_name']) ?></option><?php endforeach; ?></select></label>
            <div class="pb-form-row"><label><span>Instructor / Source</span><input type="text" name="source_name" maxlength="120" required></label><label><span>Award Date</span><input type="date" name="awarded_on" max="<?= e($today) ?>" value="<?= e($today) ?>" required></label></div>
            <label><span>Badge Note <small>optional</small></span><textarea name="note" maxlength="255" rows="2" placeholder="Recognition note"></textarea></label>
            <button type="submit" class="pb-primary-button"><i data-lucide="badge-plus"></i>Award Badge</button>
          </form>
        </article>

        <article class="pb-panel">
          <div class="pb-panel-head compact"><div><span class="pb-section-kicker">BADGE CATALOG</span><h2>Create Badge</h2><p>Add another recognition badge.</p></div><span class="pb-action-icon is-blue"><i data-lucide="shapes"></i></span></div>
          <form method="post" action="index.php?page=points-badges" class="pb-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="points_badges_action" value="create_badge">
            <div class="pb-form-row"><label><span>Badge Name</span><input type="text" name="badge_name" maxlength="120" placeholder="e.g. Growth Champion" required></label><label><span>Icon</span><select name="icon_key" required><?php foreach (pb_icon_options() as $key => $icon): ?><option value="<?= e($key) ?>"><?= e((string)$icon['emoji']) ?> <?= e((string)$icon['label']) ?></option><?php endforeach; ?></select></label></div>
            <label><span>Description <small>optional</small></span><textarea name="badge_description" maxlength="255" rows="2" placeholder="What this badge recognizes"></textarea></label>
            <button type="submit" class="pb-secondary-button"><i data-lucide="plus"></i>Create Badge</button>
          </form>
        </article>
      </div>
    </section>

    <section class="pb-bottom-grid">
      <article class="pb-panel"><div class="pb-panel-head"><div><span class="pb-section-kicker">BADGE CATALOG</span><h2>Available Badges</h2><p>Badges HR can post to employees.</p></div></div><div class="pb-badge-catalog"><?php foreach ($data['badges'] as $badge): ?><div class="pb-badge-card"><span class="pb-badge-icon"><?= e((string)$badge['icon']) ?></span><div><strong><?= e((string)$badge['badge_name']) ?></strong><p><?= e((string)($badge['description'] ?? 'Recognition badge')) ?></p></div></div><?php endforeach; ?></div></article>
      <article class="pb-panel"><div class="pb-panel-head"><div><span class="pb-section-kicker">RECENT POSTS</span><h2>Recognition Activity</h2><p>Latest points and badge entries.</p></div></div><div class="pb-activity-list"><?php $activityCount=0; foreach (array_slice($data['recent_points'],0,5) as $entry): $activityCount++; ?><div class="pb-activity-item"><span class="pb-activity-icon is-points"><i data-lucide="plus"></i></span><div><strong>+<?= (int)$entry['points'] ?> points · <?= e((string)$entry['full_name']) ?></strong><small><?= e((string)$entry['source_name']) ?> · <?= e(date('M j, Y',strtotime((string)$entry['awarded_on']))) ?></small></div></div><?php endforeach; ?><?php foreach (array_slice($data['recent_badges'],0,5) as $entry): $activityCount++; ?><div class="pb-activity-item"><span class="pb-activity-icon is-badge"><?= e(pb_badge_icon((string)$entry['icon_key'])) ?></span><div><strong><?= e((string)$entry['badge_name']) ?> · <?= e((string)$entry['full_name']) ?></strong><small><?= e((string)$entry['source_name']) ?> · <?= e(date('M j, Y',strtotime((string)$entry['awarded_on']))) ?></small></div></div><?php endforeach; ?><?php if ($activityCount===0): ?><div class="pb-inline-empty">No recognition activity yet.</div><?php endif; ?></div></article>
    </section>
  <?php endif; ?>
</main>
