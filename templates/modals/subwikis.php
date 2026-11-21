<!-- Subwikis Management Modal -->
<div class="modal-overlay" id="subwikis-modal">
    <div class="modal-card" style="max-width: 800px;">
        <div class="modal-header">
            <h3>📚 <?= htmlspecialchars(__('subwikis.modal_title', 'Deployed Subwikis')) ?></h3>
            <button class="modal-close" data-close="subwikis-modal">&times;</button>
        </div>
        
        <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 1.25rem;">
            <?= htmlspecialchars(__('subwikis.modal_desc', 'Deploy and manage single-level subwikis from this parent Qwiki. Subwikis run with isolated content and users, but receive automated code updates from this parent wiki.')) ?>
        </p>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
            <span style="font-size: 0.88rem; font-weight: 600;"><?= htmlspecialchars(__('subwikis.active_list', 'Active Subwikis')) ?></span>
            <button type="button" class="btn btn-outline btn-sm" id="btn-sync-docs-shortcut" style="font-size: 0.8rem; padding: 0.25rem 0.6rem;" title="<?= htmlspecialchars(__('subwikis.sync_docs_title', 'Deploy or update the official Qwiki documentation mirror subwiki')) ?>">
                📚 <?= htmlspecialchars(__('subwikis.deploy_docs_mirror', 'Deploy Qwiki Docs Mirror')) ?>
            </button>
        </div>

        <div class="subwiki-list-container" style="margin-bottom: 1.5rem; max-height: 250px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 6px;">
            <table class="subwiki-table" style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
                <thead>
                    <tr>
                        <th style="padding: 0.6rem 0.75rem;"><?= htmlspecialchars(__('subwikis.col_title_slug', 'Title / Slug')) ?></th>
                        <th style="padding: 0.6rem 0.75rem;">URL</th>
                        <th style="padding: 0.6rem 0.75rem; text-align: right;"><?= htmlspecialchars(__('common.action', 'Action')) ?></th>
                    </tr>
                </thead>
                <tbody id="subwiki-table-body">
                    <tr>
                        <td colspan="3" style="padding: 1.5rem; text-align: center; color: var(--text-muted);"><?= htmlspecialchars(__('subwikis.loading', 'Loading subwikis...')) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div style="border-top: 1px solid var(--border-color); padding-top: 1.25rem;">
            <h4 style="margin-bottom: 0.75rem; font-size: 1rem;"><?= htmlspecialchars(__('subwikis.deploy_new_heading', 'Deploy New Subwiki')) ?></h4>
            <form id="deploy-subwiki-form" autocomplete="off">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 0.75rem;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="new-subwiki-title"><?= htmlspecialchars(__('subwikis.title_label', 'Subwiki Title')) ?></label>
                        <input type="text" name="title" id="new-subwiki-title" class="form-control" placeholder="<?= htmlspecialchars(__('subwikis.title_ph', 'e.g. Electrical Engineering')) ?>" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="new-subwiki-slug"><?= htmlspecialchars(__('subwikis.slug_label', 'Folder Slug (Group with dash)')) ?></label>
                        <input type="text" name="slug" id="new-subwiki-slug" class="form-control" placeholder="e.g. engineering-electrical" required>
                        <small id="subwiki-slug-feedback" style="display: block; margin-top: 0.25rem; font-size: 0.78rem;"></small>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="new-subwiki-user"><?= htmlspecialchars(__('subwikis.admin_user_label', 'Initial Admin Username')) ?></label>
                        <input type="text" name="adminUser" id="new-subwiki-user" class="form-control" value="admin" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="new-subwiki-pass"><?= htmlspecialchars(__('subwikis.admin_pass_label', 'Initial Admin Password')) ?></label>
                        <input type="password" name="adminPass" id="new-subwiki-pass" class="form-control" placeholder="<?= htmlspecialchars(__('subwikis.admin_pass_ph', 'Password for subwiki')) ?>" autocomplete="new-password" required>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom: 0.6rem;">
                    <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem; font-weight: normal; font-size: 0.85rem; cursor: pointer;">
                        <input type="checkbox" name="isDocMirror" id="new-subwiki-is-doc-mirror" value="1">
                        <?= __('subwikis.mirror_docs_html', '<strong>Mirror Official Qwiki Documentation</strong> (Links to canonical manual; auto-updates on new releases)') ?>
                    </label>
                </div>
                <div class="form-group" style="margin-bottom: 1rem;" id="group-include-demo">
                    <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem; font-weight: normal; font-size: 0.85rem; cursor: pointer;">
                        <input type="checkbox" name="includeDemo" id="new-subwiki-include-demo" value="1">
                        <?= htmlspecialchars(__('subwikis.include_demo', 'Include sample starter pages (independent editable copy)')) ?>
                    </label>
                </div>
                <button type="submit" class="btn btn-primary" id="btn-submit-deploy-subwiki" style="width: 100%;">🚀 <?= htmlspecialchars(__('subwikis.deploy_btn', 'Deploy Subwiki')) ?></button>
                <p id="deploy-subwiki-loading" style="display: none; text-align: center; color: var(--accent-color); margin-top: 0.5rem; font-size: 0.85rem;"><?= htmlspecialchars(__('subwikis.deploying', 'Deploying subwiki... Please wait.')) ?></p>
            </form>
        </div>
    </div>
</div>
