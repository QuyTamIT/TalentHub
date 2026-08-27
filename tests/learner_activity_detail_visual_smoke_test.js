'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');

const optInEnabled = process.env.PHASE71_VISUAL_SMOKE === '1';

if (!optInEnabled) {
  test('Phase 7.1 learner activity detail browser smoke', {
    skip: 'SKIP: set PHASE71_VISUAL_SMOKE=1 to run the opt-in Playwright integration test.',
  }, () => {});
} else {
  test('Phase 7.1 learner activity detail browser smoke', { timeout: 45000 }, async () => {
    const fs = require('node:fs');
    const path = require('node:path');
    const root = path.resolve(__dirname, '..');
    const sessionPath = path.join(root, '.codex_tmp', 'phase7-visual', 'session.json');
    const fallbackPath = '/app/learner/assets/activities/illustrations/hero-detail.svg';
    let browser = null;
    let sessionFilePresent = false;

    try {
      sessionFilePresent = fs.existsSync(sessionPath);
      assert.ok(
        sessionFilePresent,
        'Phase 7.1 visual smoke requires a temporary session fixture at .codex_tmp/phase7-visual/session.json.',
      );
      const baseUrl = validateLocalBaseUrl(process.env.PHASE71_BASE_URL);

      let session;
      try {
        session = JSON.parse(fs.readFileSync(sessionPath, 'utf8'));
      } catch {
        assert.fail('Phase 7.1 visual smoke session fixture is unreadable or invalid JSON.');
      }
      assertSessionFixture(session);

      let chromium;
      try {
        ({ chromium } = require('playwright'));
      } catch {
        assert.fail('Phase 7.1 visual smoke requires Playwright, but the package is unavailable.');
      }

      try {
        browser = await chromium.launch({ headless: true });
      } catch {
        assert.fail('Phase 7.1 visual smoke could not launch Chromium.');
      }
      const context = await browser.newContext();
      await context.addCookies([{
        name: session.name,
        value: session.id,
        url: baseUrl,
        httpOnly: true,
        sameSite: 'Lax',
      }]);
      const page = await context.newPage();
      const failedRequests = [];
      page.on('requestfailed', request => failedRequests.push(request.url()));

      let detailResponse;
      try {
        detailResponse = await page.goto(
          `${baseUrl}/app/learner/activity-detail.php?id=${encodeURIComponent(session.activityId)}`,
          { waitUntil: 'networkidle', timeout: 30000 },
        );
      } catch {
        assert.fail(`Phase 7.1 visual smoke could not reach the local test server at ${baseUrl}.`);
      }
      const detail = await page.evaluate(() => {
        const cover = document.querySelector('.learner-activity-detail-hero__cover img');
        const boot = JSON.parse(document.getElementById('learner-activities-boot')?.textContent || 'null');
        return {
          coverLoaded: Boolean(cover?.complete && cover?.naturalWidth),
          coverUsesRealAsset: Boolean(cover?.getAttribute('src'))
            && !cover.getAttribute('src').endsWith('/illustrations/hero-detail.svg'),
          catalogCount: Array.isArray(boot?.catalog) ? boot.catalog.length : -1,
          registrationCount: Array.isArray(boot?.registrations) ? boot.registrations.length : -1,
        };
      });

      await page.setContent(`<img id="fallback" src="${baseUrl}${fallbackPath}" alt="Fallback activity illustration">`);
      await page.locator('#fallback').waitFor({ state: 'visible' });
      await page.waitForFunction(() => document.querySelector('#fallback')?.complete === true);
      const fallbackLoaded = await page.locator('#fallback').evaluate(
        image => image.naturalWidth > 0 && image.naturalHeight > 0,
      );

      let foreignResponse;
      try {
        foreignResponse = await page.goto(
          `${baseUrl}/app/learner/activity-detail.php?id=${encodeURIComponent(session.foreignActivityId)}`,
          { waitUntil: 'networkidle', timeout: 30000 },
        );
      } catch {
        assert.fail(`Phase 7.1 visual smoke could not reach the local test server at ${baseUrl}.`);
      }
      const foreign = {
        safeNotFound: await page.getByText('Không tìm thấy hoạt động', { exact: true }).count() === 1,
        hasBootPayload: await page.locator('#learner-activities-boot').count() > 0,
      };

      assert.equal(detailResponse?.status(), 200, 'Real-cover activity detail returns HTTP 200.');
      assert.equal(detail.coverLoaded, true, 'Real activity cover loads successfully.');
      assert.equal(detail.coverUsesRealAsset, true, 'Real-cover activity does not use the fallback illustration.');
      assert.equal(detail.catalogCount, 1, 'Database boot catalog contains only the current activity.');
      assert.equal(detail.registrationCount, 0, 'Unregistered activity boot contains no registrations.');
      assert.equal(fallbackLoaded, true, 'Fallback SVG loads successfully in Chromium.');
      assert.equal(foreignResponse?.status(), 200, 'Cross-school detail returns the safe page response.');
      assert.equal(foreign.safeNotFound, true, 'Cross-school detail renders safe not-found.');
      assert.equal(foreign.hasBootPayload, false, 'Cross-school detail emits no boot payload.');
      assert.deepEqual(failedRequests, [], 'Browser observes no failed request.');
    } finally {
      if (browser !== null) await browser.close().catch(() => {});
      if (sessionFilePresent && fs.existsSync(sessionPath)) {
        try {
          fs.unlinkSync(sessionPath);
        } catch {
          assert.fail('Phase 7.1 visual smoke could not remove the temporary session fixture.');
        }
      }
    }
  });
}

function validateLocalBaseUrl(rawValue) {
  assert.equal(
    typeof rawValue,
    'string',
    'PHASE71_BASE_URL is required when PHASE71_VISUAL_SMOKE=1.',
  );
  let parsed;
  try {
    parsed = new URL(rawValue);
  } catch {
    assert.fail('PHASE71_BASE_URL must be a valid absolute localhost URL.');
  }
  assert.ok(
    parsed.protocol === 'http:' || parsed.protocol === 'https:',
    'PHASE71_BASE_URL must use http or https.',
  );
  assert.ok(
    parsed.hostname === 'localhost' || parsed.hostname === '127.0.0.1',
    'PHASE71_BASE_URL may only target localhost or 127.0.0.1.',
  );
  assert.equal(parsed.username, '', 'PHASE71_BASE_URL must not include credentials.');
  assert.equal(parsed.password, '', 'PHASE71_BASE_URL must not include credentials.');
  assert.equal(parsed.pathname, '/', 'PHASE71_BASE_URL must not include an application path.');
  assert.equal(parsed.search, '', 'PHASE71_BASE_URL must not include a query string.');
  assert.equal(parsed.hash, '', 'PHASE71_BASE_URL must not include a fragment.');
  return parsed.origin;
}

function assertSessionFixture(session) {
  assert.ok(session && typeof session === 'object' && !Array.isArray(session), 'Visual smoke session fixture must be an object.');
  for (const field of ['name', 'id', 'activityId', 'foreignActivityId']) {
    assert.ok(typeof session[field] === 'string' && session[field].trim() !== '', `Visual smoke session fixture is missing ${field}.`);
  }
}
