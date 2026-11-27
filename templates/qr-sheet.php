<?php
/**
 * Printable A4 QR Decal Sheet Template (?qr_sheet=1)
 * Expects: $qrDecals (array), $qrCategories (array), $siteTitle (string), $brandLogoUrl (string), $assetsUrl (string)
 */
$safeSiteTitle = htmlspecialchars($siteTitle ?? 'Qwiki Manual');
$safeLogoUrl = !empty($brandLogoUrl) ? htmlspecialchars($brandLogoUrl) : '';
$qrJsPath = rtrim($assetsUrl ?? 'assets', '/') . '/js/qrcode.min.js';
$qrJsVer = @filemtime(__DIR__ . '/../assets/js/qrcode.min.js') ?: time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= $safeSiteTitle ?> — QR Equipment Decals</title>
  <style>
    @page {
      size: A4 portrait;
      margin: 10mm;
    }
    * {
      box-sizing: border-box;
    }
    body {
      margin: 0;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
      color: #111827;
      background: #f3f4f6;
    }
    .qr-toolbar {
      position: sticky;
      top: 0;
      z-index: 20;
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      padding: 0.75rem 1.25rem;
      background: #111827;
      color: #ffffff;
    }
    .qr-toolbar-group {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 0.5rem;
    }
    .qr-toolbar select,
    .qr-toolbar button {
      padding: 0.4rem 0.75rem;
      border-radius: 6px;
      border: 1px solid #374151;
      background: #1f2937;
      color: #ffffff;
      font-size: 0.85rem;
      cursor: pointer;
    }
    .qr-toolbar .btn-print {
      border-color: #2563eb;
      background: #2563eb;
      font-weight: 600;
    }
    .qr-sheet {
      width: 210mm;
      margin: 1rem auto;
      padding: 10mm;
      background: #ffffff;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
    }
    .qr-decal-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 4mm;
    }
    .qr-decal-grid.layout-3x5 {
      grid-template-columns: repeat(3, 1fr);
      gap: 3mm;
    }
    .qr-decal-card {
      position: relative;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      height: 67.7mm;
      padding: 3.5mm;
      border: 1px dashed #9ca3af;
      border-radius: 3mm;
      background: #ffffff;
      break-inside: avoid;
      page-break-inside: avoid;
    }
    .qr-decal-grid.layout-3x5 .qr-decal-card {
      height: 38.1mm;
      padding: 2.2mm;
    }
    .qr-decal-card.excluded {
      opacity: 0.35;
    }
    .qr-decal-check {
      position: absolute;
      top: 2mm;
      right: 2mm;
      width: 15px;
      height: 15px;
      cursor: pointer;
    }
    .qr-decal-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 2mm;
      border-bottom: 1px solid #e5e7eb;
      padding-bottom: 1.5mm;
      padding-right: 18px;
    }
    .qr-decal-logo {
      max-height: 7mm;
      max-width: 32mm;
      object-fit: contain;
    }
    .qr-decal-brand {
      font-size: 8.5pt;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .qr-decal-cat {
      font-size: 7pt;
      font-weight: 600;
      color: #4b5563;
      text-transform: uppercase;
    }
    .qr-decal-body {
      display: flex;
      align-items: center;
      gap: 3mm;
      flex: 1;
      padding-top: 2mm;
    }
    .qr-decal-svg {
      flex-shrink: 0;
      line-height: 0;
    }
    .qr-decal-svg svg {
      display: block;
      width: 32mm;
      height: 32mm;
    }
    .qr-decal-grid.layout-3x5 .qr-decal-svg svg {
      width: 21mm;
      height: 21mm;
    }
    .qr-decal-meta {
      display: flex;
      flex-direction: column;
      justify-content: center;
      min-width: 0;
      gap: 1.2mm;
    }
    .qr-decal-title {
      font-size: 10pt;
      font-weight: 700;
      line-height: 1.2;
      word-break: break-word;
    }
    .qr-decal-grid.layout-3x5 .qr-decal-title {
      font-size: 8pt;
    }
    .qr-decal-cta {
      font-size: 8pt;
      font-weight: 600;
      color: #1f2937;
    }
    .qr-decal-grid.layout-3x5 .qr-decal-cta {
      font-size: 6.5pt;
    }
    .qr-decal-url {
      font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
      font-size: 6.5pt;
      color: #4b5563;
      word-break: break-all;
    }
    .qr-decal-grid.layout-3x5 .qr-decal-url {
      display: none;
    }
    @media print {
      body {
        background: #ffffff;
      }
      .qr-toolbar,
      .qr-decal-check,
      .qr-decal-card.excluded,
      .qr-decal-card.cat-hidden {
        display: none !important;
      }
      .qr-sheet {
        width: auto;
        margin: 0;
        padding: 0;
        box-shadow: none;
      }
      .qr-decal-header {
        padding-right: 0;
      }
      .qr-decal-card {
        border-style: solid;
        border-color: #d1d5db;
      }
    }
  </style>
</head>
<body>
  <div class="qr-toolbar">
    <div class="qr-toolbar-group">
      <strong><?= $safeSiteTitle ?> — QR Decal Sheet</strong>
      <select id="qr-sheet-cat-filter" aria-label="Filter by category">
        <option value="">All Categories (<?= count($qrDecals) ?>)</option>
        <?php foreach ($qrCategories as $catName): ?>
          <option value="<?= htmlspecialchars($catName) ?>"><?= htmlspecialchars($catName) ?></option>
        <?php endforeach; ?>
      </select>
      <select id="qr-sheet-layout" aria-label="Label preset">
        <option value="2x4">2×4 A4 (99.1 × 67.7 mm)</option>
        <option value="3x5">3×5 A4 (63.5 × 38.1 mm)</option>
      </select>
    </div>
    <div class="qr-toolbar-group">
      <button type="button" id="btn-qr-select-all">Select All</button>
      <button type="button" id="btn-qr-select-none">Deselect All</button>
      <button type="button" class="btn-print" onclick="window.print()">🖨️ Print / Save PDF</button>
    </div>
  </div>

  <main class="qr-sheet">
    <div class="qr-decal-grid" id="qr-decal-grid">
      <?php foreach ($qrDecals as $idx => $decal): ?>
        <div class="qr-decal-card" data-category="<?= htmlspecialchars($decal['category']) ?>" data-url="<?= htmlspecialchars($decal['shareUrl']) ?>">
          <input type="checkbox" class="qr-decal-check" checked title="Include in print sheet" />
          <div class="qr-decal-header">
            <?php if ($safeLogoUrl): ?>
              <img src="<?= $safeLogoUrl ?>" alt="<?= $safeSiteTitle ?>" class="qr-decal-logo" />
            <?php else: ?>
              <span class="qr-decal-brand"><?= $safeSiteTitle ?></span>
            <?php endif; ?>
            <?php if (!empty($decal['category'])): ?>
              <span class="qr-decal-cat"><?= htmlspecialchars($decal['category']) ?></span>
            <?php endif; ?>
          </div>
          <div class="qr-decal-body">
            <div class="qr-decal-svg" id="qr-svg-<?= $idx ?>"></div>
            <div class="qr-decal-meta">
              <div class="qr-decal-title"><?= htmlspecialchars($decal['title']) ?></div>
              <div class="qr-decal-cta">Scan for live manual, wiring &amp; parts</div>
              <div class="qr-decal-url"><?= htmlspecialchars($decal['shareUrl']) ?></div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </main>

  <script src="<?= htmlspecialchars($qrJsPath) ?>?v=<?= $qrJsVer ?>"></script>
  <script>
    (function () {
      var grid = document.getElementById('qr-decal-grid');
      var cards = Array.prototype.slice.call(document.querySelectorAll('.qr-decal-card'));
      cards.forEach(function (card, idx) {
        var url = card.getAttribute('data-url');
        var box = document.getElementById('qr-svg-' + idx);
        if (url && box && window.QRCode && window.QRCode.generateSvg) {
          box.innerHTML = window.QRCode.generateSvg(url, 180, 2);
        }
        var cb = card.querySelector('.qr-decal-check');
        if (cb) {
          cb.addEventListener('change', function () {
            card.classList.toggle('excluded', !cb.checked);
          });
        }
      });

      var layoutSel = document.getElementById('qr-sheet-layout');
      if (layoutSel && grid) {
        layoutSel.addEventListener('change', function () {
          grid.classList.toggle('layout-3x5', layoutSel.value === '3x5');
        });
      }

      var catSel = document.getElementById('qr-sheet-cat-filter');
      if (catSel) {
        catSel.addEventListener('change', function () {
          var target = catSel.value;
          cards.forEach(function (card) {
            var match = !target || card.getAttribute('data-category') === target;
            card.classList.toggle('cat-hidden', !match);
            card.style.display = match ? '' : 'none';
          });
        });
      }

      var btnAll = document.getElementById('btn-qr-select-all');
      var btnNone = document.getElementById('btn-qr-select-none');
      if (btnAll) {
        btnAll.addEventListener('click', function () {
          cards.forEach(function (card) {
            if (card.classList.contains('cat-hidden')) return;
            var cb = card.querySelector('.qr-decal-check');
            if (cb) { cb.checked = true; card.classList.remove('excluded'); }
          });
        });
      }
      if (btnNone) {
        btnNone.addEventListener('click', function () {
          cards.forEach(function (card) {
            if (card.classList.contains('cat-hidden')) return;
            var cb = card.querySelector('.qr-decal-check');
            if (cb) { cb.checked = false; card.classList.add('excluded'); }
          });
        });
      }
    })();
  </script>
</body>
</html>
