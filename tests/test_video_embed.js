const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');
const assert = require('assert');

// Setup JSDOM environment
const html = `
<!DOCTYPE html>
<html>
<head></head>
<body>
  <div class="content-body" id="content-body">
    <!-- Test 1: YouTube standard link -->
    <p><a href="https://www.youtube.com/watch?v=dQw4w9WgXcQ">https://www.youtube.com/watch?v=dQw4w9WgXcQ</a></p>

    <!-- Test 2: YouTube short link with timestamp -->
    <p><a href="https://youtu.be/dQw4w9WgXcQ?t=1m30s">https://youtu.be/dQw4w9WgXcQ?t=1m30s</a></p>

    <!-- Test 3: YouTube with custom title -->
    <p><a href="https://youtu.be/dQw4w9WgXcQ">Never Gonna Give You Up</a></p>

    <!-- Test 4: YouTube Shorts -->
    <p><a href="https://www.youtube.com/shorts/3lP_1iQJ2h4">https://www.youtube.com/shorts/3lP_1iQJ2h4</a></p>

    <!-- Test 5: Vimeo URL -->
    <p><a href="https://vimeo.com/76979871">https://vimeo.com/76979871</a></p>

    <!-- Test 6: Loom Share URL -->
    <p><a href="https://www.loom.com/share/d46d0a7a40b3438ea238e8ba73a9042c">https://www.loom.com/share/d46d0a7a40b3438ea238e8ba73a9042c</a></p>

    <!-- Test 7: Direct MP4 link -->
    <p><a href="https://example.com/demo.mp4">https://example.com/demo.mp4</a></p>

    <!-- Test 8: Image markdown pointing to video file with alt -->
    <p><img src="uploads/videos/sample.webm" alt="Sample Clip" /></p>

    <!-- Test 9: Inline link inside paragraph (MUST NOT convert to embed) -->
    <p id="inline-test">Here is a sentence with <a href="https://youtu.be/dQw4w9WgXcQ">https://youtu.be/dQw4w9WgXcQ</a> inline.</p>

    <!-- Test 10: Non-video link (MUST NOT convert to embed) -->
    <p id="non-video-test"><a href="https://example.com/docs">https://example.com/docs</a></p>
  </div>
</body>
</html>
`;

const dom = new JSDOM(html, {
  url: 'http://localhost:8000/index.php',
  runScripts: 'dangerously'
});

const { window } = dom;
global.window = window;
global.document = window.document;
global.URL = window.URL;

// Read app.js and execute in dom context
const appJsCode = fs.readFileSync(path.join(__dirname, '../assets/js/app.js'), 'utf8');
window.eval(appJsCode);

// Dispatch DOMContentLoaded event
window.document.dispatchEvent(new window.Event('DOMContentLoaded'));

const contentBody = window.document.getElementById('content-body');
const wrappers = contentBody.querySelectorAll('.qwiki-video-wrapper');

console.log(`Found ${wrappers.length} video wrappers.`);
assert.strictEqual(wrappers.length, 8, 'Expected exactly 8 video wrappers to be generated');

// Verify Test 1: YouTube standard
const yt1 = wrappers[0].querySelector('iframe');
assert.ok(yt1, 'Test 1 should contain an iframe');
assert.ok(yt1.src.includes('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'), 'Test 1 should use youtube-nocookie embed URL');
assert.strictEqual(wrappers[0].querySelector('.qwiki-video-caption'), null, 'Test 1 should have no caption');

// Verify Test 2: YouTube with timestamp (1m30s = 90s)
const yt2 = wrappers[1].querySelector('iframe');
assert.ok(yt2, 'Test 2 should contain an iframe');
assert.ok(yt2.src.includes('start=90'), `Test 2 should contain start=90, got: ${yt2.src}`);

// Verify Test 3: YouTube with custom title
const yt3 = wrappers[2].querySelector('iframe');
assert.ok(yt3, 'Test 3 should contain an iframe');
const cap3 = wrappers[2].querySelector('.qwiki-video-caption');
assert.ok(cap3, 'Test 3 should have a caption');
assert.strictEqual(cap3.textContent, 'Never Gonna Give You Up', 'Caption should match custom link text');

// Verify Test 4: YouTube Shorts
const yt4 = wrappers[3].querySelector('iframe');
assert.ok(yt4, 'Test 4 should contain an iframe');
assert.ok(yt4.src.includes('https://www.youtube-nocookie.com/embed/3lP_1iQJ2h4'), 'Test 4 should embed the Shorts video');

// Verify Test 5: Vimeo
const vimeo = wrappers[4].querySelector('iframe');
assert.ok(vimeo, 'Test 5 should contain an iframe');
assert.ok(vimeo.src.includes('https://player.vimeo.com/video/76979871'), 'Test 5 should embed Vimeo player');

// Verify Test 6: Loom
const loom = wrappers[5].querySelector('iframe');
assert.ok(loom, 'Test 6 should contain an iframe');
assert.ok(loom.src.includes('https://www.loom.com/embed/d46d0a7a40b3438ea238e8ba73a9042c'), 'Test 6 should embed Loom player');

// Verify Test 7: Direct MP4
const directVideo = wrappers[6].querySelector('video');
assert.ok(directVideo, 'Test 7 should contain a video element');
assert.strictEqual(directVideo.controls, true, 'Test 7 video should have controls');
const source1 = directVideo.querySelector('source');
assert.strictEqual(source1.src, 'https://example.com/demo.mp4');
assert.strictEqual(source1.type, 'video/mp4');

// Verify Test 8: Image markdown to WebM with alt
const webmVideo = wrappers[7].querySelector('video');
assert.ok(webmVideo, 'Test 8 should contain a video element');
const source2 = webmVideo.querySelector('source');
assert.strictEqual(source2.getAttribute('src'), 'uploads/videos/sample.webm');
assert.strictEqual(source2.type, 'video/webm');
const cap8 = wrappers[7].querySelector('.qwiki-video-caption');
assert.ok(cap8, 'Test 8 should have a caption from alt text');
assert.strictEqual(cap8.textContent, 'Sample Clip');

// Verify Test 9: Inline link was preserved
const inlineP = contentBody.querySelector('#inline-test');
assert.ok(inlineP, 'Test 9 inline paragraph should still exist');
assert.strictEqual(inlineP.querySelectorAll('a').length, 1, 'Inline paragraph should still have its link');
assert.strictEqual(inlineP.querySelector('.qwiki-video-wrapper'), null, 'Inline paragraph should not have a video wrapper');

// Verify Test 10: Non-video link was preserved
const nonVideoP = contentBody.querySelector('#non-video-test');
assert.ok(nonVideoP, 'Test 10 non-video paragraph should still exist');
assert.strictEqual(nonVideoP.querySelectorAll('a').length, 1, 'Non-video paragraph should still have its link');

console.log('✅ All video embed tests passed successfully!');
