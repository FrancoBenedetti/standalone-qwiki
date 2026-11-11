<?php
namespace Qwiki\Core;

/**
 * Lightweight Zero-Dependency Mailer.
 * Supports socket SMTP (TLS/SSL/Plain with AUTH LOGIN) and native PHP mail() fallback.
 */
class Mailer {
    /**
     * Send an email using configured transport (SMTP if enabled, otherwise PHP mail()).
     *
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $body Plain text body
     * @param array $headers Optional custom headers
     * @return array ['success' => bool, 'error' => string|null]
     */
    public static function send(string $to, string $subject, string $body, array $headers = []): array {
        $to = trim($to);
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Invalid recipient email address.'];
        }

        $config = Config::load();
        $smtp = $config['smtp'] ?? [];

        if (!empty($smtp['enabled']) && !empty($smtp['host'])) {
            return self::sendSmtp($to, $subject, $body, $smtp, $headers);
        }

        return self::sendPhpMail($to, $subject, $body, $smtp, $headers);
    }

    /**
     * Send email via native PHP mail() with envelope sender alignment.
     */
    public static function sendPhpMail(string $to, string $subject, string $body, array $smtpConfig = [], array $customHeaders = []): array {
        $fromEmail = !empty($smtpConfig['fromEmail']) ? trim($smtpConfig['fromEmail']) : self::getDefaultFromEmail();
        $fromName  = !empty($smtpConfig['fromName']) ? trim($smtpConfig['fromName']) : 'Standalone Qwiki';

        $cleanHost = preg_replace('/[^a-zA-Z0-9\.\-]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
        if (empty($cleanHost)) $cleanHost = 'localhost';

        $headers = [];
        $headers[] = "From: {$fromName} <{$fromEmail}>";
        $headers[] = "Reply-To: <{$fromEmail}>";
        $headers[] = "Date: " . date('r');
        $headers[] = "Message-ID: <" . time() . "." . bin2hex(random_bytes(8)) . "@{$cleanHost}>";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: text/plain; charset=UTF-8";
        $headers[] = "Content-Transfer-Encoding: 8bit";
        $headers[] = "X-Mailer: Standalone Qwiki Mailer";
        $headers[] = "Auto-Submitted: auto-generated";

        foreach ($customHeaders as $k => $v) {
            $headers[] = "{$k}: {$v}";
        }

        $headersStr = implode("\r\n", $headers) . "\r\n";

        // Try with envelope sender -f parameter
        $sent = @mail($to, $subject, $body, $headersStr, "-f" . $fromEmail);
        if (!$sent) {
            // Fallback without 5th parameter if host restricts extra arguments
            $sent = @mail($to, $subject, $body, $headersStr);
        }

        if ($sent) {
            return ['success' => true];
        }

        return [
            'success' => false,
            'error' => 'Native mail() dispatch failed. Check server MTA or configure SMTP in Settings.'
        ];
    }

    /**
     * Send email via pure PHP socket SMTP client.
     */
    public static function sendSmtp(string $to, string $subject, string $body, array $smtp, array $customHeaders = []): array {
        $host       = trim($smtp['host'] ?? '127.0.0.1');
        $port       = (int)($smtp['port'] ?? 587);
        $encryption = strtolower(trim($smtp['encryption'] ?? 'tls')); // 'none', 'tls', 'ssl'
        $username   = trim($smtp['username'] ?? '');
        $password   = (string)($smtp['password'] ?? '');
        $fromEmail  = !empty($smtp['fromEmail']) ? trim($smtp['fromEmail']) : self::getDefaultFromEmail();
        $fromName   = !empty($smtp['fromName']) ? trim($smtp['fromName']) : 'Standalone Qwiki';
        $timeout    = 10;

        $log = [];
        $socket = null;

        $readResponse = function() use (&$socket, &$log) {
            $response = '';
            while ($line = fgets($socket, 512)) {
                $response .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            $log[] = '< ' . trim($response);
            return $response;
        };

        $sendCommand = function($cmd, $hide = false) use (&$socket, &$log) {
            $log[] = '> ' . ($hide ? '******' : trim($cmd));
            fwrite($socket, $cmd . "\r\n");
        };

        try {
            $protocol = ($encryption === 'ssl' || $port === 465) ? 'ssl://' : 'tcp://';
            $remoteTarget = $protocol . $host . ':' . $port;

            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ]);

            $errno = 0;
            $errstr = '';
            $socket = @stream_socket_client($remoteTarget, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);

            if (!$socket) {
                return [
                    'success' => false,
                    'error' => "Cannot connect to SMTP host {$host}:{$port} ({$errstr})",
                    'log' => implode("\n", $log)
                ];
            }

            stream_set_timeout($socket, $timeout);

            // 1. Initial banner
            $greeting = $readResponse();
            if (substr($greeting, 0, 3) !== '220') {
                fclose($socket);
                return ['success' => false, 'error' => "Unexpected SMTP banner: {$greeting}", 'log' => implode("\n", $log)];
            }

            // 2. EHLO
            $cleanClient = preg_replace('/[^a-zA-Z0-9\.\-]/', '', $_SERVER['SERVER_NAME'] ?? 'localhost');
            if (empty($cleanClient)) $cleanClient = 'localhost';
            $sendCommand("EHLO {$cleanClient}");
            $ehloRes = $readResponse();

            // 3. STARTTLS if configured
            if ($encryption === 'tls' && $protocol === 'tcp://') {
                $sendCommand("STARTTLS");
                $tlsRes = $readResponse();
                if (substr($tlsRes, 0, 3) === '220') {
                    $cryptoOk = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                    if (!$cryptoOk) {
                        fclose($socket);
                        return ['success' => false, 'error' => "TLS encryption negotiation failed", 'log' => implode("\n", $log)];
                    }
                    $sendCommand("EHLO {$cleanClient}");
                    $readResponse();
                }
            }

            // 4. Authenticate if username provided
            if (!empty($username)) {
                $sendCommand("AUTH LOGIN");
                $authRes = $readResponse();
                if (substr($authRes, 0, 3) !== '334') {
                    fclose($socket);
                    return ['success' => false, 'error' => "SMTP server rejected AUTH LOGIN: {$authRes}", 'log' => implode("\n", $log)];
                }

                $sendCommand(base64_encode($username));
                $uRes = $readResponse();
                if (substr($uRes, 0, 3) !== '334') {
                    fclose($socket);
                    return ['success' => false, 'error' => "SMTP username rejected: {$uRes}", 'log' => implode("\n", $log)];
                }

                $sendCommand(base64_encode($password), true);
                $pRes = $readResponse();
                if (substr($pRes, 0, 3) !== '235') {
                    fclose($socket);
                    return ['success' => false, 'error' => "SMTP authentication failed: {$pRes}", 'log' => implode("\n", $log)];
                }
            }

            // 5. MAIL FROM
            $sendCommand("MAIL FROM:<{$fromEmail}>");
            $fromRes = $readResponse();
            if (substr($fromRes, 0, 3) !== '250') {
                fclose($socket);
                return ['success' => false, 'error' => "MAIL FROM rejected: {$fromRes}", 'log' => implode("\n", $log)];
            }

            // 6. RCPT TO
            $sendCommand("RCPT TO:<{$to}>");
            $rcptRes = $readResponse();
            if (substr($rcptRes, 0, 3) !== '250' && substr($rcptRes, 0, 3) !== '251') {
                fclose($socket);
                return ['success' => false, 'error' => "RCPT TO rejected: {$rcptRes}", 'log' => implode("\n", $log)];
            }

            // 7. DATA
            $sendCommand("DATA");
            $dataRes = $readResponse();
            if (substr($dataRes, 0, 3) !== '354') {
                fclose($socket);
                return ['success' => false, 'error' => "DATA rejected: {$dataRes}", 'log' => implode("\n", $log)];
            }

            // 8. Headers and payload
            $cleanHost = preg_replace('/[^a-zA-Z0-9\.\-]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
            if (empty($cleanHost)) $cleanHost = 'localhost';

            $headers = [];
            $headers[] = "From: {$fromName} <{$fromEmail}>";
            $headers[] = "To: <{$to}>";
            $headers[] = "Reply-To: <{$fromEmail}>";
            $headers[] = "Subject: {$subject}";
            $headers[] = "Date: " . date('r');
            $headers[] = "Message-ID: <" . time() . "." . bin2hex(random_bytes(8)) . "@{$cleanHost}>";
            $headers[] = "MIME-Version: 1.0";
            $headers[] = "Content-Type: text/plain; charset=UTF-8";
            $headers[] = "Content-Transfer-Encoding: 8bit";
            $headers[] = "X-Mailer: Standalone Qwiki SMTP Mailer";
            $headers[] = "Auto-Submitted: auto-generated";

            foreach ($customHeaders as $k => $v) {
                $headers[] = "{$k}: {$v}";
            }

            $msgPayload = implode("\r\n", $headers) . "\r\n\r\n";
            // Normalize line endings to CRLF and perform dot-stuffing
            $normalizedBody = str_replace(["\r\n", "\r", "\n"], "\n", $body);
            $lines = explode("\n", $normalizedBody);
            foreach ($lines as $line) {
                if (isset($line[0]) && $line[0] === '.') {
                    $line = '.' . $line; // dot stuffing
                }
                $msgPayload .= $line . "\r\n";
            }
            $msgPayload .= ".\r\n";

            fwrite($socket, $msgPayload);
            $sendRes = $readResponse();
            if (substr($sendRes, 0, 3) !== '250') {
                fclose($socket);
                return ['success' => false, 'error' => "Message dispatch failed: {$sendRes}", 'log' => implode("\n", $log)];
            }

            // 9. QUIT
            $sendCommand("QUIT");
            $readResponse();
            fclose($socket);

            return ['success' => true, 'log' => implode("\n", $log)];

        } catch (\Throwable $e) {
            if (is_resource($socket)) {
                @fclose($socket);
            }
            return [
                'success' => false,
                'error' => "SMTP Exception: " . $e->getMessage(),
                'log' => implode("\n", $log)
            ];
        }
    }

    /**
     * Test an SMTP configuration without permanently saving it.
     */
    public static function testConnection(array $smtpConfig, string $testRecipient): array {
        $subject = "Standalone Qwiki: SMTP Connection Test";
        $body = "Hello,\n\nThis is a test notification confirming that Standalone Qwiki has successfully connected to your SMTP server and dispatched an email message.\n\nTimestamp: " . date('r') . "\n\nBest regards,\nStandalone Qwiki";
        return self::sendSmtp($testRecipient, $subject, $body, $smtpConfig);
    }

    /**
     * Compute fallback from-email based on server environment.
     */
    public static function getDefaultFromEmail(): string {
        $host = preg_replace('/[^a-zA-Z0-9\.\-]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
        if (empty($host) || $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) {
            return 'no-reply@qwiki.local';
        }
        $parts = explode(':', $host);
        return 'no-reply@' . $parts[0];
    }
}
