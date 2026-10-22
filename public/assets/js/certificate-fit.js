document.addEventListener('DOMContentLoaded', () => {
    const fitElements = document.querySelectorAll(
        '.fit-certificate-text'
    );

    const fitElement = (element) => {
        const computed = window.getComputedStyle(element);

        let size = parseFloat(computed.fontSize);
        const minimum = Number(
            element.dataset.fitMin || 18
        );
        const step = Number(
            element.dataset.fitStep || 1
        );

        const singleLine =
            element.dataset.fitSingleLine === '1';

        if (singleLine) {
            element.style.whiteSpace = 'nowrap';
        }

        let guard = 0;

        while (
            size > minimum
            && (
                element.scrollWidth > element.clientWidth + 1
                || element.scrollHeight > element.clientHeight + 1
            )
            && guard < 120
        ) {
            size -= step;
            element.style.fontSize = `${size}px`;
            guard++;
        }
    };

    const fitAll = () => {
        fitElements.forEach(fitElement);
    };

    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(fitAll);
    } else {
        fitAll();
    }

    window.addEventListener('resize', () => {
        window.requestAnimationFrame(fitAll);
    });
});
