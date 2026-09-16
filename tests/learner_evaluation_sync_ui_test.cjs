const {chromium} = require('playwright');
const assert = require('node:assert/strict');
const path = require('node:path');
(async()=>{
 const browser=await chromium.launch(process.env.PLAYWRIGHT_CHANNEL ? {channel:process.env.PLAYWRIGHT_CHANNEL} : {channel:'chrome'});
 try {
  const page=await browser.newPage(); const errors=[];
  page.on('pageerror',error=>errors.push(error.message));
  const terms={graded:{status:'Đã công bố',evaluation:{criteria:[{name:'Chuyên môn',score:75.25,max:100}],
   skills:[{label:'Backend',score:85.25,maxScore:100,category:'skill_group'},{label:'Frontend',score:0,maxScore:100,category:'skill_group'},{label:'Python',score:4.25,maxScore:5,category:'technical'}]}},
   empty:{status:'Đã công bố',evaluation:{criteria:[],skills:[]}}};
  await page.setContent(`<body class="learner-app"><select id="learner-evaluation-term"><option value="empty">Empty</option><option value="graded">Graded</option></select>
   <div data-evaluation-content></div><div data-evaluation-summary></div><div data-evaluation-empty></div><div data-evaluation-status></div>
   <div data-evaluation-criteria></div><div data-evaluation-skills-list></div><div data-evaluation-skills></div>
   <script id="learner-evaluation-data" type="application/json">${JSON.stringify(terms)}</script></body>`);
  await page.addScriptTag({path:path.join(__dirname,'../assets/js/learner.js')});
  await page.evaluate(()=>document.dispatchEvent(new Event('DOMContentLoaded')));
  await page.selectOption('#learner-evaluation-term','graded');
  assert.equal(await page.locator('[data-evaluation-skill-row]').count(),3);
  assert.match(await page.locator('[data-evaluation-skills-list]').innerText(),/85.25/);
  assert.match(await page.locator('[data-evaluation-skills-list]').innerText(),/Nhóm kỹ năng/);
  assert.match(await page.locator('[data-evaluation-skills-list]').innerText(),/4.25\s*\/ 5/);
  assert.match(await page.locator('[data-evaluation-skills]').innerText(),/Frontend \(0\/100\)/);
  assert.match(await page.locator('[data-evaluation-criteria]').innerText(),/75.25/);
  await page.selectOption('#learner-evaluation-term','empty');
  assert.equal(await page.locator('[data-evaluation-skill-row]').count(),0);
  assert.match(await page.locator('[data-evaluation-skills]').innerText(),/Chưa có kỹ năng/);
  assert.equal(errors.length,0,errors.join('\n'));
  console.log('learner_evaluation_sync_ui_test: OK');
 } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
