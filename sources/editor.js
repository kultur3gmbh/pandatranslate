(function () {
	'use strict';
	var config = window.PandaTranslateEditor;
	if (!config || !window.PandaTranslateRequest) return;
	var request = window.PandaTranslateRequest.create(config),
		i18n = config.i18n;
	var selected = null,
		generation = 0,
		automaticBusy = false;
	var languages = new Map();
	var tokenPattern = /(\[\[PT:\d+:(?:OPEN|CLOSE|VOID)\]\])/;
	var shell = document.createElement('aside');
	shell.className = 'pandatranslate-panel is-open';
	shell.setAttribute('translate', 'no');
	shell.innerHTML =
		'<header><h2></h2><button type="button" class="pandatranslate-close contactpanda-list-action"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button></header>' +
		'<p data-preview></p><label class="pandatranslate-editor-api"><span></span><select></select></label>' +
		'<button type="button" class="contactpanda-large-action pandatranslate-button pandatranslate-page-button" data-automatic-page><span class="dashicons dashicons-translation" aria-hidden="true"></span><span></span></button>' +
		'<section class="pandatranslate-page-progress" hidden><strong data-progress-title></strong><span data-progress-count></span><span class="pandatranslate-progress-track"><i data-progress-bar></i></span><small data-progress-detail></small></section>' +
		'<p class="pandatranslate-empty" role="status"></p><form hidden><label><span data-source-label></span><textarea data-source readonly rows="2"></textarea></label><div data-languages></div></form>';
	document.body.appendChild(shell);

	function setOpen(open) {
		shell.classList.toggle('is-open', open);
		document.body.classList.toggle('pandatranslate-editor-open', open);
		shell.inert = !open;
	}
	setOpen(true);
	shell.querySelector('h2').textContent = i18n.title;
	var close = shell.querySelector('.pandatranslate-close');
	close.setAttribute('aria-label', i18n.close);
	close.addEventListener('click', function () {
		setOpen(false);
	});
	var preview = shell.querySelector('[data-preview]');
	preview.textContent = i18n.previewNotice + ' ';
	if (config.publicUrl) {
		var link = document.createElement('a');
		link.href = config.publicUrl;
		link.target = '_blank';
		link.rel = 'noopener';
		link.textContent = i18n.publicPage;
		preview.appendChild(link);
	}
	var empty = shell.querySelector('.pandatranslate-empty');
	empty.textContent = i18n.select;
	var form = shell.querySelector('form');
	form.addEventListener('submit', function (event) {
		event.preventDefault();
	});
	var source = form.querySelector('[data-source]');
	form.querySelector('[data-source-label]').textContent = i18n.source;
	var languageList = form.querySelector('[data-languages]');
	var pageButton = shell.querySelector('[data-automatic-page]');
	var pageProgress = shell.querySelector('.pandatranslate-page-progress');
	var apiLabel = shell.querySelector('.pandatranslate-editor-api');
	var apiSelect = apiLabel.querySelector('select'),
		apiChoices = config.apis || {};
	apiLabel.firstElementChild.textContent = i18n.apiLabel;
	Object.keys(apiChoices).forEach(function (api) {
		apiSelect.add(new Option(apiChoices[api], api, false, api === config.defaultApi));
	});
	if (!apiSelect.options.length) apiSelect.add(new Option(i18n.noApi, ''));
	apiSelect.addEventListener('change', updateApiLabels);

	function updateControls(panel) {
		panel.save.disabled = panel.publish.disabled = !panel.ready || panel.busy;
		panel.automatic.disabled = !panel.ready || panel.busy || automaticBusy || !apiSelect.value;
		panel.automatic.lastElementChild.textContent = i18n.automatic.replace(
			'%s',
			apiChoices[apiSelect.value] || i18n.noApi,
		);
	}
	function updateApiLabels() {
		pageButton.lastElementChild.textContent = i18n.automaticPage.replace(
			'%s',
			apiChoices[apiSelect.value] || i18n.noApi,
		);
		pageButton.disabled = !apiSelect.value || automaticBusy;
		apiSelect.disabled = automaticBusy || Object.keys(apiChoices).length < 2;
		languages.forEach(updateControls);
	}
	updateApiLabels();

	function decode(text) {
		var field = document.createElement('textarea');
		field.innerHTML = text.replace(/</g, '&lt;');
		return field.value;
	}
	function encode(text) {
		return text
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/\[\[PT:/g, '&#91;[PT:');
	}
	function updateLanguage(panel, data) {
		if (!panel || !data.editor) return;
		panel.revision = data.revision || '';
		panel.status.value =
			data.status === 'published' && config.canPublish === false
				? 'reviewed'
				: data.status || 'untranslated';
		source.value = data.editor.source;
		panel.fields.replaceChildren();
		panel.tokens = data.editor.preview_tokens || {};
		var parts = data.editor.text.split(tokenPattern);
		panel.parts = parts.map(function (part, index) {
			if (index % 2) {
				var token = panel.tokens[part] || '';
				if (/^Panda Translate(?: Multilingual)?$/i.test(token)) {
					var brand = document.createElement('span');
					brand.textContent = token;
					panel.fields.append(brand);
				}
				return part;
			}
			if (!part.trim()) return part;
			var input = document.createElement('textarea');
			input.rows = part.length > 85 ? 3 : 1;
			input.value = decode(part);
			input.setAttribute('aria-label', i18n.translation + ' · ' + panel.name);
			input.addEventListener('input', function () {
				if (selected && panel.language === config.language) {
					var html = serialize(panel).replace(
						/\[\[PT:\d+:(?:OPEN|CLOSE|VOID)\]\]/g,
						function (token) {
							return panel.tokens[token] || '';
						},
					);
					updatePreview(selected, html);
				}
			});
			panel.fields.append(input);
			return { input: input, initial: input.value, raw: part };
		});
		panel.ready = true;
		updateControls(panel);
	}
	function address(node, language) {
		return {
			key: node.dataset.pandatranslateUnit,
			source_hash: node.dataset.pandatranslateSourceHash,
			language: language,
			reference_language: config.language,
		};
	}
	function panelIsCurrent(panel, node, version) {
		return selected === node && generation === version && languages.get(panel.language) === panel;
	}
	function loadLanguage(panel) {
		if (panel.ready || panel.busy || !selected) return;
		panel.busy = true;
		updateControls(panel);
		var node = selected,
			version = generation;
		var args = Object.assign(address(node, panel.language), { editor_view: 1 });
		request(window.PandaTranslateRequest.query(config.restUrl, args))
			.then(function (data) {
				if (!panelIsCurrent(panel, node, version)) return;
				if (!data.editor || data.source_hash !== node.dataset.pandatranslateSourceHash) {
					panel.message.textContent = i18n.requestResponseError;
					return;
				}
				updateLanguage(panel, data);
			})
			.catch(function (error) {
				if (panelIsCurrent(panel, node, version)) panel.message.textContent = error.message;
			})
			.finally(function () {
				panel.busy = false;
				if (panelIsCurrent(panel, node, version)) updateControls(panel);
			});
	}
	function serialize(panel) {
		return panel.parts
			.map(function (part) {
				return typeof part === 'string'
					? part
					: part.initial === part.input.value
						? part.raw
						: encode(part.input.value);
			})
			.join('');
	}
	function submit(panel, nextStatus, automatic) {
		if (!selected || !panel.ready || panel.busy || (automatic && automaticBusy)) return;
		panel.busy = true;
		if (automatic) automaticBusy = true;
		updateApiLabels();
		var node = selected,
			version = generation,
			args = address(node, panel.language);
		if (automatic) {
			args.api = apiSelect.value;
			args.confirm = true;
		} else {
			args.revision = panel.revision;
			args.status = nextStatus || panel.status.value;
			args.translated_text = serialize(panel);
			args.preserve_translation = panel.parts.every(function (part) {
				return typeof part === 'string' || part.initial === part.input.value;
			});
		}
		panel.message.textContent = automatic ? i18n.translating : i18n.saving;
		request(automatic ? config.autoUrl : config.restUrl, args)
			.then(function (data) {
				if (!panelIsCurrent(panel, node, version)) return;
				if (panel.language === config.language && typeof data.translated_html === 'string')
					updatePreview(node, data.translated_html);
				updateLanguage(panel, data);
				panel.message.textContent = i18n.saved;
			})
			.catch(function (error) {
				if (panelIsCurrent(panel, node, version)) panel.message.textContent = error.message;
			})
			.finally(function () {
				panel.busy = false;
				if (automatic) automaticBusy = false;
				updateApiLabels();
			});
	}
	function updatePreview(node, html) {
		if (node.matches('input[type=submit]')) node.value = decode(html);
		else node.innerHTML = html;
	}
	function makeLanguage(code, name) {
		var details = document.createElement('details');
		details.className = 'pandatranslate-editor-language';
		details.innerHTML =
			'<summary></summary><fieldset data-translation-group><legend></legend><div data-translation></div></fieldset><label><span></span><select data-status></select></label>' +
			'<div class="pandatranslate-actions"><button type="button" class="contactpanda-large-action" data-automatic><span class="dashicons dashicons-translation" aria-hidden="true"></span><span></span></button>' +
			'<button type="button" class="contactpanda-large-action" data-save data-contactpanda-action="save" data-contactpanda-action-mode="labelled"><span class="dashicons dashicons-saved" aria-hidden="true"></span><span></span></button>' +
			'<button type="button" class="contactpanda-large-action is-primary" data-publish data-contactpanda-action="publish" data-contactpanda-action-mode="labelled"><span class="dashicons dashicons-megaphone" aria-hidden="true"></span><span></span></button></div><p data-message role="status"></p>';
		var panel = {
			language: code,
			name: name,
			details: details,
			ready: false,
			busy: false,
			status: details.querySelector('[data-status]'),
			fields: details.querySelector('[data-translation]'),
			message: details.querySelector('[data-message]'),
			save: details.querySelector('[data-save]'),
			publish: details.querySelector('[data-publish]'),
			automatic: details.querySelector('[data-automatic]'),
		};
		details.querySelector('summary').textContent = name;
		details.querySelector('legend').textContent = i18n.translation;
		details.querySelector('label span').textContent = i18n.status;
		Object.keys(i18n.statusLabels).forEach(function (key) {
			var option = new Option(i18n.statusLabels[key], key);
			option.disabled = key === 'published' && config.canPublish === false;
			panel.status.add(option);
		});
		panel.save.lastElementChild.textContent = i18n.save;
		panel.publish.lastElementChild.textContent = i18n.publish;
		panel.publish.hidden = config.canPublish === false;
		panel.save.addEventListener('click', function () {
			submit(panel);
		});
		panel.publish.addEventListener('click', function () {
			submit(panel, 'published');
		});
		panel.automatic.addEventListener('click', function () {
			submit(panel, '', true);
		});
		details.addEventListener('toggle', function () {
			if (details.open) loadLanguage(panel);
		});
		updateControls(panel);
		languageList.append(details);
		languages.set(code, panel);
	}
	function openUnit(node) {
		if (selected) selected.classList.remove('pandatranslate-selected');
		selected = node;
		generation++;
		node.classList.add('pandatranslate-selected');
		setOpen(true);
		form.hidden = false;
		empty.hidden = true;
		source.value = '';
		languageList.replaceChildren();
		languages.clear();
		var options = config.languages || {};
		Object.keys(options).forEach(function (code) {
			makeLanguage(code, options[code]);
		});
		var current = languages.get(config.language) || languages.values().next().value;
		if (current) current.details.open = true;
	}
	document.addEventListener('pandatranslate:media-selected', function () {
		if (selected) selected.classList.remove('pandatranslate-selected');
		selected = null;
		generation++;
		setOpen(true);
	});
	document.addEventListener(
		'click',
		function (event) {
			if (
				shell.contains(event.target) ||
				event.target.closest('img[data-pandatranslate-media],.media-modal,.media-modal-backdrop')
			)
				return;
			var unit = event.target.closest('[data-pandatranslate-unit]');
			if (unit) {
				event.preventDefault();
				event.stopPropagation();
				openUnit(unit);
				return;
			}
			var candidate = event.target.closest('p,h1,h2,h3,h4,h5,h6,li,td,th,button,a[role=button]');
			if (
				!config.captureAllowed ||
				!config.postId ||
				!candidate ||
				candidate.closest(
					'#wpadminbar,.pandatranslate-switcher,form,[translate=no],[data-no-translation],.notranslate,.trp-no-translation',
				) ||
				candidate.querySelector('p,li,td,th,div,img,input,textarea,select') ||
				!candidate.textContent.trim()
			)
				return;
			event.preventDefault();
			event.stopPropagation();
			var version = ++generation;
			request(config.dynamicUrl, {
				post_id: config.postId,
				language: config.language,
				source_html: candidate.innerHTML,
			})
				.then(function (data) {
					if (version !== generation) return;
					candidate.dataset.pandatranslateUnit = data.key;
					candidate.dataset.pandatranslateSourceHash = data.source_hash;
					openUnit(candidate);
				})
				.catch(function (error) {
					if (version !== generation) return;
					empty.hidden = false;
					empty.textContent = error.message;
				});
		},
		true,
	);
	shell.querySelector('[data-automatic-page]').addEventListener('click', function () {
		if (!apiSelect.value || automaticBusy) return;
		automaticBusy = true;
		updateApiLabels();
		var api = apiSelect.value,
			nodes = new Map();
		document.querySelectorAll('[data-pandatranslate-unit]').forEach(function (node) {
			if (
				node.closest(
					'#wpadminbar,.pandatranslate-panel,.fusion-builder-ui,.elementor-editor-active',
				)
			)
				return;
			var key = node.dataset.pandatranslateUnit;
			if (!nodes.has(key)) nodes.set(key, []);
			nodes.get(key).push(node);
		});
		var units = Array.from(nodes.keys()),
			index = 0,
			done = 0,
			failed = 0,
			button = this,
			total = units.length,
			stopping = false,
			streak = 0,
			lastCode = '',
			lastError = '';
		var cancel = document.createElement('button');
		cancel.type = 'button';
		cancel.className = 'contactpanda-large-action';
		cancel.dataset.contactpandaAction = 'cancel';
		cancel.dataset.contactpandaActionMode = 'labelled';
		cancel.textContent = config.i18n.cancelLabel;
		pageProgress.appendChild(cancel);
		if (window.ContactPandaAdminUI) window.ContactPandaAdminUI.enhance(pageProgress);
		cancel.addEventListener('click', function () {
			stopping = true;
			cancel.disabled = true;
			pageProgress.querySelector('[data-progress-detail]').textContent = config.i18n.cancelling;
		});
		button.disabled = true;
		apiSelect.disabled = true;
		pageProgress.hidden = false;
		pageProgress.classList.remove('has-errors', 'is-complete');
		pageProgress.querySelector('[data-progress-title]').textContent =
			config.i18n.progressTitle + ' · ' + apiChoices[api];
		function render() {
			var processed = done + failed,
				percent = total ? Math.round((processed / total) * 100) : 100;
			pageProgress.querySelector('[data-progress-count]').textContent =
				processed + ' / ' + total + ' (' + percent + '%)';
			pageProgress.querySelector('[data-progress-bar]').style.width = percent + '%';
			pageProgress.querySelector('[data-progress-detail]').textContent =
				done +
				' ' +
				config.i18n.progressDone +
				' · ' +
				failed +
				' ' +
				config.i18n.progressFailed +
				(lastError ? ' · ' + lastError : '');
		}
		function next() {
			render();
			if (stopping || streak >= 3 || index >= total) {
				automaticBusy = false;
				updateApiLabels();
				cancel.remove();
				pageProgress.classList.add(failed ? 'has-errors' : 'is-complete');
				if (stopping)
					pageProgress.querySelector('[data-progress-detail]').textContent = config.i18n.cancelled;
				if (streak >= 3)
					pageProgress.querySelector('[data-progress-detail]').textContent =
						config.i18n.repeatedErrors + ' ' + lastError;
				return;
			}
			var key = units[index++];
			request(config.autoUrl, { key: key, language: config.language, api: api, confirm: true })
				.then(function (data) {
					if (typeof data.translated_html === 'string')
						nodes.get(key).forEach(function (node) {
							updatePreview(node, data.translated_html);
						});
					if (selected && selected.dataset.pandatranslateUnit === key)
						updateLanguage(languages.get(config.language), data);
					done++;
					streak = 0;
				})
				.catch(function (error) {
					failed++;
					var code = error.code || 'request';
					streak = code === lastCode ? streak + 1 : 1;
					lastCode = code;
					lastError = error.message || code;
				})
				.finally(next);
		}
		next();
	});

	new MutationObserver(function () {
		document.dispatchEvent(new CustomEvent('pandatranslate:content-changed'));
	}).observe(document.body, { childList: true, subtree: true });
})();
