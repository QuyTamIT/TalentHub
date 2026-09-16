const {chromium} = require('./regrading-tools/node_modules/playwright-core');
const fs = require('fs');
const assert = require('node:assert/strict');
const session = JSON.parse(fs.readFileSync(__dirname + '/regrading-session.json'));
const origin = 'http://127.0.0.1:8125';
(async () => {
 const browser = await chromium.launch({executablePath: process.env.LOCALAPPDATA + '/ms-playwright/chromium-1228/chrome-win64/chrome.exe', headless:true});
 try {
 const teacher = await browser.newContext({viewport:{width:1440,height:1000}});
 await teacher.addCookies([{...session.teacher,url:origin}]);
 const page = await teacher.newPage();
 await page.goto(origin + '/app/teacher/grading.php?student_id=' + session.studentId + '&activity_id=' + session.activityId);
 await page.locator('#studentGradingForm').waitFor();
 assert.equal(await page.locator('.verified-skill-row').count(),12);
 assert.equal(await page.locator('[name="assessmentId"]').inputValue(),session.assessmentId);
 console.log('Teacher opens existing Nguyễn Văn C assessment; 12 groups unchanged');
 const skills = Object.fromEntries(session.skills.map(s=>[s.code,s.id]));
 // Exercise the legacy skill POST contract without changing the group UI in the app.
 async function submitSkills(scores) {
   await page.evaluate(({scores,skills}) => {
     document.querySelectorAll('[data-test-skill]').forEach(el=>el.remove());
     document.querySelectorAll('[name^="skillGroups["]').forEach(el=>el.disabled=true);
     const form=document.getElementById('studentGradingForm');
     Object.entries(scores).forEach(([code,score],index)=>{
       for (const [key,value] of Object.entries({skillId:skills[code],score:String(score)})) {
         const input=document.createElement('input'); input.type='hidden'; input.name=`skills[${index}][${key}]`; input.value=value; input.dataset.testSkill='1'; form.append(input);
       }
     });
   },{scores,skills});
   const response = page.waitForResponse(r=>r.url().endsWith('/grading.php') && r.request().method()==='POST');
   await page.locator('.btn-submit').click();
   const res = await response; const body = await res.json();
   assert.equal(res.status(),200,JSON.stringify(body)); assert.equal(body.success,true);
   assert.equal(body.assessmentId,session.assessmentId);
   await page.locator('#gradingSuccessToast:not([hidden])').waitFor();
   assert.match(await page.locator('#gradingSuccessMessage').innerText(),/Đã cập nhật đánh giá thành công cho Nguyễn Văn C/);
   return body;
 }
 let navigations=0;page.on('request',request=>{if(request.isNavigationRequest() && request.frame()===page.mainFrame())navigations++;});
 if (!process.argv.includes('--verify-only')) {
   const first=await submitSkills({mysql:90,rest_api:90,sql:85});
   const second=await submitSkills({mysql:95,rest_api:80});
   assert.equal(second.version,first.version+1);assert.equal(navigations,0);
 } else {
   await page.evaluate(()=>showGradingSuccess('Đã cập nhật đánh giá thành công cho Nguyễn Văn C.'));
 }
 await page.screenshot({path:__dirname+'/regrading-teacher.png',fullPage:true});
 console.log('PASS real POST: first test pass 90/90/85; regrade 95/80/remove SQL; same assessment; toast; no reload');
 const student=await browser.newContext({viewport:{width:1440,height:1000}});
 await student.addCookies([{...session.student,url:origin}]);
 const passport=await student.newPage();
 await passport.goto(origin+'/app/learner/talent-passport.php');
 await passport.screenshot({path:__dirname+'/regrading-student.png',fullPage:true});
 const html=await passport.content();fs.writeFileSync(__dirname+'/regrading-student.html',html);
 const body=await passport.locator('body').innerText();
 assert.match(body,/Nguyễn Văn C/);assert.match(body,/MySQL/);assert.match(body,/REST API/);
 assert.doesNotMatch(body,/(?:^|\n)SQL(?:\n|$)/);
 console.log('Passport URL',passport.url());
 console.log(body.substring(body.indexOf('MySQL')-30,body.indexOf('MySQL')+220));
 await passport.reload();assert.doesNotMatch(await passport.locator('body').innerText(),/(?:^|\n)SQL(?:\n|$)/);
 console.log('PASS Passport reload still excludes SQL');
 // A failed AJAX request must never turn into a navigation/native form submit.
 let posts=0;
 await page.route('**/grading.php',async route=>{
   if(route.request().method()==='POST'){posts++; await route.fulfill({status:200,contentType:'text/html',body:'invalid JSON'});}
   else await route.continue();
 });
 await page.locator('.btn-submit').click();
 await page.locator('#ajaxAlertNotification').filter({hasText:'Chưa xác nhận'}).waitFor();
 assert.equal(posts,1);assert.equal(navigations,0);
 assert.equal(await page.locator('.btn-submit').isEnabled(),true);
 await page.unroute('**/grading.php');
 await page.route('**/grading.php',async route=>{
   if(route.request().method()==='POST'){posts++; await route.abort('failed');} else await route.continue();
 });
 await page.locator('.btn-submit').click();
 await page.waitForFunction(()=>document.querySelector('#studentGradingForm').dataset.saving==='0');
 assert.equal(posts,2);assert.equal(navigations,0);
 console.log('PASS invalid JSON/network failure: exactly one POST each, no automatic resubmit/reload');
 await page.setViewportSize({width:390,height:844});
 await page.evaluate(()=>showGradingSuccess('Đã cập nhật đánh giá thành công cho Nguyễn Văn C.'));
 await page.screenshot({path:__dirname+'/regrading-mobile.png',fullPage:true});
 const box=await page.locator('#gradingSuccessToast').boundingBox();assert.ok(box.x>=0 && box.x+box.width<=390);
 console.log('PASS mobile toast fits viewport');
 } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
