const {chromium}=require('playwright');
const assert=require('node:assert/strict');
const fs=require('node:fs');
(async()=>{
 const browser=await chromium.launch({channel:'msedge',headless:true});
 try{
  const page=await browser.newPage();let role='student',lastPost,fail=false;
  let report=null;
  const item=()=>({kind:'project',contextId:'project-1',title:'Project <script>alert(1)</script>',organization:'School',mentorName:'Teacher',studentName:'Student',report});
  await page.route('http://portfolio.test/**',async route=>{
   if(!route.request().url().endsWith('/api'))return route.fulfill({contentType:'text/html',body:`<!doctype html><meta charset="utf-8"><div data-portfolio data-role="${role}" data-endpoint="http://portfolio.test/api"></div>`});
   if(fail)return route.fulfill({status:503,contentType:'application/json',body:JSON.stringify({error:{message:'Không tải được dữ liệu mới'}})});
   if(route.request().method()==='POST'){
    lastPost=route.request().postDataJSON();assert.equal(route.request().headers()['x-csrf-token'],'token');
    report={...report,...lastPost,id:'report-1',version:(report?.version||0)+1,revision:1,status:lastPost.decision||(lastPost.submit?'submitted':'draft'),history:[]};
   }
   const data=role==='student'?{projects:[item()],internships:[],csrfToken:'token'}:{items:[item()],skills:[{id:'skill-1',name:'PHP'}],csrfToken:'token'};
   await route.fulfill({contentType:'application/json',body:JSON.stringify({data})});
  });
  const open=async()=>{await page.goto('http://portfolio.test/page');await page.addScriptTag({content:fs.readFileSync('assets/js/learner-portfolio.js','utf8')});await page.getByRole('heading',{level:3}).waitFor();};
  await open();
  assert.equal(await page.locator('article script').count(),0);
  await page.getByLabel('Nội dung / đóng góp cá nhân').fill('My verified project evidence');
  await page.getByRole('button',{name:'Gửi giảng viên',exact:true}).click();
  await page.getByText('Chờ giảng viên duyệt · Phiên bản 1',{exact:true}).waitFor();
  assert.equal(lastPost.submit,true);assert.equal(lastPost.expectedVersion,0);
  assert.equal(await page.getByRole('button',{name:'Xác nhận',exact:true}).count(),0);
  console.log('[PASS] Student submits scoped payload with CSRF; pending is immutable and text is escaped');
  role='teacher';await open();
  await page.getByLabel('Nhận xét (bắt buộc khi yêu cầu sửa hoặc thu hồi)').fill('Meets criteria');
  await page.getByLabel('Kỹ năng thực sự được xác nhận (tối đa 10, có thể không chọn)').selectOption('skill-1');
  await page.getByRole('button',{name:'Xác nhận',exact:true}).click();
  await page.getByRole('button',{name:'Thu hồi xác nhận',exact:true}).waitFor();
  assert.deepEqual(lastPost.skillIds,['skill-1']);assert.equal(lastPost.decision,'verified');assert.equal(lastPost.reportId,'report-1');
  console.log('[PASS] Assigned-teacher UI sends review decision and explicit selected skills');
  fail=true;await page.getByRole('button',{name:'Tải lại',exact:true}).click();
  await page.getByText('Không tải được dữ liệu mới',{exact:true}).waitFor();
  assert.equal(await page.locator('article').count(),0);
  console.log('[PASS] Failed refresh removes stale review controls');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
