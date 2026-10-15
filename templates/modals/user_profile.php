<!-- User Profile & Security Modal -->
<div class="modal-overlay" id="user-profile-modal">
    <div class="modal-card" style="max-width: 580px;">
        <div class="modal-header">
            <h3>👤 Account &amp; Security</h3>
            <button class="modal-close" data-close="user-profile-modal">&times;</button>
        </div>
        
        <div class="modal-body" style="padding: 1rem 0;">
            <div style="display: flex; align-items: center; justify-content: space-between; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color); margin-bottom: 1.25rem;">
                <div>
                    <strong style="font-size: 1.1rem; color: var(--text-primary);"><?= htmlspecialchars($currentUser['username'] ?? '') ?></strong>
                    <span class="doc-badge <?= $isAdmin ? 'badge-md' : 'badge-pdf' ?>" style="margin-left: 0.5rem; text-transform: uppercase;"><?= htmlspecialchars($currentUser['role'] ?? 'viewer') ?></span>
                </div>
            </div>

            <!-- Email Management Section -->
            <div style="margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-color);">
                <h4 style="margin: 0 0 0.5rem 0; color: var(--text-primary);">Email Address</h4>
                <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0 0 1rem 0;">
                    Required for self-service password resets. A verification email will be dispatched when saving a new address.
                </p>
                <form id="profile-email-form">
                    <div class="form-group" style="margin-bottom: 0.75rem;">
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="email" id="profile-user-email" class="form-control" placeholder="name@yourdomain.com" value="" required>
                            <button type="submit" class="btn btn-primary" id="btn-save-profile-email" style="white-space: nowrap;">Save Email</button>
                        </div>
                    </div>
                    <div id="profile-email-status-container" style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; font-size: 0.85rem;">
                        <span id="profile-email-badge"></span>
                        <button type="button" class="btn btn-outline btn-sm" id="btn-resend-verification" style="display: none; padding: 0.2rem 0.5rem;">Resend Verification Link</button>
                    </div>
                </form>
            </div>

            <!-- Two-Factor Authentication Section -->
            <div>
                <h4 style="margin: 0 0 0.5rem 0; color: var(--text-primary);">Two-Factor Authentication (2FA)</h4>
                <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0 0 1rem 0;">
                    Protect your account with Time-Based One-Time Passwords (TOTP) using authenticator apps like Google Authenticator, Bitwarden, or 1Password.
                </p>

                <!-- State 1: 2FA Disabled -->
                <div id="profile-2fa-disabled-view">
                    <div style="padding: 1rem; background: var(--bg-surface, rgba(0,0,0,0.02)); border: 1px solid var(--border-color); border-radius: 6px; margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <span style="font-size: 0.9rem; font-weight: 600;">Status: </span>
                            <span style="color: var(--text-muted); font-size: 0.88rem;">Disabled</span>
                        </div>
                        <button type="button" class="btn btn-primary btn-sm" id="btn-start-2fa-setup">🔐 Enable 2FA</button>
                    </div>
                </div>

                <!-- State 2: 2FA Enrollment Wizard -->
                <div id="profile-2fa-setup-view" style="display: none; padding: 1.25rem; background: var(--bg-surface, rgba(0,0,0,0.02)); border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 1rem;">
                    <h5 style="margin: 0 0 0.5rem 0;">Step 1: Scan QR Code or Enter Key</h5>
                    <p style="font-size: 0.83rem; color: var(--text-muted); margin: 0 0 1rem 0;">Scan this QR code in your authenticator app, or enter the secret key manually.</p>
                    
                    <div style="display: flex; flex-direction: column; align-items: center; margin-bottom: 1rem; text-align: center;">
                        <div id="profile-2fa-qr-container" style="background: #fff; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); display: inline-block; margin-bottom: 0.75rem;"></div>
                        <div style="font-size: 0.85rem; font-family: monospace; background: var(--bg-surface, #f3f4f6); padding: 0.4rem 0.8rem; border-radius: 4px; border: 1px solid var(--border-color); margin-bottom: 0.5rem; word-break: break-all;" id="profile-2fa-secret-text"></div>
                        <button type="button" class="btn btn-outline btn-sm" id="btn-copy-2fa-secret" style="font-size: 0.8rem; padding: 0.2rem 0.5rem;">📋 Copy Secret Key</button>
                    </div>

                    <h5 style="margin: 1.25rem 0 0.5rem 0;">Step 2: Enter 6-Digit Code to Confirm</h5>
                    <form id="profile-2fa-confirm-form">
                        <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">
                            <input type="text" id="profile-2fa-confirm-code" class="form-control" placeholder="000000" maxlength="6" style="text-align: center; font-family: monospace; font-size: 1.1rem; letter-spacing: 0.2rem;" required autocomplete="one-time-code">
                            <button type="submit" class="btn btn-primary" id="btn-confirm-2fa" style="white-space: nowrap;">Activate 2FA</button>
                        </div>
                    </form>

                    <div style="text-align: right;">
                        <button type="button" class="btn-link" id="btn-cancel-2fa-setup" style="background: none; border: none; font-size: 0.8rem; color: var(--text-muted); cursor: pointer;">Cancel Setup</button>
                    </div>
                </div>

                <!-- State 3: 2FA Active View -->
                <div id="profile-2fa-active-view" style="display: none;">
                    <div style="padding: 1rem; background: rgba(16,185,129,0.08); border: 1px solid #10b981; border-radius: 6px; margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <span style="font-size: 0.9rem; font-weight: 600; color: #047857;">🔒 Status: Active</span>
                            <p style="font-size: 0.82rem; color: var(--text-muted); margin: 0.25rem 0 0 0;">Two-Factor Authentication is currently protecting this account.</p>
                        </div>
                        <button type="button" class="btn btn-outline btn-sm btn-danger-text" id="btn-disable-my-2fa">Disable 2FA</button>
                    </div>
                </div>

                <!-- Recovery Codes Display Card -->
                <div id="profile-2fa-recovery-codes-card" style="display: none; padding: 1.25rem; background: rgba(245,158,11,0.08); border: 1px solid #f59e0b; border-radius: 8px;">
                    <h5 style="margin: 0 0 0.25rem 0; color: #b45309;">⚠️ Save Your Emergency Recovery Codes</h5>
                    <p style="font-size: 0.82rem; color: var(--text-muted); margin: 0 0 0.75rem 0;">If you ever lose access to your authenticator app, each of these 8 codes can be used once to log in. Save them in a secure place.</p>
                    <div id="profile-2fa-codes-list" style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-family: monospace; font-size: 0.9rem; background: var(--bg-surface, #fff); padding: 0.75rem; border-radius: 6px; border: 1px solid var(--border-color); margin-bottom: 0.75rem;"></div>
                    <div style="display: flex; gap: 0.5rem;">
                        <button type="button" class="btn btn-primary btn-sm" id="btn-copy-recovery-codes">📋 Copy All Codes</button>
                        <button type="button" class="btn btn-outline btn-sm" id="btn-done-recovery-codes">Done</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
