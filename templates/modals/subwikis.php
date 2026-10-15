<!-- Subwikis Management Modal -->
<div class="modal-overlay" id="subwikis-modal">
    <div class="modal-card" style="max-width: 800px;">
        <div class="modal-header">
            <h3>🌐 Deployed Subwikis</h3>
            <button class="modal-close" data-close="subwikis-modal">&times;</button>
        </div>
        
        <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 1.25rem;">
            Deploy and manage single-level subwikis from this parent Qwiki. Subwikis run with isolated content and users, but receive automated code updates from this parent wiki.
        </p>

        <div class="subwiki-list-container" style="margin-bottom: 1.5rem; max-height: 250px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 6px;">
            <table class="subwiki-table" style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
                <thead>
                    <tr>
                        <th style="padding: 0.6rem 0.75rem;">Title / Slug</th>
                        <th style="padding: 0.6rem 0.75rem;">URL</th>
                        <th style="padding: 0.6rem 0.75rem; text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody id="subwiki-table-body">
                    <tr>
                        <td colspan="3" style="padding: 1.5rem; text-align: center; color: var(--text-muted);">Loading subwikis...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div style="border-top: 1px solid var(--border-color); padding-top: 1.25rem;">
            <h4 style="margin-bottom: 0.75rem; font-size: 1rem;">Deploy New Subwiki</h4>
            <form id="deploy-subwiki-form" autocomplete="off">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 0.75rem;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="new-subwiki-title">Subwiki Title</label>
                        <input type="text" name="title" id="new-subwiki-title" class="form-control" placeholder="e.g. Electrical Engineering" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="new-subwiki-slug">Folder Slug (Group with dash)</label>
                        <input type="text" name="slug" id="new-subwiki-slug" class="form-control" placeholder="e.g. engineering-electrical" required>
                        <small id="subwiki-slug-feedback" style="display: block; margin-top: 0.25rem; font-size: 0.78rem;"></small>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="new-subwiki-user">Initial Admin Username</label>
                        <input type="text" name="adminUser" id="new-subwiki-user" class="form-control" value="admin" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="new-subwiki-pass">Initial Admin Password</label>
                        <input type="password" name="adminPass" id="new-subwiki-pass" class="form-control" placeholder="Password for subwiki" autocomplete="new-password" required>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem; font-weight: normal; font-size: 0.85rem;">
                        <input type="checkbox" name="includeDemo" value="1">
                        Include sample documentation pages
                    </label>
                </div>
                <button type="submit" class="btn btn-primary" id="btn-submit-deploy-subwiki" style="width: 100%;">🚀 Deploy Subwiki</button>
                <p id="deploy-subwiki-loading" style="display: none; text-align: center; color: var(--accent-color); margin-top: 0.5rem; font-size: 0.85rem;">Deploying subwiki... Please wait.</p>
            </form>
        </div>
    </div>
</div>
