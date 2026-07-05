<?php
if (!function_exists('smtp_mailer_setting')) {
    function smtp_mailer_setting($conn, $key, $fallback = '')
    {
        $key = (string) $key;
        $settingTables = [
            ['table' => 'sm_settings', 'key' => 'setting_key', 'value' => 'setting_value'],
            ['table' => 'sm_app_settings', 'key' => 'setting_key', 'value' => 'setting_value'],
            ['table' => 'sm_app_settings', 'key' => 'name', 'value' => 'value'],
        ];

        foreach ($settingTables as $config) {
            $table = $config['table'];
            $keyColumn = $config['key'];
            $valueColumn = $config['value'];
            $escapedTable = $conn->real_escape_string($table);
            $exists = @$conn->query("SHOW TABLES LIKE '" . $escapedTable . "'");
            if (!$exists || $exists->num_rows === 0) {
                continue;
            }

            $hasKey = @$conn->query("SHOW COLUMNS FROM `$table` LIKE '" . $conn->real_escape_string($keyColumn) . "'");
            $hasValue = @$conn->query("SHOW COLUMNS FROM `$table` LIKE '" . $conn->real_escape_string($valueColumn) . "'");
            if (!$hasKey || !$hasValue || $hasKey->num_rows === 0 || $hasValue->num_rows === 0) {
                continue;
            }

            $stmt = $conn->prepare("SELECT `$valueColumn` AS setting_value FROM `$table` WHERE `$keyColumn` = ? LIMIT 1");
            if (!$stmt) {
                continue;
            }
            $stmt->bind_param('s', $key);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row && array_key_exists('setting_value', $row)) {
                return (string) $row['setting_value'];
            }
        }

        return $fallback;
    }
}

if (!function_exists('smtp_mailer_read')) {
    function smtp_mailer_read($socket)
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') break;
        }
        return $response;
    }
}

if (!function_exists('smtp_mailer_command')) {
    function smtp_mailer_command($socket, $command, array $expectedCodes)
    {
        if ($command !== null) {
            fwrite($socket, $command . "\r\n");
        }
        $response = smtp_mailer_read($socket);
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            return [false, trim($response)];
        }
        return [true, trim($response)];
    }
}

if (!function_exists('smtp_mailer_dot_stuff')) {
    function smtp_mailer_dot_stuff($body)
    {
        $body = str_replace(["\r\n", "\r"], "\n", (string) $body);
        $lines = explode("\n", $body);
        foreach ($lines as &$line) {
            if (isset($line[0]) && $line[0] === '.') {
                $line = '.' . $line;
            }
        }
        return implode("\r\n", $lines);
    }
}

if (!function_exists('smtp_mailer_send')) {
    function smtp_mailer_send($conn, $toEmail, $toName, $subject, $body, $isHtml = false)
    {
        $host = smtp_mailer_setting($conn, 'smtp_host', 'smtp.gmail.com');
        $port = (int) smtp_mailer_setting($conn, 'smtp_port', '587');
        $encryption = strtolower(smtp_mailer_setting($conn, 'smtp_encryption', 'tls'));
        $username = smtp_mailer_setting($conn, 'smtp_username', '');
        $password = smtp_mailer_setting($conn, 'smtp_password', '');
        $fromEmail = smtp_mailer_setting($conn, 'smtp_from_email', $username);
        $fromName = smtp_mailer_setting($conn, 'smtp_from_name', 'SMARTLINK SOFT');

        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) return [false, 'Recipient email is invalid.'];
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) return [false, 'SMTP from email is invalid.'];
        if ($host === '' || $port <= 0 || $username === '' || $password === '') return [false, 'SMTP settings are incomplete.'];
        if (stripos($host, 'gmail.com') !== false) {
            $password = preg_replace('/\s+/', '', $password);
        }
        if (!function_exists('stream_socket_client')) return [false, 'PHP stream sockets are not available.'];

        $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
        if (!$socket) return [false, 'SMTP connection failed: ' . $errstr];
        stream_set_timeout($socket, 20);

        try {
            [$ok, $msg] = smtp_mailer_command($socket, null, [220]);
            if (!$ok) throw new Exception($msg);

            [$ok, $msg] = smtp_mailer_command($socket, 'EHLO localhost', [250]);
            if (!$ok) throw new Exception($msg);

            if ($encryption === 'tls') {
                [$ok, $msg] = smtp_mailer_command($socket, 'STARTTLS', [220]);
                if (!$ok) throw new Exception($msg);
                $crypto = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if ($crypto !== true) throw new Exception('Unable to start TLS encryption.');
                [$ok, $msg] = smtp_mailer_command($socket, 'EHLO localhost', [250]);
                if (!$ok) throw new Exception($msg);
            }

            [$ok, $msg] = smtp_mailer_command($socket, 'AUTH LOGIN', [334]);
            if (!$ok) throw new Exception($msg);
            [$ok, $msg] = smtp_mailer_command($socket, base64_encode($username), [334]);
            if (!$ok) throw new Exception($msg);
            [$ok, $msg] = smtp_mailer_command($socket, base64_encode($password), [235]);
            if (!$ok) throw new Exception($msg);

            [$ok, $msg] = smtp_mailer_command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
            if (!$ok) throw new Exception($msg);
            [$ok, $msg] = smtp_mailer_command($socket, 'RCPT TO:<' . $toEmail . '>', [250, 251]);
            if (!$ok) throw new Exception($msg);
            [$ok, $msg] = smtp_mailer_command($socket, 'DATA', [354]);
            if (!$ok) throw new Exception($msg);

            $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
            $headers = [
                'Date: ' . date('r'),
                'From: ' . mb_encode_mimeheader($fromName, 'UTF-8') . ' <' . $fromEmail . '>',
                'To: ' . mb_encode_mimeheader($toName ?: $toEmail, 'UTF-8') . ' <' . $toEmail . '>',
                'Subject: ' . $encodedSubject,
                'MIME-Version: 1.0',
                'Content-Type: ' . ($isHtml ? 'text/html' : 'text/plain') . '; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
            ];
            fwrite($socket, implode("\r\n", $headers) . "\r\n\r\n" . smtp_mailer_dot_stuff($body) . "\r\n.\r\n");
            [$ok, $msg] = smtp_mailer_command($socket, null, [250]);
            if (!$ok) throw new Exception($msg);
            smtp_mailer_command($socket, 'QUIT', [221, 250]);
            fclose($socket);
            return [true, 'Email sent.'];
        } catch (Throwable $e) {
            @fwrite($socket, "QUIT\r\n");
            @fclose($socket);
            return [false, 'SMTP error: ' . $e->getMessage()];
        }
    }
}
?>
