(() => {
    const toggleButton = document.getElementById('togglePassword');
    const passwordField = document.getElementById('password');

    if (toggleButton && passwordField) {
        toggleButton.addEventListener('click', () => {
            const visible = passwordField.type === 'text';

            passwordField.type =
                visible ? 'password' : 'text';

            toggleButton.textContent =
                visible ? 'Show' : 'Hide';

            toggleButton.setAttribute(
                'aria-label',
                visible
                    ? 'Show password'
                    : 'Hide password'
            );
        });
    }

    const source =
        document.getElementById(
            'clientLogoSource'
        );

    const targets =
        document.querySelectorAll(
            '[data-client-logo]'
        );

    if (!source || !targets.length) {
        return;
    }

    const renderLogo = () => {
        if (
            !source.naturalWidth
            || !source.naturalHeight
        ) {
            return;
        }

        const scratch =
            document.createElement('canvas');

        scratch.width =
            source.naturalWidth;

        scratch.height =
            source.naturalHeight;

        const ctx =
            scratch.getContext(
                '2d',
                {
                    willReadFrequently: true,
                }
            );

        if (!ctx) {
            return;
        }

        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = 'high';

        ctx.drawImage(
            source,
            0,
            0,
            scratch.width,
            scratch.height
        );

        const image =
            ctx.getImageData(
                0,
                0,
                scratch.width,
                scratch.height
            );

        const data = image.data;

        const cornerSamples = [
            [4, 4],
            [scratch.width - 5, 4],
            [4, scratch.height - 5],
            [scratch.width - 5, scratch.height - 5],
        ];

        const background =
            [0, 0, 0];

        cornerSamples.forEach(
            ([x, y]) => {
                const index =
                    (
                        y
                        * scratch.width
                        + x
                    )
                    * 4;

                background[0] +=
                    data[index];

                background[1] +=
                    data[index + 1];

                background[2] +=
                    data[index + 2];
            }
        );

        background[0] /= 4;
        background[1] /= 4;
        background[2] /= 4;

        let minX = scratch.width;
        let minY = scratch.height;
        let maxX = 0;
        let maxY = 0;

        for (
            let y = 0;
            y < scratch.height;
            y++
        ) {
            for (
                let x = 0;
                x < scratch.width;
                x++
            ) {
                const index =
                    (
                        y
                        * scratch.width
                        + x
                    )
                    * 4;

                const r = data[index];
                const g = data[index + 1];
                const b = data[index + 2];

                const distance =
                    Math.sqrt(
                        (r - background[0]) ** 2
                        + (g - background[1]) ** 2
                        + (b - background[2]) ** 2
                    );

                /*
                 * Feather the client's flat green background away
                 * rather than using a hard cutoff. This keeps the
                 * original anti-aliased artwork significantly cleaner.
                 */
                if (distance <= 18) {
                    data[index + 3] = 0;
                    continue;
                }

                if (distance < 46) {
                    const alpha =
                        Math.round(
                            (
                                (distance - 18)
                                / 28
                            )
                            * 255
                        );

                    data[index + 3] =
                        Math.min(
                            data[index + 3],
                            alpha
                        );
                }

                const maxChannel =
                    Math.max(r, g, b);

                const minChannel =
                    Math.min(r, g, b);

                const neutral =
                    maxChannel
                    - minChannel
                    < 30;

                /*
                 * White artwork from the supplied dark-background
                 * logo becomes Grow/Flow forest green on this
                 * light login screen. Bright green brand accents
                 * are kept as supplied by the client.
                 */
                if (
                    neutral
                    && maxChannel > 218
                ) {
                    data[index] = 17;
                    data[index + 1] = 68;
                    data[index + 2] = 42;
                }

                if (data[index + 3] > 12) {
                    minX =
                        Math.min(minX, x);

                    minY =
                        Math.min(minY, y);

                    maxX =
                        Math.max(maxX, x);

                    maxY =
                        Math.max(maxY, y);
                }
            }
        }

        ctx.putImageData(
            image,
            0,
            0
        );

        if (
            maxX <= minX
            || maxY <= minY
        ) {
            return;
        }

        /*
         * Slightly tighter crop than V2 so the logo reads
         * larger and closer to the approved visual direction.
         */
        const contentWidth =
            maxX - minX + 1;

        const contentHeight =
            maxY - minY + 1;

        const padX =
            Math.round(
                contentWidth * .025
            );

        const padY =
            Math.round(
                contentHeight * .025
            );

        const sx =
            Math.max(
                0,
                minX - padX
            );

        const sy =
            Math.max(
                0,
                minY - padY
            );

        const sw =
            Math.min(
                scratch.width - sx,
                contentWidth + padX * 2
            );

        const sh =
            Math.min(
                scratch.height - sy,
                contentHeight + padY * 2
            );

        targets.forEach(
            (canvas) => {
                const out =
                    canvas.getContext('2d');

                if (!out) {
                    return;
                }

                const cssWidth =
                    Math.max(
                        1,
                        canvas.clientWidth
                    );

                const cssHeight =
                    Math.max(
                        1,
                        canvas.clientHeight
                    );

                const dpr =
                    Math.min(
                        window.devicePixelRatio
                        || 1,
                        3
                    );

                canvas.width =
                    Math.round(
                        cssWidth * dpr
                    );

                canvas.height =
                    Math.round(
                        cssHeight * dpr
                    );

                out.clearRect(
                    0,
                    0,
                    canvas.width,
                    canvas.height
                );

                out.imageSmoothingEnabled =
                    true;

                out.imageSmoothingQuality =
                    'high';

                const scale =
                    Math.min(
                        canvas.width / sw,
                        canvas.height / sh
                    )
                    * 1.035;

                const dw =
                    sw * scale;

                const dh =
                    sh * scale;

                const dx =
                    (
                        canvas.width
                        - dw
                    )
                    / 2;

                const dy =
                    (
                        canvas.height
                        - dh
                    )
                    / 2;

                out.drawImage(
                    scratch,
                    sx,
                    sy,
                    sw,
                    sh,
                    dx,
                    dy,
                    dw,
                    dh
                );
            }
        );
    };

    if (source.complete) {
        renderLogo();
    } else {
        source.addEventListener(
            'load',
            renderLogo,
            {
                once: true,
            }
        );
    }

    let resizeFrame = 0;

    window.addEventListener(
        'resize',
        () => {
            cancelAnimationFrame(
                resizeFrame
            );

            resizeFrame =
                requestAnimationFrame(
                    renderLogo
                );
        }
    );
})();
