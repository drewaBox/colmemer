(() => {
    const formStates = new Map();
    const trackedFormSelector = 'form:not([data-navigation-guard-ignore])';
    const confirmation = document.createElement('dialog');
    confirmation.className = 'navigation-confirm-dialog';
    confirmation.setAttribute('aria-labelledby', 'navigation-confirm-title');
    confirmation.innerHTML = `
        <div class="navigation-confirm-card">
            <span class="navigation-confirm-mark" aria-hidden="true">!</span>
            <p class="eyebrow">UNSAVED WORK</p>
            <h2 id="navigation-confirm-title">Leave this page?</h2>
            <p data-navigation-confirm-message>
                You have changes or a process in progress. Leaving now may interrupt it.
            </p>
            <div class="navigation-confirm-actions">
                <button class="secondary-button" type="button" data-navigation-stay>Stay here</button>
                <button class="danger-button" type="button" data-navigation-leave>Leave page</button>
            </div>
        </div>`;
    document.body.append(confirmation);

    const message = confirmation.querySelector('[data-navigation-confirm-message]');
    const eyebrow = confirmation.querySelector('.eyebrow');
    const title = confirmation.querySelector('#navigation-confirm-title');
    const stayButton = confirmation.querySelector('[data-navigation-stay]');
    const leaveButton = confirmation.querySelector('[data-navigation-leave]');
    let activeConfirmation = null;
    let allowUnload = false;
    let allowSubmit = false;
    let allowLink = false;
    let allowPopstate = false;
    let historySentinel = false;
    let restoreGuardEntry = false;
    let guardedBackTargetIsSentinel = false;

    function formValues(form) {
        return Array.from(form.elements)
            .filter((element) => !element.disabled && element.name
                && !['submit', 'button', 'reset'].includes(element.type))
            .map((element) => {
                if (element.type === 'checkbox' || element.type === 'radio') {
                    return [element.name, element.type, element.value, element.checked];
                }
                if (element.type === 'file') {
                    return [element.name, Array.from(element.files || []).map((file) => `${file.name}:${file.size}`)];
                }
                return [element.name, element.value];
            });
    }

    function isExcludedForm(form) {
        return form.method.toLowerCase() === 'get'
            || form.hidden
            || Boolean(form.closest('[hidden], dialog:not([open])'))
            || form.matches('[data-navigation-guard-ignore], [data-student-edit-password-confirm],'
                + '[data-document-password-confirm], [data-course-deactivate-confirm]');
    }

    function stateFor(form) {
        if (!formStates.has(form)) {
            formStates.set(form, {
                baseline: JSON.stringify(formValues(form)),
                dirty: false,
                pending: false,
            });
        }
        return formStates.get(form);
    }

    function refreshDirtyState(form) {
        const state = stateFor(form);
        state.dirty = JSON.stringify(formValues(form)) !== state.baseline;
        if (state.dirty) {
            ensureHistorySentinel();
        }
        return state.dirty;
    }

    function localUnsavedWork() {
        let dirty = false;
        document.querySelectorAll(trackedFormSelector).forEach((form) => {
            if (isExcludedForm(form)) {
                return;
            }
            const state = stateFor(form);
            state.dirty = JSON.stringify(formValues(form)) !== state.baseline;
            dirty = dirty || state.dirty || state.pending;
        });
        return dirty;
    }

    function hasUnsavedWork() {
        if (localUnsavedWork()) {
            return true;
        }
        for (const frame of document.querySelectorAll('iframe')) {
            try {
                if (frame.contentWindow?.__appNavigationGuard?.hasUnsavedWork()) {
                    return true;
                }
            } catch (error) {
                if (error instanceof DOMException && error.name === 'SecurityError') {
                    continue;
                }
                throw error;
            }
        }
        return false;
    }

    function syncParentDialog(locked) {
        if (window.parent !== window) {
            window.parent.postMessage({ type: 'public-dialog-scroll-lock', locked }, window.location.origin);
        }
    }

    function showConfirmation({ section, heading, body, confirmLabel }) {
        if (activeConfirmation) {
            return activeConfirmation.promise;
        }

        eyebrow.textContent = section;
        title.textContent = heading;
        message.textContent = body;
        leaveButton.textContent = confirmLabel;

        let resolveConfirmation;
        const promise = new Promise((resolve) => {
            resolveConfirmation = resolve;
        });
        activeConfirmation = { promise, resolve: resolveConfirmation };
        stayButton.focus();
        confirmation.showModal();
        syncParentDialog(true);
        return activeConfirmation.promise;
    }

    function confirmLeave(reason) {
        if (!hasUnsavedWork()) {
            return Promise.resolve(true);
        }
        return showConfirmation({
            section: reason === 'refresh' ? 'CONFIRM RELOAD' : 'UNSAVED WORK',
            heading: reason === 'refresh' ? 'Reload this page?' : 'Leave this page?',
            body: reason === 'refresh'
                ? 'You have changes or a process in progress. Reloading now may interrupt it.'
                : 'You have changes or a process in progress. Leaving now may interrupt it.',
            confirmLabel: reason === 'refresh' ? 'Reload page' : 'Leave page',
        });
    }

    function finishConfirmation(leave) {
        if (!activeConfirmation) {
            return;
        }
        const resolve = activeConfirmation.resolve;
        activeConfirmation = null;
        confirmation.close();
        syncParentDialog(false);
        resolve(leave);
    }

    stayButton.addEventListener('click', () => finishConfirmation(false));
    leaveButton.addEventListener('click', () => finishConfirmation(true));
    confirmation.addEventListener('cancel', (event) => {
        event.preventDefault();
        finishConfirmation(false);
    });
    confirmation.addEventListener('click', (event) => {
        if (event.target === confirmation) {
            finishConfirmation(false);
        }
    });

    document.addEventListener('input', (event) => {
        const form = event.target.form;
        if (form && !isExcludedForm(form)) {
            refreshDirtyState(form);
        }
    }, true);
    document.addEventListener('change', (event) => {
        const form = event.target.form;
        if (form && !isExcludedForm(form)) {
            refreshDirtyState(form);
        }
    }, true);
    document.addEventListener('reset', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || isExcludedForm(form)) {
            return;
        }
        window.setTimeout(() => {
            const state = stateFor(form);
            state.baseline = JSON.stringify(formValues(form));
            state.dirty = false;
            state.pending = false;
        });
    }, true);

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || isExcludedForm(form) || event.defaultPrevented) {
            return;
        }
        const state = stateFor(form);
        state.pending = true;
        allowSubmit = true;
    });

    document.addEventListener('click', async (event) => {
        const target = event.target instanceof Element ? event.target : null;
        if (!target) {
            return;
        }

        const cancelButton = target.closest('button');
        if (cancelButton && !allowLink
            && (cancelButton.matches('[data-close-workflow], [data-document-cancel],'
                + '[data-close-student-edit], [data-close-student-edit-password],'
                + '[data-close-individual-student], [data-close-import-wizard], [data-close-course-dialog]')
                || cancelButton.textContent.trim().toLowerCase() === 'cancel')) {
            const form = cancelButton.form;
            if (form && refreshDirtyState(form)) {
                event.preventDefault();
                event.stopImmediatePropagation();
                const leave = await confirmLeave('leave');
                if (leave) {
                    form.reset();
                    allowLink = true;
                    cancelButton.click();
                    allowLink = false;
                }
            }
            return;
        }

        const link = target.closest('a[href]');
        if (!link || allowLink || event.defaultPrevented || event.button !== 0
            || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey
            || link.target === '_blank' || link.hasAttribute('download')) {
            return;
        }
        const url = new URL(link.href, window.location.href);
        if (url.origin !== window.location.origin || url.href === window.location.href
            || (url.pathname === window.location.pathname
                && url.search === window.location.search && url.hash !== '')) {
            return;
        }
        if (!hasUnsavedWork()) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        const leave = await confirmLeave('leave');
        if (!leave) {
            return;
        }

        allowLink = true;
        link.click();
        allowLink = false;
    }, true);

    window.addEventListener('keydown', async (event) => {
        const isRefresh = event.key === 'F5'
            || ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'r');
        if (!isRefresh || !hasUnsavedWork()) {
            return;
        }

        event.preventDefault();
        const leave = await confirmLeave('refresh');
        if (leave) {
            allowUnload = true;
            window.location.reload();
        }
    }, true);

    function ensureHistorySentinel() {
        if (historySentinel || !window.history?.pushState) {
            return;
        }
        historySentinel = true;
        window.history.pushState({ ...(window.history.state || {}), __appNavigationGuard: true }, '', window.location.href);
    }

    window.addEventListener('popstate', async (event) => {
        if (restoreGuardEntry) {
            restoreGuardEntry = false;
            historySentinel = true;
            return;
        }
        if (allowPopstate || !hasUnsavedWork()) {
            allowPopstate = false;
            historySentinel = Boolean(event.state?.__appNavigationGuard);
            return;
        }

        event.stopImmediatePropagation();
        guardedBackTargetIsSentinel = Boolean(event.state?.__appNavigationGuard);
        restoreGuardEntry = true;
        window.history.forward();
        const leave = await confirmLeave('leave');
        if (leave) {
            allowPopstate = true;
            if (guardedBackTargetIsSentinel) {
                window.history.back();
            } else {
                window.history.go(-2);
            }
        }
    }, true);

    const dialogObserver = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.target instanceof HTMLDialogElement
                && mutation.target.open
                && mutation.target !== confirmation) {
                mutation.target.querySelectorAll('form').forEach((form) => {
                    if (isExcludedForm(form)) {
                        return;
                    }
                    const state = stateFor(form);
                    state.baseline = JSON.stringify(formValues(form));
                    state.dirty = false;
                    state.pending = false;
                });
            }
        });
    });
    document.querySelectorAll('dialog').forEach((dialog) => {
        dialogObserver.observe(dialog, { attributes: true, attributeFilter: ['open'] });
    });

    document.querySelectorAll(trackedFormSelector).forEach((form) => {
        if (!isExcludedForm(form)) {
            stateFor(form);
        }
    });

    window.addEventListener('beforeunload', (event) => {
        if (allowUnload || allowSubmit || !hasUnsavedWork()) {
            return;
        }
        event.preventDefault();
        event.returnValue = '';
    });

    window.addEventListener('pageshow', () => {
        allowUnload = false;
        allowSubmit = false;
        document.querySelectorAll(trackedFormSelector).forEach((form) => {
            const state = stateFor(form);
            state.pending = false;
        });
    });

    window.__appNavigationGuard = {
        confirmAction: (options) => showConfirmation(options),
        confirmLeave,
        hasUnsavedWork,
        resetFormState: (form) => {
            if (!(form instanceof HTMLFormElement)) {
                return;
            }
            const state = stateFor(form);
            state.baseline = JSON.stringify(formValues(form));
            state.dirty = false;
            state.pending = false;
        },
    };
})();
