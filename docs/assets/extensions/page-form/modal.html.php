<?php
/**
 * Form Page Creation Modal Content
 * Variables available in scope: $activeBook, $config, $extension
 */
$books = $config['books'] ?? [];
?>
<div class="form-group">
    <label class="form-label">Target Category / Folder</label>
    <select name="bookId" class="form-control" required>
        <?php if (!empty($categoryHierarchy)): ?>
            <?php foreach ($categoryHierarchy as $cat): ?>
                <option value="<?= htmlspecialchars($cat['id']) ?>" <?= (($currentCategoryId ?? '') === $cat['id']) ? 'selected' : '' ?>>
                    <?= str_repeat('&nbsp;&nbsp;', $cat['depth']) ?><?= $cat['depth'] > 0 ? '↳ ' : '' ?><?= htmlspecialchars($cat['path']) ?>
                </option>
            <?php endforeach; ?>
        <?php else: ?>
            <?php foreach ($books as $b): ?>
                <?php if (($b['type'] ?? 'folder') === 'folder' && isset($b['id'])): ?>
                    <option value="<?= htmlspecialchars($b['id']) ?>" <?= ($activeBook && ($activeBook['id'] ?? '') === $b['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($b['title']) ?>
                    </option>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </select>
</div>

<div class="form-group">
    <label class="form-label">Form Title</label>
    <input type="text" name="title" id="form-create-title" class="form-control" placeholder="e.g. Feedback Survey, Contact Us, Registration" required>
</div>

<div class="form-group">
    <label class="form-label">Starter Template</label>
    <select id="form-create-template-select" class="form-control">
        <option value="feedback">🌟 Feedback Survey (Ratings, Features, Comments)</option>
        <option value="contact">✉️ Contact Us (Name, Email, Subject, Message)</option>
        <option value="rsvp">📅 Event RSVP (Attendance, Dietary, Notes)</option>
        <option value="blank">📄 Blank Form (Start from scratch)</option>
    </select>
</div>

<div class="form-group">
    <label class="form-label">Form Description / Instructions <span class="text-muted" style="font-weight: normal; font-size: 0.85em;">(optional)</span></label>
    <textarea name="description" id="form-create-desc" class="form-control" style="min-height: 60px;" placeholder="Provide brief instructions or context for respondents..."></textarea>
</div>

<div class="form-group">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
        <label class="form-label" style="margin-bottom: 0;">Form Fields</label>
        <button type="button" class="btn btn-outline btn-sm" id="btn-add-field-create">+ Add Field</button>
    </div>
    
    <div id="form-builder-fields-create" class="form-builder-container">
        <!-- Dynamically rendered field cards -->
    </div>
</div>

<details style="margin-bottom: 1.25rem; border: 1px solid var(--border-color, #333); border-radius: 6px; padding: 0.75rem;">
    <summary style="cursor: pointer; font-weight: 600; font-size: 0.9rem; user-select: none;">⚙️ Advanced Submission & Notification Settings</summary>
    <div style="margin-top: 0.85rem; display: flex; flex-direction: column; gap: 0.75rem;">
        <div>
            <label class="form-label">Submit Button Label</label>
            <input type="text" id="form-create-submit-text" class="form-control" value="Submit">
        </div>
        <div>
            <label class="form-label">Success Message</label>
            <input type="text" id="form-create-success-msg" class="form-control" value="Thank you! Your response has been recorded.">
        </div>
        <div style="display: flex; gap: 1.5rem; flex-wrap: wrap;">
            <label style="cursor: pointer; font-size: 0.9rem;">
                <input type="checkbox" id="form-create-require-login"> Require Login to Submit
            </label>
            <label style="cursor: pointer; font-size: 0.9rem;">
                <input type="checkbox" id="form-create-allow-multiple" checked> Allow Multiple Submissions
            </label>
        </div>
        <div>
            <label class="form-label">Notification Email <span class="text-muted" style="font-weight: normal; font-size: 0.85em;">(optional, alerts on new response)</span></label>
            <input type="email" id="form-create-notify-email" class="form-control" placeholder="admin@example.com">
        </div>
        <div>
            <label class="form-label">Outgoing Webhook URL <span class="text-muted" style="font-weight: normal; font-size: 0.85em;">(optional, POST JSON to Slack / Discord / Zapier)</span></label>
            <input type="url" id="form-create-webhook-url" class="form-control" placeholder="https://hooks.slack.com/services/...">
        </div>
    </div>
</details>

<!-- Serialized schema payload -->
<input type="hidden" name="schema_json" id="form-create-schema-json" value="">

<button type="submit" class="btn btn-primary" style="width: 100%;" id="btn-submit-create-form">📝 Create Form Document</button>
