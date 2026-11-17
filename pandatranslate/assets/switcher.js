(function(){
	'use strict';
	document.addEventListener('click',function(event){
		var switcher=event.target.closest('.pandatranslate-switcher.is-dropdown');
		document.querySelectorAll('.pandatranslate-switcher.is-dropdown.is-open').forEach(function(open){if(open!==switcher){open.classList.remove('is-open');var link=open.querySelector('[data-pandatranslate-toggle]');if(link)link.setAttribute('aria-expanded','false');}});
		if(!switcher)return;
		var current=event.target.closest('[data-pandatranslate-toggle]');if(!current)return;
		event.preventDefault();event.stopPropagation();var opening=!switcher.classList.contains('is-open');switcher.classList.toggle('is-open',opening);current.setAttribute('aria-expanded',opening?'true':'false');
	});
	document.addEventListener('keydown',function(event){if(event.key!=='Escape')return;document.querySelectorAll('.pandatranslate-switcher.is-dropdown.is-open').forEach(function(open){open.classList.remove('is-open');var link=open.querySelector('[data-pandatranslate-toggle]');if(link){link.setAttribute('aria-expanded','false');link.focus();}});});
}());
