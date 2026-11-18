(() => {
    const highlightTimeouts = new WeakMap();

    function highlightCancelButton(button) {
        const previousTimeout = highlightTimeouts.get(button);
        if (previousTimeout !== undefined) {
            window.clearTimeout(previousTimeout);
        }
        button.classList.remove('cancel-no-changes-highlight');
        void button.offsetWidth;
        button.classList.add('cancel-no-changes-highlight');
        const timeout = window.setTimeout(() => {
            button.classList.remove('cancel-no-changes-highlight');
            highlightTimeouts.delete(button);
        }, 1200);
        highlightTimeouts.set(button, timeout);
    }

    function watch(form, saveButton, cancelButton, hasChanges) {
        let wasChanged = false;
        const update = (highlightWhenUnchanged = true) => {
            const changed = Boolean(hasChanges());
            saveButton.disabled = !changed;
            if (highlightWhenUnchanged && wasChanged && !changed) {
                highlightCancelButton(cancelButton);
            }
            wasChanged = changed;
        };

        form.addEventListener('input', update);
        form.addEventListener('change', update);
        form.addEventListener('reset', () => window.setTimeout(() => update(false), 0));
        update();
        return update;
    }

    window.AppFormChangeFeedback = { watch };
})();
