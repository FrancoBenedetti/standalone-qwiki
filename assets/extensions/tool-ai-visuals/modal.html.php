<?php
/**
 * AI Visuals & Chart Generator Modal Dialog
 */
?>
<div class="modal-overlay" id="modal-ai-visuals">
    <div class="modal-card" style="max-width: 650px;">
        <div class="modal-header">
            <h3>✨ Agentic Visual & Chart Generator</h3>
            <button class="modal-close" data-close="modal-ai-visuals">&times;</button>
        </div>
        <form id="form-ai-visuals">
            <div class="form-group">
                <label class="form-label">Visual Type</label>
                <select name="type" id="ai-visual-type" class="form-control">
                    <option value="chart_bar">📊 Bar Chart (Revenue, Comparisons, Metrics)</option>
                    <option value="chart_line">📈 Line Chart (Trends, Time Series)</option>
                    <option value="chart_pie">🥧 Pie / Donut Chart (Proportions)</option>
                    <option value="diagram_flow">🔀 Flow / Process Diagram (Mermaid / SVG)</option>
                    <option value="badge_pill">🏷️ Status / Architecture Badge</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Directive / Title / Labels</label>
                <input type="text" name="prompt" id="ai-visual-prompt" class="form-control" placeholder="e.g. Monthly Active Users (Jan: 1200, Feb: 1900, Mar: 2800)" required>
            </div>
            <div class="form-group">
                <label class="form-label">Data Points or Specification (Optional JSON/CSV/Text)</label>
                <textarea name="data" id="ai-visual-data" class="form-control" style="min-height: 80px; font-family: monospace; font-size: 0.85rem;" placeholder='Labels: Q1, Q2, Q3, Q4&#10;Data: 45, 62, 85, 110'></textarea>
            </div>
            <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                <button type="submit" class="btn btn-primary" id="btn-generate-ai-visual">Generate & Save</button>
            </div>
        </form>

        <div id="ai-visual-preview-section" style="display: none; margin-top: 1.5rem; border-top: 1px solid var(--border-color); padding-top: 1rem;">
            <label class="form-label">Generated Visual Preview</label>
            <div id="ai-visual-preview-container" class="ai-visual-preview-box">
                <!-- Preview injected here -->
            </div>
            <div style="margin-top: 1rem; display: flex; gap: 0.5rem; align-items: center;">
                <input type="text" id="ai-visual-markdown-snippet" class="form-control" readonly style="flex: 1; font-family: monospace; font-size: 0.85rem;">
                <button type="button" class="btn btn-outline btn-sm" id="btn-copy-ai-snippet">Copy Markdown</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-insert-ai-visual" style="display: none;">Insert into Editor</button>
            </div>
        </div>
    </div>
</div>
