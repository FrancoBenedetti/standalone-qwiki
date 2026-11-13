const fs = require('fs');
const assert = require('assert');
const path = require('path');

const createChapterPhp = fs.readFileSync(path.join(__dirname, '../templates/modals/create_chapter.php'), 'utf8');
const appJs = fs.readFileSync(path.join(__dirname, '../assets/js/app.js'), 'utf8');

// 1. Check .modal-tabs is used instead of .tab-header
assert.ok(createChapterPhp.includes('class="modal-tabs"'), 'create_chapter.php should use .modal-tabs');
assert.ok(!createChapterPhp.includes('class="tab-header"'), 'create_chapter.php should not use legacy .tab-header');

// 2. Check master category selector #chapter-modal-category exists above .modal-tabs
const masterIdx = createChapterPhp.indexOf('id="chapter-modal-category"');
const tabsIdx = createChapterPhp.indexOf('class="modal-tabs"');
assert.ok(masterIdx > -1, '#chapter-modal-category must exist');
assert.ok(masterIdx < tabsIdx, '#chapter-modal-category must appear above .modal-tabs');

// 3. Check redundant <select name="bookId"> are replaced with hidden inputs
assert.ok(!/<select[^>]*name="bookId"/i.test(createChapterPhp), 'No <select name="bookId"> should remain in create_chapter.php tabs');
assert.ok(createChapterPhp.includes('id="link-root-override"'), '#tab-link must include #link-root-override checkbox');

// 4. Check app.js implements syncChapterModalCategory
assert.ok(appJs.includes('syncChapterModalCategory'), 'app.js must define syncChapterModalCategory');

console.log('PASS: Add chapter modal structure and category sync tests passed');
