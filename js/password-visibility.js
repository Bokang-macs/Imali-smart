/* Accessible password visibility controls shared by login and registration. */
(function () {
    'use strict';

    function setVisibility(button, input, visible) {
        input.type = visible ? 'text' : 'password';
        button.setAttribute('aria-pressed', visible ? 'true' : 'false');
        button.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
        button.title = visible ? 'Hide password' : 'Show password';
        button.querySelector('[data-password-toggle-label]').textContent = visible ? 'Hide' : 'Show';
    }

    document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
        var inputId = button.getAttribute('data-password-toggle');
        var input = inputId ? document.getElementById(inputId) : null;
        if (!input) return;

        button.addEventListener('click', function () {
            setVisibility(button, input, input.type === 'password');
            input.focus({ preventScroll: true });
        });

        button.addEventListener('keydown', function (event) {
            if (event.key === ' ' || event.key === 'Enter') {
                event.preventDefault();
                button.click();
            }
        });
    });
})();
