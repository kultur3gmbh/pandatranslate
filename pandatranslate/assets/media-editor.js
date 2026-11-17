(function () {
    'use strict';
    var config = window.PandaTranslateMedia, editor = window.PandaTranslateEditor;
    if (!config || !editor || !window.ContactPandaAdminUI) return;
    document.body.classList.add('pandatranslate-editor-active');
    var shell = document.querySelector('.pandatranslate-panel'), selected, revision, attachment = 0, generation = 0;
    var panel = document.createElement('section'); panel.hidden = true; panel.className = 'pandatranslate-media-panel';
    var title = document.createElement('h3'); title.textContent = config.labels.title; panel.appendChild(title);
    var original = document.createElement('img'); original.alt = config.labels.source; original.style.cssText = 'max-width:100%;max-height:180px;object-fit:contain;display:block;margin:0 auto 16px'; panel.appendChild(original);
    var picker = ContactPandaAdminUI.createImagePicker({allowExternal: false, onChange: function (item) { attachment = item ? item.id : 0; }}); panel.appendChild(picker);
    var fields = {};
    ['alt', 'title', 'caption'].forEach(function (key) { var label = document.createElement('label'), text = document.createElement('span'), input = document.createElement(key === 'caption' ? 'textarea' : 'input'); text.textContent = config.labels[key === 'title' ? 'titleText' : key]; label.append(text, input); panel.appendChild(label); fields[key] = input; });
    var message = document.createElement('p'); message.setAttribute('role', 'status');
    function button(label, action, callback) { var node = document.createElement('button'); node.type = 'button'; node.className = 'contactpanda-large-action'; node.dataset.contactpandaAction = action; node.dataset.contactpandaActionMode = 'labelled'; node.textContent = label; node.addEventListener('click', callback); panel.appendChild(node); return node; }
    var saveButton = button(config.labels.save, 'save', function () { persist(false); });
    var removeButton = button(config.labels.remove, 'delete', function () { persist(true); });
    panel.appendChild(message); shell.appendChild(panel); ContactPandaAdminUI.enhance(panel);
    function request(method, body) { return fetch(config.url + (method === 'GET' ? '?' + new URLSearchParams(body).toString() : ''), {method: method, headers: {'Content-Type': 'application/json', 'X-WP-Nonce': editor.nonce}, body: method === 'GET' ? undefined : JSON.stringify(body)}).then(function (response) { return response.json().then(function (data) { if (!response.ok) throw new Error(data.message); return data; }); }); }
    function load(data) { revision = data.revision; attachment = data.mapping ? data.mapping.attachment_id : 0; picker.contactPandaSetImages(data.image ? [{id: attachment, url: data.image.src, alt: data.image.alt}] : []); ['alt', 'title', 'caption'].forEach(function (key) { fields[key].value = data.mapping ? data.mapping[key] : ''; }); }
    function persist(remove) {
        if (!selected || !revision || (!remove && !attachment)) return;
        var node = selected, version = generation;
        saveButton.disabled = removeButton.disabled = true;
        request('POST', {reference: node.dataset.pandatranslateMedia, language: editor.language, revision: revision, remove: remove, attachment_id: attachment, alt: fields.alt.value, title: fields.title.value, caption: fields.caption.value}).then(function (data) {
            if (version !== generation) return;
            load(data); message.textContent = config.labels.saved;
            // Reload the server-rendered responsive image, picture sources and lazy attributes together.
            window.location.reload();
        }).catch(function (error) { if (version === generation) message.textContent = error.message || config.labels.failed; }).finally(function () { if (version === generation) saveButton.disabled = removeButton.disabled = false; });
    }
    document.addEventListener('click', function (event) {
        if (shell.contains(event.target) || event.target.closest('.media-modal,.media-modal-backdrop')) return;
        var node = event.target.closest('img[data-pandatranslate-media]');
        if (!node) { panel.hidden = true; generation++; return; }
        event.preventDefault(); event.stopImmediatePropagation(); document.dispatchEvent(new CustomEvent('pandatranslate:media-selected')); selected = node; var version = ++generation; revision = null;
        shell.classList.add('is-open'); shell.querySelector('form').hidden = true; shell.querySelector('.pandatranslate-empty').hidden = true; panel.hidden = false;
        var source = JSON.parse(node.dataset.pandatranslateMediaOriginal); original.src = source.src;
        message.textContent = ''; saveButton.disabled = removeButton.disabled = true;
        request('GET', {reference: node.dataset.pandatranslateMedia, language: editor.language}).then(function (data) { if (version !== generation) return; load(data); saveButton.disabled = removeButton.disabled = false; }).catch(function (error) { if (version === generation) message.textContent = error.message; });
    }, true);
}());
