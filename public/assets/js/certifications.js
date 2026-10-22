document.addEventListener('DOMContentLoaded', () => {
    if (window.lucide) {
        window.lucide.createIcons();
    }

    const program =
        document.getElementById('certProgram');

    const templateDownload =
        document.getElementById('certTemplateDownload');

    if (program && templateDownload) {
        const refreshTemplateLink = () => {
            const programId = program.value;

            if (programId === '') {
                templateDownload.href = '#';
                templateDownload.classList.add('is-disabled');
                templateDownload.setAttribute(
                    'aria-disabled',
                    'true'
                );
                templateDownload.innerHTML =
                    '<i data-lucide="file-spreadsheet"></i>'
                    + ' Select Training to Download Excel';
            } else {
                templateDownload.href =
                    'training-attendance-template.php?program_id='
                    + encodeURIComponent(programId);

                templateDownload.classList.remove('is-disabled');
                templateDownload.removeAttribute('aria-disabled');
                templateDownload.innerHTML =
                    '<i data-lucide="file-spreadsheet"></i>'
                    + ' Download Program Attendance Excel';
            }

            if (window.lucide) {
                window.lucide.createIcons();
            }
        };

        program.addEventListener(
            'change',
            refreshTemplateLink
        );

        refreshTemplateLink();
    }

    const librarySearch =
        document.getElementById('certLibrarySearch');

    const libraryTable =
        document.getElementById('certLibraryTable');

    if (librarySearch && libraryTable) {
        const rows = Array.from(
            libraryTable.querySelectorAll(
                'tbody tr[data-search]'
            )
        );

        librarySearch.addEventListener('input', () => {
            const query = librarySearch.value
                .trim()
                .toLowerCase();

            rows.forEach((row) => {
                row.hidden =
                    query !== ''
                    && !String(row.dataset.search || '')
                        .includes(query);
            });
        });
    }

    const preview =
        document.getElementById('certAttendancePreview');

    if (preview) {
        setTimeout(() => {
            preview.scrollIntoView({
                behavior: 'smooth',
                block: 'start',
            });
        }, 120);
    }
});
