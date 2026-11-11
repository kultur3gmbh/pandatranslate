(function () {
    'use strict';
    var dictionary = window.PandaTranslateDynamic && window.PandaTranslateDynamic.dictionary;
    if (!dictionary) return;
    var excluded = '#wpadminbar,.pandatranslate-panel,.pandatranslate-switcher,.pandatranslate-menu-link,[translate="no"],[data-no-translation],.notranslate,script,style,textarea,input,select,code,pre,[contenteditable="true"],.woocommerce-checkout,.woocommerce-cart,.woocommerce-MyAccount-content';
    var observer, queue = new Set(), scheduled = false;
    function translate(root) {
        if (!root || !root.isConnected || (root.nodeType === 1 && root.closest(excluded))) return;
        var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT), node = root.nodeType === 3 ? root : walker.nextNode();
        while (node) { var parent = node.parentElement, text = node.nodeValue, key = text.trim(); if (parent && !parent.closest(excluded) && Object.prototype.hasOwnProperty.call(dictionary, key) && dictionary[key] !== key) node.nodeValue = text.slice(0, text.indexOf(key)) + dictionary[key] + text.slice(text.indexOf(key) + key.length); node = walker.nextNode(); }
    }
    function flush() { scheduled = false; observer.disconnect(); queue.forEach(translate); queue.clear(); observer.observe(document.body, {childList: true, characterData: true, subtree: true}); }
    observer = new MutationObserver(function (changes) { changes.forEach(function (change) { if (change.type === 'characterData') queue.add(change.target); else change.addedNodes.forEach(function (node) { if (node.nodeType === 1 || node.nodeType === 3) queue.add(node); }); }); if (!scheduled) { scheduled = true; requestAnimationFrame(flush); } });
    queue.add(document.body); flush();
}());
