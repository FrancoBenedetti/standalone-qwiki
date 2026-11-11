<!-- Login Modal -->
<div class="modal-overlay" id="login-modal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="login-modal-title">Account Authentication</h3>
            <button class="modal-close" data-close="login-modal">&times;</button>
        </div>
        <form id="login-form">
            <div class="form-group">
                <label class="form-label" for="login-username">Username or Email</label>
                <input type="text" id="login-username" name="username" class="form-control" placeholder="Enter username or email (default: admin)" autocomplete="username" required>
            </div>
            <div class="form-group" style="margin-bottom: 0.5rem;">
                <label class="form-label" for="login-password">Password</label>
                <input type="password" id="login-password" name="password" class="form-control" placeholder="Enter password (default: admin)" autocomplete="current-password" required>
            </div>
            <div style="text-align: right; margin-bottom: 1.25rem;">
                <button type="button" class="btn-link" id="btn-open-forgot-pwd" style="background: none; border: none; font-size: 0.8rem; color: var(--primary-color, #2563eb); cursor: pointer; text-decoration: underline; padding: 0;">Forgot Password?</button>
            </div>
            <button type="submit" class="btn btn-primary" id="btn-login-submit" style="width: 100%;">Log In</button>
        </form>

        <form id="login-2fa-form" style="display: none;">
            <div style="text-align: center; margin-bottom: 1.25rem;">
                <div style="font-size: 2.25rem; margin-bottom: 0.25rem;">🔒</div>
                <h4 style="margin: 0 0 0.25rem 0;" id="login-2fa-heading">Two-Factor Authentication</h4>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;" id="login-2fa-desc">Enter the 6-digit verification code from your authenticator app.</p>
            </div>
            <div class="form-group">
                <label class="form-label" for="login-2fa-code" id="login-2fa-label" style="text-align: center; display: block;">Verification Code</label>
                <input type="text" id="login-2fa-code" name="code" class="form-control" placeholder="000000" maxlength="10" autocomplete="one-time-code" style="text-align: center; font-size: 1.25rem; letter-spacing: 0.2rem; font-family: monospace;" required>
            </div>
            <button type="submit" class="btn btn-primary" id="btn-2fa-submit" style="width: 100%; margin-bottom: 0.75rem;">Verify &amp; Continue</button>
            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem; margin-top: 0.5rem;">
                <button type="button" class="btn-link" id="btn-toggle-recovery-code" style="background: none; border: none; color: var(--primary-color, #2563eb); cursor: pointer; text-decoration: underline; padding: 0;">Use recovery code</button>
                <button type="button" class="btn-link" id="btn-back-to-login" style="background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 0;">← Back to login</button>
            </div>
        </form>
    </div>
</div>
