'use strict';
const {test}=require('node:test');
const assert=require('node:assert/strict');
const {createAiSummaryController}=require('../assets/js/learner-ai-summary.js');
const view={open(){},close(){},render(){}};
test('post-assessment generation allows the backend validation retry budget and retries transport with the same key',async()=>{
  const requests=[];
  const controller=createAiSummaryController({view,createIdempotencyKey:()=> 'synthetic-summary-key',api:{
    get:async()=>({state:'not_generated'}),
    send:async(method,url,body,options)=>{requests.push(options);if(requests.length===1)throw Object.assign(new Error('timeout'),{code:'REQUEST_TIMEOUT'});return {state:'ready_model',executive_summary:'Kế hoạch học tập.'};}
  }});
  assert.equal((await controller.run()).state,'ready_model');
  assert.equal(requests.length,2);
  assert.equal(requests[0].idempotencyKey,requests[1].idempotencyKey);
  assert.ok(requests[0].timeoutMs>=90000);
});
test('post-assessment refresh keeps a previously available roadmap on transport failure',async()=>{
  const controller=createAiSummaryController({view,api:{get:async()=>({state:'stale_model',executive_summary:'Kết quả đã lưu.'}),send:async()=>{throw Object.assign(new Error('offline'),{code:'NETWORK_ERROR'});}}});
  const result=await controller.run();assert.equal(result.state,'stale_model');assert.equal(result.executive_summary,'Kết quả đã lưu.');
});
