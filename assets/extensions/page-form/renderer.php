<?php
/**
 * Form Page Type Renderer
 * Variables available: $chapter, $book, $extension
 */
use Qwiki\Core\Auth;
use Qwiki\Core\Config;

$baseDir = Config::getBaseDir();
$relFile = $chapter['file'] ?? '';
$filePath = Config::safePath($baseDir, $relFile);

if (!$filePath || !file_exists($filePath)) {
    echo "<div class='alert warning'>Form file not found: " . htmlspecialchars($relFile) . "</div>";
    return;
}

$rawJson = file_get_contents($filePath);
$schema = json_decode($rawJson, true);
if (!is_array($schema)) {
    echo "<div class='alert warning'>Invalid form schema format in: " . htmlspecialchars($relFile) . "</div>";
    return;
}

$subFile = preg_replace('/\.form\.json$/i', '.submissions.json', $filePath);
$submissionCount = 0;
if (file_exists($subFile)) {
    $lines = file($subFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $submissionCount = $lines ? count($lines) : 0;
}

$isAdmin = Auth::isAdmin();
$currentUser = Auth::getCurrentUser();
$isLoggedIn = !empty($currentUser);
$config = Config::load();

$now = time();
$secret = $config['adminPasswordHash'] ?? 'qwiki-form-token';
$antiSpamToken = $now . '.' . hash_hmac('sha256', (string)$now, $secret);

$requireLogin = !empty($schema['requireLogin']);
$title = $schema['title'] ?? ($chapter['title'] ?? 'Form');
$description = $schema['description'] ?? '';
$submitButtonText = $schema['submitButtonText'] ?? 'Submit';
$fields = $schema['fields'] ?? [];
?>
<div class="form-page-container">
    <?php if ($isAdmin): ?>
        <div class="form-admin-toolbar">
            <div class="form-admin-info">
                <span class="badge badge-form">FORM</span>
                <span class="form-submission-count-badge" id="form-count-badge">
                    📊 <strong id="form-count-val"><?= $submissionCount ?></strong> <?= $submissionCount === 1 ? 'response' : 'responses' ?>
                </span>
            </div>
            <div class="form-admin-actions">
                <button type="button" class="btn btn-outline btn-sm" id="btn-view-form-submissions"
                        data-file="<?= htmlspecialchars($relFile) ?>"
                        data-title="<?= htmlspecialchars($title) ?>">
                    📋 View Responses
                </button>
                <a href="api/admin.php?action=ext_form_export_csv&file=<?= urlencode($relFile) ?>" 
                   class="btn btn-outline btn-sm" id="btn-export-form-csv" title="Download all submissions as CSV spreadsheet">
                    📥 Export CSV
                </a>
                <button type="button" class="btn btn-primary btn-sm" id="btn-edit-form-schema"
                        data-file="<?= htmlspecialchars($relFile) ?>"
                        data-title="<?= htmlspecialchars($title) ?>">
                    ✏️ Edit Form
                </button>
            </div>
        </div>
    <?php endif; ?>

    <div class="form-card">
        <div class="form-header">
            <h1 class="form-title"><?= htmlspecialchars($title) ?></h1>
            <?php if (!empty($description)): ?>
                <p class="form-desc"><?= nl2br(htmlspecialchars($description)) ?></p>
            <?php endif; ?>
        </div>

        <?php if ($requireLogin && !$isLoggedIn): ?>
            <div class="form-alert form-alert-info">
                <span class="form-alert-icon">🔒</span>
                <div>
                    <strong>Login Required</strong><br>
                    You must be logged in to submit this form. Please log in using the button in the top menu.
                </div>
            </div>
        <?php else: ?>
            <div id="form-feedback-area" style="display: none;"></div>

            <form id="qwiki-rendered-form" data-file="<?= htmlspecialchars($relFile) ?>" novalidate>
                <input type="hidden" name="action" value="ext_form_submit">
                <input type="hidden" name="file" value="<?= htmlspecialchars($relFile) ?>">
                <input type="hidden" name="_qwiki_t" value="<?= htmlspecialchars($antiSpamToken) ?>">

                <!-- Invisible spam honeypot -->
                <div style="position: absolute; left: -9999px; top: -9999px; opacity: 0; pointer-events: none;" aria-hidden="true">
                    <input type="text" name="_qwiki_hp" tabindex="-1" autocomplete="off" value="">
                </div>

                <div class="form-fields-container">
                    <?php foreach ($fields as $f): 
                        $fId = htmlspecialchars($f['id'] ?? 'field_' . uniqid());
                        $fLabel = htmlspecialchars($f['label'] ?? 'Field');
                        $fType = $f['type'] ?? 'text';
                        $fReq = !empty($f['required']);
                        $fPlaceholder = htmlspecialchars($f['placeholder'] ?? '');
                        $fHelp = htmlspecialchars($f['help'] ?? '');
                        $fOptions = is_array($f['options'] ?? null) ? $f['options'] : [];
                    ?>
                        <div class="form-group" data-field-id="<?= $fId ?>">
                            <label class="form-label" for="fld-<?= $fId ?>">
                                <?= $fLabel ?>
                                <?php if ($fReq): ?><span class="form-required-star" title="Required">*</span><?php endif; ?>
                            </label>

                            <?php if ($fType === 'textarea'): ?>
                                <textarea name="fields[<?= $fId ?>]" id="fld-<?= $fId ?>" class="form-control"
                                          placeholder="<?= $fPlaceholder ?>" <?= $fReq ? 'required' : '' ?>></textarea>

                            <?php elseif ($fType === 'select'): ?>
                                <select name="fields[<?= $fId ?>]" id="fld-<?= $fId ?>" class="form-control" <?= $fReq ? 'required' : '' ?>>
                                    <option value="">-- Please Select --</option>
                                    <?php foreach ($fOptions as $opt): ?>
                                        <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
                                    <?php endforeach; ?>
                                </select>

                            <?php elseif ($fType === 'radio'): ?>
                                <div class="form-options-group">
                                    <?php foreach ($fOptions as $idx => $opt): 
                                        $optVal = htmlspecialchars($opt);
                                    ?>
                                        <label class="form-option-item">
                                            <input type="radio" name="fields[<?= $fId ?>]" value="<?= $optVal ?>" <?= ($fReq && $idx === 0) ? 'required' : '' ?>>
                                            <span><?= $optVal ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>

                            <?php elseif ($fType === 'checkbox'): ?>
                                <div class="form-options-group">
                                    <?php if (count($fOptions) === 0): ?>
                                        <label class="form-option-item">
                                            <input type="checkbox" name="fields[<?= $fId ?>]" value="1" <?= $fReq ? 'required' : '' ?>>
                                            <span><?= $fLabel ?></span>
                                        </label>
                                    <?php else: ?>
                                        <?php foreach ($fOptions as $opt): 
                                            $optVal = htmlspecialchars($opt);
                                        ?>
                                            <label class="form-option-item">
                                                <input type="checkbox" name="fields[<?= $fId ?>][]" value="<?= $optVal ?>">
                                                <span><?= $optVal ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>

                            <?php else: ?>
                                <input type="<?= htmlspecialchars($fType) ?>" name="fields[<?= $fId ?>]" id="fld-<?= $fId ?>" class="form-control"
                                       placeholder="<?= $fPlaceholder ?>" <?= $fReq ? 'required' : '' ?>>
                            <?php endif; ?>

                            <?php if (!empty($fHelp)): ?>
                                <div class="form-help-text"><?= $fHelp ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="form-submit-actions">
                    <button type="submit" class="btn btn-primary" id="btn-submit-qwiki-form">
                        <?= htmlspecialchars($submitButtonText) ?>
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($isAdmin): ?>
    <!-- Submissions Viewer Modal -->
    <div id="modal-form-submissions" class="modal" style="display: none;">
        <div class="modal-content form-submissions-modal-dialog">
            <div class="modal-header">
                <h3 id="modal-form-submissions-title">Submissions</h3>
                <button type="button" class="modal-close" id="btn-close-submissions-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap;">
                    <input type="text" id="submissions-search-input" class="form-control" placeholder="🔍 Search responses by keyword, user, email..." style="max-width: 320px;">
                    <div style="display: flex; gap: 0.5rem;">
                        <button type="button" class="btn btn-outline btn-sm" id="btn-refresh-submissions">🔄 Refresh</button>
                        <a href="api/admin.php?action=ext_form_export_csv&file=<?= urlencode($relFile) ?>" class="btn btn-outline btn-sm" id="btn-modal-export-csv">📥 Download CSV</a>
                        <button type="button" class="btn btn-danger btn-sm" id="btn-clear-all-submissions" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3);">🗑️ Clear All</button>
                    </div>
                </div>
                <div class="form-submissions-table-wrap">
                    <div id="form-submissions-content">
                        <!-- Submissions table injected by JS -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Form Schema Modal -->
    <div id="modal-edit-form-schema" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 760px; width: 90%;">
            <div class="modal-header">
                <h3>✏️ Edit Form Design & Settings</h3>
                <button type="button" class="modal-close" id="btn-close-edit-schema-modal">&times;</button>
            </div>
            <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
                <form id="form-edit-schema-form">
                    <div class="form-group">
                        <label class="form-label">Form Title</label>
                        <input type="text" id="edit-form-title" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Form Description / Instructions</label>
                        <textarea id="edit-form-desc" class="form-control" style="min-height: 60px;"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <label class="form-label" style="margin-bottom: 0;">Form Fields</label>
                            <button type="button" class="btn btn-outline btn-sm" id="btn-add-field-edit">+ Add Field</button>
                        </div>
                        <div id="form-builder-fields-edit" class="form-builder-container">
                            <!-- Field cards rendered dynamically -->
                        </div>
                    </div>

                    <details style="margin-bottom: 1.25rem; border: 1px solid var(--border-color, #333); border-radius: 6px; padding: 0.75rem;">
                        <summary style="cursor: pointer; font-weight: 600; font-size: 0.9rem; user-select: none;">⚙️ Settings & Notifications</summary>
                        <div style="margin-top: 0.85rem; display: flex; flex-direction: column; gap: 0.75rem;">
                            <div>
                                <label class="form-label">Submit Button Label</label>
                                <input type="text" id="edit-form-submit-text" class="form-control">
                            </div>
                            <div>
                                <label class="form-label">Success Message</label>
                                <input type="text" id="edit-form-success-msg" class="form-control">
                            </div>
                            <div style="display: flex; gap: 1.5rem; flex-wrap: wrap;">
                                <label style="cursor: pointer; font-size: 0.9rem;">
                                    <input type="checkbox" id="edit-form-require-login"> Require Login to Submit
                                </label>
                                <label style="cursor: pointer; font-size: 0.9rem;">
                                    <input type="checkbox" id="edit-form-allow-multiple"> Allow Multiple Submissions
                                </label>
                            </div>
                            <div>
                                <label class="form-label">Notification Email <span class="text-muted" style="font-weight: normal; font-size: 0.85em;">(optional)</span></label>
                                <input type="email" id="edit-form-notify-email" class="form-control">
                            </div>
                            <div>
                                <label class="form-label">Outgoing Webhook URL <span class="text-muted" style="font-weight: normal; font-size: 0.85em;">(optional)</span></label>
                                <input type="url" id="edit-form-webhook-url" class="form-control">
                            </div>
                        </div>
                    </details>

                    <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1rem;">
                        <button type="button" class="btn btn-outline" id="btn-cancel-edit-schema">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="btn-save-edit-schema">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>
