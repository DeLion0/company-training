<article class="certificate-page certificate-3">
    <div class="c3-frame"></div>
    <div class="botanical-wash wash-left"></div>
    <div class="botanical-wash wash-right"></div>
    <div class="c3-wave wave-top"></div>
    <div class="c3-wave wave-bottom"></div>

    <div class="c3-branch branch-left">
        <span class="stem"></span>
        <i class="l1"></i><i class="l2"></i><i class="l3"></i><i class="l4"></i><i class="l5"></i>
    </div>
    <div class="c3-branch branch-right">
        <span class="stem"></span>
        <i class="l1"></i><i class="l2"></i><i class="l3"></i><i class="l4"></i><i class="l5"></i>
    </div>

    <header class="gf-brand brand-centered c3-brand">
        <div class="gf-wordmark">
            <span>Grow</span><em>/</em><span>Flow</span>
            <i class="logo-leaf a"></i>
            <i class="logo-leaf b"></i>
        </div>
        <small>LEARNING MANAGEMENT</small>
        <p>PEOPLE &nbsp;/&nbsp; SKILLS &nbsp;/&nbsp; A BRIGHTER TOMORROW</p>
    </header>

    <section class="certificate-content c3-content">
        <div class="gold-dash"></div>
        <h1>Certificate of Completion</h1>
        <p class="caps-kicker">THIS CERTIFIES THAT</p>

        <h2><?= e((string) $certificate['recipient_name']) ?></h2>

        <div class="botanical-name-rule">
            <span></span>
            <b>❧</b>
            <span></span>
        </div>

        <p class="completion-copy">has successfully completed the</p>
        <h3><?= e((string) $certificate['training_title']) ?></h3>

        <p class="recognition-copy">
            <?= e((string) $certificate['recognition']) ?>
        </p>

        <p class="event-date">
            Completed on <?= e((string) $certificate['date_issued']) ?>
        </p>
        <p class="certificate-number">
            Certificate No. <?= e((string) $certificate['certificate_number']) ?>
        </p>
    </section>

    <footer class="c3-footer">
        <div class="botanical-seal">
            <div class="botanical-seal-inner">
                <span class="seal-leaf"></span>
                <b>GROW/FLOW</b>
                <small>LEARNING MANAGEMENT</small>
            </div>
        </div>
        <p>LEARNING TODAY. A BRIGHTER TOMORROW.</p>
    </footer>
</article>
