<?php
$user = authenticated_user();

if (!$user || ($user['role'] ?? '') !== 'hr') {
    http_response_code(403);
    exit('Access denied.');
}
?>
<?php
// Called only after authentication + HR authorization by the router.
require_once __DIR__ . '/../core/training-data.php';
$trainingData = training_overview((int)$user['id']);
$trainingStats = $trainingData['stats'];
$trainingPrograms = $trainingData['programs'];
$trainingFlash = $_SESSION['training_message'] ?? null;
$trainingError = $_SESSION['training_error'] ?? null;
unset($_SESSION['training_message'], $_SESSION['training_error']);
?>
<main class="content">
    <?php if ($trainingFlash): ?><div class="subtle-info" role="status" style="margin-bottom:18px;color:#176b40"><?= e((string)$trainingFlash) ?></div><?php endif; ?>
    <?php if ($trainingError): ?><div class="session-error" role="alert" style="margin-bottom:18px"><?= e((string)$trainingError) ?></div><?php endif; ?>
    <div class="page-heading">
        <div>
            <div class="eyebrow"><span class="eyebrow-dot"></span> LEARNING & DEVELOPMENT</div>
            <h1>Training Programs<span class="heading-period">.</span></h1>
            <p>Create meaningful learning experiences that help your people grow.</p>
        </div><button type="button" class="button button-primary" id="newProgram"><i data-lucide="plus"></i> New program</button>
    </div>
    <section class="metrics" aria-label="Overview">
        <div class="metric">
            <div class="metric-icon leaf"><i data-lucide="book-open"></i></div><span>Active programs</span><strong id="activeMetric"><?= (int)$trainingStats['programs'] ?></strong><small>Published training programs</small>
        </div>
        <div class="metric">
            <div class="metric-icon sky"><i data-lucide="users"></i></div><span>Total capacity</span><strong id="capacityMetric"><?= (int)$trainingStats['capacity'] ?></strong><small>Available training seats</small>
        </div>
        <div class="metric">
            <div class="metric-icon peach"><i data-lucide="calendar-days"></i></div><span>Scheduled sessions</span><strong id="sessionMetric"><?= (int)$trainingStats['upcoming'] ?></strong><small>Upcoming learning sessions</small>
        </div>
    </section>
    <div class="content-grid">
        <section class="form-panel" id="formPanel">
            <div class="panel-heading">
                <div class="panel-icon"><i data-lucide="plus-circle"></i></div>
                <div>
                    <h2>Create a training program</h2>
                    <p>Build a learning experience, one detail at a time.</p>
                </div><span class="draft-tag">NEW DRAFT</span>
            </div>
            <form id="trainingForm" method="post" action="?page=training" novalidate>
                <input type="hidden" name="training_action" value="create">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <div class="form-section">
                    <div class="section-kicker">01 <span></span> PROGRAM INFORMATION</div>
                    <div class="field"><label for="title">Program title <b>*</b></label><input required maxlength="160" id="title" name="title" placeholder="e.g. Effective Workplace Communication"></div>
                    <div class="field"><label for="purpose">Purpose / description <b>*</b></label><textarea required id="purpose" rows="3" placeholder="Why is this training important and who is it for?"></textarea></div>
                    <div class="field"><label for="objectives">Learning objectives <b>*</b></label><textarea required id="objectives" rows="3" placeholder="What should participants be able to do after this training?"></textarea></div>
                </div>
                <div class="form-section">
                    <div class="section-kicker">02 <span></span> SCHEDULE & RECURRENCE</div>
                    <div class="subtle-info"><i data-lucide="info"></i><span>Choose a date range, weekly meeting day, and number of sessions. We'll create the schedule for you.</span></div>
                    <div class="two-col">
                        <div class="field"><label for="startDate">Start date <b>*</b></label><input required id="startDate" type="date"></div>
                        <div class="field"><label for="endDate">End date <b>*</b></label><input required id="endDate" type="date"></div>
                    </div>
                    <div class="two-col">
                        <div class="field"><label for="weekday">Repeat every <b>*</b></label><select id="weekday">
                                <option value="0">Sunday</option>
                                <option value="1">Monday</option>
                                <option value="2">Tuesday</option>
                                <option value="3" selected>Wednesday</option>
                                <option value="4">Thursday</option>
                                <option value="5">Friday</option>
                                <option value="6">Saturday</option>
                            </select></div>
                        <div class="field"><label for="meetingCount">Number of meetings <b>*</b></label><input id="meetingCount" type="number" min="1" max="100" value="3" required></div>
                    </div>
                    <div class="two-col">
                        <div class="field"><label for="startTime">Start time <b>*</b></label><input id="startTime" type="time" value="06:00" required></div>
                        <div class="field"><label for="endTime">End time <b>*</b></label><input id="endTime" type="time" value="10:00" required></div>
                    </div>
                    <div class="field"><label>Generated sessions <span class="field-optional">LIVE PREVIEW</span></label>
                        <div id="sessionsList" class="sessions-list" aria-live="polite"></div>
                        <p class="field-hint" id="dateHint"></p>
                    </div>
                </div>
                <div class="form-section">
                    <div class="section-kicker">03 <span></span> FACILITATOR & VENUE</div>
                    <div class="field"><label for="instructors">Instructor(s) <b>*</b></label><input required id="instructors" placeholder="e.g. Dr. Maria Santos, John Cruz">
                        <p class="field-hint">Separate multiple instructors with commas.</p>
                    </div>
                    <div class="field"><label for="venue">Location / venue <b>*</b></label>
                        <div class="input-with-icon"><i data-lucide="map-pin"></i><input required id="venue" placeholder="e.g. Conference Room A, Main Office"></div>
                    </div>
                    <div class="field"><label for="capacity">Maximum participants <b>*</b></label><input id="capacity" type="number" min="1" max="10000" value="30" required></div>
                </div>
                <div class="form-actions"><span id="formMessage" role="status"></span><button type="button" class="button button-quiet" id="resetForm">Clear form</button><button type="submit" class="button button-primary"><i data-lucide="eye"></i> Review program <i data-lucide="arrow-right"></i></button></div>
            </form>
        </section>
        <aside class="right-column">
            <div class="tips-card">
                <div class="tips-top"><span class="tips-icon"><i data-lucide="lightbulb"></i></span><span>QUICK TIP</span></div>
                <h3>Better skills, stronger teams.</h3>
                <p>Clear learning objectives help employees see how a training program can improve their day-to-day work.</p>
                <div class="tips-pattern"><i data-lucide="sprout"></i></div>
            </div>
            <div class="summary-card">
                <div class="aside-heading"><i data-lucide="calendar-check"></i>
                    <h3>Schedule at a glance</h3>
                </div>
                <div class="summary-line"><span>Frequency</span><strong id="summaryFrequency">Every Wednesday</strong></div>
                <div class="summary-line"><span>Meetings</span><strong id="summaryMeetings">3 sessions</strong></div>
                <div class="summary-line"><span>Duration</span><strong id="summaryDuration">4h per session</strong></div>
                <div class="summary-divider"></div>
                <p>Dates update instantly when you change the schedule.</p>
            </div>
            <div class="notice-card"><i data-lucide="shield-check"></i>
                <p><strong>Review before publishing</strong><br>Programs are saved to the company database only after final review and confirmation. No emails are sent.</p>
            </div>
        </aside>
    </div>

<section class="form-panel" aria-labelledby="savedProgramsTitle" style="margin-top:28px;padding:24px">
  <div class="panel-heading"><div class="panel-icon"><i data-lucide="list-checks"></i></div><div><h2 id="savedProgramsTitle">Saved training programs</h2><p>Published programs in your company database.</p></div></div>
  <?php if (!$trainingPrograms): ?>
    <p class="field-hint">No training programs saved yet. Create and publish your first program above.</p>
  <?php else: ?>
    <div style="display:grid;gap:12px;margin-top:16px">
    <?php foreach ($trainingPrograms as $program): ?>
      <details class="gf-training-saved" style="border:1px solid #dce9e0;border-radius:12px;padding:15px;background:#fff">
        <summary style="cursor:pointer;font-weight:700;display:flex;gap:16px;justify-content:space-between;flex-wrap:wrap">
          <span><?= e($program['title']) ?></span>
          <span style="color:#26834d;font-weight:600;font-size:12px"><?= e(ucfirst($program['status'])) ?> · <?= (int)$program['meeting_count'] ?> sessions · <?= (int)$program['capacity'] ?> seats</span>
        </summary>
        <div style="padding-top:12px;line-height:1.7">
          <p><strong>Purpose:</strong> <?= nl2br(e($program['purpose'])) ?></p>
          <p><strong>Objectives:</strong> <?= nl2br(e($program['objectives'])) ?></p>
          <p><strong>Instructor(s):</strong> <?= e($program['instructors']) ?></p>
          <p><strong>Venue:</strong> <?= e($program['venue']) ?></p>
          <p><strong>Sessions:</strong></p>
          <ol style="padding-left:22px">
          <?php foreach($program['sessions'] as $session): ?>
            <li><?= e($session['session_date']) ?> · <?= e(substr($session['start_time'],0,5)) ?>–<?= e(substr($session['end_time'],0,5)) ?></li>
          <?php endforeach; ?>
          </ol>
          <p class="field-hint">Applications and approval counts will appear in the upcoming Participants module.</p>
        </div>
      </details>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
</main>
<dialog id="reviewDialog" class="review-dialog">
    <div class="dialog-heading">
        <div><span class="eyebrow">FINAL REVIEW</span>
            <h2>Review your program</h2>
        </div><button id="closeReview" class="icon-button" aria-label="Close review"><i data-lucide="x"></i></button>
    </div>
    <div id="reviewContents" class="review-body"></div>
    <div class="dialog-actions"><button class="button button-quiet" id="backToEdit">Back to edit</button><button class="button button-primary" id="publishButton"><i data-lucide="check-circle-2"></i> Publish Program</button></div>
</dialog>
<div id="toast" class="toast" role="status" aria-live="polite"></div>