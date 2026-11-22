const assert = require('assert').strict;
const fs = require('fs');
const vm = require('vm');

const english = {checked: true, matches: () => true};
const italian = {checked: false, matches: () => true};
const feedback = {textContent: '', dataset: {message: 'Deactivate the current target first.'}};
const targets = [english, italian];
let onChange;
const rows = {
    dataset: {languageLimit: '2'},
    addEventListener(event, handler) { if (event === 'change') onChange = handler; },
    querySelectorAll() { return targets.filter(target => target.checked); }
};
const document = {
    querySelector(selector) {
        if (selector === '[data-language-rows]') return rows;
        if (selector === '[data-language-activation-feedback]') return feedback;
        return null;
    },
    querySelectorAll() { return []; },
    addEventListener() {}
};
vm.runInNewContext(fs.readFileSync(require.resolve('../assets/admin.js'), 'utf8'), {
    window: {PandaTranslateAdmin: {}}, document
});

italian.checked = true;
onChange({target: italian});
assert.equal(english.checked, true);
assert.equal(italian.checked, false);
assert.equal(feedback.textContent, feedback.dataset.message);

english.checked = false;
onChange({target: english});
italian.checked = true;
onChange({target: italian});
assert.equal(italian.checked, true);
assert.equal(feedback.textContent, '');

rows.dataset.languageLimit = '0';
english.checked = true;
onChange({target: english});
assert.equal(italian.checked, true);
assert.equal(english.checked, true);
console.log('Free preserves its current target and Multilingual can activate multiple targets.');
