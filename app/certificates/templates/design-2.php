<article class="certificate-page certificate-2">
    <div class="c2-border"></div>
    <div class="soft-arc arc-top"></div>
    <div class="soft-arc arc-bottom"></div>
    <div class="c2-watermark c2-watermark-left"></div>
    <div class="c2-watermark c2-watermark-right"></div>

    <header class="gf-brand brand-left c2-brand">
        <div class="gf-wordmark modern">
            <span class="mark-pair"><i></i><i></i></span>
            <span>Grow</span><em>/</em><span>Flow</span>
        </div>
        <small>LEARNING MANAGEMENT</small>
    </header>

    <div class="c2-motto c2-motto-top">
        <i></i>
        <span>PEOPLE<br>SKILLS<br>BRIGHTER<br>TOMORROWS</span>
    </div>

    <section class="certificate-content c2-content">
        <p class="caps-kicker">CERTIFICATE OF</p>
        <h1>COMPLETION</h1>

        <div class="modern-rule"><span></span><b></b><span></span></div>

        <p class="intro-copy spaced">Presented to</p>

        <h2><?= e((string) $certificate['recipient_name']) ?></h2>

        <p class="completion-copy spaced">For successfully completing</p>

        <h3><?= e((string) $certificate['training_title']) ?></h3>

        <p class="recognition-copy">
            <?= e((string) $certificate['recognition']) ?>
        </p>
    </section>

    <footer class="c2-footer">
        <div class="modern-meta">
            <div>
                <i class="mini-icon calendar-icon"></i>
                <span>DATE ISSUED</span>
                <strong><?= e((string) $certificate['date_issued']) ?></strong>
            </div>
            <div>
                <i class="mini-icon doc-icon"></i>
                <span>CERTIFICATE NO.</span>
                <strong><?= e((string) $certificate['certificate_number']) ?></strong>
            </div>
            <div>
                <i class="mini-icon location-icon"></i>
                <span>TRAINING FORMAT</span>
                <strong><?= e((string) $certificate['training_format']) ?></strong>
            </div>
        </div>

        <div class="modern-seal">
            <div class="modern-seal-inner">
                <span class="seal-leaf"></span>
                <b>GROW/FLOW</b>
                <small>COMPLETION</small>
            </div>
        </div>
    </footer>

    <div class="c2-bottom-copy left">A MORE<br>CAPABLE<br>PEOPLE<br>TOMORROW</div>
    <div class="c2-bottom-copy right">LEARN<br>GROW<br>BELONG<br>MAKE AN IMPACT</div>
</article>
