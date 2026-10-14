<?php

if (!isset($employeePortalData) || !is_array($employeePortalData)) {
    throw new RuntimeException('Employee portal data was not loaded.');
}

$employee = $employeePortalData['employee'];
$months = $employeePortalData['months'];
$monthly = $employeePortalData['monthly'];
$latest = $employeePortalData['latest'];
$history = $employeePortalData['performance_history'];
$latestScore = $latest ? (float)$latest['score'] : null;
$historyScores = array_map(static fn(array $row): float => (float)$row['score'], $history);
$averageScore = $historyScores ? round(array_sum($historyScores) / count($historyScores), 1) : null;
$bestScore = $historyScores ? max($historyScores) : null;
?>

<main class="employee-content employee-module-page">
    <header class="employee-page-heading">
        <div>
            <span class="employee-page-kicker">DEVELOPMENT / PERFORMANCE</span>
            <h1>My Performance</h1>
            <p>Your official monthly performance history. This page is read-only for employees.</p>
        </div>

        <div class="employee-heading-stat <?= $latestScore !== null && $latestScore < 50 ? 'is-amber' : '' ?>">
            <span>Latest Score</span>
            <strong><?= $latestScore === null ? '—' : number_format($latestScore, 1) . '%' ?></strong>
            <small><?= $latest ? e(date('F Y', strtotime((string)$latest['review_month']))) : 'No evaluation yet' ?></small>
        </div>
    </header>

    <section class="employee-performance-summary-grid">
        <article class="employee-summary-card">
            <span class="employee-summary-icon is-green"><i data-lucide="activity"></i></span>
            <small>Latest Performance</small>
            <strong><?= $latestScore === null ? '—' : number_format($latestScore, 1) . '%' ?></strong>
            <p><?= $latestScore === null ? 'No official score yet.' : ($latestScore < 50 ? 'Below the current training priority threshold.' : 'At or above the current training priority threshold.') ?></p>
        </article>

        <article class="employee-summary-card">
            <span class="employee-summary-icon is-blue"><i data-lucide="chart-no-axes-combined"></i></span>
            <small>12-Month Average</small>
            <strong><?= $averageScore === null ? '—' : number_format($averageScore, 1) . '%' ?></strong>
            <p>Average of the official monthly ratings currently recorded.</p>
        </article>

        <article class="employee-summary-card">
            <span class="employee-summary-icon is-purple"><i data-lucide="trophy"></i></span>
            <small>Best Recorded Score</small>
            <strong><?= $bestScore === null ? '—' : number_format($bestScore, 1) . '%' ?></strong>
            <p>Highest score in the latest available performance history.</p>
        </article>
    </section>

    <section class="employee-panel employee-performance-main-panel">
        <div class="employee-panel-head">
            <div>
                <span class="employee-section-kicker">SIX-MONTH TREND</span>
                <h2>Monthly Performance</h2>
                <p>Missing months mean no official rating was saved for that month.</p>
            </div>

            <span class="employee-read-only-pill"><i data-lucide="lock-keyhole"></i> Read only</span>
        </div>

        <div class="employee-performance-chart employee-performance-chart-large" role="img" aria-label="Six month employee performance chart">
            <div class="employee-chart-y"><span>100</span><span>75</span><span>50</span><span>25</span><span>0</span></div>

            <div class="employee-chart-plot">
                <div class="employee-chart-grid"><i></i><i></i><i></i><i></i><i></i></div>

                <div class="employee-chart-bars">
                    <?php foreach ($months as $month): ?>
                        <?php
                        $score = $monthly[$month['key']] ?? null;
                        $height = $score === null ? 0 : max(3, min(100, (float)$score));
                        ?>
                        <div class="employee-chart-month">
                            <div class="employee-chart-bar-area" title="<?= e((string)$month['long_label']) ?><?= $score !== null ? ' · ' . number_format((float)$score, 1) . '%' : ' · No rating' ?>">
                                <?php if ($score !== null): ?>
                                    <span class="employee-chart-score"><?= number_format((float)$score, 1) ?>%</span>
                                    <span class="employee-chart-bar <?= (float)$score < 50 ? 'is-low' : '' ?>" style="height:<?= $height ?>%"></span>
                                <?php else: ?>
                                    <span class="employee-chart-no-data">No rating</span>
                                <?php endif; ?>
                            </div>
                            <strong><?= e((string)$month['label']) ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="employee-performance-note">
            <i data-lucide="info"></i>
            <span>Performance ratings are recorded by HR or your Department Head. Employees cannot edit their own official scores.</span>
        </div>
    </section>

    <section class="employee-panel">
        <div class="employee-panel-head">
            <div>
                <span class="employee-section-kicker">HISTORY</span>
                <h2>Monthly Rating History</h2>
                <p>Latest official records saved for your employee profile.</p>
            </div>
        </div>

        <?php if ($history): ?>
            <div class="employee-history-table-wrap">
                <table class="employee-history-table">
                    <thead><tr><th>MONTH</th><th>SCORE</th><th>NOTES</th><th>SOURCE</th></tr></thead>
                    <tbody>
                        <?php foreach ($history as $row): ?>
                            <tr>
                                <td><strong><?= e(date('F Y', strtotime((string)$row['review_month']))) ?></strong></td>
                                <td><span class="employee-score-pill <?= (float)$row['score'] < 50 ? 'is-low' : '' ?>"><?= number_format((float)$row['score'], 1) ?>%</span></td>
                                <td><?= e(trim((string)($row['notes'] ?? '')) ?: '—') ?></td>
                                <td><?= e(ucwords(str_replace('_', ' ', (string)$row['source']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="employee-empty horizontal">
                <i data-lucide="clipboard-minus"></i>
                <div><strong>No performance history yet</strong><span>Your official monthly ratings will appear here after HR or your Department Head records them.</span></div>
            </div>
        <?php endif; ?>
    </section>
</main>
