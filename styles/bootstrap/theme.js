(function () {
    'use strict';
    var key = 'letodms-color-mode';
    var preference = null;
    var system = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
    try { preference = window.localStorage.getItem(key); } catch (ignore) {}
    if (preference !== 'light' && preference !== 'dark') preference = null;

    function apply() {
        var dark = preference ? preference === 'dark' : !!(system && system.matches);
        document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
        var buttons = document.querySelectorAll('[data-theme-toggle]');
        for (var i = 0; i < buttons.length; i++) {
            buttons[i].setAttribute('aria-pressed', dark ? 'true' : 'false');
            buttons[i].setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
            buttons[i].title = dark ? 'Switch to light mode' : 'Switch to dark mode';
            buttons[i].querySelector('[data-theme-icon]').textContent = dark ? '\u2600' : '\u263d';
            buttons[i].querySelector('[data-theme-label]').textContent = dark ? 'Light mode' : 'Dark mode';
        }
    }
    // Runs in the head, before styles and body paint, to avoid a bright flash.
    apply();
    document.addEventListener('DOMContentLoaded', apply);
    document.addEventListener('click', function (event) {
        var button = event.target.closest ? event.target.closest('[data-theme-toggle]') : null;
        if (!button) return;
        preference = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        try { window.localStorage.setItem(key, preference); } catch (ignore) {}
        apply();
    });
    window.addEventListener('storage', function (event) {
        if (event.key !== key && event.key !== null) return;
        preference = event.newValue === 'dark' || event.newValue === 'light' ? event.newValue : null;
        apply();
    });
    if (system) {
        if (system.addEventListener) system.addEventListener('change', apply);
        else if (system.addListener) system.addListener(apply);
    }
}());
