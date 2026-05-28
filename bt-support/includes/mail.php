<?php
class Mailer {
    private array $cfg;

    public function __construct() {
        $this->cfg = require __DIR__ . '/../config/config.php';
    }

    public function send(string $to, string $subject, string $body, string $to_name = ''): bool {
        $method = $this->cfg['mail_method'] ?? 'php';
        if ($method === 'smtp') {
            return $this->sendSmtp($to, $subject, $body, $to_name);
        }
        return $this->sendPhpMail($to, $subject, $body, $to_name);
    }

    private function sendPhpMail(string $to, string $subject, string $body, string $to_name): bool {
        $from      = $this->cfg['smtp_from'] ?? ini_get('sendmail_from');
        $from_name = $this->cfg['smtp_from_name'] ?? 'BT-Support';
        $headers   = "MIME-Version: 1.0\r\n";
        $headers  .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers  .= "From: =?UTF-8?B?" . base64_encode($from_name) . "?= <{$from}>\r\n";
        $headers  .= "Reply-To: {$from}\r\n";
        $to_header = $to_name ? "=?UTF-8?B?" . base64_encode($to_name) . "?= <{$to}>" : $to;
        return @mail($to_header, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
    }

    private function sendSmtp(string $to, string $subject, string $body, string $to_name): bool {
        $host     = $this->cfg['smtp_host'];
        $port     = (int)($this->cfg['smtp_port'] ?? 587);
        $user     = $this->cfg['smtp_user'];
        $pass     = $this->cfg['smtp_pass'];
        $from     = $this->cfg['smtp_from'];
        $from_name= $this->cfg['smtp_from_name'] ?? 'BT-Support';
        $secure   = $this->cfg['smtp_secure'] ?? 'tls';

        try {
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ]);
            if ($secure === 'ssl') {
                $socket = @stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
            } else {
                $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
            }
            if (!$socket) {
                throw new \RuntimeException("No se pudo conectar a {$host}:{$port} — {$errstr} ({$errno})");
            }

            // 10 s read timeout so fgets() never blocks forever
            stream_set_timeout($socket, 10);

            $this->smtpRead($socket);
            $this->smtpWrite($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
            $response = $this->smtpRead($socket);

            if ($secure === 'tls') {
                $this->smtpWrite($socket, "STARTTLS");
                $this->smtpRead($socket);
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->smtpWrite($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
                $this->smtpRead($socket);
            }

            $this->smtpWrite($socket, "AUTH LOGIN");
            $this->smtpRead($socket);
            $this->smtpWrite($socket, base64_encode($user));
            $this->smtpRead($socket);
            $this->smtpWrite($socket, base64_encode($pass));
            $auth = $this->smtpRead($socket);
            if (strpos($auth, '235') === false) { fclose($socket); return false; }

            $this->smtpWrite($socket, "MAIL FROM:<{$from}>");
            $this->smtpRead($socket);
            $this->smtpWrite($socket, "RCPT TO:<{$to}>");
            $this->smtpRead($socket);
            $this->smtpWrite($socket, "DATA");
            $this->smtpRead($socket);

            $msg  = "From: =?UTF-8?B?" . base64_encode($from_name) . "?= <{$from}>\r\n";
            $msg .= "To: " . ($to_name ? "=?UTF-8?B?" . base64_encode($to_name) . "?= <{$to}>" : $to) . "\r\n";
            $msg .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
            $msg .= "MIME-Version: 1.0\r\n";
            $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
            $msg .= "Content-Transfer-Encoding: base64\r\n";
            $msg .= "\r\n" . chunk_split(base64_encode($body)) . "\r\n.\r\n";

            $this->smtpWrite($socket, $msg, false);
            $sent = $this->smtpRead($socket);
            $this->smtpWrite($socket, "QUIT");
            fclose($socket);
            return strpos($sent, '250') !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function smtpWrite($socket, string $cmd, bool $crlf = true): void {
        fwrite($socket, $cmd . ($crlf ? "\r\n" : ''));
    }

    private function smtpRead($socket): string {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') break;
            $meta = stream_get_meta_data($socket);
            if ($meta['timed_out']) {
                throw new \RuntimeException('Timeout esperando respuesta del servidor SMTP');
            }
        }
        return $response;
    }

    public function sendTicketCreated(array $ticket, array $client): bool {
        $lang    = $client['language'] ?? 'es';
        $subject = $this->getEmailTemplate('ticket_created', $lang, 'subject', $ticket);
        $body    = $this->getEmailTemplate('ticket_created', $lang, 'body',    $ticket);
        return $this->send($client['email'], $subject, $body, $client['name']);
    }

    public function sendTicketReply(array $ticket, array $recipient, array $reply): bool {
        $lang    = $recipient['language'] ?? 'es';
        $data    = array_merge($ticket, ['reply_message' => $reply['message']]);
        $subject = $this->getEmailTemplate('ticket_reply', $lang, 'subject', $data);
        $body    = $this->getEmailTemplate('ticket_reply', $lang, 'body',    $data);
        return $this->send($recipient['email'], $subject, $body, $recipient['name']);
    }

    public function sendTicketResolved(array $ticket, array $client): bool {
        $lang    = $client['language'] ?? 'es';
        $subject = $this->getEmailTemplate('ticket_resolved', $lang, 'subject', $ticket);
        $body    = $this->getEmailTemplate('ticket_resolved', $lang, 'body',    $ticket);
        return $this->send($client['email'], $subject, $body, $client['name']);
    }

    public function sendPasswordReset(string $email, string $name, string $reset_url, string $lang = 'es'): bool {
        $data    = ['reset_url' => $reset_url, 'name' => $name];
        $subject = $this->getEmailTemplate('password_reset', $lang, 'subject', $data);
        $body    = $this->getEmailTemplate('password_reset', $lang, 'body',    $data);
        return $this->send($email, $subject, $body, $name);
    }

    private function getEmailTemplate(string $slug, string $lang, string $part, array $vars): string {
        try {
            $col = $part . '_' . $lang;
            $st  = db()->prepare("SELECT subject_es, subject_en, body_es, body_en FROM email_templates WHERE slug = ?");
            $st->execute([$slug]);
            $tpl = $st->fetch();
            if ($tpl) {
                $text = $tpl[$col] ?? $tpl[$part . '_es'] ?? '';
                return $this->interpolate($text, $vars);
            }
        } catch (\Throwable $e) {}
        return $this->defaultTemplate($slug, $lang, $part, $vars);
    }

    private function interpolate(string $text, array $vars): string {
        foreach ($vars as $k => $v) {
            $text = str_replace('{{' . $k . '}}', htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'), $text);
        }
        return $text;
    }

    private function defaultTemplate(string $slug, string $lang, string $part, array $vars): string {
        $cfg      = require __DIR__ . '/../config/config.php';
        $app_name = setting('company_name') ?: ($cfg['app_name'] ?? 'BT-Support');
        $logo_url = setting('company_logo') ? base_url('uploads/logos/' . setting('company_logo')) : '';

        if ($part === 'subject') {
            $subjects = [
                'ticket_created'  => ['es' => "Ticket #{$vars['ticket_number']} creado — {$app_name}",  'en' => "Ticket #{$vars['ticket_number']} created — {$app_name}"],
                'ticket_reply'    => ['es' => "Nueva respuesta en ticket #{$vars['ticket_number']}",     'en' => "New reply on ticket #{$vars['ticket_number']}"],
                'ticket_resolved' => ['es' => "Ticket #{$vars['ticket_number']} resuelto",               'en' => "Ticket #{$vars['ticket_number']} resolved"],
                'password_reset'  => ['es' => "Restablecer contraseña — {$app_name}",                   'en' => "Password reset — {$app_name}"],
            ];
            return $this->interpolate($subjects[$slug][$lang] ?? $slug, $vars);
        }

        $logo_html = $logo_url ? "<img src=\"{$logo_url}\" alt=\"{$app_name}\" style=\"max-height:50px;margin-bottom:16px\">" : "<h2 style=\"margin:0 0 16px\">{$app_name}</h2>";

        $bodies = [
            'ticket_created' => [
                'es' => "<p>Hola <strong>{{name}}</strong>,</p><p>Tu ticket <strong>#{{ticket_number}}</strong> — <em>{{subject}}</em> — ha sido creado exitosamente.</p><p>Nuestro equipo lo atenderá pronto.</p>",
                'en' => "<p>Hello <strong>{{name}}</strong>,</p><p>Your ticket <strong>#{{ticket_number}}</strong> — <em>{{subject}}</em> — has been created successfully.</p><p>Our team will attend it shortly.</p>",
            ],
            'ticket_reply' => [
                'es' => "<p>Hola <strong>{{name}}</strong>,</p><p>Hay una nueva respuesta en tu ticket <strong>#{{ticket_number}}</strong>.</p><blockquote style=\"border-left:4px solid #ddd;padding-left:12px;color:#555\">{{reply_message}}</blockquote><p><a href=\"{{ticket_url}}\">Ver ticket</a></p>",
                'en' => "<p>Hello <strong>{{name}}</strong>,</p><p>There is a new reply on your ticket <strong>#{{ticket_number}}</strong>.</p><blockquote style=\"border-left:4px solid #ddd;padding-left:12px;color:#555\">{{reply_message}}</blockquote><p><a href=\"{{ticket_url}}\">View ticket</a></p>",
            ],
            'ticket_resolved' => [
                'es' => "<p>Hola <strong>{{name}}</strong>,</p><p>Tu ticket <strong>#{{ticket_number}}</strong> ha sido marcado como <strong>resuelto</strong>.</p><p>Si el problema persiste, puedes reabrir el ticket.</p>",
                'en' => "<p>Hello <strong>{{name}}</strong>,</p><p>Your ticket <strong>#{{ticket_number}}</strong> has been marked as <strong>resolved</strong>.</p><p>If the issue persists, you can reopen the ticket.</p>",
            ],
            'password_reset' => [
                'es' => "<p>Hola <strong>{{name}}</strong>,</p><p>Recibimos una solicitud para restablecer tu contraseña.</p><p><a href=\"{{reset_url}}\" style=\"background:#0d6efd;color:#fff;padding:10px 20px;border-radius:4px;text-decoration:none\">Restablecer contraseña</a></p><p>Este enlace expira en 1 hora. Si no solicitaste esto, ignora este mensaje.</p>",
                'en' => "<p>Hello <strong>{{name}}</strong>,</p><p>We received a request to reset your password.</p><p><a href=\"{{reset_url}}\" style=\"background:#0d6efd;color:#fff;padding:10px 20px;border-radius:4px;text-decoration:none\">Reset password</a></p><p>This link expires in 1 hour. If you didn't request this, ignore this email.</p>",
            ],
        ];

        $content = $this->interpolate($bodies[$slug][$lang] ?? '', $vars);
        $app_color = setting('company_color') ?: '#0d6efd';

        return <<<HTML
        <!DOCTYPE html><html><head><meta charset="UTF-8"></head>
        <body style="font-family:Arial,sans-serif;background:#f4f6f9;margin:0;padding:24px">
        <div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1)">
          <div style="background:{$app_color};padding:24px;text-align:center;color:#fff">{$logo_html}</div>
          <div style="padding:32px;color:#333;line-height:1.6">{$content}</div>
          <div style="background:#f8f9fa;padding:16px;text-align:center;font-size:12px;color:#999">&copy; {$app_name} — {$_SERVER['SERVER_NAME']}</div>
        </div></body></html>
        HTML;
    }
}

function mailer(): Mailer {
    return new Mailer();
}
