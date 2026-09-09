'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const cssPath = path.resolve(__dirname, '../assets/css/learner.css');
const css = fs.readFileSync(cssPath, 'utf8');

test('assessment runner header has compact padding and reduced height', () => {
    assert.match(css, /\.learner-assessment-runner__header\s*\{[^}]*padding:\s*1[2-4]px\s*20px/);
});

test('question card uses flex column with pinned actions to prevent layout shift', () => {
    assert.match(css, /\.learner-question-card\s*\{[^}]*display:\s*flex/);
    assert.match(css, /\.learner-question-card\s*\{[^}]*flex-direction:\s*column/);
    assert.match(css, /\.learner-question-card__actions\s*\{[^}]*margin-top:\s*auto/);
    assert.match(css, /\.learner-question-card__actions\s*\{[^}]*border-top:/);
});

test('likert options have compact min-height and spacing', () => {
    assert.match(css, /\.learner-likert-options\s*\{[^}]*gap:\s*8px/);
    assert.match(css, /\.learner-likert-option__surface\s*\{[^}]*min-height:\s*4[6-8]px/);
    assert.match(css, /\.learner-likert-option__value\s*\{[^}]*width:\s*3[0-2]px/);
    assert.match(css, /\.learner-likert-option__value\s*\{[^}]*height:\s*3[0-2]px/);
});

test('question heading font size is balanced to avoid overflowing', () => {
    assert.match(css, /\.learner-question-card\s+h2\s*\{[^}]*clamp\(1\.1[5-8]rem/);
});

test('revealQuestion uses block nearest to eliminate page jump on advancing questions', () => {
    const jsPath = path.resolve(__dirname, '../assets/js/learner-assessment.js');
    const js = fs.readFileSync(jsPath, 'utf8');
    assert.match(js, /revealQuestion\(\)\s*\{[\s\S]*?block:\s*['"]nearest['"]/);
});

test('assessment.php has automatic cache busting for learner.css and scripts', () => {
    const phpPath = path.resolve(__dirname, '../app/learner/assessment.php');
    const php = fs.readFileSync(phpPath, 'utf8');
    assert.match(php, /learner\.css\?v=<\?=\s*\(int\)\s*@?filemtime/);
    assert.match(php, /learner-assessment\.js\?v=<\?=\s*\(int\)\s*@?filemtime/);
});

