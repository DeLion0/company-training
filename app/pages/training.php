<?php

$user = authenticated_user();

if (!$user || ($user['role'] ?? '') !== 'hr') {
    http_response_code(403);
    exit('Access denied.');
}

require_once __DIR__ . '/../core/training-data.php';

$trainingData = training_overview(
    (int) $user['id']
);

$trainingStats =
    $trainingData['stats'];

$trainingPrograms =
    $trainingData['programs'];

$trainingDepartments =
    $trainingData['departments'] ?? [];

$priorityThreshold =
    (float) (
        $trainingData['priority_threshold']
        ?? 50
    );

$trainingFlash =
    $_SESSION['training_message']
    ?? null;

$trainingError =
    $_SESSION['training_error']
    ?? null;

unset(
    $_SESSION['training_message'],
    $_SESSION['training_error']
);

$priorityThresholdLabel =
    rtrim(
        rtrim(
            number_format(
                $priorityThreshold,
                2
            ),
            '0'
        ),
        '.'
    );
?>

<main class="content">

    <!-- ==========================================
         FLASH MESSAGE
         ========================================== -->

    <?php if ($trainingFlash): ?>

        <div
            class="subtle-info"
            role="status"
            style="
                margin-bottom:18px;
                color:#176b40;
            ">
            <i data-lucide="circle-check"></i>

            <span>
                <?= e(
                    (string) $trainingFlash
                ) ?>
            </span>
        </div>

    <?php endif; ?>


    <?php if ($trainingError): ?>

        <div
            class="session-error"
            role="alert"
            style="
                margin-bottom:18px;
                padding:14px 16px;
                border:1px solid #f0d3ce;
                background:#fff8f7;
                border-radius:10px;
            ">
            <?= e(
                (string) $trainingError
            ) ?>
        </div>

    <?php endif; ?>


    <!-- ==========================================
         PAGE HEADER
         ========================================== -->

    <div class="page-heading">

        <div>

            <div class="eyebrow">

                <span
                    class="eyebrow-dot"></span>

                LEARNING & DEVELOPMENT

            </div>


            <h1>

                Training Programs

                <span
                    class="heading-period">.</span>

            </h1>


            <p>

                Create meaningful learning
                experiences that help your
                people grow.

            </p>

        </div>


        <button
            type="button"
            class="button button-primary"
            id="newProgram">

            <i data-lucide="plus"></i>

            New program

        </button>

    </div>


    <!-- ==========================================
         METRICS
         ========================================== -->

    <section
        class="metrics"
        aria-label="Training overview">

        <div class="metric">

            <div
                class="metric-icon leaf">

                <i
                    data-lucide="book-open"></i>

            </div>


            <span>
                Active programs
            </span>


            <strong
                id="activeMetric">
                <?= (int) (
                    $trainingStats['programs']
                    ?? 0
                ) ?>
            </strong>


            <small>
                Published training programs
            </small>

        </div>


        <div class="metric">

            <div
                class="metric-icon sky">

                <i
                    data-lucide="users"></i>

            </div>


            <span>
                Total capacity
            </span>


            <strong
                id="capacityMetric">
                <?= (int) (
                    $trainingStats['capacity']
                    ?? 0
                ) ?>
            </strong>


            <small>
                Available training seats
            </small>

        </div>


        <div class="metric">

            <div
                class="metric-icon peach">

                <i
                    data-lucide="calendar-days"></i>

            </div>


            <span>
                Scheduled sessions
            </span>


            <strong
                id="sessionMetric">
                <?= (int) (
                    $trainingStats['upcoming']
                    ?? 0
                ) ?>
            </strong>


            <small>
                Upcoming learning sessions
            </small>

        </div>

    </section>


    <!-- ==========================================
         MAIN GRID
         ========================================== -->

    <div class="content-grid">


        <!-- ======================================
             CREATE TRAINING
             ====================================== -->

        <section
            class="form-panel"
            id="formPanel">

            <div class="panel-heading">

                <div class="panel-icon">

                    <i
                        data-lucide="plus-circle"></i>

                </div>


                <div>

                    <h2>
                        Create a training program
                    </h2>

                    <p>
                        Build a learning experience,
                        one detail at a time.
                    </p>

                </div>


                <span class="draft-tag">

                    NEW DRAFT

                </span>

            </div>


            <form
                id="trainingForm"
                method="post"
                action="?page=training"
                novalidate>

                <input
                    type="hidden"
                    name="training_action"
                    value="create">

                <input
                    type="hidden"
                    name="csrf"
                    value="<?= e(
                                csrf_token()
                            ) ?>">


                <!-- ==================================
                     01 PROGRAM INFORMATION
                     ================================== -->

                <div class="form-section">

                    <div class="section-kicker">

                        01

                        <span></span>

                        PROGRAM INFORMATION

                    </div>


                    <div class="field">

                        <label for="title">

                            Program title

                            <b>*</b>

                        </label>


                        <input
                            required
                            maxlength="160"
                            id="title"
                            name="title"
                            placeholder="e.g. Effective Workplace Communication">

                    </div>


                    <div class="field">

                        <label for="purpose">

                            Purpose / description

                            <b>*</b>

                        </label>


                        <textarea
                            required
                            id="purpose"
                            name="purpose"
                            rows="3"
                            placeholder="Why is this training important and who is it for?"></textarea>

                    </div>


                    <div class="field">

                        <label for="objectives">

                            Learning objectives

                            <b>*</b>

                        </label>


                        <textarea
                            required
                            id="objectives"
                            name="objectives"
                            rows="3"
                            placeholder="What should participants be able to do after this training?"></textarea>

                    </div>

                </div>


                <!-- ==================================
                     02 SCHEDULE
                     ================================== -->

                <div class="form-section">

                    <div class="section-kicker">

                        02

                        <span></span>

                        SCHEDULE & RECURRENCE

                    </div>


                    <div class="subtle-info">

                        <i
                            data-lucide="info"></i>


                        <span>

                            Choose a date range,
                            weekly meeting day,
                            and number of sessions.

                            We'll create the
                            schedule for you.

                        </span>

                    </div>


                    <div class="two-col">

                        <div class="field">

                            <label
                                for="startDate">

                                Start date

                                <b>*</b>

                            </label>


                            <input
                                required
                                id="startDate"
                                name="startDate"
                                type="date">

                        </div>


                        <div class="field">

                            <label
                                for="endDate">

                                End date

                                <b>*</b>

                            </label>


                            <input
                                required
                                id="endDate"
                                name="endDate"
                                type="date">

                        </div>

                    </div>


                    <div class="two-col">

                        <div class="field">

                            <label
                                for="weekday">

                                Repeat every

                                <b>*</b>

                            </label>


                            <select
                                id="weekday"
                                name="weekday">

                                <option value="0">
                                    Sunday
                                </option>

                                <option value="1">
                                    Monday
                                </option>

                                <option value="2">
                                    Tuesday
                                </option>

                                <option
                                    value="3"
                                    selected>
                                    Wednesday
                                </option>

                                <option value="4">
                                    Thursday
                                </option>

                                <option value="5">
                                    Friday
                                </option>

                                <option value="6">
                                    Saturday
                                </option>

                            </select>

                        </div>


                        <div class="field">

                            <label
                                for="meetingCount">

                                Number of meetings

                                <b>*</b>

                            </label>


                            <input
                                id="meetingCount"
                                name="meetingCount"
                                type="number"
                                min="1"
                                max="100"
                                value="3"
                                required>

                        </div>

                    </div>


                    <div class="two-col">

                        <div class="field">

                            <label
                                for="startTime">

                                Start time

                                <b>*</b>

                            </label>


                            <input
                                id="startTime"
                                name="startTime"
                                type="time"
                                value="06:00"
                                required>

                        </div>


                        <div class="field">

                            <label
                                for="endTime">

                                End time

                                <b>*</b>

                            </label>


                            <input
                                id="endTime"
                                name="endTime"
                                type="time"
                                value="10:00"
                                required>

                        </div>

                    </div>


                    <div class="field">

                        <label>

                            Generated sessions

                            <span
                                class="field-optional">
                                LIVE PREVIEW
                            </span>

                        </label>


                        <div
                            id="sessionsList"
                            class="sessions-list"
                            aria-live="polite"></div>


                        <p
                            class="field-hint"
                            id="dateHint"></p>

                    </div>

                </div>


                <!-- ==================================
                     03 TARGET DEPARTMENTS
                     ================================== -->

                <div class="form-section">

                    <div class="section-kicker">

                        03

                        <span></span>

                        TARGET DEPARTMENTS

                    </div>


                    <div
                        class="
                            subtle-info
                            training-target-note
                        ">

                        <i
                            data-lucide="target"></i>


                        <span>

                            Select the departments
                            connected to this training.

                            Active employees whose
                            latest monthly performance
                            average is below

                            <strong>
                                <?= e(
                                    $priorityThresholdLabel
                                ) ?>%
                            </strong>

                            will automatically receive
                            a

                            <strong>
                                Priority to Take
                            </strong>

                            recommendation.

                            They are not automatically
                            joined.

                        </span>

                    </div>


                    <?php if (
                        empty($trainingDepartments)
                    ): ?>

                        <div
                            class="
                                department-empty
                                target-empty-state
                            ">

                            <i
                                data-lucide="building-2"></i>


                            <div>

                                <strong>
                                    No departments available
                                </strong>


                                <p>

                                    Add a department first
                                    before publishing a
                                    targeted training program.

                                </p>

                            </div>

                        </div>


                    <?php else: ?>


                        <div
                            class="
                                department-selector
                                target-department-grid
                            "
                            id="departmentSelector">

                            <?php foreach (
                                $trainingDepartments
                                as $department
                            ): ?>

                                <?php

                                $departmentId =
                                    (int) (
                                        $department['id']
                                        ?? 0
                                    );

                                $departmentName =
                                    (string) (
                                        $department['name']
                                        ?? ''
                                    );

                                $employeeCount =
                                    (int) (
                                        $department['employee_count']
                                        ?? 0
                                    );

                                $priorityCandidates =
                                    (int) (
                                        $department['priority_candidates']
                                        ?? 0
                                    );

                                ?>


                                <label
                                    class="
                                        department-option
                                        target-card
                                    "
                                    data-department-card>

                                    <input
                                        type="checkbox"
                                        class="target-checkbox"
                                        name="department_ids[]"
                                        value="<?= $departmentId ?>"
                                        data-department-name="<?= e(
                                                                    $departmentName
                                                                ) ?>"
                                        data-priority-count="<?= $priorityCandidates ?>">


                                    <span
                                        class="
                                            department-check
                                            target-check
                                        ">

                                        <i
                                            data-lucide="check"></i>

                                    </span>


                                    <span
                                        class="
                                            department-copy
                                            target-card-content
                                        ">

                                        <strong
                                            class="
                                                target-card-title
                                            ">

                                            <?= e(
                                                $departmentName
                                            ) ?>

                                        </strong>


                                        <small
                                            class="
                                                target-card-meta
                                            ">

                                            <?= $employeeCount ?>

                                            active employee<?= $employeeCount === 1
                                                                ? ''
                                                                : 's'
                                                            ?>


                                            <?php if (
                                                $priorityCandidates > 0
                                            ): ?>

                                                ·

                                                <?= $priorityCandidates ?>

                                                currently below

                                                <?= e(
                                                    $priorityThresholdLabel
                                                ) ?>%

                                            <?php endif; ?>

                                        </small>

                                    </span>


                                    <?php if (
                                        $priorityCandidates > 0
                                    ): ?>

                                        <span
                                            class="
                                                priority-candidate-badge
                                            ">

                                            <?= $priorityCandidates ?>

                                            priority

                                        </span>

                                    <?php endif; ?>

                                </label>

                            <?php endforeach; ?>

                        </div>


                        <div
                            class="
                                department-selection-summary
                                target-summary
                            ">

                            <div>

                                <strong
                                    id="departmentSelectionCount">

                                    0 departments selected

                                </strong>


                                <span>

                                    Select at least one
                                    department.

                                </span>

                            </div>


                            <div
                                class="
                                    priority-estimate
                                ">

                                <span>

                                    Current priority
                                    estimate

                                </span>


                                <strong
                                    id="priorityEstimate">

                                    0 employees

                                </strong>

                            </div>

                        </div>

                    <?php endif; ?>

                </div>


                <!-- ==================================
                     04 FACILITATOR & VENUE
                     ================================== -->

                <div class="form-section">

                    <div class="section-kicker">

                        04

                        <span></span>

                        FACILITATOR & VENUE

                    </div>


                    <div class="field">

                        <label
                            for="instructors">

                            Instructor(s)

                            <b>*</b>

                        </label>


                        <input
                            required
                            id="instructors"
                            name="instructors"
                            placeholder="e.g. Dr. Maria Santos, John Cruz">


                        <p class="field-hint">

                            Separate multiple
                            instructors with commas.

                        </p>

                    </div>


                    <div class="field">

                        <label
                            for="venue">

                            Location / venue

                            <b>*</b>

                        </label>


                        <div
                            class="input-with-icon">

                            <i
                                data-lucide="map-pin"></i>


                            <input
                                required
                                id="venue"
                                name="venue"
                                placeholder="e.g. Conference Room A, Main Office">

                        </div>

                    </div>


                    <div class="field">

                        <label
                            for="capacity">

                            Maximum participants

                            <b>*</b>

                        </label>


                        <input
                            id="capacity"
                            name="capacity"
                            type="number"
                            min="1"
                            max="10000"
                            value="30"
                            required>

                    </div>

                </div>


                <!-- ==================================
                     ACTIONS
                     ================================== -->

                <div class="form-actions">

                    <span
                        id="formMessage"
                        role="status"></span>


                    <button
                        type="button"
                        class="
                            button
                            button-quiet
                        "
                        id="resetForm">

                        Clear form

                    </button>


                    <button
                        type="submit"
                        class="
                            button
                            button-primary
                        ">

                        <i
                            data-lucide="eye"></i>

                        Review program

                        <i
                            data-lucide="arrow-right"></i>

                    </button>

                </div>

            </form>

        </section>


        <!-- ======================================
             RIGHT SIDEBAR
             ====================================== -->

        <aside class="right-column">

            <div class="tips-card">

                <div class="tips-top">

                    <span
                        class="tips-icon">

                        <i
                            data-lucide="lightbulb"></i>

                    </span>


                    <span>
                        QUICK TIP
                    </span>

                </div>


                <h3>

                    Better skills,
                    stronger teams.

                </h3>


                <p>

                    Connect each training to
                    the departments that actually
                    need it.

                    Priority recommendations help
                    surface training to employees
                    who may need additional support.

                </p>


                <div
                    class="tips-pattern">

                    <i
                        data-lucide="sprout"></i>

                </div>

            </div>


            <div class="summary-card">

                <div class="aside-heading">

                    <i
                        data-lucide="calendar-check"></i>


                    <h3>
                        Schedule at a glance
                    </h3>

                </div>


                <div class="summary-line">

                    <span>
                        Frequency
                    </span>

                    <strong
                        id="summaryFrequency">
                        Every Wednesday
                    </strong>

                </div>


                <div class="summary-line">

                    <span>
                        Meetings
                    </span>

                    <strong
                        id="summaryMeetings">
                        3 sessions
                    </strong>

                </div>


                <div class="summary-line">

                    <span>
                        Duration
                    </span>

                    <strong
                        id="summaryDuration">
                        4h per session
                    </strong>

                </div>


                <div class="summary-line">

                    <span>
                        Departments
                    </span>

                    <strong
                        id="summaryDepartments">
                        None selected
                    </strong>

                </div>


                <div
                    class="summary-divider"></div>


                <p>

                    Schedule and targeting
                    update before publishing.

                </p>

            </div>


            <div class="notice-card">

                <i
                    data-lucide="shield-check"></i>


                <p>

                    <strong>
                        Review before publishing
                    </strong>

                    <br>

                    Programs, sessions,
                    department targets, and
                    qualifying recommendations
                    are saved only after final
                    confirmation.

                    Recommendations do not
                    consume training seats.

                </p>

            </div>

        </aside>

    </div>


    <!-- ==========================================
         SAVED TRAINING PROGRAMS
         ========================================== -->

    <section
        class="
            form-panel
            saved-programs-panel
        "
        aria-labelledby="savedProgramsTitle"
        style="
            margin-top:28px;
        ">

        <div class="panel-heading">

            <div class="panel-icon">

                <i
                    data-lucide="list-checks"></i>

            </div>


            <div>

                <h2
                    id="savedProgramsTitle">

                    Saved training programs

                </h2>


                <p>

                    Published programs in
                    your company database.

                </p>

            </div>

        </div>


        <?php if (
            empty($trainingPrograms)
        ): ?>


            <p
                class="
                    field-hint
                    saved-programs-empty
                "
                style="
                    margin:20px 24px;
                ">

                No training programs saved yet.

                Create and publish your
                first program above.

            </p>


        <?php else: ?>


            <div
                class="
                    saved-programs-list
                ">

                <?php foreach (
                    $trainingPrograms
                    as $program
                ): ?>


                    <details
                        class="
                            gf-training-saved
                            saved-program-card
                        ">

                        <summary>

                            <span
                                class="
                                    saved-program-title
                                ">

                                <?= e(
                                    (string) (
                                        $program['title']
                                        ?? ''
                                    )
                                ) ?>

                            </span>


                            <span
                                class="
                                    saved-program-meta
                                    saved-program-badge
                                ">

                                <?= e(
                                    ucfirst(
                                        (string) (
                                            $program['status']
                                            ?? ''
                                        )
                                    )
                                ) ?>

                                ·

                                <?= (int) (
                                    $program['meeting_count']
                                    ?? 0
                                ) ?>

                                sessions

                                ·

                                <?= (int) (
                                    $program['capacity']
                                    ?? 0
                                ) ?>

                                seats

                            </span>

                        </summary>


                        <div
                            class="
                                saved-program-body
                            ">

                            <p>

                                <strong>
                                    Purpose:
                                </strong>

                                <?= nl2br(
                                    e(
                                        (string) (
                                            $program['purpose']
                                            ?? ''
                                        )
                                    )
                                ) ?>

                            </p>


                            <p>

                                <strong>
                                    Objectives:
                                </strong>

                                <?= nl2br(
                                    e(
                                        (string) (
                                            $program['objectives']
                                            ?? ''
                                        )
                                    )
                                ) ?>

                            </p>


                            <p>

                                <strong>
                                    Instructor(s):
                                </strong>

                                <?= e(
                                    (string) (
                                        $program['instructors']
                                        ?? ''
                                    )
                                ) ?>

                            </p>


                            <p>

                                <strong>
                                    Venue:
                                </strong>

                                <?= e(
                                    (string) (
                                        $program['venue']
                                        ?? ''
                                    )
                                ) ?>

                            </p>


                            <!-- ==============================
                                 TARGET DEPARTMENTS
                                 ============================== -->

                            <div
                                class="
                                    saved-target-row
                                ">

                                <div>

                                    <strong>
                                        Target departments
                                    </strong>


                                    <?php if (
                                        empty($program['departments'])
                                    ): ?>

                                        <p
                                            class="field-hint">

                                            No department
                                            targeting was
                                            recorded for this
                                            older program.

                                        </p>


                                    <?php else: ?>


                                        <div
                                            class="
                                                saved-department-tags
                                            ">

                                            <?php foreach (
                                                $program['departments']
                                                as $department
                                            ): ?>

                                                <span>

                                                    <?= e(
                                                        (string) (
                                                            $department['name']
                                                            ?? ''
                                                        )
                                                    ) ?>

                                                </span>

                                            <?php endforeach; ?>

                                        </div>

                                    <?php endif; ?>

                                </div>


                                <div
                                    class="
                                        saved-priority-stat
                                    ">

                                    <span>
                                        Priority recommended
                                    </span>


                                    <strong>

                                        <?= (int) (
                                            $program['priority_recommendations']
                                            ?? 0
                                        ) ?>

                                    </strong>


                                    <small>

                                        latest monthly score
                                        below

                                        <?= e(
                                            $priorityThresholdLabel
                                        ) ?>%

                                    </small>

                                </div>

                            </div>


                            <!-- ==============================
                                 SESSIONS
                                 ============================== -->

                            <p>

                                <strong>
                                    Sessions:
                                </strong>

                            </p>


                            <?php if (
                                empty($program['sessions'])
                            ): ?>

                                <p class="field-hint">

                                    No sessions found.

                                </p>


                            <?php else: ?>


                                <ol
                                    class="
                                        saved-session-list
                                    ">

                                    <?php foreach (
                                        $program['sessions']
                                        as $session
                                    ): ?>

                                        <li>

                                            <?= e(
                                                (string) (
                                                    $session['session_date']
                                                    ?? ''
                                                )
                                            ) ?>

                                            ·

                                            <?= e(
                                                substr(
                                                    (string) (
                                                        $session['start_time']
                                                        ?? ''
                                                    ),
                                                    0,
                                                    5
                                                )
                                            ) ?>

                                            –

                                            <?= e(
                                                substr(
                                                    (string) (
                                                        $session['end_time']
                                                        ?? ''
                                                    ),
                                                    0,
                                                    5
                                                )
                                            ) ?>

                                        </li>

                                    <?php endforeach; ?>

                                </ol>

                            <?php endif; ?>


                            <p class="field-hint">

                                Priority recommendations
                                are recommendations only.

                                Employees must still
                                choose to join the
                                training.

                            </p>

                        </div>

                    </details>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

</main>


<!-- ==========================================
     REVIEW DIALOG
     ========================================== -->

<dialog
    id="reviewDialog"
    class="review-dialog">

    <div class="dialog-heading">

        <div>

            <span class="eyebrow">
                FINAL REVIEW
            </span>


            <h2>
                Review your program
            </h2>

        </div>


        <button
            id="closeReview"
            class="icon-button"
            type="button"
            aria-label="Close review">

            <i
                data-lucide="x"></i>

        </button>

    </div>


    <div
        id="reviewContents"
        class="review-body"></div>


    <div class="dialog-actions">

        <button
            class="
                button
                button-quiet
            "
            id="backToEdit"
            type="button">

            Back to edit

        </button>


        <button
            class="
                button
                button-primary
            "
            id="publishButton"
            type="button">

            <i
                data-lucide="check-circle-2"></i>

            Publish Program

        </button>

    </div>

</dialog>


<div
    id="toast"
    class="toast"
    role="status"
    aria-live="polite"></div>