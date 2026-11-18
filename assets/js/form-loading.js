const originalButtonState = new WeakMap();

function startButtonLoading(button, loadingText = 'Please wait…') {
    if (!(button instanceof HTMLButtonElement) && !(button instanceof HTMLInputElement)) {
        return;
    }
    if (button.classList.contains('is-loading')) {
        return;
    }

    originalButtonState.set(button, {
        content: button instanceof HTMLInputElement ? button.value : button.innerHTML,
        disabled: button.disabled,
    });
    button.disabled = true;
    button.classList.add('is-loading');
    button.setAttribute('aria-busy', 'true');
    if (button instanceof HTMLInputElement) {
        button.value = loadingText;
    } else {
        button.textContent = loadingText;
    }
}

function stopButtonLoading(button) {
    const originalState = originalButtonState.get(button);
    if (!originalState) {
        return;
    }

    button.disabled = originalState.disabled;
    button.classList.remove('is-loading');
    button.removeAttribute('aria-busy');
    if (button instanceof HTMLInputElement) {
        button.value = originalState.content;
    } else {
        button.innerHTML = originalState.content;
    }
    originalButtonState.delete(button);
}

window.AppFormLoading = { startButtonLoading, stopButtonLoading };

document.addEventListener('submit', (event) => {
    if (event.defaultPrevented) {
        return;
    }

    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.matches('form[data-loading-ignore]')) {
        return;
    }

    if (form.dataset.submitting === 'true') {
        event.preventDefault();
        return;
    }

    let button = event.submitter;
    if (!button && form.dataset.loadingSource) {
        button = document.querySelector(form.dataset.loadingSource);
    }
    if (!button) {
        button = form.querySelector(
            'button[data-loading-button]:not(:disabled), input[data-loading-button]:not(:disabled), ' +
            'button[type="submit"]:not(:disabled), input[type="submit"]:not(:disabled), ' +
            'button:not([type]):not(:disabled)'
        );
    }
    if (!(button instanceof HTMLButtonElement) && !(button instanceof HTMLInputElement)) {
        return;
    }
    if (button.hasAttribute('data-loading-ignore')) {
        return;
    }

    form.dataset.submitting = 'true';
    const loadingText = button.dataset.loadingText
        || form.dataset.loadingText
        || 'Please wait…';
    startButtonLoading(button, loadingText);
});

window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-submitting]').forEach((form) => {
        delete form.dataset.submitting;
    });
    document.querySelectorAll('button.is-loading, input.is-loading').forEach((button) => {
        stopButtonLoading(button);
    });
});
