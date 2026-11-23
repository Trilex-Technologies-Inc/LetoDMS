(function () {
    'use strict';
    var endpoint = '../op/op.Module.php?module=ai-assistant';
    var presets = {
        openai: {label: 'OpenAI', protocol: 'openai', endpoint: 'https://api.openai.com/v1/chat/completions'},
        anthropic: {label: 'Anthropic', protocol: 'anthropic', endpoint: 'https://api.anthropic.com/v1/messages'}
    };
    function field(form, name) { return form.elements.namedItem(name); }
    function status(form, text) { form.querySelector('.ai-model-status').textContent = text; }
    function picker(select, ids, input) {
        select.textContent = '';
        var empty = document.createElement('option'); empty.value = ''; empty.textContent = 'Choose a model'; select.appendChild(empty);
        ids.forEach(function (id) {
            var option = document.createElement('option'); option.value = id; option.textContent = id; select.appendChild(option);
        });
        select.style.display = ids.length ? '' : 'none';
        select.value = ids.indexOf(input.value) >= 0 ? input.value : '';
        select.onchange = function () { if (select.value) input.value = select.value; };
    }
    function request(form, provider, key) {
        var data = new FormData(form);
        // Keep the form key, but never send unrelated settings or document content.
        Array.from(data.keys()).forEach(function (name) {
            if (name !== 'formtoken') data.delete(name);
        });
        data.set('action', 'list_models'); data.set('provider_id', provider); data.set('api_key', key || '');
        return fetch(endpoint, {method: 'POST', body: data, credentials: 'same-origin', cache: 'no-store'})
            .then(function (response) {
                return response.json().then(function (body) {
                    if (!response.ok || body.error) throw new Error(body.error || 'Model lookup failed.');
                    return body;
                });
            });
    }
    document.querySelectorAll('.ai-provider-form').forEach(function (form) {
        var button = form.querySelector('.ai-load-models');
        var select = form.querySelector('.ai-model-picker');
        var serial = 0;
        function changed() {
            serial++;
            button.disabled = field(form, 'endpoint').value !== presets.openai.endpoint || field(form, 'protocol').value !== 'openai';
            select.style.display = 'none';
            select.textContent = '';
        }
        form.querySelector('.ai-provider-preset').addEventListener('change', function (event) {
            var preset = presets[event.target.value];
            if (!preset) { changed(); return; }
            if (field(form, 'endpoint').value !== preset.endpoint) {
                field(form, 'model').value = ''; field(form, 'api_key').value = '';
            }
            field(form, 'endpoint').value = preset.endpoint; field(form, 'protocol').value = preset.protocol;
            if (!field(form, 'provider_id').readOnly) field(form, 'provider_id').value = event.target.value;
            if (!field(form, 'label').value || /^(OpenAI|Anthropic)$/.test(field(form, 'label').value)) field(form, 'label').value = preset.label;
            status(form, event.target.value === 'openai' ? 'Enter your API key, then load models to choose one.' : 'Enter the model ID supplied by Anthropic.');
            changed();
        });
        ['endpoint', 'protocol', 'api_key'].forEach(function (name) { field(form, name).addEventListener('input', changed); });
        button.addEventListener('click', function () {
            var current = ++serial; button.disabled = true; status(form, 'Loading models from OpenAI…');
            request(form, field(form, 'provider_id').value, field(form, 'api_key').value)
                .then(function (data) {
                    if (current !== serial) return;
                    picker(select, data.chat, field(form, 'model'));
                    status(form, data.chat.length ? 'Choose a model above. This list is filtered by model name; availability does not guarantee Chat Completions compatibility. Save and test your choice.' : 'No candidate chat models were returned. You can enter a model ID manually.');
                })
                .catch(function (error) { if (current === serial) status(form, error instanceof SyntaxError ? 'Could not read the model list. Reload this page and try again.' : error.message); })
                .finally(function () {
                    if (current === serial) button.disabled = field(form, 'endpoint').value !== presets.openai.endpoint || field(form, 'protocol').value !== 'openai';
                });
        });
        changed();
    });
    var processing = document.querySelector('.ai-processing-form');
    if (!processing) return;
    var embeddingProvider = field(processing, 'embedding_provider');
    var embeddingButton = processing.querySelector('.ai-load-embedding-models');
    var embeddingSelect = processing.querySelector('.ai-embedding-picker');
    var embeddingSerial = 0;
    function savedOpenAI() {
        return Array.from(document.querySelectorAll('.ai-provider-form')).some(function (form) {
            return field(form, 'provider_id').readOnly && field(form, 'provider_id').value === embeddingProvider.value && form.querySelector('.ai-provider-preset').value === 'openai';
        });
    }
    embeddingProvider.addEventListener('change', function () {
        embeddingSerial++; embeddingSelect.style.display = 'none';
        if (savedOpenAI()) {
            var input = field(processing, 'embedding_endpoint');
            if (!input.value) input.value = 'https://api.openai.com/v1/embeddings';
            status(processing, 'OpenAI embeddings endpoint filled in. Load models to choose an embeddings model.');
        } else status(processing, 'For other providers, enter their embeddings endpoint and model ID.');
    });
    embeddingButton.addEventListener('click', function () {
        if (!embeddingProvider.value) { status(processing, 'Choose a saved OpenAI embeddings provider first.'); return; }
        var provider = embeddingProvider.value; var current = ++embeddingSerial;
        embeddingButton.disabled = true; status(processing, 'Loading embeddings models from OpenAI…');
        request(processing, provider, '')
            .then(function (data) {
                if (current !== embeddingSerial) return;
                picker(embeddingSelect, data.embeddings, field(processing, 'embedding_model'));
                if (!field(processing, 'embedding_endpoint').value) field(processing, 'embedding_endpoint').value = 'https://api.openai.com/v1/embeddings';
                status(processing, data.embeddings.length ? 'Choose an embeddings model, then save processing settings.' : 'No embeddings models were returned. You can enter a model ID manually.');
            })
            .catch(function (error) { if (current === embeddingSerial) status(processing, error instanceof SyntaxError ? 'Could not read the model list. Reload this page and try again.' : error.message); })
            .finally(function () { embeddingButton.disabled = false; });
    });
}());
