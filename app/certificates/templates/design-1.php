<article class="certificate-page certificate-1">
    <div class="c1-border c1-border-outer"></div>
    <div class="c1-border c1-border-gold"></div>
    <div class="c1-border c1-border-inner"></div>

    <div class="c1-ribbon c1-ribbon-top">
        <span class="c1-ribbon-gold"></span>
        <span class="c1-ribbon-light"></span>
    </div>

    <div class="c1-ribbon c1-ribbon-bottom">
        <span class="c1-ribbon-gold"></span>
        <span class="c1-ribbon-light"></span>
    </div>

    <div class="c1-corner-flourish tl"><i></i><b></b></div>
    <div class="c1-corner-flourish tr"><i></i><b></b></div>
    <div class="c1-corner-flourish bl"><i></i><b></b></div>
    <div class="c1-corner-flourish br"><i></i><b></b></div>

    <div class="paper-lines"></div>
    <div class="watermark-leaf watermark-right"></div>

    <header class="gf-brand brand-centered">
        <div class="gf-wordmark">
            <span>Grow</span><em>/</em><span>Flow</span>
            <i class="logo-leaf a"></i>
            <i class="logo-leaf b"></i>
        </div>
        <small>LEARNING MANAGEMENT</small>
        <p>PEOPLE &nbsp;•&nbsp; SKILLS &nbsp;•&nbsp; A BRIGHTER TOMORROW</p>
    </header>

    <section class="certificate-content c1-content">
        <h1>CERTIFICATE OF COMPLETION</h1>

        <div class="fancy-rule">
            <span></span>
            <b>❖</b>
            <span></span>
        </div>

        <p class="intro-copy">This certificate is proudly presented to</p>

        <h2><?= e((string) $certificate['recipient_name']) ?></h2>

        <div class="name-rule">
            <span></span>
            <b>❖</b>
            <span></span>
        </div>

        <p class="completion-copy"><?= e((string) $certificate['accomplishment']) ?></p>

        <h3><?= e((string) $certificate['training_title']) ?></h3>

        <div class="program-rule"><i></i><b>❖</b><i></i></div>

        <p class="event-date">Held on <?= e((string) $certificate['training_period']) ?></p>
    </section>

    <footer class="c1-footer">
        <div class="certificate-data align-left">
            <p><span>Certificate No.</span> <?= e((string) $certificate['certificate_number']) ?></p>
            <p><span>Date Issued:</span> <?= e((string) $certificate['date_issued']) ?></p>
        </div>

        <div class="prestige-seal">
            <div class="seal-stars">✦</div>
            <div class="seal-center">
                <span class="seal-leaf"></span>
                <b>GROW/FLOW</b>
                <small>LEARNING MANAGEMENT</small>
            </div>
        </div>

        <div class="certificate-data align-right">
            <p><span>Employee ID:</span> <?= e((string) $certificate['employee_code']) ?></p>
            <p><span>Department:</span> <?= e((string) $certificate['department_name']) ?></p>
        </div>
    </footer>
</article>
