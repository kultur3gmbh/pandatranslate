'use strict';
const fs=require('fs'),vm=require('vm'),assert=require('assert').strict;
const src=fs.readFileSync(__dirname+'/../assets/admin.js','utf8');
const fragment=src.slice(src.indexOf('    var jobPollTimer='),src.indexOf('    function refreshOverview('));
async function scenario(ambiguous=false){
 const calls=[],timers=[],output={textContent:''},job={dataset:{nonce:'fixture-nonce'},innerHTML:''};
 const buttons=[{dataset:{},disabled:false},{dataset:{},disabled:true}],api={value:'google-v2',disabled:false};
 const button={dataset:{nonce:'fixture-nonce'},closest:()=>({querySelector:()=>output})};
 let running=true;
 const context={config:{cancelFailed:'Failed'},URLSearchParams,Date,crypto:{getRandomValues:a=>a.fill(1)},apiSelector:api,selectedApi:()=>api.value,clearTimeout:()=>{},refreshOverview:()=>{},
 document:{querySelector:()=>job,querySelectorAll:()=>buttons},
 window:{confirm:()=>true,setTimeout:fn=>{timers.push(fn);return timers.length;}},
 fetch:async(url,request)=>{const body=Object.fromEntries(request.body);calls.push(body);if(body.action==='pandatranslate_start_language_job'&&ambiguous)throw Error('Connection lost after enqueue');return{ok:true,json:async()=>({success:true,data:{html:'Persisted progress: 42',running}})};}};
 vm.createContext(context);vm.runInContext(fragment,context);
 context.runTranslation(button,['en','fr'],'missing');
 for(let i=0;i<5;i++)await new Promise(setImmediate);
 assert.equal(calls.filter(x=>x.action==='pandatranslate_start_language_job').length,1);
 assert.equal(calls[0].api,'google-v2');assert.equal(calls[0].mode,'missing');assert.equal(calls[0].languages,'["en","fr"]');
 assert(calls.some(x=>x.action==='pandatranslate_site_progress'));
 assert(!calls.some(x=>x.action==='pandatranslate_language_batch'));
 assert.equal(job.innerHTML,'Persisted progress: 42');assert(buttons[0].disabled&&buttons[1].disabled);
 // Polling is read-only; a closed/reopened page never restarts the provider job.
 context.pollSiteJob();for(let i=0;i<4;i++)await new Promise(setImmediate);
 assert.equal(calls.filter(x=>x.action==='pandatranslate_start_language_job').length,1);
 running=false;context.pollSiteJob();for(let i=0;i<4;i++)await new Promise(setImmediate);
 assert(!buttons[0].disabled&&buttons[1].disabled);
}
(async()=>{await scenario();await scenario(true);console.log('Background UI: one enqueue, selected API/languages, read-only persisted progress, no browser batch loop, ambiguous response does not restart, original disabled states restored: ok');})().catch(error=>{console.error(error);process.exit(1);});
