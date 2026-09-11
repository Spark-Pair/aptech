(function () {
    'use strict';
    var main = document.getElementById('main-content');
    var token = document.querySelector('meta[name="csrf-token"]');
    var isLoading = false;

    function notify(message, level) {
        if (!message) return;
        var box = document.createElement('div');
        box.className = 'portal-toast alert alert-' + (level === 'error' ? 'danger' : (level || 'success'));
        box.setAttribute('role', level === 'error' ? 'alert' : 'status');
        box.textContent = message;
        document.body.appendChild(box);
        setTimeout(function () { box.classList.add('is-visible'); }, 20);
        setTimeout(function () {
            box.classList.remove('is-visible');
            setTimeout(function () { box.remove(); }, 250);
        }, 3600);
    }

    function setBusy(target, busy) {
        isLoading = busy;
        document.body.classList.toggle('portal-loading', busy);
        if (!target) return;
        var button = target.matches && target.matches('button') ? target : target.querySelector('button[type="submit"]');
        if (button) {
            button.disabled = busy;
            if (busy) button.setAttribute('aria-busy', 'true');
            else button.removeAttribute('aria-busy');
        }
    }

    function replaceMainFromHtml(html, url, scrollToDetails, skipHistory) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var next = doc.getElementById('main-content');
        if (!next || !main) {
            window.location = url;
            return;
        }
        main.innerHTML = next.innerHTML;
        document.title = doc.title;
        if (!skipHistory) history.pushState({}, doc.title, url);
        bindAjax(main);
        var focusTarget = scrollToDetails ? document.getElementById('employee-details') : main.querySelector('.page-header h1');
        if (focusTarget) focusTarget.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function loadPage(url, scrollToDetails, skipHistory) {
        if (isLoading) return;
        setBusy(main, true);
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) { return response.text(); })
            .then(function (html) { replaceMainFromHtml(html, url, scrollToDetails, skipHistory); })
            .catch(function () { notify('Unable to refresh data. Please try again.', 'error'); })
            .finally(function () { setBusy(main, false); });
    }

    function submitForm(form) {
        if (!form.checkValidity()) return;
        setBusy(form, true);
        var methodInput = form.querySelector('input[name="_method"]');
        var method = (methodInput ? methodInput.value : form.method || 'get').toUpperCase();
        var options = {
            method: method === 'GET' ? 'GET' : 'POST',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: method === 'GET' ? null : new FormData(form)
        };
        if (token) options.headers['X-CSRF-TOKEN'] = token.content;
        if (method !== 'GET' && method !== 'POST') options.headers['X-HTTP-Method-Override'] = method;
        fetch(form.action, options)
            .then(function (response) {
                return response.json().catch(function () { return {}; }).then(function (json) {
                    if (!response.ok) throw json;
                    return json;
                });
            })
            .then(function (json) {
                notify(json.message || 'Saved successfully.', json.level || 'success');
                if (json.redirect || json.refresh) {
                    setBusy(form, false);
                    loadPage(json.redirect || window.location.href, !!json.redirect);
                } else {
                    form.reset();
                }
            })
            .catch(function (json) {
                var errors = json && json.errors ? Object.values(json.errors).flat().join(' ') : null;
                notify(errors || (json && json.message) || 'Request failed. Please check the form.', (json && json.level) || 'error');
            })
            .finally(function () { setBusy(form, false); });
    }

    function bindAjax(scope) {
        scope.querySelectorAll('form').forEach(function (form) {
            if (form.dataset.ajaxBound) return;
            form.dataset.ajaxBound = 'true';
            form.addEventListener('submit', function (event) {
                if (form.hasAttribute('data-no-ajax')) return;
                event.preventDefault();
                if ((form.method || 'get').toLowerCase() === 'get') {
                    var params = new URLSearchParams(new FormData(form));
                    loadPage(form.action.split('?')[0] + '?' + params.toString(), false);
                } else {
                    submitForm(form);
                }
            });
        });
        scope.querySelectorAll('.portal-pagination a, a[data-ajax-link]').forEach(function (link) {
            if (link.dataset.ajaxBound) return;
            link.dataset.ajaxBound = 'true';
            link.addEventListener('click', function (event) {
                event.preventDefault();
                loadPage(link.href, false);
            });
        });
        scope.querySelectorAll('[data-employee-url]').forEach(function (row) {
            console.log('hello');
            
            if (row.dataset.ajaxBound) return;
            row.dataset.ajaxBound = 'true';
            row.addEventListener('click', function () { loadPage(row.dataset.employeeUrl, true); });
        });
    }

    document.querySelectorAll('[data-print]').forEach(function (button) {
        button.addEventListener('click', function () { window.print(); });
    });
    if (main) bindAjax(document);
    window.addEventListener('popstate', function () { loadPage(window.location.href, false, true); });
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('button[aria-busy="true"]').forEach(function (button) {
            button.disabled = false; button.removeAttribute('aria-busy');
        });
    });
}());
