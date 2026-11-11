<?php
/**
 * Standalone Qwiki - Activity & Access Log Extension Handler
 */
use Qwiki\Core\Auth;
use Qwiki\Core\Config;
use Qwiki\Core\AuditLogger;

// 1. Authorization Guard: Admin only
if (!Auth::isAdmin()) {
    if (!headers_sent()) {
        http_response_code(403);
    }
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Administrator privileges required.']);
    return;
}

$action = $action ?? $_REQUEST['action'] ?? $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'ext_activity_get_logs':
        $page = isset($_REQUEST['page']) ? max(1, (int)$_REQUEST['page']) : 1;
        $perPage = isset($_REQUEST['per_page']) ? max(1, min(200, (int)$_REQUEST['per_page'])) : 50;

        $filters = [
            'category' => trim($_REQUEST['category'] ?? ''),
            'event'    => trim($_REQUEST['event'] ?? ''),
            'search'   => trim($_REQUEST['search'] ?? ''),
            'status'   => isset($_REQUEST['status']) && $_REQUEST['status'] !== '' ? trim($_REQUEST['status']) : null,
            'since'    => trim($_REQUEST['since'] ?? '')
        ];

        $result = AuditLogger::queryLogs($filters, $page, $perPage);
        $result['success'] = true;

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return;

    case 'ext_activity_get_stats':
        $stats = AuditLogger::getStats();
        $config = Config::load();

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'success'  => true,
            'stats'    => $stats,
            'settings' => [
                'ipPrivacyMode'    => $config['ipPrivacyMode'] ?? 'hash',
                'logRetentionDays' => (int)($config['logRetentionDays'] ?? 30)
            ]
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return;

    case 'ext_activity_export_csv':
        $filters = [
            'category' => trim($_REQUEST['category'] ?? ''),
            'event'    => trim($_REQUEST['event'] ?? ''),
            'search'   => trim($_REQUEST['search'] ?? ''),
            'status'   => isset($_REQUEST['status']) && $_REQUEST['status'] !== '' ? trim($_REQUEST['status']) : null,
            'since'    => trim($_REQUEST['since'] ?? '')
        ];

        // Retrieve all records matching filters (up to 5000)
        $result = AuditLogger::queryLogs($filters, 1, 5000);
        $records = $result['items'] ?? [];

        $filename = 'qwiki-activity-log-' . date('Y-m-d_His') . '.csv';

        if (!headers_sent()) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
        }

        $out = fopen('php://output', 'w');
        // UTF-8 BOM for Excel compatibility
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Header row
        fputcsv($out, [
            'ID',
            'Timestamp',
            'Category',
            'Event',
            'Status',
            'User',
            'IP / Hash',
            'IP Mode',
            'Target / Document',
            'Share Key',
            'Referrer',
            'User Agent',
            'Additional Details'
        ]);

        foreach ($records as $r) {
            $details = $r['details'] ?? [];
            $target = $details['title'] ?? $details['slug'] ?? $details['file'] ?? '';
            $shareKey = $details['share_key'] ?? '';
            $referrer = $details['referrer'] ?? '';
            $ua = $details['user_agent'] ?? '';

            // Clean details for readability
            $detailsCopy = $details;
            unset($detailsCopy['title'], $detailsCopy['slug'], $detailsCopy['file'], $detailsCopy['share_key'], $detailsCopy['referrer'], $detailsCopy['user_agent']);
            $detailsStr = !empty($detailsCopy) ? json_encode($detailsCopy, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';

            fputcsv($out, [
                $r['id'] ?? '',
                $r['ts'] ?? '',
                $r['category'] ?? '',
                $r['event'] ?? '',
                $r['status'] ?? '',
                $r['user'] ?? '',
                $r['ip'] ?? '',
                $r['ip_type'] ?? '',
                $target,
                $shareKey,
                $referrer,
                $ua,
                $detailsStr
            ]);
        }

        fclose($out);
        return;

    case 'ext_activity_purge_logs':
        $keepDays = isset($_POST['keep_days']) ? max(1, (int)$_POST['keep_days']) : 30;
        $deletedCount = AuditLogger::purgeLogs($keepDays);

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'success'       => true,
            'deleted_files' => $deletedCount,
            'message'       => "Cleaned up {$deletedCount} expired log file partition(s)."
        ]);
        return;

    case 'ext_activity_save_settings':
        $ipMode = strtolower(trim($_POST['ip_privacy_mode'] ?? 'hash'));
        if (!in_array($ipMode, ['hash', 'masked', 'raw'], true)) {
            $ipMode = 'hash';
        }

        $retentionDays = isset($_POST['retention_days']) ? max(1, (int)$_POST['retention_days']) : 30;

        $config = Config::load();
        $config['ipPrivacyMode'] = $ipMode;
        $config['logRetentionDays'] = $retentionDays;

        if (Config::save($config)) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode([
                'success' => true,
                'message' => 'Logging settings saved successfully.'
            ]);
        } else {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode([
                'success' => false,
                'error'   => 'Failed to save settings to qwiki.json'
            ]);
        }
        return;

    default:
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['success' => false, 'error' => "Unknown activity action '{$action}'."]);
        return;
}
