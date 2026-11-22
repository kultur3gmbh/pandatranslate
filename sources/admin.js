(function () {
	'use strict';
	var config = window.PandaTranslateAdmin;
	if (!config) return;
	var adminRoot = document.querySelector('.pandatranslate-admin');
	if (adminRoot) {
		new MutationObserver(function (records) {
			records.forEach(function (record) {
				var control = record.target;
				if (control.matches('button,input'))
					control.classList.toggle('is-disabled', control.disabled);
			});
		}).observe(adminRoot, { attributes: true, attributeFilter: ['disabled'], subtree: true });
	}
	var edition = document.querySelector('[data-google-edition]');
	function showEdition() {
		if (!edition) return;
		document.querySelectorAll('[data-google-fields]').forEach(function (section) {
			section.hidden = section.dataset.googleFields !== edition.value;
		});
	}
	if (edition) {
		edition.addEventListener('change', showEdition);
		showEdition();
	}
	document.querySelectorAll('[data-google-test]').forEach(function (button) {
		button.addEventListener('click', function () {
			var section = button.closest('[data-google-fields]'),
				output = section.querySelector('[data-google-result]'),
				key = document.querySelector('input[name$="[google_api_key]"]'),
				project = document.querySelector('[data-google-project]'),
				location = document.querySelector('[data-google-location]');
			var enabled = document.querySelector(
				'input[name$="[google_' + button.dataset.googleTest + '_enabled]"]',
			);
			var payload = {
				enabled: enabled ? enabled.checked : false,
				edition: button.dataset.googleTest,
				api_key: button.dataset.googleTest === 'v2' && key ? key.value : '',
				project_id: project ? project.value : '',
				location: location ? location.value : 'global',
			};
			button.disabled = true;
			button.setAttribute('aria-busy', 'true');
			output.textContent = config.testing;
			fetch(config.testUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
				body: JSON.stringify(payload),
			})
				.then(function (response) {
					return response.json().then(function (data) {
						if (!response.ok) throw data;
						return data;
					});
				})
				.then(function (data) {
					output.textContent = config.connected + ': ' + data.translation;
					if (key && button.dataset.googleTest === 'v2') key.value = '';
					window.setTimeout(function () {
						window.location.reload();
					}, 900);
				})
				.catch(function (error) {
					output.textContent = error.message || config.testFailed;
				})
				.finally(function () {
					button.disabled = false;
					button.removeAttribute('aria-busy');
				});
		});
	});
	var deeplTest = document.querySelector('[data-deepl-test]');
	if (deeplTest)
		deeplTest.addEventListener('click', function () {
			var key = document.querySelector('input[name$="[deepl_key]"]'),
				plan = document.querySelector('select[name$="[deepl_endpoint]"]'),
				output = document.querySelector('[data-deepl-result]');
			deeplTest.disabled = true;
			output.textContent = config.testing;
			fetch(config.deeplTestUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
				body: JSON.stringify({ api_key: key.value, plan: plan.value }),
			})
				.then(function (r) {
					return r.json().then(function (data) {
						if (!r.ok) throw data;
						return data;
					});
				})
				.then(function (data) {
					output.textContent = config.connected + ': ' + data.translation;
					key.value = '';
				})
				.catch(function (e) {
					output.textContent = e.message || config.testFailed;
				})
				.finally(function () {
					deeplTest.disabled = false;
				});
		});
	var apiSelector = document.querySelector('#pandatranslate-run-api'),
		runLabel = document.querySelector('[data-api-run-label]');
	function selectedApi() {
		return apiSelector ? apiSelector.value : config.defaultApi;
	}
	function updateRunLabel() {
		if (runLabel && apiSelector && apiSelector.selectedOptions.length)
			runLabel.textContent = apiSelector.value
				? apiSelector.selectedOptions[0].textContent
				: config.noApi;
	}
	if (apiSelector) {
		apiSelector.addEventListener('change', updateRunLabel);
		updateRunLabel();
	}
	var picker = document.querySelector('[data-language-picker]'),
		rows = document.querySelector('[data-language-rows]');
	if (rows)
		rows.addEventListener('change', function (event) {
			var active = event.target;
			if (
				rows.dataset.languageLimit !== '2' ||
				!active.matches('input[type="checkbox"][name$="[active]"]')
			)
				return;
			var feedback = document.querySelector('[data-language-activation-feedback]');
			var occupied =
				active.checked &&
				Array.from(
					rows.querySelectorAll(
						'[data-language-row]:not(.is-source-language) input[type="checkbox"][name$="[active]"]:checked',
					),
				).some(function (input) {
					return input !== active;
				});
			if (occupied) active.checked = false;
			if (feedback) feedback.textContent = occupied ? feedback.dataset.message : '';
		});
	function cancelRun(id, nonce) {
		return fetch(window.ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			body: new URLSearchParams({
				action: 'pandatranslate_cancel_translation',
				nonce: nonce,
				run_id: id,
			}),
		}).then(function (r) {
			return r.json().then(function (v) {
				if (!r.ok || !v.success) throw new Error(config.cancelFailed);
				return v.data;
			});
		});
	}
	var jobPollTimer = null;
	function jobControls(running) {
		document
			.querySelectorAll('[data-language-translate],[data-language-translate-all]')
			.forEach(function (button) {
				if (!button.dataset.jobPrevious)
					button.dataset.jobPrevious = button.disabled ? 'disabled' : 'enabled';
				button.disabled = running || button.dataset.jobPrevious === 'disabled';
			});
		if (apiSelector) apiSelector.disabled = running;
	}
	function pollSiteJob() {
		var host = document.querySelector('[data-site-job]');
		if (!host) return;
		clearTimeout(jobPollTimer);
		fetch(window.ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			body: new URLSearchParams({
				action: 'pandatranslate_site_progress',
				nonce: host.dataset.nonce,
			}),
		})
			.then(function (response) {
				return response.json().then(function (result) {
					if (!response.ok || !result.success) throw new Error(config.cancelFailed);
					return result.data;
				});
			})
			.then(function (data) {
				host.innerHTML = data.html;
				jobControls(data.running);
				if (window.ContactPandaAdminUI) window.ContactPandaAdminUI.enhance(host);
				if (data.running) jobPollTimer = window.setTimeout(pollSiteJob, 4000);
				else refreshOverview(host.dataset.nonce, '');
			})
			.catch(function () {
				jobPollTimer = window.setTimeout(pollSiteJob, 10000);
			});
	}
	function runTranslation(button, languages, mode) {
		if (
			!selectedApi() ||
			((mode === 'all' || mode === 'failed') && !window.confirm(button.dataset.confirm))
		)
			return;
		var host = button.closest('.pandatranslate-language-actions'),
			output = host.querySelector('[data-language-run-status]');
		var runId =
			'run-' +
			Date.now().toString(36) +
			'-' +
			Array.from(crypto.getRandomValues(new Uint32Array(2)))
				.map(function (n) {
					return n.toString(36);
				})
				.join('-');
		jobControls(true);
		fetch(window.ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			body: new URLSearchParams({
				action: 'pandatranslate_start_language_job',
				nonce: button.dataset.nonce,
				languages: JSON.stringify(languages),
				mode: mode,
				api: selectedApi(),
				run_id: runId,
			}),
		})
			.then(function (response) {
				return response.json().then(function (result) {
					if (!response.ok || !result.success)
						throw new Error((result.data && result.data.message) || config.requestFailed);
					return result.data;
				});
			})
			.then(function (data) {
				var job = document.querySelector('[data-site-job]');
				if (job) job.innerHTML = data.html;
				pollSiteJob();
			})
			.catch(function (error) {
				output.textContent = error.message;
				jobControls(false);
				pollSiteJob();
			});
	}
	function refreshOverview(nonce, message) {
		fetch(window.ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			body: new URLSearchParams({ action: 'pandatranslate_translation_overview', nonce: nonce }),
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (result) {
				var old = document.querySelector('[data-translation-overview]');
				if (!result.success || !result.data.html || !old) return;
				var container = document.createElement('div');
				container.innerHTML = result.data.html;
				old.replaceWith(container);
				var summary = document.querySelector('[data-last-run-status]');
				if (!summary) {
					summary = document.createElement('p');
					summary.dataset.lastRunStatus = '';
					summary.setAttribute('role', 'status');
					container.before(summary);
				}
				summary.textContent = message;
				if (window.ContactPandaAdminUI) window.ContactPandaAdminUI.enhance(container);
			})
			.catch(function () {});
	}
	document.addEventListener('click', function (event) {
		var button = event.target.closest('[data-language-translate],[data-language-translate-all]');
		if (!button) return;
		if (button.hasAttribute('data-language-translate-all'))
			runTranslation(button, JSON.parse(button.dataset.languages), 'missing');
		else runTranslation(button, [button.dataset.languageTranslate], button.dataset.mode);
	});

	var siteJob = document.querySelector('[data-site-job]');
	if (siteJob) pollSiteJob();
	document.addEventListener('click', function (e) {
		var b = e.target.closest('[data-site-job-cancel]');
		if (!b) return;
		b.disabled = true;
		cancelRun(b.dataset.siteJobCancel, b.dataset.nonce)
			.then(function () {
				b.textContent = config.cancelled;
				pollSiteJob();
			})
			.catch(function (err) {
				b.disabled = false;
				b.textContent = err.message;
			});
	});
	var combobox = document.querySelector('[data-language-combobox]'),
		languageSearch = document.querySelector('[data-language-search]'),
		trigger = document.querySelector('[data-language-trigger]'),
		popup = document.querySelector('[data-language-popup]'),
		selection = document.querySelector('[data-language-selection]'),
		empty = document.querySelector('[data-language-empty]'),
		options = document.querySelectorAll('[data-language-option]');
	function closeLanguages() {
		if (!popup) return;
		popup.hidden = true;
		trigger.setAttribute('aria-expanded', 'false');
	}
	function availableOptions() {
		var found = 0,
			q = languageSearch ? languageSearch.value.trim().toLowerCase() : '';
		options.forEach(function (option) {
			var used =
					rows && rows.querySelector('input[type="hidden"][value="' + option.dataset.code + '"]'),
				match =
					!q ||
					(option.dataset.code + ' ' + option.dataset.name + ' ' + option.dataset.localName)
						.toLowerCase()
						.indexOf(q) !== -1;
			option.hidden = !!used || !match;
			if (!option.hidden) found++;
		});
		if (empty) empty.hidden = found !== 0;
	}
	if (trigger && popup) {
		trigger.addEventListener('click', function () {
			popup.hidden = !popup.hidden;
			trigger.setAttribute('aria-expanded', popup.hidden ? 'false' : 'true');
			if (!popup.hidden) {
				availableOptions();
				languageSearch.focus();
			}
		});
	}
	if (languageSearch) languageSearch.addEventListener('input', availableOptions);
	options.forEach(function (option) {
		option.addEventListener('click', function () {
			picker.value = option.dataset.code;
			selection.classList.add('has-language');
			selection.innerHTML =
				'<span class="pandatranslate-option-flag">' +
				option.dataset.flag +
				'</span><code>' +
				option.dataset.code +
				'</code><span>' +
				option.dataset.name +
				'</span>';
			if (add) add.disabled = false;
			closeLanguages();
		});
	});
	document.addEventListener('click', function (event) {
		if (combobox && !combobox.contains(event.target)) closeLanguages();
	});
	function reindexLanguages() {
		if (!rows) return;
		Array.prototype.forEach.call(rows.children, function (row, index) {
			row.querySelectorAll('[name]').forEach(function (field) {
				field.name = field.name.replace(/\[languages\]\[[^\]]*\]/, '[languages][' + index + ']');
			});
		});
	}
	var dragged = null;
	if (rows) {
		rows.addEventListener('pointerdown', function (e) {
			var handle = e.target.closest('[data-language-drag]');
			if (handle) handle.closest('[data-language-row]').draggable = true;
		});
		rows.addEventListener('dragstart', function (e) {
			var row = e.target.closest('[data-language-row]');
			if (!row || row.classList.contains('is-source-language') || !row.draggable) {
				e.preventDefault();
				return;
			}
			dragged = row;
			row.classList.add('is-dragging');
			if (e.dataTransfer) {
				e.dataTransfer.effectAllowed = 'move';
				e.dataTransfer.setData('text/plain', 'language');
			}
		});
		rows.addEventListener('dragover', function (e) {
			var over = e.target.closest('[data-language-row]');
			if (!dragged || !over || over === dragged || over.classList.contains('is-source-language'))
				return;
			e.preventDefault();
			var rect = over.getBoundingClientRect();
			rows.insertBefore(dragged, e.clientY < rect.top + rect.height / 2 ? over : over.nextSibling);
		});
		rows.addEventListener('drop', function (e) {
			e.preventDefault();
			reindexLanguages();
		});
		rows.addEventListener('dragend', function () {
			if (dragged) {
				dragged.classList.remove('is-dragging');
				dragged.draggable = false;
			}
			dragged = null;
			reindexLanguages();
		});
		rows.addEventListener('keydown', function (e) {
			var handle = e.target.closest('[data-language-drag]');
			if (!handle || !['ArrowUp', 'ArrowDown'].includes(e.key)) return;
			var row = handle.closest('[data-language-row]'),
				other = e.key === 'ArrowUp' ? row.previousElementSibling : row.nextElementSibling;
			if (!other || other.classList.contains('is-source-language')) return;
			e.preventDefault();
			rows.insertBefore(row, e.key === 'ArrowUp' ? other : other.nextSibling);
			reindexLanguages();
			handle.focus();
		});
	}
	var add = document.querySelector('[data-add-language]');
	if (add && picker && rows)
		add.addEventListener('click', function () {
			var code = picker.value,
				c = config.catalog[code],
				option = document.querySelector('[data-language-option][data-code="' + code + '"]'),
				template = document.querySelector('[data-language-row-template]');
			if (rows.dataset.languageLimit === '2' && rows.children.length >= 2) {
				var notice = document.querySelector('[data-language-limit-message]');
				if (notice) notice.hidden = false;
				var upgrade = document.querySelector('.pandatranslate-upgrade a');
				if (upgrade) upgrade.focus();
				return;
			}
			if (
				!code ||
				!c ||
				!template ||
				rows.querySelector('input[name$="[code]"][value="' + code + '"]')
			)
				return;
			var row = template.content.querySelector('[data-language-row]').cloneNode(true),
				flag = option ? option.dataset.flag : '🌐';
			row.querySelector('input[name$="[code]"]').value = code;
			row.querySelector('input[name$="[slug]"]').value = code;
			row.querySelector('[data-language-locale]').value = c.locale;
			row.querySelector('[data-language-native]').textContent = c.name;
			row.querySelector('[data-language-native]').lang = code;
			row.querySelector('[data-language-local]').textContent = option
				? option.dataset.localName
				: c.name;
			row.querySelector('[data-flag-preview]').textContent = flag;
			row.querySelector('[data-flag-image]').dataset.defaultFlag = flag;
			row.querySelector('select[name$="[formality]"]').value = c.formality || 'standard';
			rows.appendChild(row);
			reindexLanguages();
			if (window.ContactPandaAdminUI) window.ContactPandaAdminUI.enhance(row);
			picker.value = '';
			selection.textContent = config.selectLanguage;
			selection.classList.remove('has-language');
			add.disabled = true;
			if (languageSearch) languageSearch.value = '';
			availableOptions();
		});
	var migration = document.querySelector('[data-migration]');
	if (migration) {
		var migrationConfirm = migration.querySelector('[data-migration-confirm]');
		var migrationMissing = migration.querySelector('[data-migration-missing]');
		var migrationStart = migration.querySelector('[data-migration-start]');
		var migrationProgress = migration.querySelector('[data-migration-progress]');
		var migrationStatus = migration.querySelector('[data-migration-status]');
		var migrationCompletedAt = document.querySelector('[data-migration-completed-at]');
		var counts = ['processed', 'imported', 'skipped', 'errors'];
		var migrationState;
		migrationConfirm.addEventListener('change', function () {
			migrationStart.disabled = !migrationConfirm.checked;
		});

		function updateMigration(done) {
			var total = migrationState.total;
			var percent = done
				? 100
				: total
					? Math.min(99, Math.round((migrationState.processed / total) * 100))
					: 0;
			updateMigrationCounters(migration, 'migration', migrationState, percent);
		}
		function updateMigrationCounters(container, prefix, stats, percent) {
			container.querySelector('[data-' + prefix + '-percent]').textContent = percent + '%';
			container.querySelector('[data-' + prefix + '-bar]').style.width = percent + '%';
			counts.concat('total').forEach(function (key) {
				container.querySelector('[data-' + prefix + '-' + key + ']').textContent = (
					Number(stats[key]) || 0
				).toLocaleString();
			});
		}
		function migrationBatch() {
			var body = new URLSearchParams({
				action: 'pandatranslate_migration_batch',
				nonce: migration.dataset.nonce,
				table_index: migrationState.table_index,
				last_id: migrationState.last_id,
				reset: migrationState.first ? '1' : '',
				missing_only: migrationMissing.checked ? '1' : '',
			});
			fetch(window.ajaxurl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				credentials: 'same-origin',
				body: body.toString(),
			})
				.then(function (response) {
					return response.json().then(function (result) {
						if (!response.ok || !result.success)
							throw new Error(result.data && result.data.message ? result.data.message : '');
						return result.data;
					});
				})
				.then(function (data) {
					migrationState.first = false;
					migrationState.table_index = data.table_index || 0;
					migrationState.last_id = data.last_id || 0;
					migrationState.total = Number(data.total) || 0;
					counts.forEach(function (key) {
						migrationState[key] += Number(data[key]) || 0;
					});
					Object.entries(data.languages || {}).forEach(function (entry) {
						var row = document.querySelector('[data-migration-language="' + entry[0] + '"]');
						var stats = entry[1];
						if (row)
							updateMigrationCounters(
								row,
								'language',
								stats,
								stats.total
									? Math.min(100, Math.round((stats.processed / stats.total) * 100))
									: 100,
							);
					});
					updateMigration(Boolean(data.done));
					if (data.done) {
						var complete = migrationState.errors === 0;
						if (migrationCompletedAt && data.completed_label) {
							migrationCompletedAt.querySelector('small').textContent = data.completed_label;
							migrationCompletedAt.hidden = false;
						}
						migrationStatus.textContent = complete
							? config.migration.complete
							: config.migration.failed;
						migrationProgress.classList.add(complete ? 'is-complete' : 'is-error');
						migrationStart.disabled =
							migrationConfirm.disabled =
							migrationMissing.disabled =
								complete;
					} else window.setTimeout(migrationBatch, 80);
				})
				.catch(function (error) {
					migrationStatus.textContent = (config.migration.failed + ' ' + error.message).trim();
					migrationProgress.classList.add('is-error');
					migrationStart.disabled = migrationConfirm.disabled = migrationMissing.disabled = false;
				});
		}
		migrationStart.addEventListener('click', function () {
			migrationState = {
				table_index: 0,
				last_id: 0,
				processed: 0,
				imported: 0,
				skipped: 0,
				errors: 0,
				total: 0,
				first: true,
			};
			migrationStart.disabled = migrationConfirm.disabled = migrationMissing.disabled = true;
			migrationProgress.classList.remove('is-error', 'is-complete');
			migrationProgress.hidden = false;
			migrationStatus.textContent = config.migration.running;
			updateMigration(false);
			document.querySelectorAll('[data-migration-language]').forEach(function (row) {
				updateMigrationCounters(row, 'language', migrationState, 0);
			});
			migrationBatch();
		});
	}
	document.querySelectorAll('.pandatranslate-color').forEach(function (input) {
		window.ContactPandaAdminUI.enhanceColorPicker(input);
	});
	document.addEventListener('click', function (event) {
		var button = event.target.closest('[data-flag-image]');
		if (!button || !window.wp || !wp.media) return;
		event.preventDefault();
		var row = button.closest('[data-language-row]'),
			field = row.querySelector('[data-flag-id]'),
			preview = row.querySelector('[data-flag-preview]'),
			frame = wp.media({
				title: config.flagMediaTitle,
				button: { text: config.flagMediaButton },
				multiple: false,
				library: { type: 'image' },
			});
		frame.on('select', function () {
			var image = frame.state().get('selection').first().toJSON();
			field.value = image.id;
			preview.innerHTML =
				'<img src="' +
				(image.sizes && image.sizes.thumbnail ? image.sizes.thumbnail.url : image.url) +
				'" alt="">';
		});
		frame.open();
	});
	document.addEventListener('click', function (event) {
		var reset = event.target.closest('[data-flag-reset]');
		if (!reset) return;
		event.preventDefault();
		var row = reset.closest('[data-language-row]'),
			button = row.querySelector('[data-flag-image]');
		row.querySelector('[data-flag-id]').value = '0';
		row.querySelector('[data-flag-preview]').textContent = button.dataset.defaultFlag || '🌐';
	});
	document.addEventListener('click', function (e) {
		var remove = e.target.closest('[data-remove-language],[data-remove-glossary]');
		if (remove) {
			remove.closest('[data-language-row],.pandatranslate-glossary-row').remove();
			reindexLanguages();
		}
	});
	var addGlossary = document.querySelector('[data-add-glossary]');
	if (addGlossary)
		addGlossary.addEventListener('click', function () {
			var host = document.querySelector('[data-glossary-rows]'),
				first = host.querySelector('.pandatranslate-glossary-row');
			if (!first) return;
			var clone = first.cloneNode(true),
				i = host.children.length;
			clone.querySelectorAll('input').forEach(function (input) {
				input.name = input.name.replace(/\[glossary\]\[\d+\]/, '[glossary][' + i + ']');
				if (input.type === 'text' || input.type === 'hidden') input.value = '';
			});
			host.appendChild(clone);
		});
})();
