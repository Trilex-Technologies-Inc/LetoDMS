(function () {
    'use strict';
    function init() {
        var guide = document.getElementById('help-guide');
        if (!guide) return;
        var input = document.getElementById('help-query');
        var clear = document.getElementById('help-clear');
        var status = document.getElementById('help-results');
        var empty = document.getElementById('help-empty');
        var topics = guide.querySelectorAll('[data-help-topic]');
        var links = guide.querySelectorAll('[data-help-link]');
        function normalize(text) {
            text = text.toLowerCase();
            return text.normalize ? text.normalize('NFD').replace(/[\u0300-\u036f]/g, '') : text;
        }
        var index = [];
        for (var i = 0; i < topics.length; i++) {
            index.push(normalize(topics[i].textContent + ' ' + (topics[i].getAttribute('data-keywords') || '')));
        }
        function filter() {
            var query = normalize(input.value.trim());
            var terms = query ? query.split(/\s+/) : [];
            var count = 0;
            for (var i = 0; i < topics.length; i++) {
                var matches = terms.every(function (term) { return index[i].indexOf(term) !== -1; });
                topics[i].hidden = !matches;
                if (matches) count++;
            }
            for (var j = 0; j < links.length; j++) {
                var topic = document.getElementById(links[j].getAttribute('href').slice(1));
                links[j].hidden = topic ? topic.hidden : false;
            }
            empty.hidden = count !== 0;
            clear.hidden = !query;
            status.textContent = query ? count + (count === 1 ? ' topic found' : ' topics found') : 'Browse all ' + topics.length + ' topics';
        }
        function reset() { input.value = ''; filter(); input.focus(); }
        document.getElementById('help-search').hidden = false;
        input.addEventListener('input', filter);
        input.addEventListener('keydown', function (event) { if (event.key === 'Escape') reset(); });
        clear.addEventListener('click', reset);
        window.addEventListener('hashchange', function () {
            var id;
            try { id = decodeURIComponent(window.location.hash.slice(1)); } catch (ignore) { return; }
            var target = document.getElementById(id);
            if (target && target.hasAttribute('data-help-topic') && target.hidden) {
                input.value = ''; filter(); target.scrollIntoView();
            }
        });
        filter();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
}());
