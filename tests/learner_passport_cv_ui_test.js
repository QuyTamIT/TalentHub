const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { fits } = require('../assets/js/learner-passport-cv.js');
test('refuse overflow rather than clipping content or shrinking text', () => {
    assert.equal(fits({scrollHeight:900,scrollWidth:680,clientWidth:680}),true);
    assert.equal(fits({scrollHeight:1200,scrollWidth:680,clientWidth:680}),false);
    assert.equal(fits({scrollHeight:900,scrollWidth:900,clientWidth:680}),false);
});
test('export re-requests data, owns identity, disables cache and does not publish a share', () => {
    const route=fs.readFileSync('app/learner/talent-passport-cv.php','utf8');
    const js=fs.readFileSync('assets/js/learner-passport-cv.js','utf8');
    assert.match(route,/'student_profile.read_own'/);
    assert.match(route,/WHERE u.id=\?/);
    assert.match(route,/no-store/);
    assert.doesNotMatch(route,/\$_GET\['student/);
    assert.match(js,/location.replace/);
    assert.match(js,/fonts.ready/);
    assert.doesNotMatch(js,/profile-shares|innerHTML/);
});
