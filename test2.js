const { JSDOM } = require('jsdom');

const html = `
<div class="sidebar-nav">
  <div class="nav-category-item" data-drag-type="category" data-node-id="b1" data-node-title="Book 1">
    <div class="nav-document-list">
      <a class="nav-link" data-drag-type="document" data-doc-title="Doc 1" data-doc-slug="doc1"></a>
    </div>
  </div>
  <div class="nav-category-item" data-drag-type="category" data-node-id="b2" data-node-title="Book 2">
    <div class="nav-document-list">
      <div class="nav-category-item" data-drag-type="category" data-node-id="b3" data-node-title="Book 3">
        <div class="nav-document-list"></div>
      </div>
    </div>
  </div>
</div>
`;

const dom = new JSDOM(html);
const document = dom.window.document;

function extractCategoryNodeFromDOM(catEl) {
  const nodeId = catEl.getAttribute('data-node-id');
  const nodeTitle = catEl.getAttribute('data-node-title');
  const docList = catEl.querySelector(':scope > .nav-document-list');

  const chapters = [];
  const subfolders = [];

  if (docList) {
    docList.querySelectorAll(':scope > .nav-link[data-drag-type="document"]').forEach(docEl => {
      chapters.push({
        title: docEl.getAttribute('data-doc-title'),
        slug: docEl.getAttribute('data-doc-slug')
      });
    });

    docList.querySelectorAll(':scope > .nav-category-item[data-drag-type="category"]').forEach(subCatEl => {
      subfolders.push(extractCategoryNodeFromDOM(subCatEl));
    });
  }

  const result = { id: nodeId, title: nodeTitle };
  if (chapters.length > 0) result.chapters = chapters;
  if (subfolders.length > 0) result.subfolders = subfolders;
  return result;
}

const tree = [];
document.querySelectorAll('.sidebar-nav > .nav-category-item[data-drag-type="category"]').forEach(topCatEl => {
  tree.push(extractCategoryNodeFromDOM(topCatEl));
});

console.log(JSON.stringify(tree, null, 2));

// Simulate drag-drop: drag b3 to be a sibling of b1 (top level)
const b3 = document.querySelector('[data-node-id="b3"]');
const b1 = document.querySelector('[data-node-id="b1"]');
b1.parentNode.insertBefore(b3, b1);

const tree2 = [];
document.querySelectorAll('.sidebar-nav > .nav-category-item[data-drag-type="category"]').forEach(topCatEl => {
  tree2.push(extractCategoryNodeFromDOM(topCatEl));
});

console.log("AFTER MOVE:");
console.log(JSON.stringify(tree2, null, 2));
