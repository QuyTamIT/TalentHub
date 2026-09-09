// Requires bundled playwright and pdf-lib via NODE_PATH; produces synthetic QA artifacts only.
const { chromium } = require('playwright');
const { PDFDocument } = require('pdf-lib');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
(async () => {
    const browser=await chromium.launch({channel:'msedge',headless:true});
    const output=path.resolve('.codex_tmp/issue04-pdf'); fs.mkdirSync(output,{recursive:true});
    try {
        for (const mode of ['normal','sparse','long','portfolio']) {
            const page=await browser.newPage();
            const html=execFileSync(process.env.TALENTHUB_TEST_PHP || 'D:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe',['tests/learner_passport_cv_fixture.php',mode],{encoding:'utf8'});
            await page.setContent(html.replace(/<link[^>]+>/g,'').replace(/<script[\s\S]*?<\/script>/g,''));
            await page.addStyleTag({content:fs.readFileSync('assets/css/learner-passport-cv.css','utf8')});
            await page.emulateMedia({media:'print'});
            await page.evaluate(()=>document.fonts.ready);
            const bounds=await page.locator('[data-cv-content]').evaluate(el=>({height:el.scrollHeight,width:el.scrollWidth,client:el.clientWidth}));
            assert.ok(bounds.height<=268*96/25.4,`${mode} exceeds A4 content height: ${bounds.height}`);
            assert.ok(bounds.width<=bounds.client+1,`${mode} horizontal overflow`);
            const buffer=await page.pdf({path:path.join(output,`${mode}.pdf`),preferCSSPageSize:true,printBackground:true,displayHeaderFooter:false});
            const pdf=await PDFDocument.load(buffer);
            assert.equal(pdf.getPageCount(),1,`${mode} page count`);
            const size=pdf.getPage(0).getSize();
            assert.ok(Math.abs(size.width-595.28)<1 && Math.abs(size.height-841.89)<1,`${mode} A4 size`);
            console.log(`[PASS] ${mode}: one A4 page, content ${bounds.height}px, no overflow`);
            await page.close();
        }
        // Exercise the actual export controller, including a fresh document request.
        const page=await browser.newPage();
        let requests=0;
        const fixture=execFileSync(process.env.TALENTHUB_TEST_PHP || 'D:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe',['tests/learner_passport_cv_fixture.php','normal'],{encoding:'utf8'});
        await page.addInitScript(()=>{ window.print=()=>{ window.cvPrintCalls=(window.cvPrintCalls||0)+1; }; });
        await page.route('http://cv.test/**',async route=>{
            const url=new URL(route.request().url());
            if (url.pathname.endsWith('.css')) return route.fulfill({contentType:'text/css',body:fs.readFileSync('assets/css/learner-passport-cv.css','utf8')});
            if (url.pathname.endsWith('.js')) return route.fulfill({contentType:'text/javascript',body:fs.readFileSync('assets/js/learner-passport-cv.js','utf8')});
            requests++;
            let body=fixture;
            if (requests>1) body=body.replaceAll('Nguyễn Minh Anh','Hồ sơ vừa cập nhật');
            if (url.searchParams.has('overflow')) body=body.replace('</head>','<style>[data-cv-content]{min-height:1500px}</style></head>');
            return route.fulfill({contentType:'text/html; charset=utf-8',body});
        });
        await page.goto('http://cv.test/app/learner/talent-passport-cv.php');
        assert.equal(await page.evaluate(()=>window.cvPrintCalls||0),0,'preview must not print automatically');
        await page.locator('[data-cv-export]').click();
        await page.waitForFunction(()=>window.cvPrintCalls===1);
        assert.equal(requests,2,'export must reload data');
        assert.match(await page.locator('h1').innerText(),/Hồ sơ vừa cập nhật/);
        assert.equal(new URL(page.url()).search,'','export marker removed to avoid repeated printing');
        console.log('[PASS] Browser export reloads current content before opening print');
        await page.goto('http://cv.test/app/learner/talent-passport-cv.php?export=1&overflow=1');
        await page.waitForFunction(()=>document.body.classList.contains('cv-overflow'));
        assert.equal(await page.evaluate(()=>window.cvPrintCalls||0),0,'overflow must not auto-print');
        assert.equal(await page.locator('[data-cv-error]').isVisible(),true);
        console.log('[PASS] Browser overflow displays error without auto-printing');
        await page.close();
    } finally { await browser.close(); }
})().catch(e=>{ console.error(e);process.exit(1); });
