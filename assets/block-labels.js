(function () {
    'use strict';
    var catalog = window.PandaTranslateBlockLabels;
    if (!catalog || !window.wp || !window.wp.hooks) return;
    var hooks=window.wp.hooks, own=Object.prototype.hasOwnProperty, rules=null;
    try { if (typeof Intl !== 'undefined' && Intl.PluralRules) rules=new Intl.PluralRules(catalog.locale); } catch(e) {}
    function singular(translation,text,domain,context) {
        domain=domain||'default';
        var labels=context ? ((catalog.context||{})[domain]||{})[context] : (catalog.plain||{})[domain];
        return labels && own.call(labels,text) ? labels[text] : translation;
    }
    function plural(translation,single,multiple,number,domain,context) {
        if (!rules || typeof number !== 'number' || !isFinite(number)) return translation;
        var groups=(catalog.plural||{})[domain||'default']||[], category=rules.select(number);
        for(var i=0;i<groups.length;i++) {
            var group=groups[i];
            if(group.singular===single && group.plural===multiple && group.context===(context||'') && own.call(group.forms,category)) return group.forms[category];
        }
        return translation;
    }
    hooks.addFilter('i18n.gettext','pandatranslate/published-block-labels',function(value,text,domain){return singular(value,text,domain,'');});
    hooks.addFilter('i18n.gettext_with_context','pandatranslate/published-context-labels',function(value,text,context,domain){return singular(value,text,domain,context);});
    hooks.addFilter('i18n.ngettext','pandatranslate/published-plural-labels',function(value,single,multiple,number,domain){return plural(value,single,multiple,number,domain,'');});
    hooks.addFilter('i18n.ngettext_with_context','pandatranslate/published-context-plurals',function(value,single,multiple,number,context,domain){return plural(value,single,multiple,number,domain,context);});
}());
