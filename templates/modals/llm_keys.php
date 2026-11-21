<!-- LLM & API Keys Management Modal -->
<div class="modal-overlay" id="llm-keys-modal">
    <div class="modal-card" style="max-width: 880px;">
        <div class="modal-header">
            <h3>🤖 <?= htmlspecialchars(__('llm.modal_title', 'Controlled LLM & AI Agent Access')) ?></h3>
            <button class="modal-close" data-close="llm-keys-modal">&times;</button>
        </div>
        
        <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 1.25rem;">
            <?= htmlspecialchars(__('llm.modal_desc', 'Create and manage scoped, revocable access keys for autonomous AI agents, LLMs (Claude Desktop, Cursor IDE, ChatGPT), and RAG pipelines to explore the wiki tree and analyze document content.')) ?>
        </p>

        <div style="margin-bottom: 1.25rem; padding: 0.75rem 1rem; background: var(--bg-surface, rgba(0,0,0,0.02)); border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.85rem; display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;">
            <div>
                <strong>✨ <?= htmlspecialchars(__('llm.gemini_title', 'Native Gemini AI Assistant')) ?></strong>
                <span style="color: var(--text-muted); display: block; font-size: 0.8rem; margin-top: 0.15rem;"><?= htmlspecialchars(__('llm.gemini_desc', 'Configure Gemini model selection, API key, and editorial AI tools.')) ?></span>
            </div>
            <button type="button" class="btn btn-outline btn-sm" id="btn-open-gemini-from-llm" data-gemini-tab="settings" style="white-space: nowrap;"><?= htmlspecialchars(__('llm.ai_settings_btn', 'AI Assistant Settings')) ?></button>
        </div>

        <form id="add-llm-key-form" style="margin-bottom: 1.5rem; padding: 1.25rem; background: var(--bg-surface, rgba(0,0,0,0.02)); border: 1px solid var(--border-color); border-radius: 8px;">
            <h4 style="margin-bottom: 1rem; color: var(--text-primary);"><?= htmlspecialchars(__('llm.generate_heading', 'Generate New Access Key')) ?></h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label"><?= htmlspecialchars(__('llm.name_purpose', 'Name / Purpose')) ?> <span style="color: var(--danger-color, #ef4444); font-size: 0.75rem;">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="<?= htmlspecialchars(__('llm.name_ph', 'e.g. Claude Desktop, Cursor AI, Support Bot')) ?>" required>
                </div>
                <div>
                    <label class="form-label"><?= htmlspecialchars(__('llm.auth_branch', 'Authorised Branch / Category')) ?></label>
                    <select name="category" class="form-control">
                        <option value=""><?= htmlspecialchars(__('llm.all_categories', 'All Categories (Entire Wiki)')) ?></option>
                        <?php foreach ($categoryHierarchy as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['id']) ?>">
                                <?= str_repeat('&nbsp;&nbsp;', $cat['depth']) ?><?= $cat['depth'] > 0 ? '↳ ' : '' ?><?= htmlspecialchars($cat['path']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: var(--text-muted); font-size: 0.75rem; display: block; margin-top: 0.25rem;"><?= htmlspecialchars(__('llm.auth_branch_help', 'Key will only be permitted to view this branch and its subcategories.')) ?></small>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label"><?= htmlspecialchars(__('llm.permitted_types', 'Permitted Document Types')) ?></label>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 0.25rem; font-size: 0.85rem;">
                        <label style="display: flex; align-items: center; gap: 0.25rem; cursor: pointer;">
                            <input type="checkbox" name="allowedTypes[]" value="markdown" checked> Markdown (.md)
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.25rem; cursor: pointer;">
                            <input type="checkbox" name="allowedTypes[]" value="html"> HTML (.html)
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.25rem; cursor: pointer;">
                            <input type="checkbox" name="allowedTypes[]" value="pdf"> PDF (.pdf)
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.25rem; cursor: pointer;">
                            <input type="checkbox" name="allowedTypes[]" value="gdoc"> Google Docs
                        </label>
                    </div>
                </div>
                <div>
                    <label class="form-label"><?= htmlspecialchars(__('llm.expiration_date', 'Expiration Date (Optional)')) ?></label>
                    <input type="datetime-local" name="expiresAt" class="form-control">
                    <small style="color: var(--text-muted); font-size: 0.75rem; display: block; margin-top: 0.25rem;"><?= htmlspecialchars(__('llm.expiration_help', 'Leave empty for a non-expiring key.')) ?></small>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-sm"><?= htmlspecialchars(__('llm.generate_btn', '+ Generate API Key')) ?></button>
        </form>

        <h4 style="margin-bottom: 1rem; color: var(--text-primary);"><?= htmlspecialchars(__('llm.configured_keys', 'Configured Access Keys')) ?></h4>
        <div id="llm-keys-list-container" style="overflow-x: auto;">
            <p style="color: var(--text-muted);"><?= htmlspecialchars(__('llm.loading_keys', 'Loading keys...')) ?></p>
        </div>

        <!-- Integration Endpoints Guide -->
        <details style="margin-top: 1.5rem; padding: 0.75rem 1rem; background: var(--bg-surface, rgba(0,0,0,0.02)); border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.85rem;">
            <summary style="cursor: pointer; font-weight: 600; color: var(--text-primary);">📡 <?= htmlspecialchars(__('llm.endpoints_title', 'Integration Endpoints & Quick Start')) ?></summary>
            <div style="margin-top: 0.75rem; display: flex; flex-direction: column; gap: 0.5rem; line-height: 1.5;">
                <div><?= __('llm.ep_auth_html', '<strong>Authentication:</strong> Pass your key in the HTTP header: <code>Authorization: Bearer &lt;key&gt;</code> or via query parameter <code>?key=&lt;key&gt;</code>.') ?></div>
                <div><?= __('llm.ep_tree_html', '<strong>1. Document Tree Mode:</strong> <code>{base_url}api/llm.php?mode=tree</code> (JSON) or <code>?mode=tree&amp;format=llms.txt</code> (<a href="https://llmstxt.org" target="_blank" rel="noopener">llms.txt</a> Markdown).', ['base_url' => htmlspecialchars(\Qwiki\Core\Config::getBaseUrl())]) ?></div>
                <div><?= __('llm.ep_doc_html', '<strong>2. Document Access Mode:</strong> <code>{base_url}api/llm.php?mode=doc&amp;slug=&lt;document-slug&gt;</code> (Supports optional <code>format=raw</code>).', ['base_url' => htmlspecialchars(\Qwiki\Core\Config::getBaseUrl())]) ?></div>
                <div><?= __('llm.ep_search_html', '<strong>3. Search Mode:</strong> <code>{base_url}api/llm.php?mode=search&amp;q=&lt;query&gt;</code>', ['base_url' => htmlspecialchars(\Qwiki\Core\Config::getBaseUrl())]) ?></div>
                <div><?= __('llm.ep_schema_html', '<strong>4. OpenAPI Tool Schema:</strong> <code>{base_url}api/llm.php?mode=schema</code> (1-click import into ChatGPT Custom Actions or Claude Projects).', ['base_url' => htmlspecialchars(\Qwiki\Core\Config::getBaseUrl())]) ?></div>
            </div>
        </details>
    </div>
</div>
