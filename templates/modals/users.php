<!-- User Management Modal -->
<div class="modal-overlay" id="users-modal">
    <div class="modal-card" style="max-width: 700px;">
        <div class="modal-header">
            <h3>👥 Manage Users & Permissions</h3>
            <button class="modal-close" data-close="users-modal">&times;</button>
        </div>
        
        <form id="add-user-form" autocomplete="off" style="margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-color);">
            <h4 style="margin-bottom: 1rem; color: var(--text-primary);">Add New User</h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" placeholder="e.g. john_viewer" autocomplete="off" required>
                </div>
                <div>
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Set password" autocomplete="new-password" required>
                </div>
                <div>
                    <label class="form-label">Email (Optional)</label>
                    <input type="email" name="email" class="form-control" placeholder="user@example.com" autocomplete="off">
                </div>
                <div>
                    <label class="form-label">Role</label>
                    <select name="role" class="form-control" required>
                        <option value="viewer">Viewer (Read-only)</option>
                        <option value="admin">Admin (Full Access)</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">+ Add User</button>
        </form>

        <h4 style="margin-bottom: 1rem; color: var(--text-primary);">Existing Users</h4>
        <div id="users-list-container">
            <p style="color: var(--text-muted);">Loading users...</p>
        </div>
    </div>
</div>
