(() => {
    const notifications = document.querySelectorAll('.toast-notification');

    notifications.forEach((notification) => {
        if (notification.hasAttribute('data-clean-url')) {
            const url = new URL(window.location.href);
            url.searchParams.delete('signed_out');
            window.history.replaceState(window.history.state, '', url);
        }

        window.setTimeout(() => {
            notification.classList.add('is-dismissing');
            window.setTimeout(() => notification.remove(), 250);
        }, 5000);
    });
})();
