document.querySelectorAll('[data-live-search]').forEach((input) => {
    const target = document.querySelector(input.dataset.liveSearch);
    if (!target) {
        return;
    }

    const rows = Array.from(target.querySelectorAll('[data-live-search-row]'));
    const emptyMessage = input.dataset.liveSearchEmpty
        ? document.getElementById(input.dataset.liveSearchEmpty)
        : null;

    function filterRows() {
        const query = input.value.trim().toLocaleLowerCase();
        let visibleCount = 0;

        rows.forEach((row) => {
            const text = (row.dataset.liveSearchText || row.textContent || '')
                .replace(/\s+/g, ' ')
                .toLocaleLowerCase();
            const matches = text.includes(query);
            row.hidden = !matches;
            visibleCount += matches ? 1 : 0;

            const editorRow = row.nextElementSibling;
            if (!matches && editorRow && editorRow.matches('[data-course-row], [data-document-row]')) {
                editorRow.hidden = true;
            }
        });

        if (emptyMessage) {
            emptyMessage.hidden = visibleCount > 0;
        }
    }

    input.addEventListener('input', filterRows);
    const searchForm = input.closest('form');
    if (searchForm) {
        searchForm.addEventListener('submit', (event) => {
            event.preventDefault();
            filterRows();
        });
    }
    filterRows();
});
