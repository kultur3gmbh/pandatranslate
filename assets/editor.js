(function () {
	'use strict';
	var config = window.PandaTranslateEditor;
	if (!config) return;

	var selected = null, selectedRevision = '', selectionGeneration = 0;
	document.addEventListener('pandatranslate:media-selected',function(){if(selected)selected.classList.remove('pandatranslate-selected');selected=null;selectedRevision='';selectionGeneration++;});
	var shell = document.createElement('aside');
	shell.className = 'pandatranslate-panel is-open';
	shell.innerHTML = '<header><h2></h2><button type="button" class="pandatranslate-close contactpanda-list-action" aria-label="Close"><span class="dashicons dashicons-no-alt"></span></button></header>' +
		'<div class="pandatranslate-empty"></div><button type="button" class="contactpanda-large-action pandatranslate-button pandatranslate-page-button" data-automatic-page><span class="dashicons dashicons-translation"></span><span></span></button><form hidden><label><span data-label="source"></span><textarea data-source readonly></textarea></label><label><span data-label="translation"></span><textarea data-translation></textarea></label><label><span>Status</span><select data-status><option value="untranslated">Not translated</option><option value="automatic">Automatically translated</option><option value="reviewed">Reviewed</option><option value="published">Published</option></select></label><div class="pandatranslate-actions"><button type="button" class="contactpanda-large-action pandatranslate-button" data-automatic><span class="dashicons dashicons-translation"></span><span></span></button><button type="submit" class="contactpanda-large-action pandatranslate-button" data-contactpanda-action="save" data-contactpanda-action-mode="labelled"><span class="dashicons dashicons-saved"></span><span></span></button><button type="button" class="contactpanda-large-action is-primary pandatranslate-button" data-publish data-contactpanda-action="publish" data-contactpanda-action-mode="labelled"><span class="dashicons dashicons-megaphone"></span><span></span></button></div><p data-message role="status"></p></form>';
	document.body.appendChild(shell);
	shell.querySelector('h2').textContent = config.i18n.title;
	shell.querySelector('.pandatranslate-close').setAttribute('aria-label', config.i18n.close || 'Close');
	shell.querySelectorAll('[data-status] option').forEach(function(option){option.textContent=(config.i18n.statusLabels||{})[option.value]||option.textContent;});
	shell.querySelector('.pandatranslate-empty').textContent = config.i18n.select;
	shell.querySelector('[data-label="source"]').textContent = config.i18n.source;
	shell.querySelector('[data-label="translation"]').textContent = config.i18n.translation;
	shell.querySelector('[type="submit"] span:last-child').textContent = config.i18n.save;
	shell.querySelector('[data-publish] span:last-child').textContent = config.i18n.publish;
	shell.querySelector('[data-automatic] span:last-child').textContent = config.i18n.automatic;
	shell.querySelector('[data-automatic-page] span:last-child').textContent = config.i18n.automaticPage || 'Translate entire page';
	var pageProgress=document.createElement('section');pageProgress.className='pandatranslate-page-progress';pageProgress.hidden=true;pageProgress.innerHTML='<strong data-progress-title></strong><span data-progress-count></span><span class="pandatranslate-progress-track"><i data-progress-bar></i></span><small data-progress-detail></small>';shell.querySelector('[data-automatic-page]').insertAdjacentElement('afterend',pageProgress);

	var form = shell.querySelector('form');
	var apiLabel=document.createElement('label'),apiTitle=document.createElement('span'),apiSelect=document.createElement('select'),apiChoices=config.apis||{};
	apiLabel.className='pandatranslate-editor-api';apiTitle.textContent=config.i18n.apiLabel;apiSelect.setAttribute('aria-label',config.i18n.apiLabel);
	Object.keys(apiChoices).forEach(function(id){var option=document.createElement('option');option.value=id;option.textContent=apiChoices[id];option.selected=id===config.defaultApi;apiSelect.appendChild(option);});
	if(!apiSelect.options.length){var unavailable=document.createElement('option');unavailable.value='';unavailable.textContent=config.i18n.noApi;apiSelect.appendChild(unavailable);}
	apiLabel.append(apiTitle,apiSelect);shell.querySelector('[data-automatic-page]').before(apiLabel);
	function updateApiLabels(){var name=apiChoices[apiSelect.value]||config.i18n.noApi;shell.querySelector('[data-automatic-page] span:last-child').textContent=config.i18n.automaticPage.replace('%s',name);shell.querySelector('[data-automatic] span:last-child').textContent=config.i18n.automatic.replace('%s',name);shell.querySelector('[data-automatic-page]').disabled=!apiSelect.value;shell.querySelector('[data-automatic]').disabled=!apiSelect.value;apiSelect.disabled=Object.keys(apiChoices).length<2;}
	apiSelect.addEventListener('change',updateApiLabels);updateApiLabels();
	var source = shell.querySelector('[data-source]');
	var translation = shell.querySelector('[data-translation]');
	var status = shell.querySelector('[data-status]');
	var message = shell.querySelector('[data-message]');
	if (config.canPublish === false) { shell.querySelector('[data-publish]').hidden = true; status.querySelector('option[value=published]').disabled = true; }

	function protect(html) {
		var index = 0, tokens = {};
		var text = html.replace(/<\/?(?:a|abbr|b|br|code|em|i|mark|small|span|strong|sub|sup|u)\b[^>]*>|(?<![0-9])%(?:\d+\$)?[-+0#]*(?:\d+)?(?:\.\d+)?[bcdeEfFgGosuxX%]|\{[a-z_][a-z0-9_]*\}/gi, function (tag) {
			var type = (tag[0] === '%' || tag[0] === '{' || /^<br\b/i.test(tag)) ? 'VOID' : (/^<\//.test(tag) ? 'CLOSE' : 'OPEN');
			var token = '[[PT:' + index++ + ':' + type + ']]'; tokens[token] = tag; return token;
		});
		return { text: text, tokens: tokens };
	}

	function restore(text, tokens) {
		Object.keys(tokens).forEach(function (token) { text = text.split(token).join(tokens[token]); });
		return text;
	}

	function openUnit(node) {
		if (selected) selected.classList.remove('pandatranslate-selected');
		selected = node; selectedRevision = ''; var selectionVersion = ++selectionGeneration; selected.classList.add('pandatranslate-selected'); shell.classList.add('is-open');
		var original = node.dataset.pandatranslateOriginal || node.innerHTML;
		node.dataset.pandatranslateOriginal = original;
		var prepared = protect(original);
		source.value = prepared.text; translation.value = prepared.text; status.value = 'untranslated'; message.textContent = '';
		form.hidden = false; shell.querySelector('.pandatranslate-empty').hidden = true;
		fetch(config.restUrl + '?key=' + encodeURIComponent(node.dataset.pandatranslateUnit) + '&language=' + encodeURIComponent(config.language), {headers: {'X-WP-Nonce': config.nonce}})
			.then(function (response) { return response.json(); }).then(function (row) {
				if (selected !== node || selectionVersion !== selectionGeneration) return;
				selectedRevision = row.revision || '';
				if (row.source_html) {
					node.dataset.pandatranslateOriginal = row.source_html;
					prepared = protect(row.source_html); source.value = prepared.text;
				}
				if (row.translated_html) translation.value = protect(row.translated_html).text;
				status.value = row.status === 'published' && config.canPublish === false ? 'reviewed' : (row.status || 'untranslated');
			}).catch(function(error){if(selected===node && selectionVersion===selectionGeneration)message.textContent=error.message;});
		translation.oninput = function () { if (selected !== node || selectionVersion !== selectionGeneration) return; selected.innerHTML = restore(translation.value, prepared.tokens); };
	}

	document.addEventListener('click', function (event) {
		if (event.target.closest('img[data-pandatranslate-media],.media-modal,.media-modal-backdrop')) return;
		var unit = event.target.closest('[data-pandatranslate-unit]');
		if (!unit) {
			var candidate = event.target.closest('p,h1,h2,h3,h4,h5,h6,li,td,th,button,a[role=button]');
			if (!config.captureAllowed || !config.postId || !candidate || candidate.closest('#wpadminbar,.pandatranslate-panel,.pandatranslate-switcher,form,[translate=no],[data-no-translation],.notranslate') || candidate.querySelector('p,li,td,th,div,img,input,textarea,select') || !candidate.textContent.trim()) return;
			event.preventDefault(); event.stopPropagation();
			fetch(config.dynamicUrl, {method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':config.nonce},body:JSON.stringify({post_id:config.postId,language:config.language,source_html:candidate.innerHTML})}).then(function(r){return r.json().then(function(data){if(!r.ok)throw data;return data;});}).then(function(data){candidate.dataset.pandatranslateUnit=data.key;candidate.dataset.pandatranslateSourceHash=data.source_hash;candidate.dataset.pandatranslateOriginal=data.source_html;openUnit(candidate);}).catch(function(e){message.textContent=e.message;}); return;
		}
		if (shell.contains(unit)) return;
		event.preventDefault(); event.stopPropagation(); openUnit(unit);
	}, true);

	function save(nextStatus) {
		if (!selected || !selectedRevision) return;
		var savedNode = selected, savedVersion = selectionGeneration;
		message.textContent = 'Saving…';
		var body = {revision: selectedRevision, key: selected.dataset.pandatranslateUnit, source_hash: selected.dataset.pandatranslateSourceHash, source_html: selected.dataset.pandatranslateOriginal, translated_text: translation.value, language: config.language, status: nextStatus || status.value};
		fetch(config.restUrl, {method: 'POST', headers: {'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce}, body: JSON.stringify(body)})
			.then(function (response) { return response.json().then(function (data) { if (!response.ok) throw data; return data; }); })
			.then(function (data) { if (selected !== savedNode || savedVersion !== selectionGeneration) return; selectedRevision = data.revision || ''; status.value = data.status; selected.innerHTML = data.translated_html; message.textContent = 'Saved.'; })
			.catch(function (error) { message.textContent = error.message || 'Could not save.'; });
	}

	form.addEventListener('submit', function (event) { event.preventDefault(); save(); });
	shell.querySelector('[data-publish]').addEventListener('click', function () { save('published'); });
	function confirmPaid(){try{if(sessionStorage.getItem('pandatranslatePaidConfirmed')==='1')return true;}catch(e){}if(!window.confirm(config.i18n.automaticConfirm))return false;try{sessionStorage.setItem('pandatranslatePaidConfirmed','1');}catch(e){}return true;}
	shell.querySelector('[data-automatic]').addEventListener('click', function () {
		if (!selected || !apiSelect.value || !confirmPaid()) return;
		var autoNode = selected, autoVersion = selectionGeneration;
		message.textContent = 'Translating…';
		fetch(config.autoUrl, {method: 'POST', headers: {'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce}, body: JSON.stringify({key: selected.dataset.pandatranslateUnit, language: config.language, api: apiSelect.value, confirm: true})})
			.then(function (response) { return response.json().then(function (data) { if (!response.ok) throw data; return data; }); })
			.then(function (data) { if (selected !== autoNode || autoVersion !== selectionGeneration) return; selectedRevision = data.revision || ''; selected.innerHTML = data.translated_html; translation.value = protect(data.translated_html).text; status.value = data.status; message.textContent = 'Automatic draft saved.'; })
			.catch(function (error) { message.textContent = error.message || 'Automatic translation failed.'; });
	});
	shell.querySelector('[data-automatic-page]').addEventListener('click',function(){if(!apiSelect.value||!confirmPaid())return;var api=apiSelect.value,units=Array.prototype.slice.call(document.querySelectorAll('[data-pandatranslate-unit]')).filter(function(node){return !node.closest('#wpadminbar,.pandatranslate-panel,.fusion-builder-ui,.elementor-editor-active');}),index=0,done=0,failed=0,button=this,total=units.length;button.disabled=true;apiSelect.disabled=true;pageProgress.hidden=false;pageProgress.querySelector('[data-progress-title]').textContent=(config.i18n.progressTitle||'Translating current page')+' · '+apiChoices[api];function render(){var processed=done+failed,percent=total?Math.round(processed/total*100):100;pageProgress.querySelector('[data-progress-count]').textContent=processed+' / '+total+' ('+percent+'%)';pageProgress.querySelector('[data-progress-bar]').style.width=percent+'%';pageProgress.querySelector('[data-progress-detail]').textContent=done+' '+(config.i18n.progressDone||'translated')+' · '+failed+' '+(config.i18n.progressFailed||'failed');}function next(){render();if(index>=total){button.disabled=false;apiSelect.disabled=Object.keys(apiChoices).length<2;pageProgress.classList.add(failed?'has-errors':'is-complete');return;}var node=units[index++];fetch(config.autoUrl,{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':config.nonce},body:JSON.stringify({key:node.dataset.pandatranslateUnit,language:config.language,api:api,confirm:true})}).then(function(response){return response.json().then(function(data){if(!response.ok)throw data;return data;});}).then(function(data){node.innerHTML=data.translated_html;if(selected===node){selectedRevision=data.revision||'';translation.value=protect(data.translated_html).text;status.value=data.status;}done++;}).catch(function(){failed++;}).finally(next);}next();});
	shell.querySelector('.pandatranslate-close').addEventListener('click', function () { shell.classList.remove('is-open'); });

	new MutationObserver(function () { document.dispatchEvent(new CustomEvent('pandatranslate:content-changed')); }).observe(document.body, {childList: true, subtree: true});
}());
