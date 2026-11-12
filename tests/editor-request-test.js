'use strict';
const assert = require('assert').strict;
const vm = require('vm');
const fs = require('fs');
function response(status, body) { return {ok: status >= 200 && status < 300, status, text: async () => typeof body === 'string' ? body : JSON.stringify(body)}; }
function fixture(replies) {
 const calls = [], config = {nonce:'oldnonce00',nonceUrl:'https://site.test/wp-admin/admin-ajax.php?action=rest-nonce',i18n:{requestNetworkError:'Reload and check saved results.',requestSessionError:'Sign in again.',requestOriginError:'Different origin.',requestResponseError:'Unexpected response.'}};
 const window = {location:{href:'https://site.test/fr/?pandatranslate=edit',origin:'https://site.test'}};
 vm.runInNewContext(fs.readFileSync(__dirname+'/../assets/editor-request.js','utf8'),{window,URL,fetch:async (url,options)=>{calls.push({url,options}); const reply=replies.shift();if(reply instanceof Error)throw reply;return reply;}});
 return {calls,config,request:window.PandaTranslateRequest.create(config)};
}
(async () => {
 let f=fixture([response(403,{code:'rest_cookie_invalid_nonce',message:'Expired'}),response(200,'newnonce00'),response(200,{status:'published'})]);
 assert.equal((await f.request('/wp-json/pandatranslate/v1/unit/auto',{key:'unit'})).status,'published');
 assert.equal(f.calls.length,3);assert.equal(f.calls[1].options.method,undefined);assert.equal(f.calls[2].options.headers['X-WP-Nonce'],'newnonce00');assert.equal(f.calls[0].options.body,f.calls[2].options.body);
 for (const c of f.calls) {assert.equal(c.options.credentials,'same-origin');assert.equal(c.options.redirect,'error');}
 f=fixture([new TypeError('Load failed')]); await assert.rejects(f.request('/wp-json/unit/auto',{key:'unit'}),e=>e.code==='request_network');assert.equal(f.calls.length,1,'Never replay an ambiguous POST');
 f=fixture([]);await assert.rejects(f.request('https://elsewhere.test/wp-json/unit',{key:'unit'}),e=>e.code==='request_origin');assert.equal(f.calls.length,0,'No nonce or source sent to another host');
 f=fixture([response(502,'<html>proxy failure, internal detail</html>')]);await assert.rejects(f.request('/wp-json/unit',{}),e=>e.code==='request_response'&&!e.message.includes('internal'));assert.equal(f.calls.length,1);
 f=fixture([response(429,{code:'google_quota',message:'Quota exceeded'})]);await assert.rejects(f.request('/wp-json/unit',{}),e=>e.code==='google_quota'&&e.status===429&&e.message==='Quota exceeded');assert.equal(f.calls.length,1);
 f=fixture([response(403,{code:'rest_cookie_invalid_nonce'}),response(200,'0')]);await assert.rejects(f.request('/wp-json/unit',{}),e=>e.code==='request_session');assert.equal(f.calls.length,2);
 f=fixture([response(403,{code:'rest_cookie_invalid_nonce'}),response(200,'newnonce00'),response(403,{code:'rest_cookie_invalid_nonce'})]);await assert.rejects(f.request('/wp-json/unit',{}),e=>e.code==='rest_cookie_invalid_nonce');assert.equal(f.calls.length,3,'Nonce retry is bounded');
 f=fixture([{ok:true,status:200,text:async()=>{throw new TypeError('stream lost');}}]);await assert.rejects(f.request('/wp-json/unit',{}),e=>e.code==='request_network');assert.equal(f.calls.length,1);
 console.log('Editor REST: nonce renewal, bounded retries, network ambiguity, origin guard and safe concrete errors: ok');
})().catch(e=>{console.error(e);process.exit(1);});
