<!-- User Management Modal -->
<div class="modal-overlay" id="users-modal">
    <div class="modal-card" style="max-width: 700px;">
        <div class="modal-header">
            <h3>👥 <?= htmlspecialchars(__('users.modal_title', 'Manage Users & Permissions')) ?></h3>
            <button class="modal-close" data-close="users-modal">&times;</button>
        </div>
        
        <form id="add-user-form" autocomplete="off" style="margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-color);">
            <h4 style="margin-bottom: 1rem; color: var(--text-primary);"><?= htmlspecialchars(__('users.add_new', 'Add New User')) ?></h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label"><?= htmlspecialchars(__('users.username', 'Username')) ?></label>
                    <input type="text" name="username" class="form-control" placeholder="<?= htmlspecialchars(__('users.username_ph', 'e.g. john_viewer')) ?>" autocomplete="off" required>
                </div>
                <div>
                    <label class="form-label"><?= htmlspecialchars(__('auth.password', 'Password')) ?></label>
                    <input type="password" name="password" class="form-control" placeholder="<?= htmlspecialchars(__('users.set_password_ph', 'Set password')) ?>" autocomplete="new-password" required>
                </div>
                <div>
                    <label class="form-label"><?= htmlspecialchars(__('users.email_optional', 'Email (Optional)')) ?></label>
                    <input type="email" name="email" class="form-control" placeholder="user@example.com" autocomplete="off">
                </div>
                <div>
                    <label class="form-label"><?= htmlspecialchars(__('users.role', 'Role')) ?></label>
                    <select name="role" class="form-control" required>
                        <option value="viewer"><?= htmlspecialchars(__('users.role_viewer', 'Viewer (Read-only)')) ?></option>
                        <option value="admin"><?= htmlspecialchars(__('users.role_admin', 'Admin (Full Access)')) ?></option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><?= htmlspecialchars(__('users.add_btn', '+ Add User')) ?></button>
        </form>

        <h4 style="margin-bottom: 1rem; color: var(--text-primary);"><?= htmlspecialchars(__('users.existing', 'Existing Users')) ?></h4>
        <div id="users-list-container">
            <p style="color: var(--text-muted);"><?= htmlspecialchars(__('users.loading', 'Loading users...')) ?></p>
        </div>
    </div>
</div>
