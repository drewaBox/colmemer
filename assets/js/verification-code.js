document.querySelectorAll('form[data-loading-form] .verification-code-inputs').forEach((group) => {
    const form = group.closest('form');
    const inputs = Array.from(group.querySelectorAll('input'));
    const codeField = form.querySelector('input[name="verification_code"]');
    const status = form.querySelector('.verification-status');
    let submitting = false;

    function updateCode() {
        const code = inputs.map((input) => input.value).join('');
        codeField.value = code;
        if (code.length === inputs.length && !submitting) {
            submitting = true;
            status.classList.add('is-loading');
            status.textContent = 'Verifying code…';
            form.requestSubmit();
        }
    }

    function fillFrom(input, text) {
        const digits = text.replace(/\D/g, '');
        if (!digits) {
            return;
        }

        const start = inputs.indexOf(input);
        const insertedDigits = digits.slice(0, inputs.length - start).split('');
        inputs.slice(start).forEach((target, offset) => {
            target.value = insertedDigits[offset] || '';
        });
        const next = inputs[Math.min(start + insertedDigits.length, inputs.length - 1)];
        next.focus();
        updateCode();
    }

    inputs.forEach((input, index) => {
        input.addEventListener('input', () => {
            const value = input.value.replace(/\D/g, '');
            if (value.length > 1) {
                fillFrom(input, value);
                return;
            }
            input.value = value;
            if (value && index < inputs.length - 1) {
                inputs[index + 1].focus();
            }
            updateCode();
        });

        input.addEventListener('paste', (event) => {
            event.preventDefault();
            fillFrom(input, event.clipboardData.getData('text'));
        });

        input.addEventListener('keydown', (event) => {
            if (event.key === 'Backspace' && input.value === '' && index > 0) {
                inputs[index - 1].focus();
            } else if (event.key === 'ArrowLeft' && index > 0) {
                inputs[index - 1].focus();
            } else if (event.key === 'ArrowRight' && index < inputs.length - 1) {
                inputs[index + 1].focus();
            }
        });
    });
});
