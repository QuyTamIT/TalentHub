'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '..');
const read = (relativePath) => fs.readFileSync(path.join(root, relativePath), 'utf8');

test('learner sidebar owns role selection and logout actions', () => {
    const sidebar = read('app/learner/includes/sidebar.php');
    const header = read('app/learner/includes/header.php');

    assert.match(sidebar, /class="learner-sidebar__footer"/);
    assert.match(sidebar, /href="\/role-selection\.php"/);
    assert.match(sidebar, /href="\/logout\.php"/);
    assert.doesNotMatch(header, /learner-role-switch/);
});

test('primary learner pages include the shared page banner', () => {
    [
        'profile.php', 'discover.php', 'activities.php', 'checkin.php',
        'evaluation.php', 'ai-recommendations.php', 'badges.php',
        'statistics.php', 'my-activities.php',
    ].forEach((page) => {
        assert.match(
            read(path.join('app/learner', page)),
            /includes\/page-banner\.php/,
            `${page} must render the shared learner page banner`,
        );
    });
});

test('sidebar wordmark and banner component use shared styling hooks', () => {
    const css = read('assets/css/learner.css');
    assert.match(css, /\.learner-sidebar__footer\s*\{/);
    assert.match(css, /\.learner-page-banner\s*\{/);
});

test('learner sidebar uses the centered icon and wordmark lockup', () => {
    const sidebar = read('app/learner/includes/sidebar.php');
    const css = read('assets/css/learner.css');

    assert.match(sidebar, /class="learner-brand__mark"/);
    assert.match(sidebar, /class="learner-brand__name">Talent<span>Hub<\/span>/);
    assert.match(sidebar, />Khu vực Học sinh</);
    assert.doesNotMatch(sidebar, /learner-brand__logo/);
    assert.match(css, /\.learner-sidebar__brand\s*\{[\s\S]*text-align:\s*center/);
    assert.match(css, /\.learner-brand__mark\s*\{[\s\S]*width:\s*36px/);
});

test('shared page banner is a labelled learner section', () => {
    const bannerPath = path.join(root, 'app/learner/includes/page-banner.php');
    assert.equal(fs.existsSync(bannerPath), true);

    const banner = fs.readFileSync(bannerPath, 'utf8');
    assert.match(banner, /<section class="learner-page-banner" aria-labelledby=/);
    assert.match(banner, /learner-page-banner__eyebrow/);
});
