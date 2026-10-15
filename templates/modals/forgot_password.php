<!-- Forgot Password Modal -->
<div class="modal-overlay" id="forgot-password-modal">
    <div class="modal-card" style="max-width: 440px;">
        <div class="modal-header">
            <h3>🔑 Reset Password</h3>
            <button class="modal-close" data-close="forgot-password-modal">&times;</button>
        </div>
        <form id="forgot-password-form">
            <p style="color: var(--text-muted); font-size: 0.88rem; margin-bottom: 1.25rem;">
                Enter your username or verified email address. We will dispatch a secure, one-time link to reset your password.
            </p>
            <div id="forgot-password-alert" style="display: none; padding: 0.75rem 1rem; border-radius: 6px; font-size: 0.85rem; margin-bottom: 1rem;"></div>
            <div class="form-group">
                <label class="form-label" for="forgot-identifier">Username or Email</label>
                <input type="text" id="forgot-identifier" name="identifier" class="form-control" placeholder="Enter username or email" required>
            </div>
            <button type="submit" class="btn btn-primary" id="btn-submit-forgot" style="width: 100%;">Send Reset Link</button>
            <div style="text-align: center; margin-top: 1rem;">
                <button type="button" class="btn-link" id="btn-back-from-forgot" style="background: none; border: none; font-size: 0.8rem; color: var(--text-muted); cursor: pointer; text-decoration: underline;">← Return to Login</button>
            </div>
        </form>
    </div>
</div>
