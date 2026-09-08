(function () {
    'use strict';
    document.querySelectorAll('[data-print]').forEach(function (button) {
        button.addEventListener('click', function () { window.print(); });
    });
    document.querySelectorAll('form[method="post"]').forEach(function (form) {
        form.addEventListener('submit', function () {
            if (!form.checkValidity()) return;
            var button = form.querySelector('button[type="submit"]');
            if (button) { button.disabled = true; button.setAttribute('aria-busy', 'true'); }
        });
    });
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('button[aria-busy="true"]').forEach(function (button) {
            button.disabled = false; button.removeAttribute('aria-busy');
        });
    });
}());
