<?php
/**
 * Standalone Qwiki - Activity & Access Log Extension Modal
 *
 * Rendered by ExtensionManager::renderUtilityModals()
 */
?>
<div class="modal-overlay" id="modal-activity-log" style="z-index: 1060;">
    <div class="modal-card activity-modal-card" style="max-width: 1100px; width: 95%;">
        <!-- Modal Header -->
        <div class="modal-header">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <h3 style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <span>📜</span> Activity & Access Log
                </h3>
                <span class="doc-badge badge-md" id="act-total-badge">Loading...</span>
            </div>
            <button class="modal-close" data-close="modal-activity-log" aria-label="Close">&times;</button>
        </div>

        <!-- Mode Navigation Tabs -->
        <div class="activity-mode-nav">
            <button type="button" class="activity-nav-tab active" data-tab="stream" id="act-tab-btn-stream">
                📊 Activity Stream
            </button>
            <button type="button" class="activity-nav-tab" data-tab="settings" id="act-tab-btn-settings">
                ⚙️ Logging Settings
            </button>
        </div>

        <!-- Notification Banner -->
        <div id="act-status-banner" class="activity-status-banner" style="display: none;">
            <div class="activity-spinner"></div>
            <span id="act-status-text">Loading logs...</span>
        </div>

        <!-- 1. STREAM VIEW -->
        <div id="act-view-stream" class="activity-tab-view">
            <!-- Metrics Summary Grid -->
            <div class="activity-metrics-grid">
                <div class="act-metric-card">
                    <div class="act-metric-icon">🔗</div>
                    <div class="act-metric-info">
                        <div class="act-metric-label">Share Link Hits</div>
                        <div class="act-metric-val" id="act-metric-share-hits">0</div>
                        <div class="act-metric-sub" id="act-metric-share-today">0 today</div>
                    </div>
                </div>

                <div class="act-metric-card">
                    <div class="act-metric-icon">📄</div>
                    <div class="act-metric-info">
                        <div class="act-metric-label">Top Shared Document</div>
                        <div class="act-metric-val act-truncate" id="act-metric-top-doc">None</div>
                        <div class="act-metric-sub" id="act-metric-top-doc-count">0 hits</div>
                    </div>
                </div>

                <div class="act-metric-card">
                    <div class="act-metric-icon">🛡️</div>
                    <div class="act-metric-info">
                        <div class="act-metric-label">Failed Logins (24h)</div>
                        <div class="act-metric-val" id="act-metric-failed-logins">0</div>
                        <div class="act-metric-sub">Security check</div>
                    </div>
                </div>

                <div class="act-metric-card">
                    <div class="act-metric-icon">💾</div>
                    <div class="act-metric-info">
                        <div class="act-metric-label">Log Storage</div>
                        <div class="act-metric-val" id="act-metric-storage">0 B</div>
                        <div class="act-metric-sub" id="act-metric-total-records">0 records</div>
                    </div>
                </div>
            </div>

            <!-- Filter Controls Bar -->
            <div class="act-filter-bar">
                <div class="act-filter-group">
                    <select id="act-filter-category" class="form-control form-control-sm">
                        <option value="all">All Categories</option>
                        <option value="share">🔗 Share Link Access</option>
                        <option value="auth">🔑 Authentication</option>
                        <option value="content">📝 Content & Admin</option>
                        <option value="system">⚙️ System & Locks</option>
                    </select>

                    <select id="act-filter-status" class="form-control form-control-sm">
                        <option value="">All Statuses</option>
                        <option value="200">200 OK</option>
                        <option value="401">401 Unauthorized</option>
                        <option value="403">403 Restricted</option>
                        <option value="404">404 Not Found</option>
                    </select>

                    <select id="act-filter-since" class="form-control form-control-sm">
                        <option value="">All Time</option>
                        <option value="today">Today</option>
                        <option value="-7 days">Last 7 Days</option>
                        <option value="-30 days">Last 30 Days</option>
                    </select>
                </div>

                <div class="act-search-group">
                    <input type="text" id="act-search-input" class="form-control form-control-sm" placeholder="🔍 Search document, user, IP hash, keyword...">
                </div>

                <div class="act-action-group">
                    <button type="button" class="btn btn-outline btn-sm" id="btn-act-refresh" title="Refresh Log Stream">🔄 Refresh</button>
                    <button type="button" class="btn btn-primary btn-sm" id="btn-act-export-csv" title="Export matching logs as CSV spreadsheet">📥 Export CSV</button>
                    <button type="button" class="btn btn-danger btn-sm" id="btn-act-purge" title="Purge expired log partitions" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3);">🗑️ Purge</button>
                </div>
            </div>

            <!-- Log Table -->
            <div class="act-table-wrap">
                <table class="act-table">
                    <thead>
                        <tr>
                            <th style="width: 140px;">Timestamp</th>
                            <th style="width: 130px;">Event</th>
                            <th>Target / Document</th>
                            <th style="width: 130px;">Actor / Client</th>
                            <th style="width: 75px; text-align: center;">Status</th>
                            <th style="width: 70px; text-align: center;">Details</th>
                        </tr>
                    </thead>
                    <tbody id="act-table-body">
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                                Loading activity records...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <div class="act-pagination-bar">
                <span class="act-pagination-info" id="act-pagination-info">Showing 0 of 0</span>
                <div class="act-pagination-buttons">
                    <button type="button" class="btn btn-outline btn-sm" id="btn-act-prev" disabled>← Previous</button>
                    <span id="act-page-display" style="font-size: 0.85rem; font-weight: 500; padding: 0 0.5rem;">Page 1</span>
                    <button type="button" class="btn btn-outline btn-sm" id="btn-act-next" disabled>Next →</button>
                </div>
            </div>
        </div>

        <!-- 2. SETTINGS VIEW -->
        <div id="act-view-settings" class="activity-tab-view" style="display: none;">
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0;">
                Configure privacy, anonymization, and flat-file retention policies. Settings are stored directly in <code>qwiki.json</code>.
            </p>

            <form id="act-settings-form">
                <!-- IP Privacy Mode -->
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-weight: 600; margin-bottom: 0.5rem;">IP Address Privacy & Anonymization Mode</label>
                    <div style="display: flex; flex-direction: column; gap: 0.6rem;">
                        <label style="display: flex; align-items: flex-start; gap: 0.5rem; cursor: pointer;">
                            <input type="radio" name="ip_privacy_mode" value="hash" id="act-ip-mode-hash" checked>
                            <div>
                                <span style="font-weight: 500;">Truncated SHA-256 Hash (Recommended / GDPR Compliant)</span>
                                <div style="font-size: 0.8rem; color: var(--text-muted);">
                                    Stores a 12-character cryptographic hash (e.g. <code>e3b0c44298fc</code>). Preserves unique visitor frequency while protecting user privacy.
                                </div>
                            </div>
                        </label>

                        <label style="display: flex; align-items: flex-start; gap: 0.5rem; cursor: pointer;">
                            <input type="radio" name="ip_privacy_mode" value="masked" id="act-ip-mode-masked">
                            <div>
                                <span style="font-weight: 500;">Subnet Masked IP</span>
                                <div style="font-size: 0.8rem; color: var(--text-muted);">
                                    Zeros out the host identifier (e.g. <code>192.168.1.0/24</code> or IPv6 <code>::/48</code>).
                                </div>
                            </div>
                        </label>

                        <label style="display: flex; align-items: flex-start; gap: 0.5rem; cursor: pointer;">
                            <input type="radio" name="ip_privacy_mode" value="raw" id="act-ip-mode-raw">
                            <div>
                                <span style="font-weight: 500;">Raw IP Address (Internal / Intranet Wikis)</span>
                                <div style="font-size: 0.8rem; color: var(--text-muted);">
                                    Stores complete IPv4/IPv6 address. Recommended only for corporate intranet security audits.
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Log Retention Days -->
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label for="act-retention-days" style="display: block; font-weight: 600; margin-bottom: 0.5rem;">
                        Log Retention Period (Days)
                    </label>
                    <input type="number" id="act-retention-days" name="retention_days" class="form-control" min="1" max="365" value="30" style="max-width: 140px;">
                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.35rem;">
                        Log partitions older than this threshold are safely pruned during manual or scheduled purges.
                    </div>
                </div>

                <div style="display: flex; gap: 0.75rem; margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary" id="btn-act-save-settings">💾 Save Settings</button>
                    <button type="button" class="btn btn-outline" id="btn-act-cancel-settings">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
