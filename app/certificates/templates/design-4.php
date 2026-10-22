<article class="certificate-page certificate-4">
    <div class="c4-border outer"></div>
    <div class="c4-border gold"></div>
    <div class="c4-border inner"></div>

    <div class="c4-corner tl"><span></span></div>
    <div class="c4-corner tr"><span></span></div>
    <div class="c4-corner bl"><span></span></div>
    <div class="c4-corner br"><span></span></div>

    <div class="c4-laurel laurel-left"></div>
    <div class="c4-laurel laurel-right"></div>
    <div class="c4-landscape"></div>

    <div class="c4-ribbon ribbon-left"></div>
    <div class="c4-ribbon ribbon-right"></div>

    <header class="gf-brand brand-centered c4-brand">
        <div class="gf-wordmark modern-elegant">
            <span>Grow</span><em>/</em><span>Flow</span>
            <i class="logo-leaf a"></i>
            <i class="logo-leaf b"></i>
        </div>
        <small>LEARNING MANAGEMENT</small>
    </header>

    <section class="certificate-content c4-content">
        <h1>CERTIFICATE OF COMPLETION</h1>

        <div class="heritage-rule">
            <span></span>
            <b>◆</b>
            <span></span>
        </div>

        <p class="caps-kicker">AWARDED TO</p>

        <h2><?= e((string) $certificate['recipient_name']) ?></h2>

        <div class="heritage-name-rule">
            <span></span>
            <b>◆</b>
            <span></span>
        </div>

        <p class="completion-copy"><?= e((string) $certificate['accomplishment']) ?></p>

        <h3><?= e((string) $certificate['training_title']) ?></h3>

        <p class="recognition-copy">
            <?= e((string) $certificate['recognition']) ?>
        </p>
    </section>

    <div class="heritage-meta">
        <div>
            <span>ISSUE DATE</span>
            <strong><?= e((string) $certificate['date_issued']) ?></strong>
        </div>
        <div>
            <span>CERTIFICATE ID</span>
            <strong><?= e((string) $certificate['certificate_number']) ?></strong>
        </div>
        <div>
            <span>EMPLOYEE ID</span>
            <strong><?= e((string) $certificate['employee_code']) ?></strong>
        </div>
    </div>

    <footer class="c4-footer">
        <div class="heritage-seal">
            <div class="heritage-seal-inner">
                <span class="seal-leaf"></span>
                <b>GROW/FLOW</b>
                <small>LEARNING MANAGEMENT</small>
            </div>
        </div>
    </footer>
</article>
