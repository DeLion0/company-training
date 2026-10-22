<?php

declare(strict_types=1);

if (
    !isset($certificate)
    || !is_array($certificate)
    || !isset($templateNumber)
) {
    throw new RuntimeException(
        'Certificate renderer was loaded without certificate data.'
    );
}

$templateNumber = (int) $templateNumber;

if ($templateNumber < 1 || $templateNumber > 4) {
    $templateNumber = 1;
}

$trainingStart = new DateTimeImmutable(
    (string) $certificate['range_start']
);

$trainingEnd = new DateTimeImmutable(
    (string) $certificate['range_end']
);

$issuedAt = new DateTimeImmutable(
    (string) $certificate['issued_at']
);

$trainingPeriod =
    $trainingStart->format('F j, Y')
    . ' – '
    . $trainingEnd->format('F j, Y');

$compactPeriod =
    strtoupper(
        $trainingStart->format('M j')
        . '–'
        . $trainingEnd->format('j, Y')
    );

$issuedDate =
    $issuedAt->format('F j, Y');

$issuedDateShort =
    strtoupper(
        $issuedAt->format('M j, Y')
    );

$completionDate =
    $trainingEnd->format('F j, Y');

$certificateNumber =
    (string) $certificate['certificate_number'];

$employeeName =
    (string) $certificate['full_name'];

$trainingTitle =
    (string) $certificate['training_title'];
?>

<article
    class="
        certificate-artwork
        certificate-template-<?= $templateNumber ?>
    "
    data-certificate-template="<?= $templateNumber ?>"
>
    <img
        class="certificate-background"
        src="assets/certificates/template-<?= $templateNumber ?>-master.png?v=20261022-5"
        alt=""
        aria-hidden="true"
    >


    <?php if ($templateNumber === 1): ?>

        <section
            class="certificate-layer design1-name fit-certificate-text"
            data-fit-min="30"
            data-fit-step="1"
            data-fit-single-line="1"
        >
            <?= e($employeeName) ?>
        </section>

        <section
            class="certificate-layer design1-program fit-certificate-text"
            data-fit-min="20"
            data-fit-step="1"
        >
            <?= e($trainingTitle) ?>
        </section>

        <section class="certificate-layer design1-date">
            Held on <?= e($trainingPeriod) ?>
        </section>

        <section class="certificate-layer design1-meta">
            <p>
                <b>Certificate No.</b>
                <?= e($certificateNumber) ?>
            </p>

            <p>
                <b>Date Issued:</b>
                <?= e($issuedDate) ?>
            </p>
        </section>


    <?php elseif ($templateNumber === 2): ?>

        <section
            class="certificate-layer design2-name fit-certificate-text"
            data-fit-min="30"
            data-fit-step="1"
            data-fit-single-line="1"
        >
            <?= e($employeeName) ?>
        </section>

        <section
            class="certificate-layer design2-program fit-certificate-text"
            data-fit-min="19"
            data-fit-step="1"
        >
            <?= e($trainingTitle) ?>
        </section>

        <section class="certificate-layer design2-meta design2-meta-left">
            <span>DATE ISSUED</span>
            <strong><?= e($issuedDate) ?></strong>
        </section>

        <section class="certificate-layer design2-meta design2-meta-center">
            <span>CERTIFICATE NO.</span>
            <strong><?= e($certificateNumber) ?></strong>
        </section>

        <section class="certificate-layer design2-meta design2-meta-right">
            <span>TRAINING PERIOD</span>
            <strong><?= e($compactPeriod) ?></strong>
        </section>


    <?php elseif ($templateNumber === 3): ?>

        <section
            class="certificate-layer design3-name fit-certificate-text"
            data-fit-min="30"
            data-fit-step="1"
            data-fit-single-line="1"
        >
            <?= e($employeeName) ?>
        </section>

        <section
            class="certificate-layer design3-program fit-certificate-text"
            data-fit-min="19"
            data-fit-step="1"
        >
            <?= e($trainingTitle) ?>
        </section>

        <section class="certificate-layer design3-completed">
            Completed on <?= e($completionDate) ?>
        </section>

        <section class="certificate-layer design3-number">
            Certificate No. <?= e($certificateNumber) ?>
        </section>


    <?php else: ?>

        <section
            class="certificate-layer design4-name fit-certificate-text"
            data-fit-min="30"
            data-fit-step="1"
            data-fit-single-line="1"
        >
            <?= e($employeeName) ?>
        </section>

        <section
            class="certificate-layer design4-program fit-certificate-text"
            data-fit-min="19"
            data-fit-step="1"
        >
            <?= e($trainingTitle) ?>
        </section>

        <section class="certificate-layer design4-meta design4-meta-left">
            <span>ISSUE DATE</span>
            <strong><?= e($issuedDateShort) ?></strong>
        </section>

        <section class="certificate-layer design4-meta design4-meta-center">
            <span>CERTIFICATE ID</span>
            <strong><?= e($certificateNumber) ?></strong>
        </section>

        <section class="certificate-layer design4-meta design4-meta-right">
            <span>TRAINING PERIOD</span>
            <strong><?= e($compactPeriod) ?></strong>
        </section>

    <?php endif; ?>

</article>
